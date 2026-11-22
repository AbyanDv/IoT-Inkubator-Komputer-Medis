#include <Arduino.h>
#include <ArduinoJson.h> // Wajib ArduinoJson v6
#include <DallasTemperature.h>
#include <DHT.h>
#include <LiquidCrystal_I2C.h>
#include <OneWire.h>
#include <PubSubClient.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <Wire.h>

// --- 1. Konfigurasi Perangkat & Jaringan ---
#define KODE_PERANGKAT  "INC-001"
#define SSID_WIFI       "POCO X5 5G"
#define PASSWORD_WIFI   "cobaliathplu"
#define HOST_MQTT       "41.216.180.77"
#define PASSWORD_MQTT   ""
#define ALAMAT_LCD      0x27
#define RELAY_NYALA_LOW true

const int PIN_SENSOR_RUANGAN = 4;
const int PIN_SENSOR_BAYI    = 5;

enum IndeksRelay { KIPAS, PEMANAS_1, PEMANAS_2, LAMPU_BIRU, JUMLAH_RELAY };
const int PIN_RELAY[JUMLAH_RELAY]           = {25, 26, 27, 14};
const char* NAMA_RELAY_SERVER[JUMLAH_RELAY] = {"fan", "heater_1", "heater_2", "blue_light"};
const unsigned long DURASI_KONTROL_MANUAL   = 10000;

// --- 2. Objek & Variabel Global ---
OneWire jalurOneWire(PIN_SENSOR_RUANGAN);
DallasTemperature sensorRuangan(&jalurOneWire);
DHT sensorBayi(PIN_SENSOR_BAYI, DHT22);
LiquidCrystal_I2C layarLcd(ALAMAT_LCD, 16, 2);

WiFiClientSecure klienWifiAman;
PubSubClient koneksiMqtt(klienWifiAman);
String topikPerintah, topikDataSensor, topikStatusOnline;

float suhuBayi = 0.0, suhuRuangan = 0.0, kelembapan = 0.0;
bool sensorBayiNormal = false, sensorRuanganNormal = false;

float suhuTarget = 33.5;
float kp = 25.0, ki = 0.8, kd = 10.0;
float integralPid = 0.0, errorSebelumnya = 0.0, outputPid = 0.0;

bool lampuBiruAktif            = false;
bool statusRelay[JUMLAH_RELAY] = {false, false, false, false};
int perintahManual[JUMLAH_RELAY] = {-1, -1, -1, -1}; // -1: otomatis, 0: mati, 1: nyala
bool modeManualAktif             = false;
unsigned long waktuMulaiManual   = 0;

// --- 3. Fungsi Bantuan ---
bool apakahSudahWaktunya(unsigned long &waktuTerakhir, unsigned long waktuSekarang, unsigned long jeda) {
  if (waktuSekarang - waktuTerakhir < jeda) return false;
  waktuTerakhir = waktuSekarang;
  return true;
}

float batasiAngka(float nilai, float batasBawah, float batasAtas) {
  if (nilai < batasBawah) return batasBawah;
  if (nilai > batasAtas) return batasAtas;
  return nilai;
}

bool tentukanStatusRelay(int modeManual, bool otomatis) {
  if (modeManual == 1) return true;
  if (modeManual == 0) return false;
  return otomatis;
}

void ubahKondisiRelay(int pin, bool nyala) {
  digitalWrite(pin, (nyala == RELAY_NYALA_LOW) ? LOW : HIGH);
}

// --- 4. Pembacaan Sensor (Core 1) ---
void bacaSemuaSensor(unsigned long waktuSekarang) {
  static unsigned long waktuUkurRuangan = 0, waktuBacaDht = 0;
  static bool sedangMengukurRuangan = false;

  if (!sedangMengukurRuangan) {
    sensorRuangan.requestTemperatures();
    waktuUkurRuangan = waktuSekarang;
    sedangMengukurRuangan = true;
  } else if (waktuSekarang - waktuUkurRuangan >= 500) {
    sedangMengukurRuangan = false;
    float hasil = sensorRuangan.getTempCByIndex(0);
    sensorRuanganNormal = (hasil > -50.0 && hasil < 84.0);
    if (sensorRuanganNormal) suhuRuangan = hasil;
  }

  if (apakahSudahWaktunya(waktuBacaDht, waktuSekarang, 2500)) {
    float t = sensorBayi.readTemperature();
    float h = sensorBayi.readHumidity();
    sensorBayiNormal = (!isnan(t) && !isnan(h));
    if (sensorBayiNormal) {
      suhuBayi = t;
      kelembapan = h;
    }
  }
}

