// ============================================================================
// Smart Infant Incubator — firmware ESP32 DevKit V1
//
// Satu file untuk Arduino IDE:
//   1. Buka Incubator.ino, pilih board "ESP32 Dev Module".
//   2. Isi DEVICE_CODE, WIFI_*, MQTT_PASSWORD, DEVICE_TOKEN di blok IDENTITAS,
//      atau taruh nilainya di secrets.h (file lokal, tidak masuk git).
//   3. Upload.
//
// Semua perhitungan berjalan di ESP32. Jaringan hanya menyimpan telemetry dan
// mengirim config; kalau jaringan mati, PID dan fail-safe tetap jalan.
// ============================================================================

#include <Arduino.h>
#include <ArduinoJson.h>
#include <DallasTemperature.h>
#include <DHT.h>
#include <HTTPClient.h>
#include <LiquidCrystal_I2C.h>
#include <OneWire.h>
#include <PubSubClient.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <Wire.h>
#include <math.h>
#include <freertos/FreeRTOS.h>
#include <freertos/queue.h>

// secrets.h opsional: menimpa nilai di bawah tanpa mengubah file ini.
#if defined(__has_include)
#if __has_include("secrets.h")
#include "secrets.h"
#endif
#endif

// ================================ IDENTITAS =================================
#ifndef DEVICE_CODE
#define DEVICE_CODE "INC-001"
#endif
#ifndef WIFI_SSID
#define WIFI_SSID "iot-komdis"
#endif
#ifndef WIFI_PASSWORD
#define WIFI_PASSWORD "iotkomdis123"
#endif

// Username MQTT = kode device. ACL broker hanya membuka
// incubator/<device_code>/*.
#ifndef MQTT_HOST
#define MQTT_HOST "41.216.180.77"
#endif
#ifndef MQTT_PORT
#define MQTT_PORT 8883
#endif
#ifndef MQTT_USERNAME
#define MQTT_USERNAME DEVICE_CODE
#endif
#ifndef MQTT_PASSWORD
#define MQTT_PASSWORD ""
#endif
#ifndef DEVICE_TOKEN
#define DEVICE_TOKEN ""
#endif

// 1 = verifikasi TLS terhadap CA di bawah. Pakai 1 di produksi.
#ifndef TLS_VERIFY
#define TLS_VERIFY 1
#endif

// Fallback HTTP ke Laravel. MQTT tetap jalur utama.
#ifndef HTTP_BASE_URL
#define HTTP_BASE_URL "http://iot-komdis-2.test"
#endif

#ifndef MQTT_KEEPALIVE_S
#define MQTT_KEEPALIVE_S 30
#endif
#ifndef MQTT_TOPIC_PREFIX
#define MQTT_TOPIC_PREFIX "incubator"
#endif

// ================================== TIMING ==================================
#ifndef TELEMETRY_INTERVAL_MS
#define TELEMETRY_INTERVAL_MS 5000
#endif

// Override relay kedaluwarsa setelah ini tanpa sync sukses (dead-man 60 s).
#ifndef OVERRIDE_TTL_MS
#define OVERRIDE_TTL_MS 60000
#endif
#ifndef NETWORK_BACKOFF_MAX_MS
#define NETWORK_BACKOFF_MAX_MS 30000
#endif

// ================================== KONTROL =================================
#ifndef DEFAULT_SETPOINT
#define DEFAULT_SETPOINT 33.5f
#endif

// Batas aman untuk setpoint yang datang dari jaringan.
#ifndef SETPOINT_MIN
#define SETPOINT_MIN 28.0f
#endif
#ifndef SETPOINT_MAX
#define SETPOINT_MAX 37.0f
#endif

// Di atas batas atas kedua heater mati; di bawah batas bawah probe dianggap
// rusak, bukan dingin.
#ifndef TRIP_HIGH_C
#define TRIP_HIGH_C 38.5f
#endif
#ifndef TRIP_LOW_C
#define TRIP_LOW_C 15.0f
#endif

// Histeresis staging supaya relay tidak berkedip di batas.
#ifndef HEATER_1_ON_PCT
#define HEATER_1_ON_PCT 5.0f
#endif
#ifndef HEATER_2_ON_PCT
#define HEATER_2_ON_PCT 50.0f
#endif
#ifndef HEATER_HYST_PCT
#define HEATER_HYST_PCT 3.0f
#endif

// Error penuh (K) yang memetakan ke output PID 100%. Tanpa ini gain server
// (K-per-K) tidak akan pernah mencapai stage heater 2.
#ifndef PID_FULL_SCALE_C
#define PID_FULL_SCALE_C 5.0f
#endif

// Clamp integral dalam K. Kecil sengaja: heater hanya bisa menaikkan suhu.
#ifndef PID_I_MIN_C
#define PID_I_MIN_C -1.0f
#endif
#ifndef PID_I_MAX_C
#define PID_I_MAX_C 1.0f
#endif

// =================================== SENSOR =================================
#ifndef DS18B20_PIN
#define DS18B20_PIN 4
#endif
#ifndef DHT22_PIN
#define DHT22_PIN 5
#endif
#ifndef DS18B20_RESOLUTION
#define DS18B20_RESOLUTION 11
#endif

