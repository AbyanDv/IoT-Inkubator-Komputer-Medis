# Firmware ESP32 — Smart Infant Incubator

Firmware ESP32 DevKit V1 untuk loop kontrol real-time inkubator. **Semua
perhitungan berjalan di ESP32**: VPS hanya menyimpan telemetry dan mengirim
config. Kalau jaringan mati, loop PID, staging, dan fail-safe tetap jalan.

Satu file: `Incubator/Incubator.ino`. Buka di Arduino IDE, pilih board
**ESP32 Dev Module**, upload.

- Board: ESP32 DevKit V1 / ESP32 Dev Module (WROOM-32, 4 MB flash, tanpa PSRAM).
- Sensor: DS18B20 (suhu objek, non-blocking), DHT22 (ruang + kelembapan).
- Aktuator: 4 relay (fan, heater 1, heater 2, lampu biru), LCD I2C 16x2.
- Transport: MQTT over TLS (utama) + HTTPS (cadangan), kontrak JSON sama
  dengan `app/Services/DeviceIngestionService.php`.

## Wiring

| Signal        | ESP32 pin | Catatan                        |
| ------------- | --------- | ------------------------------ |
| DS18B20 data  | GPIO 4    | pull-up 4.7 kΩ ke 3.3 V        |
| DHT22 data    | GPIO 5    | pull-up 4.7 kΩ ke 3.3 V        |
| Relay fan     | GPIO 25   | board opto, active low         |
| Relay heater 1| GPIO 26   |                                |
| Relay heater 2| GPIO 27   |                                |
| Relay blue    | GPIO 14   |                                |
| LCD SDA       | GPIO 21   | I2C, alamat 0x27               |
| LCD SCL       | GPIO 22   |                                |

Board relay unit ini **active-high** (pin HIGH = relay nyala), sudah diset
`#define RELAY_ACTIVE_LOW 0` di `secrets.h`. Untuk board active-low, hapus baris
itu atau set ke 1.

## Isi file

`Incubator.ino` disusun berurutan, tanpa file lain:

1. **IDENTITAS / TIMING / KONTROL / SENSOR / AKTUATOR / DEBUG** — semua knob,
   dibungkus `#ifndef`, jadi bisa ditimpa dari `secrets.h`.
2. **MODEL DATA** — struct POD untuk queue antar core.
3. **CA** — sertifikat broker ditanam sebagai string.
4. **KONTROL** — PID + staging + histeresis, tanpa panggilan Arduino.
5. **SENSOR** — DS18B20 non-blocking + DHT22 dengan backoff.
6. **RELAY**, **LCD**, **BRIDGE**, **PROTOKOL**, **TLS**, **TRANSPORT**.
7. **APLIKASI** — `controlLoop()` (Core 1) dan `networkLoop()` (Core 0).
8. **TASK** — dua task FreeRTOS, `setup()`, `loop()`.

Arsitektur core:

```
Core 1 (PRO_CPU) controlLoop() tiap 100 ms:
  sensorsPoll -> fail-safe -> controlStep -> relaysApply
  -> lcdRender -> bridgePublishReading

Core 0 (APP_CPU) networkLoop():
  transportMaintain -> drainEvents
  tiap TELEMETRY_INTERVAL_MS: MQTT publish, atau HTTP POST sebagai fallback
```

Handshake TLS atau reconnect Wi-Fi di Core 0 tidak pernah menunda keputusan
heater di Core 1.

## Library Arduino IDE

Tujuh library, versi ini yang diuji. Jangan pakai versi mayor yang lebih baru:
ArduinoJson v7 menghapus `StaticJsonDocument`, DallasTemperature v4 mengubah API.

| Library | Versi |
| --- | --- |
| ArduinoJson | 6.21.6 |
| PubSubClient | 2.8 |
| DallasTemperature | 3.9.0 |
| OneWire | 2.3.8 |
| DHT sensor library | 1.4.6 |
| Adafruit Unified Sensor | 1.1.14 |
| LiquidCrystal I2C (Frank de Brabander) | 1.1.2 |

Dua cara memasang:

