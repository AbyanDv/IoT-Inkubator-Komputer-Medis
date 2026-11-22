// Smart Infant Incubator - ESP32 (versi ringkas)
// Core 1 loop(): sensor -> kontrol on/off (pakai suhu ruang DS18B20) -> relay -> LCD
// Core 0 taskJaringan(): WiFi/MQTT, kirim telemetri, terima sync
// DHT22 (suhu objek + kelembapan) hanya dikirim ke dashboard, tidak dipakai kontrol. Tanpa fail-safe.

#include <Arduino.h>
#include <ArduinoJson.h> // harus v6
#include <DallasTemperature.h>
#include <DHT.h>
#include <HTTPClient.h>
#include <LiquidCrystal_I2C.h>
#include <OneWire.h>
#include <PubSubClient.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <Wire.h>

#if __has_include("secrets.h")
#include "secrets.h"
#endif
#ifndef KODE_PERANGKAT
#define KODE_PERANGKAT "INC-001"
#endif
#ifndef SSID_WIFI
#define SSID_WIFI "POCO X5 5G"
#endif
#ifndef PASSWORD_WIFI
#define PASSWORD_WIFI "cobaliathplu"
#endif
#ifndef HOST_MQTT
#define HOST_MQTT "41.216.180.77"
#endif
#ifndef PASSWORD_MQTT
#define PASSWORD_MQTT ""
#endif
#ifndef URL_HTTP
#define URL_HTTP "http://iot-komdis-2.test"
#endif
#ifndef TOKEN_PERANGKAT
#define TOKEN_PERANGKAT "dev-token-inc-001"
#endif
#ifndef ALAMAT_LCD
#define ALAMAT_LCD 0x27
#endif
#ifndef RELAY_AKTIF_RENDAH
#define RELAY_AKTIF_RENDAH 1 // 1 = modul relay active-low
#endif

// ---------- Pin ----------
const uint8_t PIN_DS18B20 = 4, PIN_DHT22 = 5;
enum Relay : uint8_t
{
    KIPAS,
    PEMANAS1,
    PEMANAS2,
    LAMPU,
    JUMLAH_RELAY
};
const uint8_t PIN_RELAY[JUMLAH_RELAY] = {25, 26, 27, 14};
const char *NAMA_RELAY[JUMLAH_RELAY] = {"fan", "heater_1", "heater_2", "blue_light"};

// ---------- Parameter ----------
const unsigned long TTL_OVERRIDE_MS = 60000;

// ---------- Objek ----------
OneWire oneWire(PIN_DS18B20);
DallasTemperature ds18b20(&oneWire);
DHT dht(PIN_DHT22, DHT22);
LiquidCrystal_I2C lcd(ALAMAT_LCD, 16, 2);
WiFiClientSecure tlsClient;
PubSubClient mqtt(tlsClient);
String topikSync, topikTelemetri, topikStatus;

// ---------- State (dibagi antar core) ----------
// suhuRuang = DS18B20 (dipakai kontrol relay)
// suhuObjek = DHT22 (hanya dikirim ke dashboard), kelembapan = DHT22
float suhuObjek = 0, suhuRuang = 0, kelembapan = 0;
bool objekValid = false, ruangValid = false;

float setpoint = 33.5f;
bool lampuAktif = false;

bool relay[JUMLAH_RELAY] = {};
int8_t ovRelay[JUMLAH_RELAY] = {-1, -1, -1, -1}; // -1 auto, 0 mati, 1 nyala
unsigned long overrideSampaiMs = 0;

// ---------- Helper ----------
float clampF(float v, float lo, float hi) { return v < lo ? lo : (v > hi ? hi : v); }
bool pilih(int8_t ov, bool otomatis) { return ov < 0 ? otomatis : ov > 0; }
void setRelay(uint8_t pin, bool nyala) { digitalWrite(pin, nyala != (bool)RELAY_AKTIF_RENDAH); }

bool sudahWaktu(unsigned long &terakhir, unsigned long ms, unsigned long interval)
{
    if (ms - terakhir < interval)
        return false;
    terakhir = ms;
    return true;
}