// ================================== AKTUATOR ================================
#ifndef RELAY_ACTIVE_HIGH
#define RELAY_ACTIVE_HIGH 1
#endif
#ifndef RELAY_FAN_PIN
#define RELAY_FAN_PIN 14
#endif
#ifndef RELAY_HEATER_1_PIN
#define RELAY_HEATER_1_PIN 26
#endif
#ifndef RELAY_HEATER_2_PIN
#define RELAY_HEATER_2_PIN 27
#endif
#ifndef RELAY_BLUE_LIGHT_PIN
#define RELAY_BLUE_LIGHT_PIN 25
#endif

#ifndef LCD_I2C_ADDR
#define LCD_I2C_ADDR 0x27
#endif
#ifndef LCD_SDA_PIN
#define LCD_SDA_PIN 21
#endif
#ifndef LCD_SCL_PIN
#define LCD_SCL_PIN 22
#endif

// =================================== DEBUG ==================================
#ifndef FW_VERSION
#define FW_VERSION "1.0.0"
#endif
#ifndef SERIAL_BAUD
#define SERIAL_BAUD 115200
#endif

// ================================ MODEL DATA ================================
// Hanya struct POD. Tidak ada alokasi, jadi queue FreeRTOS menyalin per nilai
// dan tidak ada pointer yang bisa menggantung antar core.

struct Reading {
  float objectC;
  float roomC;
  float humidity;
  bool dsOk;
  bool dhtOk;

  float setpoint;
  float pidOutput;

  bool fan;
  bool heater1;
  bool heater2;
  bool blueLight;

  bool tripped;
  bool wifiDown;
  uint32_t uptimeSeconds;
};

struct Command {
  float setpoint;
  float kp;
  float ki;
  float kd;
  bool blueLightEnabled;
  bool isPaired;
  char babyCode[16];
  // -1 = tanpa override, kontrol yang memutuskan. 0/1 memaksa relay.
  int8_t overrideFan;
  int8_t overrideHeater1;
  int8_t overrideHeater2;
  int8_t overrideBlueLight;
  uint32_t receivedAtMs;
  uint32_t expiresAtMs;
};

enum class EventKind : uint8_t { SensorFault, Overheat, DeviceBoot, NetworkLost };

struct Event {
  EventKind kind;
};

// ==================================== CA ====================================
// CA broker VPS (salinan /opt/mosquitto/certs/ca.crt). Ganti isinya kalau CA
// broker diganti, lalu upload ulang.
static const char CA_CERT_PEM[] = R"CERT(
-----BEGIN CERTIFICATE-----
MIIFXDCCA0SgAwIBAgITVyhb2Z2TOkIf5bYTlie7ou6cNjANBgkqhkiG9w0BAQsF
ADA2MR8wHQYDVQQDDBZJb1QgSW5jdWJhdG9yIExvY2FsIENBMRMwEQYDVQQKDApJ
b1QgS29tZGlzMB4XDTI2MTExOTA4Mzk1NVoXDTM2MTExNjA4Mzk1NVowNjEfMB0G
A1UEAwwWSW9UIEluY3ViYXRvciBMb2NhbCBDQTETMBEGA1UECgwKSW9UIEtvbWRp
czCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAMWshOyUcn/XaNEv0nPN
Zh2MYCMkvg+gkoZAZ/AsxOH82OrsksQWA5s5+R8yuVsZe0BD+sjm3glDq9S+KH2T
SrHq4QfC9sIpjNITdeLoJMqFx6umV6ZzoUUgERn1bapOUeBo96qIV+2rkd/MFfk2
gdc6PVWlyDr86BuEBzt1waflTf5rZoH0kFhoEh9udGa1uldJM1tZBPxtUwyvkflk
msSgPblOblGskT6RnEdXlktyUNi+99S/kgUfaRVguMSb+OzWouMZVRH70SDRbPBm
ESFK+aLo8UR9Bh6kvHkq7otXlaQRJDt5wv6dqow6YoFtEM10nR8mFncoy7TCnY98
0f5/zT5Tv9bkStylIwBdeVp4ammcqu0t+aJSSrDnvnJQHDi9xRc8Zzeza/edxxov
U5WWzUTndN4cQ4uVSHB3L8BRQ3gssaEny1WPOUtBHtFqKYmflvVyGOwBTzWeEM0R
7me/atno+5t+iYe9qfFxmTw2INdW2aZPEkCbQbyVQxgivtPN47xymImtKqP9Zn+k
wKz/SDn3qgrw+yrDWf4V6VSe/RoPNgm+skGvXL/5ICFdU61t2tTSV3RYz+vmH53M
URH4WZk05WS1BU+HFw6mWp/Be8sYSM4lv5vhwDTJJ0KOmBsIOmm9aPSr9+u/nt6D
ya+t73eBjbWj0ZAkmG4ec/9hAgMBAAGjYzBhMB0GA1UdDgQWBBSudeRZnXU7JfGi
7cntYf9/dZtRyzAfBgNVHSMEGDAWgBSudeRZnXU7JfGi7cntYf9/dZtRyzAPBgNV
HRMBAf8EBTADAQH/MA4GA1UdDwEB/wQEAwIBBjANBgkqhkiG9w0BAQsFAAOCAgEA
St3sfY7MjwpGvyD+AoPoFT9jwA6l/DhnwocNADGc8h0s5TzPeRyrzE2DSHR+1gtb
YWOnZtcmJxy2P9tXo2qCaeWDKQs95Wr5c2vJ842xuO55Xyh2BFRiQ08LxPYjmV47
B4311/9XHEQv7MWrrGQaEesczJ/d7TLQDr0v8SH7RjmR/xfZ5onmAvcknJRNhV5t
R9frgl56YwShS6ojIV33mDfE8pCZRV5mg+qQUK7rVxW6qNh/WrZf7xyBVOFuQBMl
r85EaW96SfyPPo0OAAIC1KiMINKcDmQnbyGpjWSt2ujkTWuCPes7ZuswJ7p/mfH1
hMaYZJrr5GRP+7yViBZPYICnJqiIpaZ50Y4AyZDwJZka1b/wEOMoc8t7HJAYP6pl
bqdSTAdRbQQEwayadC7k4Y9JXTuFHtKKlAwe+6SxohSASr9j156VpgG4Yo4ADGFn
8l9vm7wvlnIUIxD35ncpS8RotQNgm4LBYh80EoSG36M3Cn/m5QNnUBL3ayftvDch
6dPsRjE9rs1VAk52p1SdcFwVDl597Vom7UbtrIi+/JfbAUjEAFuEs5RbbJ0jT3wD
nUYJV88rZEokg5CZVMV4UZeUIgREgpQOvP67xc+ys8a1USX5cBYLwT5olx2Ifjjp
QwKuZvapKlPcMXWYKbhQBYCNaXkMjGKNZ8K3NvUfwz4=
-----END CERTIFICATE-----
)CERT";

