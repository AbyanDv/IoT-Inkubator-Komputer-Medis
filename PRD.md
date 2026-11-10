# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## Smart Infant Incubator – IoT Monitoring and Management System

**Versi:** 1.2 (Penyederhanaan Alur Pairing Direct Dashboard - Non-OTP)\
**Status:** Approved Draft – Siap Implementasi\
**Platform:** Web Application + IoT Microcontroller (ESP32)\
**Backend Framework:** Laravel (PHP 8.2+)\
**Database:** MySQL / MariaDB / SQLite (Dev)\
**Frontend:** Blade + Tailwind CSS + Alpine.js\
**Hardware:** ESP32 DevKit V1, Sensor DS18B20, DHT22, LCD I2C 16x2, MOSFET Driver PWM, Dummy Heater, Fan PWM, Blue LED\
**Target Deployment:** Linux VPS (Nginx + HTTPS)

---

# 1. Pendahuluan

## 1.1 Latar Belakang & Pernyataan Masalah

Inkubator bayi merupakan perangkat biomedis kritis yang berfungsi mempertahankan lingkungan mikro (suhu dan kelembapan) yang stabil bagi bayi baru lahir berisiko tinggi atau prematur. Pada fasilitas kesehatan di daerah dengan keterbatasan pengawasan langsung atau perawat jaga, pemantauan kondisi lingkungan inkubator secara berkala sangat krusial untuk mencegah hipotermia maupun hipertermia.

Proyek **Smart Infant Incubator** dikembangkan sebagai **prototipe riset dan pembelajaran IoT pada mata kuliah Komputer Medis**. Sistem mengintegrasikan mikrokontroler ESP32 dengan backend berbasis Laravel untuk:
- Memantau kondisi suhu objek (simulasi kontak tubuh) dan lingkungan inkubator secara berkala.
- Mengimplementasikan kendali umpan balik pemanas (*dummy heater*) menggunakan algoritma PID lokal berbasis modulasi lebar pulsa (*PWM*) via driver MOSFET.
- Mengatur sirkulasi udara melalui kipas DC berbasis kendali PWM.
- Menyediakan simulasi visual lampu fototerapi (LED biru).
- Menyediakan web dashboard terpusat untuk administrasi bayi simulasi, penghubungan perangkat langsung (*direct dashboard pairing*), pemantauan telemetri real-time, visualisasi grafik historis, serta pengaturan setpoint jarak jauh.

## 1.2 Batasan Etika & Keselamatan Medis (*Safety Boundary*)

1. **Bukan Alat Medis Terakreditasi:** Prototipe ini ditujukan murni untuk pengujian teknis, riset, dan demonstrasi akademik. **DILARANG KERAS** digunakan pada manusia/bayi secara langsung.
2. **Beban Uji Dummy:** Pemanas menggunakan beban resistif dummy uji laboratorium berdaya rendah, bukan pemanas inkubator ruang klinis.
3. **Bukan Penentu Keputusan Medis:** Aplikasi dan algoritma dilarang secara otomatis menyimpulkan kebutuhan suhu atau diagnosis klinis hanya dari berat badan atau usia gestasi bayi. Nilai setpoint suhu harus dimasukkan secara sadar oleh operator berwenang.
4. **Indikator Simulasi LED Biru:** LED biru hanya berfungsi sebagai visualisasi status on/off instruksional, bukan perangkat fototerapi dengan spektrum panjang gelombang terkalibrasi (*460–490 nm*) atau dosis radiasi terverifikasi.
5. **Fail-Safe Lokal Otonom:** Pengendalian keselamatan hardware berjalan 100% mandiri di firmware ESP32. Ketiadaan jaringan internet/kegagalan server VPS tidak boleh mengganggu stabilitas termal lokal atau memicu *thermal runaway*.

---

# 2. Target Pengguna & Role-Based Access Control (RBAC)

Aplikasi menerapkan sistem otorisasi berbasis peran untuk memastikan integritas operasional:

