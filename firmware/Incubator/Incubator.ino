// ============================================================================
// Smart Infant Incubator — firmware ESP32 DevKit V1
//
// Satu file. Arduino IDE: board "ESP32 Dev Module", partition 1.2 MB APP.
// Library: ArduinoJson 6.21.x, PubSubClient 2.8, DallasTemperature 3.9.x,
// OneWire 2.3.8, DHT sensor library 1.4.6, Adafruit Unified Sensor 1.1.14,
// LiquidCrystal I2C 1.1.2.
//
// Semua perhitungan di ESP32. Jaringan hanya simpan telemetry dan kirim config;
// kalau jaringan mati, PID dan fail-safe tetap jalan.
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
#ifndef KODE_PERANGKAT
#define KODE_PERANGKAT "INC-001"
#endif
#ifndef SSID_WIFI
#define SSID_WIFI "POCO X5 5G"
#endif
#ifndef PASSWORD_WIFI
#define PASSWORD_WIFI "cobaliathplu"
#endif

// User MQTT = kode perangkat. ACL broker hanya membuka incubator/<kode>/*.
#ifndef HOST_MQTT
#define HOST_MQTT "41.216.180.77"
#endif
#ifndef PORT_MQTT
#define PORT_MQTT 8883
#endif
#ifndef USER_MQTT
#define USER_MQTT KODE_PERANGKAT
#endif
#ifndef PASSWORD_MQTT
#define PASSWORD_MQTT PASSWORD_MQTT
#endif
#ifndef TOKEN_PERANGKAT
#define TOKEN_PERANGKAT TOKEN_PERANGKAT
#endif

// 1 = verifikasi TLS terhadap CA di bawah. Pakai 1 di produksi.
#ifndef VERIFIKASI_TLS
#define VERIFIKASI_TLS 1
#endif

// Fallback HTTP ke Laravel. MQTT tetap jalur utama.
#ifndef URL_HTTP
#define URL_HTTP "http://iot-komdis-2.test"
#endif

#ifndef KEEPALIVE_MQTT_DETIK
#define KEEPALIVE_MQTT_DETIK 30
#endif
#ifndef AWALAN_TOPIK
#define AWALAN_TOPIK "incubator"
#endif

// ================================== TIMING ==================================
#ifndef INTERVAL_TELEMETRI_MS
#define INTERVAL_TELEMETRI_MS 5000
#endif

// Override relay mati sendiri setelah ini tanpa sync sukses (dead-man 60 s).
#ifndef TTL_OVERRIDE_MS
#define TTL_OVERRIDE_MS 60000
#endif
#ifndef BATAS_MUNDUR_MS
#define BATAS_MUNDUR_MS 30000
#endif

// ================================== KONTROL =================================
#ifndef SETPOINT_AWAL
#define SETPOINT_AWAL 33.5f
#endif

// Batas aman untuk setpoint yang datang dari jaringan.
#ifndef SETPOINT_MIN
#define SETPOINT_MIN 28.0f
#endif
#ifndef SETPOINT_MAX
#define SETPOINT_MAX 37.0f
#endif

// Di atas batas atas kedua pemanas mati; di bawah batas bawah probe rusak.
#ifndef TRIP_ATAS_C
#define TRIP_ATAS_C 38.5f
#endif
#ifndef TRIP_BAWAH_C
#define TRIP_BAWAH_C 15.0f
#endif

// Histeresis staging supaya relay tidak berkedip di batas.
#ifndef AMBANG_PEMANAS1_PCT
#define AMBANG_PEMANAS1_PCT 5.0f
#endif
#ifndef AMBANG_PEMANAS2_PCT
#define AMBANG_PEMANAS2_PCT 50.0f
#endif
#ifndef HISTERESIS_PCT
#define HISTERESIS_PCT 3.0f
#endif

// Error penuh (K) yang memetakan ke keluaran PID 100%. Tanpa ini gain server
// (K-per-K) tidak akan pernah mencapai stage pemanas 2.
#ifndef SKALA_PENUH_C
#define SKALA_PENUH_C 5.0f
#endif

// Clamp integral dalam K. Kecil sengaja: pemanas hanya bisa menaikkan suhu.
#ifndef INTEGRAL_MIN_C
#define INTEGRAL_MIN_C -1.0f
#endif
#ifndef INTEGRAL_MAX_C
#define INTEGRAL_MAX_C 1.0f
#endif

// =================================== SENSOR =================================
#ifndef PIN_DS18B20
#define PIN_DS18B20 4
#endif
#ifndef PIN_DHT22
#define PIN_DHT22 5
#endif
#ifndef RESOLUSI_DS18B20
#define RESOLUSI_DS18B20 11
#endif