**Library Manager.** Tools → Manage Libraries, cari nama di atas, pilih versi
yang sama, Install. ArduinoJson harus v6; kalau IDE menawarkan v7, tolak.

**arduino-cli.** Sekali jalan, tanpa UI:

```bash
arduino-cli lib install "ArduinoJson@6.21.6"
arduino-cli lib install "PubSubClient@2.8.0"
arduino-cli lib install "DallasTemperature@3.9.0"
arduino-cli lib install "OneWire@2.3.8"
arduino-cli lib install "DHT sensor library@1.4.6"
arduino-cli lib install "Adafruit Unified Sensor@1.1.14"
arduino-cli lib install "LiquidCrystal I2C@1.1.2"
```

Cek hasilnya: `arduino-cli lib list`. Library masuk ke `~/Arduino/libraries`,
folder yang sama yang dibaca Arduino IDE.


## Config

Dua lapis:

- Blok `#ifndef` di bagian atas `Incubator.ino` — default semua knob.
- `Incubator/secrets.h` — nilai per device dan rahasia. Disertakan otomatis
  bila ada, dan nilainya menang atas default.

Rahasia (`MQTT_PASSWORD`, `DEVICE_TOKEN`) tidak pernah masuk repo.

Konstanta kontrol, disetel untuk inkubator 28–37 °C:

| Key                | Default | Arti                                     |
| ------------------ | ------- | ---------------------------------------- |
| `DEFAULT_SETPOINT` | 33.5 °C | fallback lokal sebelum sync pertama      |
| `SETPOINT_MIN/MAX` | 28/37 °C| clamp untuk setpoint dari jaringan       |
| `TRIP_HIGH_C`      | 38.5 °C | overheat: heater mati, fan nyala         |
| `TRIP_LOW_C`       | 15.0 °C | di bawah ini probe dianggap rusak        |
| `HEATER_1_ON_PCT`  | 5 %     | ambang stage heater 1                    |
| `HEATER_2_ON_PCT`  | 50 %    | ambang stage heater 2                    |
| `HEATER_HYST_PCT`  | 3 %     | pita lepas, mencegah relay berkedip      |
| `PID_FULL_SCALE_C` | 5.0 K   | error yang memetakan ke output 100 %     |
| `PID_I_MIN/MAX_C`  | ±1.0 K  | clamp integral (anti-windup)             |
| `OVERRIDE_TTL_MS`  | 60000   | umur override relay tanpa server         |

Gain server (`pid_kp/ki/kd`) tetap bermakna K-per-K: output % =
`(kp·e + ∫ki·e + kd·de/dt) · 100 / PID_FULL_SCALE_C`.

## Aturan fail-safe (hanya firmware)

1. Fault sensor (`ds18b20: false`), overheat, atau bacaan rendah tidak masuk
   akal: heater 1 + 2 OFF, fan ON, LCD `FAIL-SAFE ON`.
2. Trip sticky: heater tetap mati sampai device reboot. Blip sesaat tidak boleh
   menyalakan panas sendiri.
3. Override relay dari dashboard kedaluwarsa `OVERRIDE_TTL_MS` setelah sync
   sukses terakhir (dead-man 60 s sesuai PRD).
4. Urutan prioritas: fail-safe > override > PID > idle.

## Kontrak dengan Laravel

- Body telemetry = `StoreTelemetryRequest::payloadRules()`.
- Body sync = `DeviceIngestionService::syncPayload()`:
  `config`, `is_paired`, `baby.code`, `relay_overrides`.
- Key yang tidak ada mempertahankan nilai terakhir; payload parsial tidak bisa
  mereset controller. `relay_overrides: {}` berarti "kembali ke auto", jadi
  parser memperlakukan objek kosong sebagai perintah hapus, bukan field hilang.
- `timestamp` tidak dikirim: Laravel yang menstempel `recorded_at`.

## Batas keamanan

Firmware prototipe untuk riset dan pengajaran. Bukan alat medis. Jangan dipakai
pada manusia. Ambang trip termal bersifat lokal dan tidak bisa diturunkan dari
jaringan.