| Role | Deskripsi & Hak Akses |
| :--- | :--- |
| **Super Admin** | Akses penuh sistem: manajemen akun pengguna, manajemen master data perangkat, audit trail log, konfigurasi sistem global, pairing override. |
| **Admin** | Manajemen data bayi simulasi, registrasi & pairing perangkat inkubator, pengaturan parameter simulasi (setpoint suhu, mode fan, switch LED), ekspor riwayat telemetri. |
| **Operator** | Akses harian: monitoring dashboard real-time, melihat status aktuator, meninjau alarm/notifikasi teknis, input catatan pemantauan bayi. |
| **Viewer** | Akses baca (*read-only*): hanya dapat melihat ringkasan dashboard dan riwayat telemetri tanpa hak mengubah konfigurasi atau melakukan pairing. |

---

# 3. Arsitektur Teknis & Pola Komunikasi

## 3.1 Diagram Arsitektur Komponen

```
+-------------------------------------------------------------+
|                      WEB DASHBOARD                          |
|         (Tailwind CSS + Alpine.js Real-Time Engine)          |
|  - Live Status Cards (2-3s Polling)  - Interactive Charts   |
|  - Direct Device-Baby Assignment     - Config Controller     |
+------------------------------+------------------------------+
                               |
                               | HTTPS REST API (JSON)
                               v
+-------------------------------------------------------------+
|                     LARAVEL BACKEND VPS                     |
|                                                             |
|  - Device Auth & Token Auth       - Direct Pairing Service  |
|  - Telemetry Ingestion API        - Piggybacked Config Sync |
|  - Live Telemetry Cache Endpoint  - Alert & Notification    |
|  - Time-Series Pruning Engine     - Audit Log Tracking      |
+------------------------------+------------------------------+
                               |
                               v Database Engine
+-------------------------------------------------------------+
|                  MYSQL / MARIADB DATABASE                   |
|   (Composite Indexes on Telemetry: incubator_id, created_at)|
+-------------------------------------------------------------+
                               ^
                               | HTTPS / Wi-Fi WPA2
                               |
+------------------------------+------------------------------+
|                     ESP32 MICROCONTROLLER                   |
|                                                             |
| [Core 0: Network Task]          [Core 1: Real-Time Engine]  |
| - Wi-Fi Manager & HTTPS Client  - Non-blocking DS18B20 Read |
| - Telemetry Dispatcher          - DHT22 Sampling            |
| - Config Extractor (Piggyback)  - Local PID Heater Control  |
| - FreeRTOS Comm Queue           - Fan PWM & Blue LED Drive  |
|                                 - LCD I2C 16x2 Display      |
|                                 - Hardware Fail-Safe Trip   |
+-------------------------------------------------------------+
```

## 3.2 Pola Komunikasi & Optimasi Jaringan (Piggybacking Pattern)

Menghindari overhead TLS Handshake (400–1000 ms per koneksi pada ESP32) akibat polling config terpisah:
1. ESP32 mengirim `POST /api/devices/telemetry` setiap 3–5 detik, menyertakan header token perangkat dan field `applied_config_version`.
2. Laravel memvalidasi data dan memeriksa apakah versi konfigurasi aktif di server lebih tinggi daripada `applied_config_version`.
3. **Piggybacked Response:** Jika ada konfigurasi baru, server menyertakan payload konfigurasi di dalam respons JSON telemetri.
4. ESP32 mengekstrak payload baru, menerapkannya ke variabel kontrol lokal, dan pada pengiriman telemetri berikutnya mengirim versi yang telah diperbarui sebagai konfirmasi (ACK) otomatis.

## 3.3 Mekanisme Real-Time Dashboard (HTTP Short Polling)

1. Dashboard tidak menggunakan WebSocket daemon (Reverb/Pusher) untuk meminimalkan beban memori dan ketergantungan proses background di VPS.
2. Komponen Alpine.js pada browser melakukan HTTP GET polling setiap **2 s.d. 3 detik** ke endpoint ultra-cepat:  
   `GET /api/incubators/{id}/live-telemetry`
3. Endpoint ini hanya membaca 1 baris telemetri terakhir (atau cache memory) dengan waktu respons `< 30ms`.

---

# 4. Spesifikasi Fungsional Detail

## 4.1 Manajemen Perangkat & Alur Pairing Langsung (Direct Dashboard Assignment)