// ================================== KONTROL =================================
// PID + staging relay murni. Tanpa panggilan Arduino, jadi logikanya bisa
// diuji terpisah di compiler host.

struct ControlState {
  float integralC = 0;
  float previousError = 0;
  float pidOutputPct = 0;
  bool hasPreviousError = false;
  bool heater1 = false;
  bool heater2 = false;
};

float clampf(float value, float low, float high) {
  if (value < low) {
    return low;
  }
  if (value > high) {
    return high;
  }
  return value;
}

// Stage hanya mati setelah output turun HEATER_HYST_PCT di bawah ambangnya,
// jadi nilai di batas tidak membuat relay berkedip.
bool stageActive(float outputPct, float thresholdPct, bool current) {
  if (current) {
    return outputPct > thresholdPct - HEATER_HYST_PCT;
  }
  return outputPct >= thresholdPct;
}

void controlClear(ControlState &state) {
  state.integralC = 0;
  state.pidOutputPct = 0;
  state.heater1 = false;
  state.heater2 = false;
}

void controlReset(ControlState &state) {
  controlClear(state);
  state.previousError = 0;
  state.hasPreviousError = false;
}

// dtSeconds = periode kontrol nyata dari pemanggil, supaya integral dan
// turunan tidak melonjak setelah loop sempat tertahan.
void controlStep(ControlState &state, float objectC, bool objectValid, float dtSeconds,
                 const Command &cmd) {
  if (!objectValid) {
    // Tanpa bacaan yang bisa dipercaya, tidak ada panas.
    controlClear(state);
    state.hasPreviousError = false;
    return;
  }

  // Dua ekstrem sama-sama tidak aman: terlalu panas, atau jauh di bawah suhu
  // inkubator mana pun berarti probe rusak.
  if (objectC > TRIP_HIGH_C || objectC < TRIP_LOW_C) {
    controlClear(state);
    state.hasPreviousError = false;
    return;
  }

  const float setpoint = clampf(cmd.setpoint, SETPOINT_MIN, SETPOINT_MAX);
  const float error = setpoint - objectC;

  // Integral disimpan dalam kelvin, lalu seluruh suku diskalakan
  // PID_FULL_SCALE_C supaya kp/ki/kd tetap berarti seperti di UI server.
  state.integralC = clampf(state.integralC + cmd.ki * error * dtSeconds, PID_I_MIN_C, PID_I_MAX_C);

  // Turunan dilewati pada sampel pertama setelah reset: dt belum diketahui dan
  // slope karangan akan menyentak heater.
  float derivative = 0;
  if (state.hasPreviousError && dtSeconds > 0.0f) {
    derivative = (error - state.previousError) / dtSeconds;
  }
  state.previousError = error;
  state.hasPreviousError = true;

  const float output = clampf((cmd.kp * error + state.integralC + cmd.kd * derivative) *
                                  (100.0f / PID_FULL_SCALE_C),
                              0.0f, 100.0f);
  state.pidOutputPct = output;

  state.heater1 = stageActive(output, HEATER_1_ON_PCT, state.heater1);
  state.heater2 = stageActive(output, HEATER_2_ON_PCT, state.heater2);
}

// =================================== SENSOR =================================
// DS18B20 + DHT22, keduanya dibaca tanpa memblokir loop kontrol.

static OneWire gWire(DS18B20_PIN);
static DallasTemperature gDs(&gWire);
static DHT gDht(DHT22_PIN, DHT22);

static bool gDsConversionPending = false;
static uint32_t gDsConversionStartedMs = 0;
static uint32_t gDhtRequestedMs = 0;
static uint32_t gDhtRetryAfterMs = 0;

// Waktu konversi Dallas per resolusi 9..12 bit, diindeks (resolusi - 9).
static const uint16_t kConversionMs[] = {94, 188, 375, 750};

