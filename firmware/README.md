# Smart Infant Incubator - ESP32 Production Firmware

Firmware produksi untuk mikrokontroler **ESP32 DevKit V1** pada sistem Smart Infant Incubator (IoT Monitoring & Management System). Firmware ini mengimplementasikan arsitektur **FreeRTOS Dual-Core**, kendali suhu presisi lokal berbasis algoritma **PID dengan Anti-Windup Clamping**, proteksi keselamatan hardware otomatis (**Fail-Safe Guard**), antarmuka display **LCD 16x2 I2C**, serta sinkronisasi telemetri HTTPS dengan pola **Config Piggybacking**.

---

## 1. Arsitektur Dual-Core FreeRTOS

Firmware memanfaatkan kedua core prosesor Xtensa LX6 pada ESP32 untuk memisahkan domain waktu nyata (*hard real-time*) dari domain jaringan (*asynchronous networking*):

```
+-------------------------------------------------------------------------+
|                    ESP32 DUAL-CORE EXECUTION MODEL                      |
|                                                                         |
|  CORE 1 (Pro-CPU) : Real-Time Control Engine (Prioritas Tinggi: 5)      |
|  ---------------------------------------------------------------------  |
|  - Akuisisi Non-blocking DS18B20 (setWaitForConversion = false, 10-bit) |
|  - Sampling DHT22 non-blocking (interval 2000 ms)                       |
|  - Algoritma PID Lokal Heater PWM (Kp, Ki, Kd, Anti-Windup Clamping)   |
|  - Modulasi PWM Kipas DC (Mode Auto & Manual)                           |
|  - Indikator Visual Simulasi Fototerapi (Blue LED)                      |
|  - Format & Refresh LCD 16x2 I2C (2 Hz)                                 |
|  - Hardware Fail-Safe Guard (PWM = 0 seketika jika sensor rusak/putus)  |
|  - Dispatch Snapshot Telemetri ke FreeRTOS Queue (non-blocking)         |
|                                                                         |
|                                    |                                    |
|                                    | FreeRTOS Queue (depth = 5)         |
|                                    v                                    |
|                                                                         |
|  CORE 0 (App-CPU) : Network & Telemetry Task (Prioritas Sedang: 2)      |
|  ---------------------------------------------------------------------  |
|  - Wi-Fi Connection Manager & Auto-Reconnect non-blocking               |
|  - Dequeue Snapshot Telemetri                                           |
|  - Serialisasi Payload JSON RFC 8259                                    |
|  - HTTPS/HTTP Client POST /api/devices/telemetry                        |
|  - Ekstraksi Piggybacked Response (has_config_update & status pairing)  |
|  - Thread-Safe Config Update via FreeRTOS Mutex (configMutex)           |
+-------------------------------------------------------------------------+
```

### Keunggulan Desain Dual-Core:
1. **Zero Control Loop Stalling:** TLS Handshake dan transmisi HTTPS membutuhkan waktu 400–2500ms pada ESP32. Dengan mengisolasi jaringan ke Core 0, loop PID di Core 1 berjalan stabil setiap 100ms (10 Hz) tanpa terpengaruh kondisi sinyal Wi-Fi.
2. **Wi-Fi Disconnect Immunity:** Jika koneksi Wi-Fi atau server VPS offline, Core 1 tetap mempertahankan setpoint suhu lokal dengan algoritma PID secara kontinu tanpa restart atau crash.

---

## 2. Pemetaan Pin Hardware (Pinout)

Konfigurasi pin pada `firmware/esp32-incubator/include/config.h`:

| Komponen Hardware | GPIO ESP32 | Mode Pin | Deskripsi Rangkaian |
| :--- | :--- | :--- | :--- |
| **DS18B20 (Sensor Suhu Objek)** | `GPIO 4` | Input / OneWire | Wajib resistor pull-up eksternal 4.7kΩ ke 3.3V |
| **DHT22 (Suhu & Kelembapan Ruang)** | `GPIO 16` | Input | Wajib resistor pull-up eksternal 10kΩ ke 3.3V |
| **Heater Dummy (MOSFET Driver)** | `GPIO 18` | LEDC PWM (Ch 0) | Logic-Level N-MOSFET (Gate), Frekuensi 5 kHz, 8-bit |
| **DC Fan (Driver Kipas Sirkulasi)** | `GPIO 19` | LEDC PWM (Ch 1) | Logic-Level MOSFET (Gate), Frekuensi 5 kHz, 8-bit |
| **Blue LED (Simulasi Fototerapi)** | `GPIO 2` | Digital Output | Onboard / Eksternal Blue LED + Resistor 330Ω |
| **I2C LCD SDA** | `GPIO 21` | I2C Data | Default ESP32 Hardware I2C SDA (Backpack PCF8574) |
| **I2C LCD SCL** | `GPIO 22` | I2C Clock | Default ESP32 Hardware I2C SCL (Backpack PCF8574) |