Perangkat fisik ESP32 diidentifikasi melalui **Device Token** atau kombinasi `device_code` dan `mac_address`. Pengoperasian pairing disederhanakan sepenuhnya menjadi aksi **Direct Web Assignment** melalui dashboard, tanpa memerlukan kode OTP numerik manual yang membebani operator.

### 1. Registrasi & Autentikasi Perangkat (Provisioning)
- Admin mendaftarkan perangkat inkubator di dashboard (Nama: `Inkubator A1`, Kode: `INC-001`, MAC Address: `24:6F:28:AB:CD:EF`).
- Sistem Laravel menerbitkan `device_token` unik yang disimpan di ESP32 (misal di konfigurasi firmware).
- Saat ESP32 menyala dan terhubung ke Wi-Fi, perangkat langsung mengirim telemetri dan heartbeat menggunakan header `X-Device-Token: <token>`.
- Jika belum dihubungkan dengan data bayi, status perangkat adalah `unpaired` (standby).

### 2. Alur Pairing / Penghubungan ke Bayi (Web Dashboard)
1. Admin membuka menu **Manajemen Inkubator** atau **Data Bayi** di Web Dashboard.
2. Admin memilih inkubator yang berstatus `unpaired` (standby).
3. Admin memilih data bayi simulasi dari dropdown (bayi yang berstatus `active` dan belum menempati inkubator lain).
4. Admin menekan tombol **"Hubungkan ke Bayi" (Pair)**.
5. Server Laravel:
   - Memvalidasi bahwa inkubator dan bayi dalam status tersedia.
   - Mengisi `incubators.current_baby_id = $baby->id` dan mengeset `status = 'paired'`.
   - Mencatat log pada tabel `device_pairings` (`incubator_id`, `baby_id`, `paired_by_user_id`, `paired_at`, `status = 'active'`).
6. **Sinkronisasi Otomatis ke ESP32 via Telemetri:**
   - Pada respons pengiriman telemetri berikutnya (`POST /api/devices/telemetry`), server menyertakan status dan ringkasan data bayi aktif:
     ```json
     {
       "status": "success",
       "is_paired": true,
       "baby": {
         "code": "BAYI-2026-001",
         "name": "Bayi Ny. Siti"
       }
     }
     ```
   - Firmware ESP32 langsung memperbarui tampilan baris ke-2 LCD 16x2: `BAYI-001 [PAIRED]`.

### 3. Alur Pelepasan / Unpairing (Web Dashboard)
1. Setelah simulasi perawatan bayi selesai, Admin menekan tombol **"Lepaskan (Unpair)"** pada dashboard inkubator.
2. Server Laravel mengosongkan `incubators.current_baby_id = null`, mengembalikan status perangkat menjadi `unpaired`, mengisi `unpaired_at`, dan mengubah status riwayat pairing menjadi `completed`.
3. Pada respons telemetri berikutnya, server mengirimkan `is_paired: false, baby: null`.
4. Layar LCD ESP32 otomatis beralih menampilkan: `INC-001 [STANDBY]`.

## 4.2 Manajemen Data Bayi Simulasi

Data bayi simulasi disimpan untuk memberikan konteks pada monitoring inkubator:
- `id`: Auto increment primary key
- `baby_code`: Kode unik (misal: `BAYI-2026-001`)
- `name`: Nama bayi simulasi
- `gender`: `male` / `female`
- `birth_date`: Tanggal dan jam kelahiran
- `gestational_age_weeks`: Usia kehamilan (24–42 minggu)
- `birth_weight_grams`: Berat lahir (500–5000 gram)
- `birth_length_cm`: Panjang lahir (25–60 cm)
- `current_weight_grams`: Berat badan terkini
- `medical_notes`: Catatan kondisi fisik simulasi
- `status`: `active` (sedang dirawat di inkubator) / `discharged` (selesai)

## 4.3 Monitoring Sensor & Semantik Klinis

1. **Sensor DS18B20:**
   - **Semantik Tampilan UI:** *"Suhu Objek / Kontak Simulasi"* (bukan suhu rektal/aksila bayi klinis terverifikasi).
   - Resolusi: 10-bit / 11-bit (waktu konversi ~187ms / 375ms non-blocking).
   - Rentang Valid: 20.00°C – 45.00°C.
   - Deteksi Error: Pembacaan `-127.00°C` atau `85.00°C` (kondisi kabel terputus / daya tidak stabil) langsung dilaporkan sebagai `SENSOR_ERROR` dan mematikan pemanas secara otomatis.