// ================================== AKTUATOR ================================
// Board relay unit ini active-high. Board active-low: set 1 di secrets.h.
#ifndef RELAY_AKTIF_RENDAH
#define RELAY_AKTIF_RENDAH 0
#endif
#ifndef PIN_KIPAS
#define PIN_KIPAS 25
#endif
#ifndef PIN_PEMANAS1
#define PIN_PEMANAS1 26
#endif
#ifndef PIN_PEMANAS2
#define PIN_PEMANAS2 27
#endif
#ifndef PIN_LAMPU
#define PIN_LAMPU 14
#endif

#ifndef ALAMAT_LCD
#define ALAMAT_LCD 0x27
#endif
#ifndef PIN_SDA_LCD
#define PIN_SDA_LCD 21
#endif
#ifndef PIN_SCL_LCD
#define PIN_SCL_LCD 22
#endif

// =================================== DEBUG ==================================
#ifndef VERSI_FW
#define VERSI_FW "1.0.0"
#endif
#ifndef BAUD_SERIAL
#define BAUD_SERIAL 115200
#endif

// ================================ MODEL DATA ================================
// Struct POD saja: queue FreeRTOS menyalin per nilai, tanpa pointer antar core.

struct Pembacaan {
  float suhuObjek;
  float suhuRuang;
  float kelembapan;
  bool objekValid;
  bool dhtValid;
  bool kipas;
  bool pemanas1;
  bool pemanas2;
  bool lampu;
  bool trip;
  bool wifiMati;
  uint32_t uptimeDetik;
};

struct Perintah {
  float setpoint;
  float kp;
  float ki;
  float kd;
  bool lampuAktif;
  bool terpasang;
  char kodeBayi[16];
  // -1 = tanpa override, kontrol yang memutuskan. 0/1 memaksa relay.
  int8_t overrideKipas;
  int8_t overridePemanas1;
  int8_t overridePemanas2;
  int8_t overrideLampu;
  uint32_t kedaluwarsaMs;
};

enum class Kejadian : uint8_t { SensorRusak, TerlaluPanas, Boot, JaringanHilang };

// ==================================== CA ====================================
// CA broker VPS (salinan /opt/mosquitto/certs/ca.crt). Ganti isinya kalau CA
// broker berubah, lalu upload ulang.
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
// PID + staging murni: tanpa panggilan Arduino, jadi matematikanya bisa diuji
// terpisah.

struct Pid {
  float integralC = 0;
  float errorLalu = 0;
  float keluaranPct = 0;
  bool adaErrorLalu = false;
  bool pemanas1 = false;
  bool pemanas2 = false;
};

float batasi(float nilai, float bawah, float atas) {
  return nilai < bawah ? bawah : (nilai > atas ? atas : nilai);
}

// Stage hanya mati setelah keluaran turun HISTERESIS_PCT di bawah ambangnya,
// jadi nilai di batas tidak membuat relay berkedip.
bool tahapAktif(float keluaranPct, float ambangPct, bool sekarang) {
  return sekarang ? keluaranPct > ambangPct - HISTERESIS_PCT : keluaranPct >= ambangPct;
}

void kosongkanPid(Pid &pid) {
  pid.integralC = 0;
  pid.keluaranPct = 0;
  pid.pemanas1 = false;
  pid.pemanas2 = false;
}

// dt = periode kontrol nyata dari pemanggil, supaya integral dan turunan tidak
// melonjak setelah loop sempat tertahan.
void langkahPid(Pid &pid, float suhu, bool valid, float dt, const Perintah &perintah) {
  // Tanpa bacaan yang bisa dipercaya, atau di luar rentang masuk akal, tidak
  // ada panas.
  if (!valid || suhu > TRIP_ATAS_C || suhu < TRIP_BAWAH_C) {
    kosongkanPid(pid);
    pid.adaErrorLalu = false;
    return;
  }

  const float setpoint = batasi(perintah.setpoint, SETPOINT_MIN, SETPOINT_MAX);
  const float error = setpoint - suhu;

  // Integral dalam kelvin, lalu seluruh suku diskalakan SKALA_PENUH_C supaya
  // kp/ki/kd tetap berarti seperti di UI server.
  pid.integralC = batasi(pid.integralC + perintah.ki * error * dt, INTEGRAL_MIN_C, INTEGRAL_MAX_C);

  // Turunan dilewati pada sampel pertama: dt belum diketahui dan slope karangan
  // akan menyentak pemanas.
  float turunan = 0;
  if (pid.adaErrorLalu && dt > 0.0f) {
    turunan = (error - pid.errorLalu) / dt;
  }
  pid.errorLalu = error;
  pid.adaErrorLalu = true;

  pid.keluaranPct =
      batasi((perintah.kp * error + pid.integralC + perintah.kd * turunan) * (100.0f / SKALA_PENUH_C),
             0.0f, 100.0f);
  pid.pemanas1 = tahapAktif(pid.keluaranPct, AMBANG_PEMANAS1_PCT, pid.pemanas1);
  pid.pemanas2 = tahapAktif(pid.keluaranPct, AMBANG_PEMANAS2_PCT, pid.pemanas2);
}

