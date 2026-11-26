#include <Arduino.h>
#include <ArduinoJson.h> // v6
#include <DallasTemperature.h>
#include <DHT.h>
#include <LiquidCrystal_I2C.h>
#include <OneWire.h>
#include <Preferences.h>
#include <PubSubClient.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <Wire.h>
#include <driver/gpio.h>

// ================= Konfigurasi =================
#define KODE_PERANGKAT "INC-001"
#define SSID_WIFI "POCO X5 5G"
#define PASSWORD_WIFI "cobaliathplu"
#define HOST_MQTT "41.216.180.77"
#define PASSWORD_MQTT ""
#define ALAMAT_LCD 0x27
#define RELAY_NYALA_LOW true

constexpr uint8_t PIN_DHT = 5; // DHT22 (suhu ruangan)
constexpr uint8_t PIN_DS = 4; // DS18B20 (suhu bayi)

enum Relay { KIPAS, PEMANAS_1, PEMANAS_2, LAMPU_BIRU, JUMLAH_RELAY };
constexpr uint8_t PIN_RELAY[JUMLAH_RELAY] = {14, 26, 27, 25};
constexpr const char* NAMA_RELAY[JUMLAH_RELAY] = {"fan", "heater_1", "heater_2", "blue_light"};

constexpr uint32_t DURASI_MANUAL = 10000; // ms; setelah itu relay kembali otomatis
constexpr uint32_t INTERVAL_DHT = 2500; // ms (DHT22 min. 2 dtk) = periode sampel PID
constexpr uint32_t JEDA_RELAY = 30; // ms antar perubahan relay (cegah lonjakan arus)
constexpr float BAYI_MAKS = 37.5; // pengaman: pemanas dipaksa mati
constexpr float RUANGAN_MAKS = 38.0;
constexpr float INTEGRAL_MAKS = 15.0; // kontribusi maksimum suku integral (poin PID)

// ================= Objek & variabel =================
OneWire jalurOneWire(PIN_DS);
DallasTemperature sensorBayi(&jalurOneWire);
DHT sensorRuangan(PIN_DHT, DHT22);
LiquidCrystal_I2C lcd(ALAMAT_LCD, 16, 2);
WiFiClientSecure klienAman;
PubSubClient mqtt(klienAman);
Preferences pref;
String topikSync, topikTelemetri, topikStatus;

float suhuBayi, suhuRuangan, kelembapan;
bool sensorBayiOk, sensorRuanganOk, sampelBaru;

// Config (diubah dari dashboard, disimpan di flash)
float suhuTarget = 33.5, kp = 40.0, ki = 0.5, kd = 40.0;
bool lampuBiruAktif = false;

float integralPid, suhuLalu, turunanFilter, outputPid;
bool pidSiap;

bool relayTarget[JUMLAH_RELAY], relayAktif[JUMLAH_RELAY];
volatile int8_t perintahManual[JUMLAH_RELAY] = {-1, -1, -1, -1}; // -1 auto, 0 mati, 1 nyala
volatile bool modeManual;
volatile uint32_t waktuMulaiManual;
volatile bool kirimSegera; // minta telemetri dikirim sekarang

// ================= Bantuan =================
bool sudahWaktunya(uint32_t &terakhir, uint32_t now, uint32_t jeda) {
if (now - terakhir < jeda) return false;
terakhir = now;
return true;
}

float batasi(float v, float lo, float hi) { return v < lo ? lo : (v > hi ? hi : v); }

// Nyala jika nilai >= on, mati jika <= off, selain itu pertahankan
bool histeresis(bool sekarang, float nilai, float on, float off) {
return nilai >= on ? true : (nilai <= off ? false : sekarang);
}

void muatConfig() {
pref.begin("inc", true);
suhuTarget = pref.getFloat("sp", suhuTarget);
kp = pref.getFloat("kp", kp);
ki = pref.getFloat("ki", ki);
kd = pref.getFloat("kd", kd);
lampuBiruAktif = pref.getBool("led", lampuBiruAktif);
pref.end();
}