2. **Sensor DHT22:**
   - **Semantik Tampilan UI:** *"Suhu Lingkungan Inkubator"* dan *"Kelembapan Relatif Inkubator"*.
   - Rentang Valid Suhu: 15.00°C – 50.00°C.
   - Rentang Valid Kelembapan: 10.0% – 95.0% RH.
   - Deteksi Error: Nilai `NaN` dilaporkan sebagai `DHT_READ_FAIL`.

## 4.4 Pengendalian Aktuator

1. **Heater Dummy (Modulasi PID via Driver MOSFET):**
   - Menggunakan PWM frekuensi 1 kHz hingga 5 kHz pada pin ESP32.
   - Status dilaporkan sebagai: Nilai duty cycle PWM (`0 - 255`) dan persentase daya (`0% - 100%`).
   - Algoritma: PID mandiri pada ESP32 dengan konstanta `Kp`, `Ki`, `Kd`.
2. **Fan DC (Modulasi PWM):**
   - Mode operasi: `auto` (mengikuti profil suhu/kelembapan) atau `manual_pwm`.
   - Status dilaporkan sebagai: Nilai PWM (`0 - 255`) dan RPM simulasi/persentase (`0% - 100%`).
3. **Simulasi Blue Light (LED Biru):**
   - Status dilaporkan: `true` (ON) / `false` (OFF).
   - Kontrol via dashboard hanya sebagai simulasi on/off instruksional.

## 4.5 Konfigurasi Sistem & Sinkronisasi Versi

Setiap inkubator memiliki versi konfigurasi aktif di server:
- `config_version`: Integer sekuensial (dimulai dari `1`, increment setiap kali ada perubahan di dashboard).
- `temperature_setpoint`: Rentang yang diizinkan sistem: `28.0°C` s.d. `37.0°C` (safety clamp di firmware).
- `pid_kp`, `pid_ki`, `pid_kd`: Nilai tuning algoritma PID.
- `fan_mode`: `auto` / `manual`.
- `fan_pwm_manual`: `0 - 255`.
- `blue_led_enabled`: Boolean (`true` / `false`).
- `telemetry_interval_sec`: Interval pengiriman telemetri (default: `3` detik).

---

# 5. Spesifikasi Firmware ESP32 (FreeRTOS Dual-Core Architecture)

Untuk menjamin waktu nyata (*real-time performance*) dan mencegah *watchdog reset* akibat latensi jaringan:

```
+-------------------------------------------------------------+
|               ESP32 DUAL-CORE EXECUTION MODEL               |
|                                                             |
|  CORE 1 (Pro-CPU) : Real-Time Control Loop (Priority: High) |
|  ---------------------------------------------------------  |
|  1. Non-blocking DS18B20 sampling (setWaitForConversion)   |
|  2. DHT22 non-blocking reading interval                     |
|  3. Local PID Controller Loop (Hitung error & set PWM)      |
|  4. Hardware Fail-Safe Guard:                               |
|     - If (temp_obj > 38.5°C || temp_obj < 15.0°C) -> PWM=0  |
|     - If (sensor_error == true) -> PWM = 0                  |
|  5. Refresh LCD I2C 16x2 (Suhu, Kelembapan, Status/Bayi)    |
|  6. Push data ke FreeRTOS Queue                             |
|                                                             |
|                          |                                  |
|                          | FreeRTOS Queue (QueueHandle_t)   |
|                          v                                  |
|                                                             |
|  CORE 0 (App-CPU) : Network & Telemetry Loop (Priority: Med)|
|  ---------------------------------------------------------  |
|  1. Wi-Fi Auto-Reconnect Manager                            |
|  2. Read item from Queue                                    |
|  3. Construct JSON Payload                                  |
|  4. HTTPS POST /api/devices/telemetry                       |
|  5. Inspect Response:                                       |
|     - Parse & apply "config" payload (jika ada update)      |
|     - Update pairing state & active baby info untuk LCD     |
+-------------------------------------------------------------+
```