static float gObjectC = 0;
static bool gObjectOk = false;
static float gRoomC = 0;
static float gHumidity = 0;
static bool gDhtOk = false;
// uint32_t: penghitung poll bisa berjalan berhari-hari tanpa wrap.
static uint32_t gPolls = 0;

void sensorsBegin() {
  gDs.setWaitForConversion(false);
  gDs.begin();
  gDs.setResolution(DS18B20_RESOLUTION);
  gDht.begin();
}

void sensorsPoll(uint32_t nowMs) {
  ++gPolls;

  // DS18B20: minta dan baca di poll berbeda, jadi konversi 375 ms tidak
  // menahan loop kontrol.
  if (!gDsConversionPending) {
    gDs.requestTemperatures();
    gDsConversionPending = true;
    gDsConversionStartedMs = nowMs;
  } else {
    const uint8_t index = constrain(DS18B20_RESOLUTION, 9, 12) - 9;
    if (nowMs - gDsConversionStartedMs >= kConversionMs[index]) {
      gDsConversionPending = false;
      const float value = gDs.getTempCByIndex(0);
      // -127 = probe terlepas, 85 = nilai register saat power-on. Keduanya
      // bukan suhu yang mungkin dilihat inkubator ini.
      gObjectOk = value > TRIP_LOW_C - 5.0f && value < 84.0f;
      if (gObjectOk) {
        gObjectC = value;
      }
    }
  }

  // DHT22 butuh 2 s antar pembacaan; kegagalan di-backoff supaya sensor hilang
  // tidak membuat loop menunggu timeout berulang.
  if (gDhtRetryAfterMs != 0) {
    if (nowMs < gDhtRetryAfterMs) {
      return;
    }
    gDhtRetryAfterMs = 0;
  }
  if (nowMs - gDhtRequestedMs < 2500) {
    return;
  }
  gDhtRequestedMs = nowMs;

  const float room = gDht.readTemperature();
  const float humidity = gDht.readHumidity();
  if (isnan(room) || isnan(humidity) || room < 10.0f || room > 45.0f || humidity < 5.0f ||
      humidity > 99.0f) {
    gDhtOk = false;
    gDhtRetryAfterMs = nowMs + 30000;
    return;
  }
  gRoomC = room;
  gHumidity = humidity;
  gDhtOk = true;
}

float sensorsObjectC() { return gObjectC; }
bool sensorsObjectOk() { return gObjectOk; }
float sensorsRoomC() { return gRoomC; }
float sensorsHumidity() { return gHumidity; }
bool sensorsDhtReady() { return gDhtOk; }

// True setelah kedua sensor memberi sampel, atau setelah ~2000 poll, supaya
// sensor yang hilang tidak membuat device diam selamanya.
bool sensorsWarm() { return (gObjectOk && gDhtOk) || gPolls > 2000; }

// ================================== RELAY ===================================
// Satu tabel: menambah relay cukup satu baris dan satu field di Reading.

static const uint8_t kRelayPins[] = {RELAY_FAN_PIN, RELAY_HEATER_1_PIN, RELAY_HEATER_2_PIN,
                                     RELAY_BLUE_LIGHT_PIN};

// Board active-low menarik koil saat pin LOW. Menulis level tidak-aktif sebelum
// pinMode mencegah relay berbunyi saat power-on.
void relayWrite(uint8_t pin, bool on) {
  digitalWrite(pin, (RELAY_ACTIVE_HIGH ? on : !on) ? HIGH : LOW);
}

void relaysBegin() {
  for (uint8_t pin : kRelayPins) {
    relayWrite(pin, false);
    pinMode(pin, OUTPUT);
    relayWrite(pin, false);
  }
}

void relaysApply(const Reading &r) {
  const bool states[] = {r.fan, r.heater1, r.heater2, r.blueLight};
  for (size_t i = 0; i < sizeof(kRelayPins); ++i) {
    relayWrite(kRelayPins[i], states[i]);
  }
}

// Fan nyala, kedua heater mati. Sirkulasi adalah kondisi aman untuk inkubator
// tertutup.
void relaysForceSafe() {
  relayWrite(RELAY_FAN_PIN, true);
  relayWrite(RELAY_HEATER_1_PIN, false);
  relayWrite(RELAY_HEATER_2_PIN, false);
}

// =================================== LCD ====================================
// Buffer tetap, bukan String: tidak ada alokasi heap per frame LCD.

static LiquidCrystal_I2C gLcd(LCD_I2C_ADDR, 16, 2);
static char gLcdLine1[17] = {0};
static char gLcdLine2[17] = {0};

void lcdWriteLine(uint8_t row, const char *text) {
  // Pad ke 16 karakter supaya sisa teks lama ikut terhapus.
  char padded[17];
  snprintf(padded, sizeof(padded), "%-16.16s", text);
  gLcd.setCursor(0, row);
  gLcd.print(padded);
}

void lcdBegin() {
  Wire.begin(LCD_SDA_PIN, LCD_SCL_PIN);
  gLcd.init();
  gLcd.backlight();
  gLcd.clear();
}

void lcdSelfTest() {
  gLcd.clear();
  gLcd.setCursor(0, 0);
  gLcd.print("INCUBATOR");
  gLcd.setCursor(0, 1);
  gLcd.print(FW_VERSION);
}