### Diagram Skema Rangkaian (ASCII Wiring):
```
             +-----------------------+
             |     ESP32 DevKit      |
             |                       |
(Pullup 4.7k)| GPIO 4  <-----------> | DS18B20 Data (Pin 2)
 (Pullup 10k)| GPIO 16 <-----------> | DHT22 Data (Pin 2)
             | GPIO 18 ------------> | Gate Driver MOSFET Heater (PWM 5kHz)
             | GPIO 19 ------------> | Gate Driver MOSFET Kipas (PWM 5kHz)
             | GPIO 2  ------------> | Anoda Blue LED (+330Ω ke GND)
             | GPIO 21 <-----------> | LCD 16x2 I2C SDA
             | GPIO 22 <-----------> | LCD 16x2 I2C SCL
             | 3V3     ------------> | VCC DS18B20, DHT22
             | 5V/VIN  ------------> | VCC I2C Backpack LCD (5V)
             | GND     ------------> | Common GND (Sistem & Driver MOSFET)
             +-----------------------+
```

---

## 3. Mekanisme Fail-Safe Thermal Guard

Sesuai standar prototipe keselamatan riset medis (PRD v1.2), pemanas tidak boleh mengalami *thermal runaway* dalam kondisi kegagalan sensor:

1. **Deteksi Kabel Sensor Terputus:**
   - DS18B20 mengembalikan `-127.00°C` saat data terputus, atau `85.00°C` jika dibaca sebelum konversi selesai.
   - Tindakan: Heater PWM seketika dipaksa ke **0% (OFF)** dalam < 100ms.
   - PID Controller di-*reset* (integral = 0) untuk mencegah *windup accumulation*.
2. **Ambang Batas Suhu Kritis (Overheat Guard):**
   - Jika suhu objek terbaca `> 38.5°C`:
     - Heater PWM langsung dipaksa ke **0%**.
     - Kipas sirkulasi dipaksa berjalan maksimal (**100% PWM / 255**) untuk pendinginan darurat.
     - LCD menampilkan: `ALARM: OVERHEAT!`.
3. **Ambang Batas Suhu Abnormal (Underheat Anomaly):**
   - Jika suhu objek terbaca `< 15.0°C`, sistem mendeteksi anomali sensor/kabel dan mematikan pemanas.
4. **Sensor Timeout Watchdog:**
   - Jika tidak ada pembacaan valid yang diterima dalam durasi **2500ms**, fail-safe otomatis aktif.

---

## 4. Tampilan Antarmuka LCD 16x2

LCD I2C 16x2 menampilkan telemetri dan status operasional perangkat:

### Kondisi Normal (Standby):
```
+----------------+
|T:32.4C H:58% 40%|  <- Baris 1: Suhu Objek, Kelembapan, Daya Heater %
|INC-001 [STANDBY]|  <- Baris 2: Kode Inkubator & Status Standby
+----------------+
```

### Kondisi Normal (Terhubung / Paired dengan Bayi):
```
+----------------+
|T:32.4C H:58% 40%|  <- Baris 1: Suhu Objek, Kelembapan, Daya Heater %
|BAYI-001 [PAIRED]|  <- Baris 2: Kode Bayi Simulasi & Status Paired
+----------------+
```

### Kondisi Fail-Safe (Sensor Error / Overheat):
```
+----------------+
|T:ERR SENSOR-FLT|  <- Baris 1: Peringatan Keselamatan Sensor
|INC-001 [STANDBY]|  <- Baris 2: Status Operasional
+----------------+
```
atau
```
+----------------+
|ALARM: OVERHEAT!|
|INC-001 [STANDBY]|
+----------------+
```

---

## 5. Protokol Komunikasi & Config Piggybacking

ESP32 mengirim data telemetri ke server Laravel setiap 3–5 detik via endpoint:
`POST /api/devices/telemetry`