void simpanConfig() { // NVS melewati penulisan jika nilainya sama
pref.begin("inc", false);
pref.putFloat("sp", suhuTarget);
pref.putFloat("kp", kp);
pref.putFloat("ki", ki);
pref.putFloat("kd", kd);
pref.putBool("led", lampuBiruAktif);
pref.end();
}

// ================= Sensor =================
void bacaSensor(uint32_t now) {
static uint32_t tMinta = 0, tDht = 0;
static bool menunggu = false;
static uint8_t gagalDht = 0;

// DS18B20 non-blocking: minta tiap 1 dtk, baca setelah 400 ms (11-bit = 375 ms)
if (!menunggu) {
if (now - tMinta >= 1000) { sensorBayi.requestTemperatures(); tMinta = now; menunggu = true; }
} else if (now - tMinta >= 400) {
menunggu = false;
float t = sensorBayi.getTempCByIndex(0);
sensorBayiOk = t > -50.0 && t < 84.0; // 85 = nilai power-on, -127 = terputus
if (sensorBayiOk) suhuBayi = t;
}

// DHT22: toleransi 2 gagal baca beruntun sebelum dianggap rusak
if (sudahWaktunya(tDht, now, INTERVAL_DHT)) {
float t = sensorRuangan.readTemperature(), h = sensorRuangan.readHumidity();
if (!isnan(t) && !isnan(h)) {
gagalDht = 0; sensorRuanganOk = true;
suhuRuangan = t; kelembapan = h;
sampelBaru = true;
} else if (gagalDht < 3 && ++gagalDht == 3) {
sensorRuanganOk = false;
sampelBaru = true;
}
}
}

// ================= PID (1x per sampel DHT) =================
// Output -100 (terlalu panas) .. +100 (butuh pemanasan penuh)
void hitungPid() {
if (!sensorRuanganOk) { integralPid = turunanFilter = outputPid = 0; pidSiap = false; return; }

float error = suhuTarget - suhuRuangan;
if (!pidSiap) { suhuLalu = suhuRuangan; pidSiap = true; }

// Turunan dari pengukuran (tanpa setpoint kick) + low-pass
turunanFilter += 0.3f * ((suhuLalu - suhuRuangan) - turunanFilter);
suhuLalu = suhuRuangan;

// Integral hanya dekat target (anti-windup)
if (ki > 0.0f) {
float batas = INTEGRAL_MAKS / ki;
if (fabsf(error) < 1.0f) integralPid += error;
integralPid = batasi(integralPid, -batas, batas);
} else {
integralPid = 0;
}

outputPid = batasi(kp * error + ki * integralPid + kd * turunanFilter, -100, 100);
}

// ================= Relay =================
void tulisRelay(uint8_t pin, bool nyala) { digitalWrite(pin, nyala == RELAY_NYALA_LOW ? LOW : HIGH); }

// Satu perubahan relay per JEDA_RELAY tanpa delay() (non-blocking)
void terapkanRelay(uint32_t now) {
static uint32_t terakhir = 0;
if (now - terakhir < JEDA_RELAY) return;
for (int i = 0; i < JUMLAH_RELAY; i++) {
if (relayTarget[i] == relayAktif[i]) continue;
tulisRelay(PIN_RELAY[i], relayTarget[i]);
relayAktif[i] = relayTarget[i];
terakhir = now;
kirimSegera = true; // dashboard langsung dapat status terbaru
return;
}
}