// ---------- Sync dari Laravel ----------
void terapkanSync(const char *json, size_t len)
{
    StaticJsonDocument<128> filter;
    filter["config"] = true;
    filter["relay_overrides"] = true;

    StaticJsonDocument<512> doc;
    if (deserializeJson(doc, json, len, DeserializationOption::Filter(filter)))
        return;

    JsonObject cfg = doc["config"];
    if (!cfg.isNull())
    {
        setpoint = clampF(cfg["temperature_setpoint"] | setpoint, 28.0f, 37.0f);
        lampuAktif = cfg["blue_led_enabled"] | lampuAktif;
    }

    JsonObject ov = doc["relay_overrides"]; // {} = kembali auto
    if (!ov.isNull())
    {
        for (uint8_t i = 0; i < JUMLAH_RELAY; i++)
        {
            JsonVariant v = ov[NAMA_RELAY[i]];
            ovRelay[i] = v.is<bool>() ? v.as<bool>() : -1;
        }
        overrideSampaiMs = millis() + TTL_OVERRIDE_MS;
    }
}

// ---------- Jaringan (Core 0) ----------
// Sensor tidak valid dikirim sebagai 0 + sensor_status=false (NA di dashboard)
size_t buatTelemetri(char *buf, size_t n)
{
    StaticJsonDocument<384> doc;
    doc["device_code"] = KODE_PERANGKAT;
    doc["temperature_object"] = objekValid ? roundf(suhuObjek * 100) / 100 : 0; // dari DHT22
    doc["temperature_room"] = ruangValid ? roundf(suhuRuang * 100) / 100 : 0;   // dari DS18B20
    doc["humidity"] = objekValid ? roundf(kelembapan * 10) / 10 : 0;            // dari DHT22
    for (uint8_t i = 0; i < JUMLAH_RELAY; i++)
        doc["relays"][NAMA_RELAY[i]] = relay[i];
    doc["sensor_status"]["ds18b20"] = ruangValid;
    doc["sensor_status"]["dht22"] = objekValid;
    doc["uptime_s"] = millis() / 1000;
    return serializeJson(doc, buf, n);
}

void rawatJaringan(unsigned long ms)
{
    static unsigned long cobaWifiMs = 0, cobaMqttMs = 0;

    if (WiFi.status() != WL_CONNECTED)
    {
        if (sudahWaktu(cobaWifiMs, ms, 5000))
            WiFi.reconnect();
        return;
    }
    if (mqtt.connected())
    {
        mqtt.loop();
        return;
    }
    if (!sudahWaktu(cobaMqttMs, ms, 5000))
        return;

    if (mqtt.connect(KODE_PERANGKAT, KODE_PERANGKAT, PASSWORD_MQTT, topikStatus.c_str(), 1, true, "{\"online\":false}"))
    {
        mqtt.publish(topikStatus.c_str(), "{\"online\":true}", true);
        mqtt.subscribe(topikSync.c_str(), 1);
    }
}

void kirimTelemetri()
{
    if (WiFi.status() != WL_CONNECTED)
        return;
    char body[384];
    size_t n = buatTelemetri(body, sizeof body);

    if (mqtt.connected())
    {
        mqtt.publish(topikTelemetri.c_str(), body);
        return;
    }

    // Fallback HTTP jika MQTT mati; respons berisi sync
    HTTPClient http;
    http.setTimeout(3000);
    if (!http.begin(String(URL_HTTP) + "/api/devices/telemetry"))
        return;
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-Device-Token", TOKEN_PERANGKAT);
    if (http.POST((uint8_t *)body, n) == HTTP_CODE_OK)
    {
        String res = http.getString();
        terapkanSync(res.c_str(), res.length());
    }
    http.end();
}

void taskJaringan(void *)
{
    unsigned long kirimMs = 0;
    for (;;)
    {
        unsigned long ms = millis();
        rawatJaringan(ms);
        if (sudahWaktu(kirimMs, ms, 3000))
            kirimTelemetri();
        vTaskDelay(pdMS_TO_TICKS(10));
    }
}

// ---------- Sensor (Core 1) ----------
void bacaSensor(unsigned long ms)
{
    static unsigned long reqDsMs = 0, reqDhtMs = 0;
    static bool menungguDs = false;

    // DS18B20 non-blocking: minta konversi, ambil hasil 500 ms kemudian
    if (!menungguDs)
    {
        ds18b20.requaestTemperatures();
        reqDsMs = ms;
        menungguDs = true;
    }
    else if (ms - reqDsMs >= 500)
    {
        menungguDs = false;
        float t = ds18b20.getTempCByIndex(0);
        ruangValid = t > -50 && t < 84; // tolak -127 (lepas) dan 85 (default)
        if (ruangValid)
            suhuRuang = t;
    }

    // DHT22 tiap 2,5 detik (hanya untuk dashboard)
    if (sudahWaktu(reqDhtMs, ms, 2500))
    {
        float r = dht.readTemperature(), h = dht.readHumidity();
        objekValid = !isnan(r) && !isnan(h);
        if (objekValid)
        {
            suhuObjek = r;
            kelembapan = h;
        }
    }
}