// =================================== SENSOR =================================
// DS18B20 + DHT22, keduanya dibaca tanpa memblokir loop kontrol.

static OneWire kawatSuhu(PIN_DS18B20);
static DallasTemperature sensorObjek(&kawatSuhu);
static DHT sensorDht(PIN_DHT22, DHT22);

static bool konversiJalan = false;
static uint32_t konversiMulaiMs = 0;
static uint32_t dhtDimintaMs = 0;
static uint32_t dhtCobaLagiMs = 0;

// Waktu konversi Dallas per resolusi 9..12 bit, diindeks (resolusi - 9).
static const uint16_t WAKTU_KONVERSI_MS[] = {94, 188, 375, 750};

static float suhuObjekC = 0;
static bool objekValid = false;
static float suhuRuangC = 0;
static float kelembapanPct = 0;
static bool dhtValid = false;
// uint32_t: penghitung poll bisa berjalan berhari-hari tanpa wrap.
static uint32_t jumlahPoll = 0;

void mulaiSensor() {
  sensorObjek.setWaitForConversion(false);
  sensorObjek.begin();
  sensorObjek.setResolution(RESOLUSI_DS18B20);
  sensorDht.begin();
}

void pollSensor(uint32_t ms) {
  ++jumlahPoll;

  // DS18B20: minta dan baca di poll berbeda, jadi konversi 375 ms tidak menahan
  // loop kontrol.
  if (!konversiJalan) {
    sensorObjek.requestTemperatures();
    konversiJalan = true;
    konversiMulaiMs = ms;
  } else if (ms - konversiMulaiMs >= WAKTU_KONVERSI_MS[constrain(RESOLUSI_DS18B20, 9, 12) - 9]) {
    konversiJalan = false;
    const float nilai = sensorObjek.getTempCByIndex(0);
    // -127 = probe terlepas, 85 = nilai register saat power-on. Keduanya bukan
    // suhu yang mungkin dilihat inkubator ini.
    objekValid = nilai > TRIP_BAWAH_C - 5.0f && nilai < 84.0f;
    if (objekValid) {
      suhuObjekC = nilai;
    }
  }

  // DHT22 butuh 2 s antar pembacaan; kegagalan di-backoff supaya sensor hilang
  // tidak membuat loop menunggu timeout berulang.
  if (dhtCobaLagiMs != 0) {
    if (ms < dhtCobaLagiMs) {
      return;
    }
    dhtCobaLagiMs = 0;
  }
  if (ms - dhtDimintaMs < 2500) {
    return;
  }
  dhtDimintaMs = ms;

  const float ruang = sensorDht.readTemperature();
  const float lembap = sensorDht.readHumidity();
  if (isnan(ruang) || isnan(lembap) || ruang < 10.0f || ruang > 45.0f || lembap < 5.0f ||
      lembap > 99.0f) {
    dhtValid = false;
    dhtCobaLagiMs = ms + 30000;
    return;
  }
  suhuRuangC = ruang;
  kelembapanPct = lembap;
  dhtValid = true;
}

// True setelah kedua sensor memberi sampel, atau setelah ~2000 poll, supaya
// sensor yang hilang tidak membuat perangkat diam selamanya.
bool sensorSiap() { return (objekValid && dhtValid) || jumlahPoll > 2000; }

// ================================== RELAY ===================================
// Satu tabel: menambah relay cukup satu baris dan satu field di Pembacaan.

static const uint8_t PIN_RELAY[] = {PIN_KIPAS, PIN_PEMANAS1, PIN_PEMANAS2, PIN_LAMPU};

// Menulis level tidak-aktif sebelum pinMode mencegah relay berbunyi saat
// power-on.
void tulisRelay(uint8_t pin, bool nyala) {
  digitalWrite(pin, (RELAY_AKTIF_RENDAH ? !nyala : nyala) ? HIGH : LOW);
}