// --- 5. Algoritma PID & Kontrol Relay Bertingkat Tanpa PWM (Core 1) ---
void hitungPid() {
  if (!sensorRuanganNormal) {
    integralPid = 0.0;
    errorSebelumnya = 0.0;
    outputPid = 0.0;
    return;
  }

  float error     = suhuTarget - suhuRuangan;
  integralPid     = batasiAngka(integralPid + error, -50.0, 50.0);
  float turunan   = error - errorSebelumnya;
  errorSebelumnya = error;

  // Output PID bernilai -100 (terlalu panas) sampai +100 (butuh pemanasan penuh)
  outputPid = batasiAngka((kp * error) + (ki * integralPid) + (kd * turunan), -100.0, 100.0);
}

void aturRelay(unsigned long waktuSekarang) {
  if (modeManualAktif && (waktuSekarang - waktuMulaiManual >= DURASI_KONTROL_MANUAL)) {
    for (int i = 0; i < JUMLAH_RELAY; i++) perintahManual[i] = -1;
    modeManualAktif = false;
  }

  bool pemanas1Auto = false, pemanas2Auto = false, kipasAuto = false;

  if (sensorRuanganNormal) {
    // Pemanas 1 nyala saat output PID >= 20, mati saat <= 10
    pemanas1Auto = statusRelay[PEMANAS_1];
    if (outputPid >= 20.0) pemanas1Auto = true;
    else if (outputPid <= 10.0) pemanas1Auto = false;

    // Pemanas 2 ikut bantu saat output PID >= 60, mati saat <= 50
    pemanas2Auto = statusRelay[PEMANAS_2];
    if (outputPid >= 60.0) pemanas2Auto = true;
    else if (outputPid <= 50.0) pemanas2Auto = false;

    // Kipas nyala saat output PID <= -20 (suhu berlebih), mati saat >= -10
    kipasAuto = statusRelay[KIPAS];
    if (outputPid <= -20.0) kipasAuto = true;
    else if (outputPid >= -10.0) kipasAuto = false;
  }

  statusRelay[KIPAS]      = tentukanStatusRelay(perintahManual[KIPAS], kipasAuto);
  statusRelay[PEMANAS_1]  = tentukanStatusRelay(perintahManual[PEMANAS_1], pemanas1Auto);
  statusRelay[PEMANAS_2]  = tentukanStatusRelay(perintahManual[PEMANAS_2], pemanas2Auto);
  statusRelay[LAMPU_BIRU] = tentukanStatusRelay(perintahManual[LAMPU_BIRU], lampuBiruAktif);

  for (int i = 0; i < JUMLAH_RELAY; i++) {
    ubahKondisiRelay(PIN_RELAY[i], statusRelay[i]);
  }
}

// --- 6. Tampilan LCD Bergerak & Serial Monitor (Core 1) ---
void tampilkanTeksBerjalan(int baris, const String &teks, int &posisi) {
  String teksPenuh = "                " + teks + "                ";
  if (posisi > (int)teksPenuh.length() - 16) posisi = 0;

  layarLcd.setCursor(0, baris);
  layarLcd.print(teksPenuh.substring(posisi, posisi + 16));
  posisi++;
}