---

# 6. Spesifikasi Antarmuka REST API & Web Actions

Base URL API: `/api/v1` (atau `/api/devices`)

### Ringkasan Endpoint

| Method | Endpoint | Hak Akses | Deskripsi |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/devices/register` | Admin / Provisioning | Registrasi awal identitas perangkat & pembuatan token |
| `POST` | `/api/devices/heartbeat` | ESP32 (Device Token) | Heartbeat status perangkat (online/offline) |
| `POST` | `/api/devices/telemetry` | ESP32 (Device Token) | Ingestion telemetri + Piggybacked config & sync bayi |
| `GET`  | `/api/devices/config` | ESP32 (Device Token) | Fallback cold boot penarikan konfigurasi |
| `POST` | `/api/devices/events` | ESP32 (Device Token) | Pencatatan insiden/alarm/error hardware |
| `GET`  | `/api/incubators/{id}/live-telemetry` | Web Session (Auth) | Short polling telemetri data real-time |
| `POST` | `/incubators/{id}/pair` | Web Session (Admin) | Action web: hubungkan inkubator ke bayi simulasi |
| `POST` | `/incubators/{id}/unpair` | Web Session (Admin) | Action web: lepaskan inkubator dari bayi simulasi |

### 6.1 Web Action: `POST /incubators/{id}/pair`
Aksi pairing langsung via antarmuka web dashboard:
- **Request Body (JSON / Form Data):**
  ```json
  {
    "baby_id": 3,
    "notes": "Penempatan simulasi perawatan inkubator A1"
  }
  ```
- **Response (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Inkubator berhasil dihubungkan dengan data bayi.",
    "incubator_id": 1,
    "baby_id": 3,
    "paired_at": "2026-11-10T08:14:45Z"
  }
  ```