void mulaiRelay() {
  for (uint8_t pin : PIN_RELAY) {
    tulisRelay(pin, false);
    pinMode(pin, OUTPUT);
    tulisRelay(pin, false);
  }
}

void terapkanRelay(const Pembacaan &p) {
  const bool keadaan[] = {p.kipas, p.pemanas1, p.pemanas2, p.lampu};
  for (size_t i = 0; i < sizeof(PIN_RELAY); ++i) {
    tulisRelay(PIN_RELAY[i], keadaan[i]);
  }
}

// Sirkulasi adalah kondisi aman untuk inkubator tertutup: kipas nyala, pemanas
// mati.
void relayAman() {
  tulisRelay(PIN_KIPAS, true);
  tulisRelay(PIN_PEMANAS1, false);
  tulisRelay(PIN_PEMANAS2, false);
}

// =================================== LCD ====================================
// Buffer tetap, bukan String: tidak ada alokasi heap per frame LCD.

static LiquidCrystal_I2C lcd(ALAMAT_LCD, 16, 2);
static char baris1Lama[17] = {0};
static char baris2Lama[17] = {0};

void tulisBarisLcd(uint8_t baris, const char *teks) {
  // Pad ke 16 karakter supaya sisa teks lama ikut terhapus.
  char padat[17];
  snprintf(padat, sizeof(padat), "%-16.16s", teks);
  lcd.setCursor(0, baris);
  lcd.print(padat);
}

void mulaiLcd() {
  Wire.begin(PIN_SDA_LCD, PIN_SCL_LCD);
  lcd.init();
  lcd.backlight();
  lcd.clear();
}

void ujiLcd() {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("INCUBATOR");
  lcd.setCursor(0, 1);
  lcd.print(VERSI_FW);
}

// Panel ada di bus I2C lambat; redraw tiap loop lebih mahal daripada loop
// kontrolnya sendiri.
void gambarLcd(const char *baris1, const char *baris2) {
  if (strcmp(baris1, baris1Lama) == 0 && strcmp(baris2, baris2Lama) == 0) {
    return;
  }
  strncpy(baris1Lama, baris1, sizeof(baris1Lama) - 1);
  strncpy(baris2Lama, baris2, sizeof(baris2Lama) - 1);
  tulisBarisLcd(0, baris1Lama);
  tulisBarisLcd(1, baris2Lama);
}

// ================================== JEMBATAN ================================
// Satu-satunya jalur antar core: salinan nilai POD, tanpa lock. Satu slot per
// jalur, producer menimpa, consumer mengambil yang terbaru.

static QueueHandle_t antreanBacaan = nullptr;
static QueueHandle_t antreanPerintah = nullptr;
static QueueHandle_t antreanKejadian = nullptr;

void mulaiJembatan() {
  antreanBacaan = xQueueCreate(1, sizeof(Pembacaan));
  antreanPerintah = xQueueCreate(1, sizeof(Perintah));
  antreanKejadian = xQueueCreate(4, sizeof(Kejadian));
}

void kirimBacaan(const Pembacaan &p) { xQueueOverwrite(antreanBacaan, &p); }

bool ambilBacaan(Pembacaan &p) { return xQueueReceive(antreanBacaan, &p, 0) == pdTRUE; }

void kirimPerintah(const Perintah &p) { xQueueOverwrite(antreanPerintah, &p); }

bool ambilPerintah(Perintah &p) { return xQueueReceive(antreanPerintah, &p, 0) == pdTRUE; }

// Kejadian jarang; queue penuh membuang yang baru, bukan memblokir core kontrol.
void kirimKejadian(Kejadian k) { xQueueSend(antreanKejadian, &k, 0); }

bool ambilKejadian(Kejadian &k) { return xQueueReceive(antreanKejadian, &k, 0) == pdTRUE; }

// ================================= PROTOKOL =================================
// Kontrak wire dengan Laravel di satu tempat: kedua transport memakai parser dan
// body yang sama.

int8_t flagOverride(JsonObject override, const char *kunci) {
  if (!override[kunci].is<bool>()) {
    return -1;
  }
  return override[kunci].as<bool>() ? 1 : 0;
}

Perintah perintahAwal() {
  Perintah p{};
  p.setpoint = SETPOINT_AWAL;
  p.kp = 2.0f;
  p.ki = 0.1f;
  p.kd = 0.5f;
  p.lampuAktif = false;
  p.terpasang = false;
  p.kodeBayi[0] = '\0';
  p.overrideKipas = -1;
  p.overridePemanas1 = -1;
  p.overridePemanas2 = -1;
  p.overrideLampu = -1;
  p.kedaluwarsaMs = 0;
  return p;
}