void perbaruiLayarLcd() {
  static int posAtas = 0, posBawah = 0;

  String sBayi   = sensorBayiNormal    ? String(suhuBayi, 1) + " C"    : "ERROR";
  String sRuang  = sensorRuanganNormal ? String(suhuRuangan, 1) + " C" : "ERROR";
  String sLembap = sensorBayiNormal    ? String(kelembapan, 1) + " %"  : "ERROR";

  String barisAtas  = "SUHU BAYI: " + sBayi + " | SUHU RUANGAN: " + sRuang + " | KELEMBAPAN: " + sLembap;
  String barisBawah = "TARGET: " + String(suhuTarget, 1) + " C | PID: " + String(outputPid, 0) +
                      " | KIPAS: " + (statusRelay[KIPAS] ? "NYALA" : "MATI") +
                      " | PEMANAS 1: " + (statusRelay[PEMANAS_1] ? "NYALA" : "MATI") +
                      " | PEMANAS 2: " + (statusRelay[PEMANAS_2] ? "NYALA" : "MATI") +
                      " | LAMPU: " + (statusRelay[LAMPU_BIRU] ? "NYALA" : "MATI");

  tampilkanTeksBerjalan(0, barisAtas, posAtas);
  tampilkanTeksBerjalan(1, barisBawah, posBawah);
}

void tampilkanKeSerialMonitor() {
  Serial.println("========================================");
  Serial.printf("SUHU BAYI       : %s\n", sensorBayiNormal ? (String(suhuBayi, 1) + " C").c_str() : "TIDAK TERBACA");
  Serial.printf("SUHU RUANGAN    : %s\n", sensorRuanganNormal ? (String(suhuRuangan, 1) + " C").c_str() : "TIDAK TERBACA");
  Serial.printf("KELEMBAPAN      : %s\n", sensorBayiNormal ? (String(kelembapan, 1) + " %").c_str() : "TIDAK TERBACA");
  Serial.printf("TARGET & PID    : %.1f C | Output PID: %.1f (Kp:%.1f Ki:%.1f Kd:%.1f)\n", suhuTarget, outputPid, kp, ki, kd);
  Serial.printf("MODE KONTROL    : %s\n", modeManualAktif ? "MANUAL (10 Detik)" : "OTOMATIS (PID)");
  Serial.printf("STATUS RELAY    : Kipas=%s | Pemanas1=%s | Pemanas2=%s | Lampu=%s\n",
                statusRelay[KIPAS] ? "NYALA" : "MATI",
                statusRelay[PEMANAS_1] ? "NYALA" : "MATI",
                statusRelay[PEMANAS_2] ? "NYALA" : "MATI",
                statusRelay[LAMPU_BIRU] ? "NYALA" : "MATI");
}

// --- 7. Komunikasi WiFi & MQTT (Core 0) ---
void prosesPerintahServer(const char* dataJson, unsigned int panjangData) {
  StaticJsonDocument<512> doc;
  if (deserializeJson(doc, dataJson, panjangData)) return;

  JsonObject config = doc["config"];
  if (!config.isNull()) {
    suhuTarget     = batasiAngka(config["temperature_setpoint"] | suhuTarget, 28.0, 37.0);
    lampuBiruAktif = config["blue_led_enabled"] | lampuBiruAktif;
    kp             = batasiAngka(config["kp"] | kp, 0.0, 500.0);
    ki             = batasiAngka(config["ki"] | ki, 0.0, 100.0);
    kd             = batasiAngka(config["kd"] | kd, 0.0, 500.0);
  }

  JsonObject overrides = doc["relay_overrides"];
  if (!overrides.isNull()) {
    for (int i = 0; i < JUMLAH_RELAY; i++) {
      JsonVariant nilai = overrides[NAMA_RELAY_SERVER[i]];
      perintahManual[i] = nilai.is<bool>() ? (nilai.as<bool>() ? 1 : 0) : -1;
    }
    modeManualAktif  = true;
    waktuMulaiManual = millis();
  }
}

void saatPesanMqttDiterima(char* topik, byte* isiPesan, unsigned int panjangPesan) {
  prosesPerintahServer((const char*)isiPesan, panjangPesan);
}