// Panel ada di bus I2C lambat; redraw tiap loop lebih mahal daripada loop
// kontrolnya sendiri.
void lcdRender(const char *line1, const char *line2) {
  if (strcmp(line1, gLcdLine1) == 0 && strcmp(line2, gLcdLine2) == 0) {
    return;
  }
  strncpy(gLcdLine1, line1, sizeof(gLcdLine1) - 1);
  strncpy(gLcdLine2, line2, sizeof(gLcdLine2) - 1);
  lcdWriteLine(0, gLcdLine1);
  lcdWriteLine(1, gLcdLine2);
}

// ================================== BRIDGE ==================================
// Satu-satunya jalur antar core. Semua yang lewat sini salinan nilai struct
// POD, jadi tidak perlu lock dan tidak ada pointer menggantung.
//
// Satu slot per jalur: producer menimpa, consumer mengambil yang terbaru, jadi
// sampel basi tidak pernah menumpuk.

static QueueHandle_t gReadingQueue = nullptr;
static QueueHandle_t gCommandQueue = nullptr;
static QueueHandle_t gEventQueue = nullptr;

void bridgeBegin() {
  gReadingQueue = xQueueCreate(1, sizeof(Reading));
  gCommandQueue = xQueueCreate(1, sizeof(Command));
  gEventQueue = xQueueCreate(4, sizeof(Event));
}

void bridgePublishReading(const Reading &reading) { xQueueOverwrite(gReadingQueue, &reading); }

bool bridgeTakeReading(Reading &out, uint32_t waitMs) {
  return xQueueReceive(gReadingQueue, &out, pdMS_TO_TICKS(waitMs)) == pdTRUE;
}

void bridgePublishCommand(const Command &command) { xQueueOverwrite(gCommandQueue, &command); }

bool bridgeTakeCommand(Command &out) {
  return xQueueReceive(gCommandQueue, &out, 0) == pdTRUE;
}

void bridgePublishEvent(EventKind kind) {
  const Event event{kind};
  // Event jarang; queue penuh membuang yang baru, bukan memblokir core kontrol.
  xQueueSend(gEventQueue, &event, 0);
}

bool bridgeTakeEvent(EventKind &out) {
  Event event{};
  if (xQueueReceive(gEventQueue, &event, 0) != pdTRUE) {
    return false;
  }
  out = event.kind;
  return true;
}

// ================================= PROTOKOL =================================
// Kontrak wire dengan Laravel di satu tempat: kedua transport mem-parse JSON
// yang sama dan membangun body telemetry yang sama.

int8_t overrideFlag(JsonObject overrides, const char *key) {
  if (!overrides[key].is<bool>()) {
    return -1;
  }
  return overrides[key].as<bool>() ? 1 : 0;
}

Command protocolDefaultCommand() {
  Command cmd{};
  cmd.setpoint = DEFAULT_SETPOINT;
  cmd.kp = 2.0f;
  cmd.ki = 0.1f;
  cmd.kd = 0.5f;
  cmd.blueLightEnabled = false;
  cmd.isPaired = false;
  cmd.babyCode[0] = '\0';
  cmd.overrideFan = -1;
  cmd.overrideHeater1 = -1;
  cmd.overrideHeater2 = -1;
  cmd.overrideBlueLight = -1;
  cmd.receivedAtMs = 0;
  cmd.expiresAtMs = 0;
  return cmd;
}

// Key yang tidak ada mempertahankan nilai lama, jadi payload parsial tidak bisa
// mereset controller.
void protocolApply(const char *body, Command &cmd) {
  StaticJsonDocument<512> doc;
  if (deserializeJson(doc, body) != DeserializationError::Ok) {
    return;
  }

  JsonObject config = doc["config"].as<JsonObject>();
  if (!config.isNull()) {
    cmd.setpoint = config["temperature_setpoint"] | cmd.setpoint;
    cmd.kp = config["pid_kp"] | cmd.kp;
    cmd.ki = config["pid_ki"] | cmd.ki;
    cmd.kd = config["pid_kd"] | cmd.kd;
    cmd.blueLightEnabled = config["blue_led_enabled"] | cmd.blueLightEnabled;
  }

  cmd.isPaired = doc["is_paired"] | cmd.isPaired;
  JsonObject baby = doc["baby"].as<JsonObject>();
  const char *code = baby.isNull() ? "" : baby["code"].as<const char *>();
  strncpy(cmd.babyCode, code == nullptr ? "" : code, sizeof(cmd.babyCode) - 1);
  cmd.babyCode[sizeof(cmd.babyCode) - 1] = '\0';

  // Server selalu mengirim objek ini, kosong saat tidak ada override, jadi
  // objek kosong berarti "kembali ke auto", bukan field yang hilang.
  JsonObject overrides = doc["relay_overrides"].as<JsonObject>();
  if (!overrides.isNull()) {
    cmd.overrideFan = overrideFlag(overrides, "fan");
    cmd.overrideHeater1 = overrideFlag(overrides, "heater_1");
    cmd.overrideHeater2 = overrideFlag(overrides, "heater_2");
    cmd.overrideBlueLight = overrideFlag(overrides, "blue_light");
  }
}