// Key yang tidak ada mempertahankan nilai lama, jadi payload parsial tidak bisa
// mereset controller.
void terapkanJson(const char *body, Perintah &p) {
  StaticJsonDocument<512> doc;
  if (deserializeJson(doc, body) != DeserializationError::Ok) {
    return;
  }

  JsonObject config = doc["config"].as<JsonObject>();
  if (!config.isNull()) {
    p.setpoint = config["temperature_setpoint"] | p.setpoint;
    p.kp = config["pid_kp"] | p.kp;
    p.ki = config["pid_ki"] | p.ki;
    p.kd = config["pid_kd"] | p.kd;
    p.lampuAktif = config["blue_led_enabled"] | p.lampuAktif;
  }

  p.terpasang = doc["is_paired"] | p.terpasang;
  JsonObject bayi = doc["baby"].as<JsonObject>();
  const char *kode = bayi.isNull() ? "" : bayi["code"].as<const char *>();
  strncpy(p.kodeBayi, kode == nullptr ? "" : kode, sizeof(p.kodeBayi) - 1);
  p.kodeBayi[sizeof(p.kodeBayi) - 1] = '\0';

  // Server selalu mengirim objek ini, kosong saat tidak ada override, jadi
  // objek kosong berarti "kembali ke auto", bukan field yang hilang.
  JsonObject override = doc["relay_overrides"].as<JsonObject>();
  if (!override.isNull()) {
    p.overrideKipas = flagOverride(override, "fan");
    p.overridePemanas1 = flagOverride(override, "heater_1");
    p.overridePemanas2 = flagOverride(override, "heater_2");
    p.overrideLampu = flagOverride(override, "blue_light");
  }
}

// Body telemetry untuk POST /api/devices/telemetry dan topik telemetry. Bentuk
// identik di kedua jalur. Ditulis ke buffer pemanggil: tanpa String, tanpa heap.
size_t bodyTelemetri(const Pembacaan &p, char *keluaran, size_t ukuran) {
  StaticJsonDocument<384> doc;
  doc["device_code"] = KODE_PERANGKAT;
  // -127 = probe terlepas; server menyimpannya apa adanya dan membaca fault
  // dari sensor_status. Dua desimal: resolusi 0.0625 C, kolom decimal(5,2).
  doc["temperature_object"] = p.objekValid ? roundf(p.suhuObjek * 100.0f) / 100.0f : -127.0f;
  doc["temperature_room"] = roundf(p.suhuRuang * 100.0f) / 100.0f;
  doc["humidity"] = roundf(p.kelembapan * 10.0f) / 10.0f;
  JsonObject relay = doc.createNestedObject("relays");
  relay["fan"] = p.kipas;
  relay["heater_1"] = p.pemanas1;
  relay["heater_2"] = p.pemanas2;
  relay["blue_light"] = p.lampu;
  JsonObject status = doc.createNestedObject("sensor_status");
  status["ds18b20"] = p.objekValid;
  status["dht22"] = p.dhtValid;
  // Tanpa timestamp: Laravel yang menstempel, jamnya lebih baik daripada ESP32
  // yang belum pernah menerima NTP.
  doc["uptime_s"] = p.uptimeDetik;
  return serializeJson(doc, keluaran, ukuran);
}

size_t bodyStatus(bool online, char *keluaran, size_t ukuran) {
  StaticJsonDocument<64> doc;
  doc["online"] = online;
  return serializeJson(doc, keluaran, ukuran);
}

// =================================== TLS ====================================

void pasangTls(WiFiClientSecure &client) {
  if (VERIFIKASI_TLS) {
    client.setCACert(CA_CERT_PEM);
    return;
  }
  client.setInsecure();
}

// ================================= TRANSPORT ================================
// Sisi Core 0: satu Wi-Fi station, dua transport, satu kontrak JSON. MQTT utama
// (satu socket, sync retained, event, last-will status); HTTP cadangan.

static WiFiClientSecure tls;
static HTTPClient http;
static PubSubClient mqtt(tls);

static char topikSinkron[64] = "";
static char topikTelemetri[64] = "";
static char topikKejadian[64] = "";
static char topikStatus[64] = "";

static uint32_t mundurSampaiMs = 0;
static uint16_t mundurMs = 0;
static uint16_t portHttp = 0;
static String hostHttp;

// Command terakhir yang berhasil diantar transport mana pun. Payload parsial
// digabung ke sini, jadi respons tanpa `relay_overrides` tidak menghapus
// override yang sedang aktif.
static Perintah perintahTerakhir{};