void aturRelay(uint32_t now) {
static bool otomatis[JUMLAH_RELAY]; // state auto terpisah dari override manual

if (modeManual && now - waktuMulaiManual >= DURASI_MANUAL) {
for (int i = 0; i < JUMLAH_RELAY; i++) perintahManual[i] = -1;
modeManual = false;
}

otomatis[LAMPU_BIRU] = lampuBiruAktif;
if (sensorRuanganOk) {
otomatis[PEMANAS_1] = histeresis(otomatis[PEMANAS_1], outputPid, 20, 10);
otomatis[PEMANAS_2] = histeresis(otomatis[PEMANAS_2], outputPid, 60, 50);
otomatis[KIPAS] = histeresis(otomatis[KIPAS], -outputPid, 20, 10);
} else {
otomatis[PEMANAS_1] = otomatis[PEMANAS_2] = otomatis[KIPAS] = false;
}

for (int i = 0; i < JUMLAH_RELAY; i++) {
int8_t m = perintahManual[i];
relayTarget[i] = m < 0 ? otomatis[i] : m == 1;
}

// Pengaman: terlalu panas -> pemanas mati apa pun perintahnya
if ((sensorBayiOk && suhuBayi >= BAYI_MAKS) || (sensorRuanganOk && suhuRuangan >= RUANGAN_MAKS))
relayTarget[PEMANAS_1] = relayTarget[PEMANAS_2] = false;

terapkanRelay(now);
}

// ================= LCD & Serial =================
void tulisLcd(uint8_t baris, const char* fmt, ...) {
static char cache[2][17];
char t[32], b[17];
va_list a; va_start(a, fmt); vsnprintf(t, sizeof t, fmt, a); va_end(a);
snprintf(b, sizeof b, "%-16.16s", t);
if (!strcmp(b, cache[baris])) return; // tulis hanya jika berubah
strcpy(cache[baris], b);
lcd.setCursor(0, baris);
lcd.print(b);
}

const char* teksSuhu(char* buf, bool ok, float v) {
if (ok) snprintf(buf, 8, "%.1fC", v); else strcpy(buf, "ERROR");
return buf;
}

void perbaruiLcd() {
if ((millis() / 3000) & 1) {
tulisLcd(0, "KP:%.1f KI:%.1f", kp, ki);
tulisLcd(1, "KD:%.1f SP:%.1f", kd, suhuTarget);
} else {
char b[8], r[8];
tulisLcd(0, "SUHU BAYI :%s", teksSuhu(b, sensorBayiOk, suhuBayi));
tulisLcd(1, "SUHU RUANG:%s", teksSuhu(r, sensorRuanganOk, suhuRuangan));
}
}

void cetakSerial() {
Serial.printf("Bayi:%.1f%s Ruang:%.1f%s RH:%.0f%% SP:%.1f PID:%.1f | %s | K:%d P1:%d P2:%d L:%d\n",
suhuBayi, sensorBayiOk ? "" : "(ERR)", suhuRuangan, sensorRuanganOk ? "" : "(ERR)",
kelembapan, suhuTarget, outputPid, modeManual ? "MANUAL" : "AUTO",
relayAktif[KIPAS], relayAktif[PEMANAS_1], relayAktif[PEMANAS_2], relayAktif[LAMPU_BIRU]);
}

// ================= MQTT (Core 0) =================
void prosesPerintah(const char* data, unsigned int n) {
StaticJsonDocument<512> doc;
if (deserializeJson(doc, data, n)) return;

JsonObject cfg = doc["config"];
if (!cfg.isNull()) {
suhuTarget = batasi(cfg["temperature_setpoint"] | suhuTarget, 28.0f, 37.0f);
lampuBiruAktif = cfg["blue_led_enabled"] | lampuBiruAktif;
kp = batasi(cfg["kp"] | kp, 0.0f, 500.0f);
ki = batasi(cfg["ki"] | ki, 0.0f, 100.0f);
kd = batasi(cfg["kd"] | kd, 0.0f, 500.0f);
simpanConfig();
kirimSegera = true;
}

JsonObject ov = doc["relay_overrides"];
if (!ov.isNull()) {
bool ada = false;
for (int i = 0; i < JUMLAH_RELAY; i++) {
JsonVariant v = ov[NAMA_RELAY[i]];
perintahManual[i] = v.isNull() ? -1 : (v.as<bool>() ? 1 : 0); // terima true/false & 1/0
ada |= !v.isNull();
}
waktuMulaiManual = millis();
modeManual = ada;
}
}