String protocolTelemetryBody(const Reading &r) {
  StaticJsonDocument<384> doc;
  doc["device_code"] = DEVICE_CODE;
  // -127 = probe DS18B20 terlepas; server menyimpannya apa adanya dan membaca
  // fault dari sensor_status.
  // Dua desimal: resolusi sensor 0.0625 C, kolom Laravel decimal(5,2).
  doc["temperature_object"] = r.dsOk ? roundf(r.objectC * 100.0f) / 100.0f : -127.0f;
  doc["temperature_room"] = roundf(r.roomC * 100.0f) / 100.0f;
  doc["humidity"] = roundf(r.humidity * 10.0f) / 10.0f;
  JsonObject relayJson = doc.createNestedObject("relays");
  relayJson["fan"] = r.fan;
  relayJson["heater_1"] = r.heater1;
  relayJson["heater_2"] = r.heater2;
  relayJson["blue_light"] = r.blueLight;
  JsonObject sensorJson = doc.createNestedObject("sensor_status");
  sensorJson["ds18b20"] = r.dsOk;
  sensorJson["dht22"] = r.dhtOk;
  // Tanpa timestamp: Laravel yang menstempel, jamnya lebih baik daripada ESP32
  // yang belum pernah menerima NTP.
  doc["uptime_s"] = r.uptimeSeconds;
  String body;
  serializeJson(doc, body);
  return body;
}

String protocolStatusBody(bool online) {
  StaticJsonDocument<64> doc;
  doc["online"] = online;
  String body;
  serializeJson(doc, body);
  return body;
}

// =================================== TLS ====================================

void tlsConfigure(WiFiClientSecure &client) {
  if (TLS_VERIFY) {
    client.setCACert(CA_CERT_PEM);
    return;
  }
  client.setInsecure();
}

// ================================= TRANSPORT ================================
// Sisi Core 0: satu Wi-Fi station, dua transport, satu kontrak JSON.
//
// MQTT utama (satu socket, sync retained, event, last-will status). HTTP
// `POST /api/devices/telemetry` cadangan sesuai PRD, dan responsnya membawa
// payload sync yang sama.

static WiFiClientSecure gTls;
static HTTPClient gHttp;
static PubSubClient gMqtt(gTls);

static char gTopicSync[64] = "";
static char gTopicTelemetry[64] = "";
static char gTopicEvents[64] = "";
static char gTopicStatus[64] = "";

static uint32_t gBackoffUntilMs = 0;
static uint16_t gBackoffMs = 0;
static uint16_t gHttpPort = 0;
static String gHttpHost;

// Command terakhir yang berhasil diantar transport mana pun. Payload parsial
// digabung ke sini, jadi respons tanpa `relay_overrides` tidak menghapus
// override yang sedang aktif.
static Command gLastCommand{};

void buildTopic(char *out, size_t size, const char *suffix) {
  snprintf(out, size, "%s/%s/%s", MQTT_TOPIC_PREFIX, DEVICE_CODE, suffix);
}

// HTTP_BASE_URL dipecah sekali saat boot: kerja String per request membuang
// heap.
void parseBaseUrl() {
  const char *url = HTTP_BASE_URL;
  const char *authority = strstr(url, "://");
  gHttpHost = String(authority == nullptr ? url : authority + 3);
  const bool tls = authority != nullptr && strncmp(url, "https", 5) == 0;
  const int colon = gHttpHost.indexOf(':');
  gHttpPort =
      colon < 0 ? (tls ? 443 : 80) : static_cast<uint16_t>(gHttpHost.substring(colon + 1).toInt());
  if (colon >= 0) {
    gHttpHost.remove(colon);
  }
}

void transportAccept(const char *body) {
  protocolApply(body, gLastCommand);
  gLastCommand.receivedAtMs = millis();
  gLastCommand.expiresAtMs = gLastCommand.receivedAtMs + OVERRIDE_TTL_MS;
  bridgePublishCommand(gLastCommand);
}

void mqttOnSync(char *topicName, byte *payload, unsigned int length) {
  (void)topicName;
  // Sync retained ukurannya kecil; buffer stack supaya callback tidak menyentuh
  // heap.
  char body[512] = {0};
  const size_t copy = length < sizeof(body) - 1 ? length : sizeof(body) - 1;
  memcpy(body, payload, copy);
  transportAccept(body);
}

void transportBackoff(uint32_t nowMs) {
  gBackoffMs = gBackoffMs == 0
                   ? 2000
                   : static_cast<uint16_t>(min<uint32_t>(gBackoffMs * 2U, NETWORK_BACKOFF_MAX_MS));
  gBackoffUntilMs = nowMs + gBackoffMs;
}

void transportBegin() {
  buildTopic(gTopicSync, sizeof(gTopicSync), "sync");
  buildTopic(gTopicTelemetry, sizeof(gTopicTelemetry), "telemetry");
  buildTopic(gTopicEvents, sizeof(gTopicEvents), "events");
  buildTopic(gTopicStatus, sizeof(gTopicStatus), "status");

  parseBaseUrl();
  gLastCommand = protocolDefaultCommand();

  tlsConfigure(gTls);

  gHttp.setTimeout(4000);
  gHttp.setReuse(true);

  gMqtt.setServer(MQTT_HOST, MQTT_PORT);
  gMqtt.setBufferSize(1024);
  gMqtt.setKeepAlive(MQTT_KEEPALIVE_S);
  gMqtt.setCallback(mqttOnSync);

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
}

bool transportOnline() { return WiFi.status() == WL_CONNECTED; }

bool transportMqttConnected() { return gMqtt.connected(); }