void buatTopik(char *keluaran, size_t ukuran, const char *akhiran) {
  snprintf(keluaran, ukuran, "%s/%s/%s", AWALAN_TOPIK, KODE_PERANGKAT, akhiran);
}

// URL_HTTP dipecah sekali saat boot: kerja String per request membuang heap.
void uraiUrl() {
  const char *url = URL_HTTP;
  const char *awal = strstr(url, "://");
  hostHttp = String(awal == nullptr ? url : awal + 3);
  const bool aman = awal != nullptr && strncmp(url, "https", 5) == 0;
  const int titikDua = hostHttp.indexOf(':');
  portHttp = titikDua < 0 ? (aman ? 443 : 80)
                          : static_cast<uint16_t>(hostHttp.substring(titikDua + 1).toInt());
  if (titikDua >= 0) {
    hostHttp.remove(titikDua);
  }
}

void terimaSinkron(const char *body) {
  terapkanJson(body, perintahTerakhir);
  perintahTerakhir.kedaluwarsaMs = millis() + TTL_OVERRIDE_MS;
  kirimPerintah(perintahTerakhir);
}

void saatPesanMasuk(char *topik, byte *payload, unsigned int panjang) {
  (void)topik;
  // Sync retained ukurannya kecil; buffer stack supaya callback tidak menyentuh
  // heap.
  char body[512] = {0};
  const size_t salin = panjang < sizeof(body) - 1 ? panjang : sizeof(body) - 1;
  memcpy(body, payload, salin);
  terimaSinkron(body);
}

void jadwalkanMundur(uint32_t ms) {
  const uint32_t batas = BATAS_MUNDUR_MS;
  const uint32_t berikut = mundurMs == 0 ? 2000U : static_cast<uint32_t>(mundurMs) * 2U;
  mundurMs = berikut < batas ? berikut : batas;
  mundurSampaiMs = ms + mundurMs;
}

void mulaiTransport() {
  buatTopik(topikSinkron, sizeof(topikSinkron), "sync");
  buatTopik(topikTelemetri, sizeof(topikTelemetri), "telemetry");
  buatTopik(topikKejadian, sizeof(topikKejadian), "events");
  buatTopik(topikStatus, sizeof(topikStatus), "status");

  uraiUrl();
  perintahTerakhir = perintahAwal();
  pasangTls(tls);

  http.setTimeout(4000);
  http.setReuse(true);

  mqtt.setServer(HOST_MQTT, PORT_MQTT);
  mqtt.setBufferSize(1024);
  mqtt.setKeepAlive(KEEPALIVE_MQTT_DETIK);
  mqtt.setCallback(saatPesanMasuk);

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(SSID_WIFI, PASSWORD_WIFI);
}

bool jaringanNyala() { return WiFi.status() == WL_CONNECTED; }

bool mqttNyala() { return mqtt.connected(); }

// Reconnect Wi-Fi plus keepalive MQTT. Semua panggilan blocking ada di sini.
void rawatTransport(uint32_t ms) {
  static uint32_t sambungLagiMs = 0;
  if (!jaringanNyala()) {
    if (ms - sambungLagiMs > 2000) {
      sambungLagiMs = ms;
      WiFi.reconnect();
    }
    return;
  }

  if (!mqtt.connected()) {
    // Last will membuat broker menandai perangkat offline walau listrik putus,
    // dan status retained membuat dashboard langsung membaca keadaan sebenarnya.
    char status[64];
    bodyStatus(false, status, sizeof(status));
    if (mqtt.connect(KODE_PERANGKAT, USER_MQTT, PASSWORD_MQTT, topikStatus, 1, true, status)) {
      mqtt.subscribe(topikSinkron, 1);
    }
    return;
  }
  mqtt.loop();
}

void kirimTelemetri(const Pembacaan &p) {
  char body[384];
  bodyTelemetri(p, body, sizeof(body));
  mqtt.publish(topikTelemetri, body, false);
}

void kirimKejadianMqtt(const char *jenis, const char *prioritas) {
  if (!mqtt.connected()) {
    return;
  }
  char body[192];
  snprintf(body, sizeof(body),
           "{\"event_type\":\"%s\",\"priority\":\"%s\",\"message\":\"%s\","
           "\"payload\":{\"device_code\":\"%s\",\"firmware\":\"%s\"}}",
           jenis, prioritas, jenis, KODE_PERANGKAT, VERSI_FW);
  mqtt.publish(topikKejadian, body, false);
}