void kirimData() {
if (!mqtt.connected()) return;

StaticJsonDocument<768> doc;
doc["device_code"] = KODE_PERANGKAT;
doc["temperature_object"] = sensorBayiOk ? roundf(suhuBayi * 100) / 100 : 0;
doc["temperature_room"] = sensorRuanganOk ? roundf(suhuRuangan * 100) / 100 : 0;
doc["humidity"] = sensorRuanganOk ? roundf(kelembapan * 10) / 10 : 0;
doc["pid_output"] = roundf(outputPid * 10) / 10;
doc["setpoint"] = suhuTarget;
doc["kp"] = kp; doc["ki"] = ki; doc["kd"] = kd;
doc["manual"] = (bool)modeManual;
for (int i = 0; i < JUMLAH_RELAY; i++) doc["relays"][NAMA_RELAY[i]] = relayAktif[i];
doc["sensor_status"]["ds18b20"] = sensorBayiOk;
doc["sensor_status"]["dht22"] = sensorRuanganOk;
doc["uptime_s"] = millis() / 1000;

char payload[512];
serializeJson(doc, payload, sizeof payload);
mqtt.publish(topikTelemetri.c_str(), payload);
}

void jagaKoneksi(uint32_t now) {
static uint32_t cobaWifi = 0, cobaMqtt = 0;

if (WiFi.status() != WL_CONNECTED) {
if (sudahWaktunya(cobaWifi, now, 5000)) WiFi.reconnect();
return;
}
if (mqtt.connected()) { mqtt.loop(); return; }

if (sudahWaktunya(cobaMqtt, now, 5000) &&
mqtt.connect(KODE_PERANGKAT, KODE_PERANGKAT, PASSWORD_MQTT,
topikStatus.c_str(), 1, true, "{\"online\":false}")) {
mqtt.publish(topikStatus.c_str(), "{\"online\":true}", true);
mqtt.subscribe(topikSync.c_str(), 1);
kirimSegera = true;
}
}

void tugasJaringan(void*) {
uint32_t waktuKirim = 0;
for (;;) {
uint32_t now = millis();
jagaKoneksi(now);
if (sudahWaktunya(waktuKirim, now, 3000) || kirimSegera) {
kirimSegera = false;
waktuKirim = now;
kirimData();
}
vTaskDelay(pdMS_TO_TICKS(10));
}
}

// ================= Setup & Loop (Core 1) =================
void setup() {
Serial.begin(115200);

// Level MATI ditulis sebelum pin jadi output (cegah glitch relay saat boot)
for (int i = 0; i < JUMLAH_RELAY; i++) {
gpio_set_level((gpio_num_t)PIN_RELAY[i], RELAY_NYALA_LOW ? 1 : 0);
pinMode(PIN_RELAY[i], OUTPUT);
}

muatConfig();

Wire.begin();
lcd.init();
lcd.backlight();

sensorBayi.setWaitForConversion(false);
sensorBayi.begin();
sensorBayi.setResolution(11);
sensorRuangan.begin();

String prefix = String("incubator/") + KODE_PERANGKAT + "/";
topikSync = prefix + "sync";
topikTelemetri = prefix + "telemetry";
topikStatus = prefix + "status";

klienAman.setInsecure();
mqtt.setServer(HOST_MQTT, 8883);
mqtt.setBufferSize(1024);
mqtt.setKeepAlive(30);
mqtt.setSocketTimeout(5);
mqtt.setCallback([](char*, byte* isi, unsigned int n) { prosesPerintah((const char*)isi, n); });

WiFi.mode(WIFI_STA);
WiFi.setSleep(false); // tanpa modem sleep: latensi perintah turun
delay(200); // beri supply waktu stabil sebelum radio aktif
WiFi.begin(SSID_WIFI, PASSWORD_WIFI);
xTaskCreatePinnedToCore(tugasJaringan, "Jaringan", 16384, nullptr, 1, nullptr, 0);
}

void loop() {
static uint32_t waktuLcd = 0, waktuSerial = 0;
uint32_t now = millis();

bacaSensor(now);
if (sampelBaru) { sampelBaru = false; hitungPid(); }
aturRelay(now);

if (sudahWaktunya(waktuLcd, now, 500)) perbaruiLcd();
if (sudahWaktunya(waktuSerial, now, 2000)) cetakSerial();

delay(10);
}