// Reconnect Wi-Fi plus keepalive MQTT. Semua panggilan blocking ada di sini.
void transportMaintain(uint32_t nowMs) {
  static uint32_t lastReconnectMs = 0;
  if (!transportOnline()) {
    if (nowMs - lastReconnectMs > 2000) {
      lastReconnectMs = nowMs;
      WiFi.reconnect();
    }
    return;
  }

  if (!gMqtt.connected()) {
    // Last will membuat broker menandai device offline walau listrik putus, dan
    // status retained membuat dashboard langsung membaca keadaan sebenarnya.
    const bool ok = gMqtt.connect(DEVICE_CODE, MQTT_USERNAME, MQTT_PASSWORD, gTopicStatus, 1, true,
                                  protocolStatusBody(false).c_str());
    if (ok) {
      gMqtt.subscribe(gTopicSync, 1);
    }
    return;
  }
  gMqtt.loop();
}

void transportPublishTelemetry(const Reading &reading) {
  gMqtt.publish(gTopicTelemetry, protocolTelemetryBody(reading).c_str(), false);
}

void transportPublishEvent(const char *kind, const char *priority) {
  if (!gMqtt.connected()) {
    return;
  }
  char body[192];
  snprintf(body, sizeof(body),
           "{\"event_type\":\"%s\",\"priority\":\"%s\",\"message\":\"%s\","
           "\"payload\":{\"device_code\":\"%s\",\"firmware\":\"%s\"}}",
           kind, priority, kind, DEVICE_CODE, FW_VERSION);
  gMqtt.publish(gTopicEvents, body, false);
}

// POST reading lalu menerapkan payload sync yang menumpang di respons.
// Return false bila tidak ada yang diterapkan; command terakhir tetap berlaku.
bool transportPostTelemetry(uint32_t nowMs, const Reading &reading) {
  if (nowMs < gBackoffUntilMs) {
    return false;
  }
  if (!gHttp.begin(gTls, gHttpHost, gHttpPort, "/api/devices/telemetry", gHttpPort == 443)) {
    transportBackoff(nowMs);
    return false;
  }
  gHttp.addHeader("Content-Type", "application/json");
  gHttp.addHeader("X-Device-Token", DEVICE_TOKEN);

  const int status = gHttp.POST(protocolTelemetryBody(reading));
  // Body harus dibaca sebelum end(): socket keep-alive dipakai ulang, body yang
  // belum dibaca ikut terbuang bersama koneksi.
  const String payload = gHttp.getString();
  gHttp.end();

  if (status != HTTP_CODE_OK) {
    transportBackoff(nowMs);
    return false;
  }

  transportAccept(payload.c_str());
  gBackoffMs = 0;
  return true;
}

// ================================= APLIKASI =================================

// Milik core kontrol. Core 0 tidak menyentuhnya langsung: command baru
// menyeberang lewat queue, jadi tidak ada tulisan bersamaan ke struct ini.
static Command gCommand{};

// Sticky sampai operator re-arm: blip sensor sesaat tidak boleh menyalakan
// heater lagi dengan sendirinya.
static bool gTripped = false;

// Override relay kedaluwarsa OVERRIDE_TTL_MS setelah sync sukses terakhir,
// sehingga jaringan yang hilang mengembalikan inkubator ke kontrol otomatis.
void expireOverrides(uint32_t nowMs) {
  if (gCommand.expiresAtMs == 0 || nowMs <= gCommand.expiresAtMs) {
    return;
  }
  gCommand.overrideFan = -1;
  gCommand.overrideHeater1 = -1;
  gCommand.overrideHeater2 = -1;
  gCommand.overrideBlueLight = -1;
  gCommand.expiresAtMs = 0;
}

void enterTrip(EventKind kind) {
  if (gTripped) {
    return;
  }
  gTripped = true;
  bridgePublishEvent(kind);
  relaysForceSafe();
}

// Buffer tetap, bukan String: format LCD berjalan tiap 100 ms dan alokasi heap
// per frame memecah memori perlahan.
void formatLine1(char *out, size_t size, const Reading &r) {
  if (!r.dsOk) {
    snprintf(out, size, "SENSOR ERROR");
  } else if (r.tripped) {
    snprintf(out, size, "TRIP %5.1fC", r.objectC);
  } else {
    snprintf(out, size, "OBJ%5.1fC RH%4.1f", r.objectC, r.humidity);
  }
}

void formatLine2(char *out, size_t size, const Reading &r) {
  if (r.tripped) {
    snprintf(out, size, "FAIL-SAFE ON");
  } else if (r.wifiDown) {
    snprintf(out, size, "NO NET PID OK");
  } else if (gCommand.isPaired) {
    snprintf(out, size, "%-16.16s", gCommand.babyCode);
  } else {
    snprintf(out, size, "%s STANDBY", DEVICE_CODE);
  }
}

void applyActuators(Reading &reading, const ControlState &pid) {
  if (gTripped) {
    // Output fail-safe: sirkulasi nyala, tanpa panas.
    reading.fan = true;
    reading.heater1 = false;
    reading.heater2 = false;
  } else {
    reading.fan = gCommand.overrideFan >= 0 ? gCommand.overrideFan > 0 : true;
    reading.heater1 = gCommand.overrideHeater1 >= 0 ? gCommand.overrideHeater1 > 0 : pid.heater1;
    reading.heater2 = gCommand.overrideHeater2 >= 0 ? gCommand.overrideHeater2 > 0 : pid.heater2;
  }
  reading.blueLight =
      gCommand.overrideBlueLight >= 0 ? gCommand.overrideBlueLight > 0 : gCommand.blueLightEnabled;
}

