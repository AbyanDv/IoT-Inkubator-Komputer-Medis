# Smart Infant Incubator

Prototipe pemantauan inkubator berbasis Laravel dan ESP32. Dashboard mencatat telemetri, status perangkat, konfigurasi, serta pairing bayi. Firmware menjalankan kontrol lokal; MQTT menghubungkan perangkat dan server melalui broker ber-TLS.

> **Batas penggunaan:** Proyek ini untuk pembelajaran dan pengujian laboratorium. Bukan alat medis, bukan untuk merawat bayi, dan tidak menggantikan pengawasan tenaga kesehatan. Uji aktuator hanya dengan beban dummy.

## Isi proyek

- `app/`, `routes/`, `resources/`: aplikasi Laravel dan dashboard.
- `database/`: migrasi, factory, dan seeder.
- `firmware/esp32-incubator/`: firmware ESP32 berbasis PlatformIO.
- `simulator/`: simulator perangkat untuk menguji MQTT tanpa ESP32.
- `PRD.md`: kebutuhan produk dan kontrak API.
- `firmware/README.md`: pinout dan panduan firmware.

Dashboard memakai Laravel Blade, Tailwind CSS, dan JavaScript. Perangkat dapat mengirim data lewat REST API atau MQTT. REST API tetap tersedia untuk firmware yang belum memakai MQTT.

## Jalankan aplikasi lokal

Persyaratan: PHP 8.3+, Composer, Node.js, dan npm. Untuk setup awal:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Atur koneksi DB di `.env`. SQLite dapat dipakai untuk pengembangan lokal. Lalu jalankan:

```bash
php artisan migrate --seed
npm install
npm run build
composer run dev
```

`composer run dev` menjalankan server pengembangan yang didefinisikan proyek. Akun seed lokal memakai `admin@incubator.local` dengan kata sandi `password`. Ganti kata sandi sebelum memakai data nyata, dan jangan gunakan akun seed di server publik.

## Alur MQTT

Perangkat dan Laravel terhubung ke broker melalui TLS pada port `8883`:

| Topik | Arah | Isi |
| --- | --- | --- |
| `incubator/{device_code}/telemetry` | Perangkat ke Laravel | Payload telemetri REST yang sama |
| `incubator/{device_code}/events` | Perangkat ke Laravel | Event perangkat |
| `incubator/{device_code}/status` | Perangkat ke Laravel | Status online/offline; status offline juga dikirim sebagai LWT |
| `incubator/{device_code}/sync` | Laravel ke perangkat | Status pairing dan konfigurasi, mengikuti bentuk respons REST |

Laravel menyimpan telemetri dan event melalui jalur ingest bersama. Telemetri berikutnya mengonfirmasi versi konfigurasi yang sudah diterapkan.

## Hubungkan aplikasi lokal ke broker VPS

Bagian ini mengatur **aplikasi Laravel di komputer lokal** agar terhubung ke broker di VPS. Jangan jalankan perintah Laravel ini di VPS kecuali aplikasi Laravel memang berada di sana.

1. Minta operator VPS membuat kredensial khusus untuk setiap device dan memberikan sertifikat CA publik. Jangan memakai kredensial admin broker pada perangkat.
2. Simpan CA di lokasi yang tidak masuk Git, misalnya `storage/app/private/mqtt/ca.crt`.
3. Isi `.env` lokal:

```dotenv
MQTT_ENABLED=true
MQTT_HOST=alamat-broker
MQTT_PORT=8883
MQTT_AUTH_USERNAME=laravel-service
MQTT_AUTH_PASSWORD=isi-dari-operator
MQTT_TLS_ENABLED=true
MQTT_TLS_CA_FILE=/path/absolut/ke/ca.crt
MQTT_TLS_VERIFY_PEER=true
MQTT_TLS_VERIFY_PEER_NAME=true
MQTT_TOPIC_PREFIX=incubator
```

`MQTT_HOST` harus cocok dengan nama DNS atau alamat IP dalam sertifikat broker. Jangan menonaktifkan verifikasi TLS.

4. Jalankan subscriber di satu terminal:

```bash
php artisan mqtt:subscribe
```

Biarkan proses itu berjalan. Untuk penggunaan terus-menerus, jalankan melalui process manager di host aplikasi. Dashboard hanya menandai perangkat online setelah menerima status atau telemetri.

## Uji simulator

Salin `simulator/.env.example` menjadi `simulator/.env`, lalu isi kredensial device dan lokasi CA. File `.env` simulator diabaikan Git. Gunakan akun khusus device, bukan admin.

Perintah berikut memakai sintaks Bash. Simulator juga membaca file `.env` sendiri, jadi tidak perlu men-source file itu. Di Fish, jalankan perintah tanpa `set -a`:

```bash
python3 -m venv simulator/.venv
simulator/.venv/bin/pip install -r simulator/requirements.txt
simulator/.venv/bin/python simulator/esp32_simulator.py
```

Jalankan subscriber Laravel di terminal lain. Simulator mengirim telemetri setiap lima detik, mengirim event saat tersambung, menerima sync, dan mengirim status offline saat berhenti normal. Untuk mensimulasikan gangguan sensor, set `SIMULATE_FAULT=1` di `simulator/.env`.

## Siapkan broker di VPS

Bagian ini untuk **operator VPS**. Broker yang sudah berjalan memakai Mosquitto 2.0.11, file konfigurasi di `/opt/mosquitto`, dan listener TLS pada port `8883`.

- Konfigurasi: `/opt/mosquitto/config/mosquitto.conf`
- ACL: `/opt/mosquitto/config/acl`
- Password broker: `/opt/mosquitto/config/passwd`
- Sertifikat broker: `/opt/mosquitto/certs/`
- CA key: `/opt/mosquitto/ca/ca.key`
- Compose: `/opt/mosquitto/compose.yaml`

Listener tanpa TLS di port `1883` sudah dinonaktifkan. Pertahankan ACL per-device: username harus sama dengan `device_code`; device hanya boleh menulis `telemetry`, `events`, dan `status` miliknya, serta membaca `sync` miliknya. Laravel membutuhkan akun layanan dengan hak publish sync dan subscribe ke topik perangkat.

Untuk menambahkan device, operator membuat password broker, menambahkan ACL sesuai device code, lalu memberikan kredensial dan CA publik kepada pemilik device. Simpan CA key dan password di luar repositori. Batasi akses jaringan ke port `8883` sesuai kebutuhan; jangan membuka akses admin kepada device.

## Firmware ESP32

Firmware berada di `firmware/esp32-incubator/` dan dibangun dengan PlatformIO. Baca `firmware/README.md` untuk wiring, konfigurasi, dan langkah build. Atur Wi-Fi, URL API, serta token perangkat di file konfigurasi lokal sebelum mengunggah firmware. Jangan commit kredensial.

Firmware saat ini memakai HTTPS REST API. Simulator Python menguji alur MQTT; belum berarti firmware ESP32 sudah memakai MQTT. Gunakan beban dummy dan periksa batas fail-safe sebelum menguji rangkaian.

## Pengujian

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

## Kontribusi dan keselamatan

Jangan commit `.env`, password broker, token perangkat, CA private key, atau kredensial Wi-Fi. Jangan gunakan sistem ini untuk keputusan klinis atau perawatan pasien.