// POST reading lalu menerapkan payload sync yang menumpang di respons.
// Return false bila tidak ada yang diterapkan; command terakhir tetap berlaku.
bool postTelemetri(uint32_t ms, const Pembacaan &p) {
  if (ms < mundurSampaiMs) {
    return false;
  }
  if (!http.begin(tls, hostHttp, portHttp, "/api/devices/telemetry", portHttp == 443)) {
    jadwalkanMundur(ms);
    return false;
  }
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Device-Token", TOKEN_PERANGKAT);

  char body[384];
  const size_t panjang = bodyTelemetri(p, body, sizeof(body));
  const int status = http.POST(reinterpret_cast<uint8_t *>(body), panjang);
  // Body harus dibaca sebelum end(): socket keep-alive dipakai ulang, body yang
  // belum dibaca ikut terbuang bersama koneksi.
  const String balasan = http.getString();
  http.end();

  if (status != HTTP_CODE_OK) {
    jadwalkanMundur(ms);
    return false;
  }

  terimaSinkron(balasan.c_str());
  mundurMs = 0;
  return true;
}

// ================================= APLIKASI =================================

// Milik core kontrol. Core 0 tidak menyentuhnya langsung: perintah baru
// menyeberang lewat queue, jadi tidak ada tulisan bersamaan.
static Perintah perintah{};

// Sticky sampai operator re-arm: blip sensor sesaat tidak boleh menyalakan
// pemanas lagi dengan sendirinya.
static bool trip = false;

// Override relay kedaluwarsa TTL_OVERRIDE_MS setelah sync sukses terakhir,
// sehingga jaringan yang hilang mengembalikan inkubator ke kontrol otomatis.
void kedaluwarsaOverride(uint32_t ms) {
  if (perintah.kedaluwarsaMs == 0 || ms <= perintah.kedaluwarsaMs) {
    return;
  }
  perintah.overrideKipas = -1;
  perintah.overridePemanas1 = -1;
  perintah.overridePemanas2 = -1;
  perintah.overrideLampu = -1;
  perintah.kedaluwarsaMs = 0;
}

void masukTrip(Kejadian jenis) {
  if (trip) {
    return;
  }
  trip = true;
  kirimKejadian(jenis);
  relayAman();
}

// Buffer tetap, bukan String: format LCD berjalan tiap 100 ms dan alokasi heap
// per frame memecah memori perlahan.
void formatBaris1(char *keluaran, size_t ukuran, const Pembacaan &p) {
  if (!p.objekValid) {
    snprintf(keluaran, ukuran, "SENSOR ERROR");
  } else if (p.trip) {
    snprintf(keluaran, ukuran, "TRIP %5.1fC", p.suhuObjek);
  } else {
    snprintf(keluaran, ukuran, "OBJ%5.1fC RH%4.1f", p.suhuObjek, p.kelembapan);
  }
}

void formatBaris2(char *keluaran, size_t ukuran, const Pembacaan &p) {
  if (p.trip) {
    snprintf(keluaran, ukuran, "FAIL-SAFE ON");
  } else if (p.wifiMati) {
    snprintf(keluaran, ukuran, "NO NET PID OK");
  } else if (perintah.terpasang) {
    snprintf(keluaran, ukuran, "%-16.16s", perintah.kodeBayi);
  } else {
    snprintf(keluaran, ukuran, "%s STANDBY", KODE_PERANGKAT);
  }
}

// Urutan prioritas: fail-safe > override > PID > idle.
bool pilihAktuator(int8_t override, bool otomatis) {
  return override >= 0 ? override > 0 : otomatis;
}

void terapkanAktuator(Pembacaan &p, const Pid &pid) {
  p.kipas = trip ? true : pilihAktuator(perintah.overrideKipas, true);
  p.pemanas1 = trip ? false : pilihAktuator(perintah.overridePemanas1, pid.pemanas1);
  p.pemanas2 = trip ? false : pilihAktuator(perintah.overridePemanas2, pid.pemanas2);
  p.lampu = pilihAktuator(perintah.overrideLampu, perintah.lampuAktif);
}

void kurasKejadian() {
  Kejadian jenis;
  while (ambilKejadian(jenis)) {
    switch (jenis) {
      case Kejadian::SensorRusak:
        kirimKejadianMqtt("sensor_fault", "danger");
        break;
      case Kejadian::TerlaluPanas:
        kirimKejadianMqtt("overheat", "danger");
        break;
      case Kejadian::JaringanHilang:
        kirimKejadianMqtt("network_lost", "warning");
        break;
      default:
        kirimKejadianMqtt("device_boot", "info");
        break;
    }
  }
}