void drainEvents() {
  EventKind kind;
  while (bridgeTakeEvent(kind)) {
    switch (kind) {
      case EventKind::SensorFault:
        transportPublishEvent("sensor_fault", "danger");
        break;
      case EventKind::Overheat:
        transportPublishEvent("overheat", "danger");
        break;
      case EventKind::NetworkLost:
        transportPublishEvent("network_lost", "warning");
        break;
      default:
        transportPublishEvent("device_boot", "info");
        break;
    }
  }
}

// Core 1: sensor, PID, fail-safe, relay, LCD, publish ke queue. Tidak pernah
// memblokir.
void controlLoop() {
  static ControlState pid;
  static uint32_t lastStepMs = 0;
  static bool hadWifi = false;

  const uint32_t nowMs = millis();

  // Command dari transport mana pun diterapkan sebelum sampel ini, jadi
  // perubahan setpoint berlaku pada langkah kontrol berikutnya.
  Command incoming{};
  if (bridgeTakeCommand(incoming)) {
    gCommand = incoming;
  }

  sensorsPoll(nowMs);

  const uint32_t intervalMs = nowMs - lastStepMs;
  if (intervalMs < 100) {
    return;
  }
  const float dtSeconds = intervalMs / 1000.0f;
  lastStepMs = nowMs;

  if (!sensorsWarm()) {
    return;
  }

  const float objectC = sensorsObjectC();
  const bool objectValid = sensorsObjectOk();

  // Fail-safe paling dulu: menang atas PID dan atas semua override.
  if (!objectValid) {
    enterTrip(EventKind::SensorFault);
  } else if (objectC > TRIP_HIGH_C || objectC < TRIP_LOW_C) {
    enterTrip(EventKind::Overheat);
  }

  expireOverrides(nowMs);

  if (!gTripped) {
    controlStep(pid, objectC, objectValid, dtSeconds, gCommand);
  }

  // Satu pembacaan status Wi-Fi per langkah, bukan dua.
  const bool wifiUp = transportOnline();

  Reading reading{};
  reading.objectC = objectC;
  reading.roomC = sensorsRoomC();
  reading.humidity = sensorsHumidity();
  reading.dsOk = objectValid;
  reading.dhtOk = sensorsDhtReady();
  reading.setpoint = gCommand.setpoint;
  reading.pidOutput = pid.pidOutputPct;
  reading.tripped = gTripped;
  reading.wifiDown = !wifiUp;
  reading.uptimeSeconds = nowMs / 1000;
  applyActuators(reading, pid);

  relaysApply(reading);
  bridgePublishReading(reading);

  char line1[17];
  char line2[17];
  formatLine1(line1, sizeof(line1), reading);
  formatLine2(line2, sizeof(line2), reading);
  lcdRender(line1, line2);

  if (hadWifi && !wifiUp) {
    bridgePublishEvent(EventKind::NetworkLost);
  }
  hadWifi = wifiUp;
}

// Core 0: pengawasan Wi-Fi, publish telemetry, drain event. Semua panggilan
// blocking ada di sini.
void networkLoop() {
  const uint32_t nowMs = millis();
  transportMaintain(nowMs);
  drainEvents();

  // Core kontrol publish tiap 100 ms; server cukup tiap TELEMETRY_INTERVAL_MS.
  // Ambil yang terbaru dan buang sisanya, jadi burst setelah stall tetap
  // mengirim satu sampel.
  static uint32_t lastPublishMs = 0;
  if (nowMs - lastPublishMs < TELEMETRY_INTERVAL_MS) {
    return;
  }
  lastPublishMs = nowMs;

  Reading reading{};
  if (!bridgeTakeReading(reading, 0) || !transportOnline()) {
    return;
  }

  // MQTT lebih murah (satu socket, tanpa TLS handshake per request). HTTP tetap
  // cadangan supaya config dan pairing masih sampai saat broker mati.
  if (transportMqttConnected()) {
    transportPublishTelemetry(reading);
    return;
  }

  transportPostTelemetry(nowMs, reading);
}

// =================================== TASK ===================================

// Dua task, dua core: handshake TLS di Core 0 tidak pernah menunda keputusan
// heater di Core 1.
void controlTask(void *parameter) {
  (void)parameter;
  for (;;) {
    controlLoop();
    delay(1);
  }
}

void networkTask(void *parameter) {
  (void)parameter;
  for (;;) {
    networkLoop();
    delay(1);
  }
}

void setup() {
  Serial.begin(SERIAL_BAUD);

  lcdBegin();
  lcdSelfTest();

  sensorsBegin();
  relaysBegin();
  bridgeBegin();

  gCommand = protocolDefaultCommand();
  transportBegin();

  bridgePublishEvent(EventKind::DeviceBoot);

  xTaskCreatePinnedToCore(controlTask, "control", 4096, nullptr, 2, nullptr, 1);
  xTaskCreatePinnedToCore(networkTask, "network", 8192, nullptr, 1, nullptr, 0);
}

void loop() {
  // Kedua core sudah punya task sendiri; loop() hanya menjaga watchdog.
  delay(1000);
}
