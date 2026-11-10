# Smart Infant Incubator – IoT Monitoring and Management System

Sistem prototipe pemantauan dan pengelolaan inkubator bayi berbasis *Internet of Things* (IoT) yang mengintegrasikan mikrokontroler **ESP32**, sensor suhu kontak objek **DS18B20**, sensor suhu & kelembapan lingkungan **DHT22**, kendali pemanas *dummy* berbasis algoritma **PID** melalui *driver* **MOSFET logic-level PWM**, kendali sirkulasi udara kipas DC (*PWM*), dan simulasi visual lampu fototerapi (**LED biru**), terhubung ke *backend* web **Laravel** dan *dashboard real-time*.

> [!IMPORTANT]
> **Pernyataan Batasan Etika & Keselamatan Medis (*Safety Boundary*):**
> Proyek ini merupakan prototipe pembelajaran dan riset akademik untuk mata kuliah **Komputer Medis**, **BUKAN** alat kesehatan yang telah tervalidasi klinis untuk penggunaan pada bayi manusia secara langsung. Seluruh pengujian pemanas menggunakan beban resistif *dummy* laboratorium. Sistem **tidak** menentukan diagnosis medis atau menghitung suhu terapi secara otomatis dari berat badan/umur bayi tanpa supervisi klinis sadar.

---

## Anggota Kelompok

| Nama | NIM |
| :--- | :--- |
| **Abyan Devadi** | H1H024049 |
| **Muhammmad Faiz Mubarok** | H1H024051 |
| **Hafish Athallah** | H1H024052 |

---

## Ikhtisar Arsitektur & Fitur Utama

Sistem dirancang dengan arsitektur *fail-safe* dan komunikasi jaringan hemat latensi:

1. **Manajemen Pairing Langsung via Web Dashboard:**
   - Admin menghubungkan perangkat inkubator dengan data bayi simulasi secara instan melalui pilihan *dropdown* di antarmuka *web dashboard*, tanpa memerlukan kode OTP numerik manual yang membebani operator.
   - Status *paired* dan identitas bayi aktif otomatis disinkronkan ke ESP32 melalui respons telemetri untuk ditampilkan pada layar LCD 16x2 (`BAYI-001 [PAIRED]`).
   - Riwayat penempatan dan pelepasan (*unpairing*) tercatat rapi pada tabel audit basis data.
2. **Kendali Mandiri Lokal (*Decoupled Local PID Fail-Safe*):**
   - Mikrokontroler ESP32 menjalankan algoritma PID lokal pada beban pemanas *dummy* secara otonom di **Core 1 (Real-time Task)** dengan *non-blocking timing* pada sensor DS18B20.
   - Jika koneksi internet atau server VPS terputus, stabilitas suhu pemanas tetap terjaga.
   - Jika sensor terlepas atau rusak, proteksi *hardware guard* langsung mematikan pemanas (*PWM = 0%*).
3. **Optimasi Jaringan (*Config Piggybacking Pattern*):**
   - Perubahan konfigurasi dari *dashboard* (seperti perubahan *setpoint* suhu atau status LED biru) dikirimkan langsung di dalam *response* telemetri (`POST /api/devices/telemetry`), memangkas *handshake* HTTPS terpisah dan latensi eksekusi.
4. **Dashboard Real-Time Ringan (*HTTP Short Polling 2-3s*):**
   - Antarmuka web dibangun menggunakan Laravel Blade, Tailwind CSS, dan Alpine.js yang mengambil telemetri terkini setiap 2 s.d. 3 detik melalui endpoint berkecepatan tinggi `< 30ms`, tanpa memerlukan *daemon WebSocket* tambahan di VPS.
5. **Skalabilitas Basis Data & Time-Series Pruning:**
   - Tabel `device_telemetries` dioptimalkan dengan *composite index* `(incubator_id, created_at DESC)`.
   - Tersedia kebijakan otomatis pembersihan data mentah via *scheduled command* harian.

---

## Komponen Perangkat Keras (Hardware Stack)

| Komponen | Spesifikasi / Fungsi |
| :--- | :--- |
| **Microcontroller** | ESP32 DevKit V1 (Dual-Core 240MHz, Wi-Fi 2.4GHz) |
| **Sensor Suhu Objek** | DS18B20 Digital Waterproof (Suhu kontak simulasi) |
| **Sensor Lingkungan** | DHT22 / AM2302 (Suhu dan kelembapan udara inkubator) |
| **Display Lokal** | LCD 16x2 dengan Modul I2C (Alamat `0x27` / `0x3F`) |
| **Driver Pemanas** | Modul MOSFET Logic-Level (IRF520 / IRLZ44N) untuk switching cepat PWM |
| **Beban Pemanas Dummy** | Resistor keramik daya / beban simulasi berdaya rendah |
| **Driver Kipas** | Fan DC 12V / 5V dengan kontrol PWM kecepatan putar |
| **Indikator Visual** | LED Biru simulasi fototerapi |

---

## Tumpukan Perangkat Lunak (Software Stack)

- **Backend:** Laravel (PHP 8.2+)
- **Database:** MySQL / MariaDB (Produksi) / SQLite (Pengembangan Lokal)
- **Frontend:** Laravel Blade, Tailwind CSS, Alpine.js, Chart.js
- **Protokol:** HTTPS REST API (JSON payload)
- **Firmware:** C++ / Arduino Framework / FreeRTOS Dual-Core

---

## Dokumen Spesifikasi Lengkap

Dokumentasi lengkap mengenai kebutuhan fungsional, matriks prioritas MVP (P0/P1/P2), kontrak payload REST API, dan rancangan skema 10 tabel basis data dapat dibaca pada:
📄 **[PRD.md](file:///Users/nymytz/Documents/GITHUB/IoT-Inkubator-Komputer-Medis/PRD.md)**

---

## Struktur Folder Proyek

```
.
├── app/                  # Controller, Model, dan Business Logic Laravel
├── database/             # Migrasi, Factory, dan Seeder Basis Data
├── docs/                 # Dokumentasi teknis tambahan & skema rangkaian
├── resources/            # Blade Views, Komponen Alpine.js, dan Aset CSS
├── routes/               # Rute Web dan REST API perangkat
├── firmware/             # Kode sumber Firmware ESP32 (PlatformIO / Arduino)
├── PRD.md                # Dokumen Spesifikasi Kebutuhan Produk (PRD v1.1)
└── README.md             # Ringkasan Proyek
```