// Core 1: sensor, PID, fail-safe, relay, LCD, publish ke queue. Tidak pernah
// memblokir.
void loopKontrol() {
  static Pid pid;
  static uint32_t langkahLaluMs = 0;
  static bool wifiSebelumnya = false;

  const uint32_t ms = millis();

  // Perintah dari transport mana pun diterapkan sebelum sampel ini, jadi
  // perubahan setpoint berlaku pada langkah kontrol berikutnya.
  Perintah masuk{};
  if (ambilPerintah(masuk)) {
    perintah = masuk;
  }

  pollSensor(ms);

  const uint32_t jedaMs = ms - langkahLaluMs;
  if (jedaMs < 100) {
    return;
  }
  const float dt = jedaMs / 1000.0f;
  langkahLaluMs = ms;

  if (!sensorSiap()) {
    return;
  }

  const bool valid = objekValid;

  // Fail-safe paling dulu: menang atas PID dan atas semua override.
  if (!valid) {
    masukTrip(Kejadian::SensorRusak);
  } else if (suhuObjekC > TRIP_ATAS_C || suhuObjekC < TRIP_BAWAH_C) {
    masukTrip(Kejadian::TerlaluPanas);
  }

  kedaluwarsaOverride(ms);

  if (!trip) {
    langkahPid(pid, suhuObjekC, valid, dt, perintah);
  }

  // Satu pembacaan status Wi-Fi per langkah, bukan dua.
  const bool wifiNyala = jaringanNyala();

  Pembacaan p{};
  p.suhuObjek = suhuObjekC;
  p.suhuRuang = suhuRuangC;
  p.kelembapan = kelembapanPct;
  p.objekValid = valid;
  p.dhtValid = dhtValid;
  p.trip = trip;
  p.wifiMati = !wifiNyala;
  p.uptimeDetik = ms / 1000;
  terapkanAktuator(p, pid);

  terapkanRelay(p);
  kirimBacaan(p);

  char baris1[17];
  char baris2[17];
  formatBaris1(baris1, sizeof(baris1), p);
  formatBaris2(baris2, sizeof(baris2), p);
  gambarLcd(baris1, baris2);

  if (wifiSebelumnya && !wifiNyala) {
    kirimKejadian(Kejadian::JaringanHilang);
  }
  wifiSebelumnya = wifiNyala;
}

// Core 0: pengawasan Wi-Fi, publish telemetry, drain event. Semua panggilan
// blocking ada di sini.
void loopJaringan() {
  const uint32_t ms = millis();
  rawatTransport(ms);
  kurasKejadian();

  // Core kontrol publish tiap 100 ms; server cukup tiap INTERVAL_TELEMETRI_MS.
  // Ambil yang terbaru dan buang sisanya, jadi burst setelah stall tetap
  // mengirim satu sampel.
  static uint32_t kirimLaluMs = 0;
  if (ms - kirimLaluMs < INTERVAL_TELEMETRI_MS) {
    return;
  }
  kirimLaluMs = ms;

  Pembacaan p{};
  if (!ambilBacaan(p) || !jaringanNyala()) {
    return;
  }

  // MQTT lebih murah (satu socket, tanpa TLS handshake per request). HTTP tetap
  // cadangan supaya config dan pairing masih sampai saat broker mati.
  if (mqttNyala()) {
    kirimTelemetri(p);
    return;
  }

  postTelemetri(ms, p);
}

// =================================== TASK ===================================

// Dua task, dua core: handshake TLS di Core 0 tidak pernah menunda keputusan
// pemanas di Core 1.
void tugasKontrol(void *argumen) {
  (void)argumen;
  for (;;) {
    loopKontrol();
    delay(1);
  }
}

void tugasJaringan(void *argumen) {
  (void)argumen;
  for (;;) {
    loopJaringan();
    delay(1);
  }
}

void setup() {
  Serial.begin(BAUD_SERIAL);

  mulaiLcd();
  ujiLcd();

  mulaiSensor();
  mulaiRelay();
  mulaiJembatan();

  perintah = perintahAwal();
  mulaiTransport();

  kirimKejadian(Kejadian::Boot);

  xTaskCreatePinnedToCore(tugasKontrol, "kontrol", 4096, nullptr, 2, nullptr, 1);
  xTaskCreatePinnedToCore(tugasJaringan, "jaringan", 8192, nullptr, 1, nullptr, 0);
}

void loop() {
  // Kedua core sudah punya task sendiri; loop() hanya menjaga watchdog.
  delay(1000);
}