### 6.2 Web Action: `POST /incubators/{id}/unpair`
Aksi pelepasan hubungan bayi dari inkubator:
- **Response (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Inkubator berhasil dilepaskan (status kembali Unpaired/Standby).",
    "incubator_id": 1,
    "unpaired_at": "2026-11-10T09:30:00Z"
  }
  ```

### 6.3 `POST /api/devices/telemetry` (Dengan Piggybacked Config & Info Bayi)
- **Header:** `X-Device-Token: sec_dev_tok_93847298374`
- **Request Body:**
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
- **Response (200 OK - Versi Server Sama & Perangkat Paired):**
  ```json
  {
    "status": "success",
    "is_paired": true,
    "baby": {
      "code": "BAYI-2026-001",
      "name": "Bayi Ny. Siti"
    },
    "has_config_update": false,
    "server_config_version": 2
  }
  ```
- **Response (200 OK - Terdapat Versi Konfigurasi Baru #3):**
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

### 6.4 `GET /api/incubators/{id}/live-telemetry`
- **Response (200 OK):**
  ```json
  {
    "incubator_id": 1,
    "is_online": true,
    "last_seen_seconds_ago": 2,
    "baby": {
      "id": 3,
      "name": "Bayi Ny. Siti",
      "code": "BAYI-2026-001"
    },
    "telemetry": {
      "temperature_object": 32.45,
      "temperature_room": 29.10,
      "humidity": 58.20,
      "heater_pwm": 120,
      "fan_pwm": 80,
      "blue_led_status": false,
      "timestamp": "2026-11-10T08:15:00+07:00"
    }
  }
  ```

---

# 7. Desain Skema Database (Schema Definition)

Terdapat 10 tabel inti dengan relasi dan indeks optimal:

### 1. `users` & 2. `roles`
Mengelola akun operator, admin, super admin, dan viewer beserta hash password (bcrypt/argon2).

### 3. `babies`
Data bayi simulasi (kode, nama, tanggal lahir, usia gestasi, berat, panjang, status).

### 4. `incubators`
- `id` (PK)
- `device_code` (Unique, misal `INC-001`)
- `name` (Nama perangkat, misal `Inkubator A1`)
- `mac_address` (Unique, misal `24:6F:28:AB:CD:EF`)
- `device_token` (Hashed unique API token untuk otentikasi telemetry)
- `firmware_version` (String, misal `1.0.2`)
- `status` (Enum: `unpaired`, `paired`, `offline`, `error`)
- `current_baby_id` (FK nullable ke `babies.id`)
- `active_config_version` (Integer default `1`)
- `last_heartbeat_at` (Timestamp nullable)
- Timestamps

### 5. `device_pairings`
Tabel audit riwayat penempatan dan pelepasan bayi pada inkubator:
- `id` (PK)
- `incubator_id` (FK ke `incubators.id`)
- `baby_id` (FK ke `babies.id`)
- `paired_by_user_id` (FK nullable ke `users.id`)
- `paired_at` (Timestamp)
- `unpaired_at` (Timestamp nullable)
- `notes` (Text nullable)
- `status` (Enum: `active`, `completed`)
- Timestamps

### 6. `device_telemetries` (Tabel Time-Series)
- `id` (BigIncrements PK)
- `incubator_id` (FK ke `incubators.id`, indexed)
- `temperature_object` (Decimal 5,2: -40.00 s.d. 125.00)
- `temperature_room` (Decimal 5,2)
- `humidity` (Decimal 5,2)
- `heater_pwm` (UnsignedTinyInteger: 0–255)
- `fan_pwm` (UnsignedTinyInteger: 0–255)
- `blue_led_status` (Boolean)
- `ds18b20_status` (String/Enum: `OK`, `ERROR`, `DISCONNECTED`)
- `dht22_status` (String/Enum: `OK`, `ERROR`)
- `applied_config_version` (UnsignedInteger)
- `created_at` (Timestamp, indexed)
- **Composite Index:** `INDEX idx_incubator_created (incubator_id, created_at DESC)`

### 7. `device_configs`
- `id` (PK)
- `incubator_id` (FK ke `incubators.id`)
- `config_version` (Integer)
- `temperature_setpoint` (Decimal 4,2, default 32.00)
- `pid_kp` (Decimal 6,3, default 2.000)
- `pid_ki` (Decimal 6,3, default 0.100)
- `pid_kd` (Decimal 6,3, default 0.500)
- `fan_mode` (Enum: `auto`, `pwm`)
- `fan_pwm_manual` (UnsignedTinyInteger default 0)
- `blue_led_enabled` (Boolean default false)
- `telemetry_interval_sec` (SmallInteger default 3)
- `created_by_user_id` (FK ke `users.id`)
- Timestamps

### 8. `device_events`
Mencatat kejadian luar biasa (sensor putus, suhu melewati ambang batas aman, kegagalan Wi-Fi reconnect).

### 9. `notifications`
Pemberitahuan kepada user/operator di aplikasi web.

### 10. `audit_logs`
Riwayat modifikasi data bayi, perubahan setpoint konfigurasi, login/logout, dan aksi pairing/unpairing.

### 7.1 Strategi Skalabilitas Database & Time-Series Pruning
Dengan pengiriman telemetri tiap 3 detik per inkubator:
- 1 Inkubator = ~28.800 baris/hari.
- **Strategi Pemeliharaan:**
  - Index komposit `(incubator_id, created_at DESC)` memastikan query dashboard (`ORDER BY created_at DESC LIMIT 1`) mengeksekusi dalam `< 1ms`.
  - Artisan Command: `php artisan telemetry:prune --days=14` yang dijalankan via Laravel Task Scheduler setiap malam untuk menghapus baris mentah yang lebih lama dari 14 hari setelah diarsipkan/diekspor.

---

# 8. Matriks Prioritas Fitur (MVP Roadmap)

| Prioritas | Kode Fitur | Deskripsi | Status Target |
| :---: | :---: | :--- | :---: |
| **P0** | `AUTH-01` | Autentikasi Pengguna & RBAC Sederhana | MVP Wajib |
| **P0** | `BABY-01` | CRUD Data Bayi Simulasi & Status Perawatan | MVP Wajib |
| **P0** | `DEV-01` | Manajemen Master Perangkat Inkubator & Token Auth | MVP Wajib |
| **P0** | `PAIR-01` | Alur Pairing Langsung (Direct Dashboard Assignment & Unpair) | MVP Wajib |
| **P0** | `TELEM-01`| Telemetry Ingestion (DS18B20, DHT22, Actuators) | MVP Wajib |
| **P0** | `DASH-01` | Web Dashboard Real-Time (HTTP Polling 2-3s Alpine.js) | MVP Wajib |
| **P0** | `CONF-01` | Pengaturan Setpoint Suhu & Piggybacked Config Sync | MVP Wajib |
| **P0** | `SAFE-01` | Local Fail-Safe Trip pada ESP32 (PWM=0 jika sensor putus) | MVP Wajib |
| **P1** | `HIST-01` | Grafik Riwayat Suhu/Kelembapan & Ekspor CSV | Fase 2 |
| **P1** | `ALRT-01` | Sistem Notifikasi & Event Log Pelanggaran Ambang | Fase 2 |
| **P1** | `PRUN-01` | Otomasi Pembersihan Time-Series Data (`telemetry:prune`) | Fase 2 |
| **P2** | `MQTT-01` | Transisi Protokol ke MQTT over TLS | Lanjutan |
| **P2** | `FHIR-01` | Interoperabilitas Standar HL7 FHIR / SATUSEHAT | Lanjutan |

---

# 9. Acceptance Criteria (Definisi Selesai)

1. **Pairing Langsung via Dashboard:**
   - Admin dapat memilih inkubator berstatus `unpaired` dan menghubungkannya ke bayi simulasi dari dropdown web.
   - Status inkubator seketika berubah menjadi `paired`, dan riwayat tercatat di `device_pairings`.
   - ESP32 menerima info bayi pada respons telemetri berikutnya (maks 3 detik) dan menampilkan kode bayi di LCD 16x2.
   - Admin dapat menekan tombol "Lepaskan (Unpair)" untuk mengembalikan status inkubator ke `unpaired` (standby).
2. **Pengiriman & Penerimaan Telemetri:**
   - Data suhu objek, suhu ruang, kelembapan, status heater PWM, fan PWM, dan blue LED masuk ke tabel database setiap 3 detik tanpa kebocoran memori.
3. **Konfigurasi Tanpa Latensi Tinggi:**
   - Saat Admin mengubah setpoint di web, perubahan terkirim ke ESP32 pada respons telemetri berikutnya (maksimal dalam 3 detik) tanpa ESP32 membuka koneksi HTTP terpisah.
4. **Visualisasi Real-Time:**
   - Nilai pada kartu dashboard web diperbarui setiap 2-3 detik secara halus tanpa me-refresh seluruh halaman.
5. **Keandalan Hardware & Keselamatan Lokal:**
   - Saat sensor DS18B20 sengaja dicabut (kondisi kabel putus), heater langsung mati (*PWM = 0*) dalam waktu kurang dari 500ms, LCD menampilkan pesan error, dan sistem web menerima event `SENSOR_ERROR`.
   - Saat koneksi Wi-Fi terputus, ESP32 tetap mempertahankan setpoint suhu menggunakan kontrol PID lokal dan tidak mengalami restart.

---

# 10. Panduan Verifikasi Pengujian

| Skenario Uji | Tindakan | Hasil yang Diharapkan |
| :--- | :--- | :--- |
| **TC-01: Pairing Dashboard Sukses** | Admin pilih bayi di dropdown inkubator & klik Pair | Inkubator terhubung, status menjadi `paired`, LCD ESP32 menampilkan nama/kode bayi |
| **TC-02: Unpairing Dashboard** | Admin klik tombol Lepaskan (Unpair) di dashboard | Hubungan diputus, status kembali `unpaired`, LCD ESP32 beralih ke standby |
| **TC-03: Validasi Konflik Pairing** | Coba pasangkan bayi yang sedang aktif di inkubator lain | Sistem menolak dengan pesan error validasi bahwa bayi sedang dirawat |
| **TC-04: Piggybacked Config** | Ubah setpoint di web dari 32°C ke 34°C | ESP32 menerima payload config pada response telemetry, ACK otomatis |
| **TC-05: Sensor Fault Safety** | Cabut pin data DS18B20 saat heater aktif | PWM pemanas seketika 0%, status sensor melaporkan error |
| **TC-06: Network Disconnect** | Matikan router Wi-Fi | PID lokal tetap stabil menjalankan kontrol pemanas di Core 1 |