// ---------- Kontrol ----------
void updateKontrol(unsigned long ms)
{
    // Override hangus jika lama tidak ada sync
    if (overrideSampaiMs && (int32_t)(ms - overrideSampaiMs) > 0)
    {
        memset(ovRelay, -1, sizeof ovRelay);
        overrideSampaiMs = 0;
    }

    // Kontrol on/off berdasarkan suhu RUANG (DS18B20). Sensor error = semua otomatis mati.
    // Sebuah relay yang sedang ON memakai batas mati, yang OFF memakai batas nyala.
    bool h1 = false, h2 = false, kipasAuto = false;
    if (ruangValid)
    {
        h1 = relay[PEMANAS1] ? suhuRuang < setpoint : suhuRuang < setpoint - 0.5f;             // nyala < SP-0,5 | mati >= SP
        h2 = relay[PEMANAS2] ? suhuRuang < setpoint - 1.0f : suhuRuang < setpoint - 1.5f;      // nyala < SP-1,5 | mati >= SP-1
        kipasAuto = relay[KIPAS] ? suhuRuang > setpoint + 1.5f : suhuRuang >= setpoint + 2.0f; // nyala >= SP+2 | mati <= SP+1,5
    }

    relay[KIPAS] = pilih(ovRelay[KIPAS], kipasAuto);
    relay[PEMANAS1] = pilih(ovRelay[PEMANAS1], h1);
    relay[PEMANAS2] = pilih(ovRelay[PEMANAS2], h2);
    relay[LAMPU] = pilih(ovRelay[LAMPU], lampuAktif);

    for (uint8_t i = 0; i < JUMLAH_RELAY; i++)
        setRelay(PIN_RELAY[i], relay[i]);
}

// ---------- LCD ----------
void tulisLcd(uint8_t baris, const char *teks)
{
    char buf[17];
    snprintf(buf, sizeof buf, "%-16.16s", teks);
    lcd.setCursor(0, baris);
    lcd.print(buf);
}

void fmt(char *b, size_t n, bool ok, float v)
{
    if (ok)
        snprintf(b, n, "%.1f", v);
    else
        strcpy(b, "NA");
}

// Baris 1: B:<suhu bayi> R:<suhu ruang> Baris 2: S<setpoint> F<kipas> H<pemanas1><pemanas2> L<lampu>
void updateLcd()
{
    char bayi[8], ruang[8], a[24], b[24];
    fmt(bayi, sizeof bayi, objekValid, suhuObjek);
    fmt(ruang, sizeof ruang, ruangValid, suhuRuang);
    snprintf(a, sizeof a, "B:%s R:%s", bayi, ruang);
    snprintf(b, sizeof b, "S%.1f F%d H%d%d L%d", setpoint, relay[KIPAS], relay[PEMANAS1], relay[PEMANAS2], relay[LAMPU]);
    tulisLcd(0, a);
    tulisLcd(1, b);
}

// ---------- Setup & Loop ----------
void setup()
{
    Serial.begin(115200);

    // Relay OFF dulu sebelum jadi OUTPUT (hindari glitch saat boot)
    for (uint8_t pin : PIN_RELAY)
    {
        setRelay(pin, false);
        pinMode(pin, OUTPUT);
    }

    Wire.begin();
    lcd.init();
    lcd.backlight();

    ds18b20.setWaitForConversion(false);
    ds18b20.begin();
    ds18b20.setResolution(11);
    dht.begin();

    String dasar = String("incubator/") + KODE_PERANGKAT + "/";
    topikSync = dasar + "sync";
    topikTelemetri = dasar + "telemetry";
    topikStatus = dasar + "status";

    tlsClient.setInsecure(); // prototype
    mqtt.setServer(HOST_MQTT, 8883);
    mqtt.setBufferSize(1024);
    mqtt.setCallback([](char *, byte *p, unsigned int len)
                     { terapkanSync((const char *)p, len); });

    WiFi.mode(WIFI_STA);
    WiFi.begin(SSID_WIFI, PASSWORD_WIFI);
    xTaskCreatePinnedToCore(taskJaringan, "jaringan", 12288, nullptr, 1, nullptr, 0);
}

void loop()
{
    static unsigned long kontrolMs = 0;
    unsigned long ms = millis();

    bacaSensor(ms);
    if (ms - kontrolMs >= 500)
    {
        kontrolMs = ms;
        updateKontrol(ms);
        updateLcd();
    }
    delay(10);
}