void kirimDataKeServer() {
  if (WiFi.status() != WL_CONNECTED || !koneksiMqtt.connected()) return;

  StaticJsonDocument<512> doc;
  doc["device_code"]        = KODE_PERANGKAT;
  doc["temperature_object"] = sensorBayiNormal    ? roundf(suhuBayi * 100) / 100    : 0;
  doc["temperature_room"]   = sensorRuanganNormal ? roundf(suhuRuangan * 100) / 100 : 0;
  doc["humidity"]           = sensorBayiNormal    ? roundf(kelembapan * 10) / 10    : 0;
  doc["pid_output"]         = roundf(outputPid * 10) / 10;
  doc["kp"]                 = kp;
  doc["ki"]                 = ki;
  doc["kd"]                 = kd;

  for (int i = 0; i < JUMLAH_RELAY; i++) {
    doc["relays"][NAMA_RELAY_SERVER[i]] = statusRelay[i];
  }

  doc["sensor_status"]["ds18b20"] = sensorRuanganNormal;
  doc["sensor_status"]["dht22"]   = sensorBayiNormal;
  doc["uptime_s"]                 = millis() / 1000;

  char payload[512];
  serializeJson(doc, payload, sizeof(payload));
  koneksiMqtt.publish(topikDataSensor.c_str(), payload);
}

void jagaKoneksiJaringan(unsigned long waktuSekarang) {
  static unsigned long waktuCobaWifi = 0, waktuCobaMqtt = 0;

  if (WiFi.status() != WL_CONNECTED) {
    if (apakahSudahWaktunya(waktuCobaWifi, waktuSekarang, 5000)) WiFi.reconnect();
    return;
  }

  if (koneksiMqtt.connected()) {
    koneksiMqtt.loop();
    return;
  }

  if (apakahSudahWaktunya(waktuCobaMqtt, waktuSekarang, 5000)) {
    if (koneksiMqtt.connect(KODE_PERANGKAT, KODE_PERANGKAT, PASSWORD_MQTT,
                            topikStatusOnline.c_str(), 1, true, "{\"online\":false}")) {
      koneksiMqtt.publish(topikStatusOnline.c_str(), "{\"online\":true}", true);
      koneksiMqtt.subscribe(topikPerintah.c_str(), 1);
    }
  }
}

void tugasJaringanCore0(void* parameter) {
  unsigned long waktuKirim = 0;
  while (true) {
    unsigned long sekarang = millis();
    jagaKoneksiJaringan(sekarang);
    if (apakahSudahWaktunya(waktuKirim, sekarang, 3000)) kirimDataKeServer();
    vTaskDelay(pdMS_TO_TICKS(10));
  }
}

// --- 8. Setup & Loop Utama (Core 1) ---
void setup() {
  Serial.begin(115200);

  for (int i = 0; i < JUMLAH_RELAY; i++) {
    ubahKondisiRelay(PIN_RELAY[i], false);
    pinMode(PIN_RELAY[i], OUTPUT);
  }

  Wire.begin();
  layarLcd.init();
  layarLcd.backlight();

  sensorRuangan.setWaitForConversion(false);
  sensorRuangan.begin();
  sensorRuangan.setResolution(11);
  sensorBayi.begin();

  String prefix     = String("incubator/") + KODE_PERANGKAT + "/";
  topikPerintah     = prefix + "sync";
  topikDataSensor   = prefix + "telemetry";
  topikStatusOnline = prefix + "status";

  klienWifiAman.setInsecure();
  koneksiMqtt.setServer(HOST_MQTT, 8883);
  koneksiMqtt.setBufferSize(1024);
  koneksiMqtt.setCallback(saatPesanMqttDiterima);

  WiFi.mode(WIFI_STA);
  WiFi.begin(SSID_WIFI, PASSWORD_WIFI);
  xTaskCreatePinnedToCore(tugasJaringanCore0, "TugasJaringan", 12288, nullptr, 1, nullptr, 0);
}

void loop() {
  static unsigned long waktuKontrol = 0, waktuLcd = 0, waktuSerial = 0;
  unsigned long sekarang = millis();

  bacaSemuaSensor(sekarang);

  if (apakahSudahWaktunya(waktuKontrol, sekarang, 1000)) {
    hitungPid();
    aturRelay(sekarang);
  }

  if (apakahSudahWaktunya(waktuLcd, sekarang, 350))     perbaruiLayarLcd();
  if (apakahSudahWaktunya(waktuSerial, sekarang, 2000)) tampilkanKeSerialMonitor();

  delay(10);
}