### Header Permintaan:
```http
Content-Type: application/json
X-Device-Token: sec_dev_tok_93847298374
```

### Payload Telemetri (ESP32 -> Server):
```json
{
  "device_code": "INC-001",
  "applied_config_version": 2,
  "temperature_object": 32.45,
  "temperature_room": 29.10,
  "humidity": 58.20,
  "heater_pwm": 120,
  "fan_pwm": 80,
  "blue_led_status": false,
  "sensor_status": {
    "ds18b20": "OK",
    "dht22": "OK"
  },
  "timestamp": "2026-11-10T08:15:00+07:00"
}
```

### Respons Server dengan Piggybacked Config:
```json
{
  "status": "success",
  "is_paired": true,
  "baby": {
    "code": "BAYI-2026-001",
    "name": "Bayi Ny. Siti"
  },
  "has_config_update": true,
  "server_config_version": 3,
  "config": {
    "config_version": 3,
    "temperature_setpoint": 33.5,
    "pid": {
      "kp": 2.5,
      "ki": 0.15,
      "kd": 0.6
    },
    "fan_mode": "pwm",
    "fan_pwm_manual": 100,
    "blue_led_enabled": true,
    "telemetry_interval_sec": 3
  }
}
```

**Alur Kerja Konfirmasi (ACK):**
1. Core 0 mem-parsing objek `config` dan memvalidasi `temperature_setpoint` dalam batas aman (28.0°C – 37.0°C).
2. Parameter diterapkan ke objek memori bersama di bawah penguncian `configMutex`.
3. Variabel `appliedConfigVersion` diubah menjadi `server_config_version` (`3`).
4. Pada siklus telemetri berikutnya, ESP32 mengirimkan `"applied_config_version": 3`. Server Laravel mencatat versi tersebut telah aktif tanpa memerlukan endpoint polling terpisah.

---

## 6. Petunjuk Kompilasi & Flashing

Firmware mendukung dua alur pengembangan: **PlatformIO** (disarankan) dan **Arduino IDE**.

### Opsi A: PlatformIO CLI / VS Code PlatformIO (Disarankan)

1. **Buka Proyek:**
   Buka folder `firmware/esp32-incubator` di VS Code dengan ekstensi PlatformIO IDE terpasang.

2. **Kompilasi Firmware:**
   ```bash
   pio run
   ```

3. **Unggah ke ESP32:**
   Hubungkan ESP32 DevKit via kabel USB data:
   ```bash
   pio run --target upload
   ```

4. **Monitor Serial:**
   ```bash
   pio device monitor --speed 115200
   ```

### Opsi B: Arduino IDE

1. Pastikan **ESP32 Board Support** terpasang melalui Boards Manager (`esp32` by Espressif Systems).
2. Pasang library berikut via Library Manager:
   - `OneWire` by Paul Stoffregen
   - `DallasTemperature` by Miles Burton
   - `DHT sensor library` by Adafruit
   - `Adafruit Unified Sensor` by Adafruit
   - `LiquidCrystal_I2C` by Marco Schwartz
   - `ArduinoJson` (v7 atau v6) by Benoit Blanchon
3. Buka file `firmware/esp32-incubator/incubator_firmware.ino` (atau `esp32-incubator.ino`).
4. Pilih board: **DOIT ESP32 DEVKIT V1** atau **ESP32 Dev Module**.
5. Klik tombol **Verify** (Kompilasi) lalu **Upload**.

---

## 7. Pengujian Unit Logika & Matematika (Host Tests)

Logika kontrol PID anti-windup, proteksi keselamatan suhu, formatting LCD, dan validasi config dapat diuji langsung pada komputer host tanpa hardware fisik:

```bash
# Kompilasi dan jalankan test suite C++17
clang++ -std=c++17 -Wall -Wextra firmware/tests/test_firmware_logic.cpp -o firmware/tests/test_firmware_logic
./firmware/tests/test_firmware_logic
```

**Output Hasil Pengujian:**
```
========================================
Running ESP32 Incubator Logic Unit Tests
========================================
  [PASS] test_pid_proportional
  [PASS] test_pid_anti_windup
  [PASS] test_derivative_kick_avoidance
  [PASS] test_pid_tunings_and_reset
  [PASS] test_failsafe_guard
  [PASS] test_lcd_formatting
  [PASS] test_piggybacked_config_clamping
========================================
ALL 7 UNIT TESTS PASSED SUCCESSFULLY!
========================================
```
