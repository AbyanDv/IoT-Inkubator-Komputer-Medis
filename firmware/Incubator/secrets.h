#pragma once
// Nilai lokal device ini. Tidak masuk git.
// Sumber: broker VPS 41.216.180.77 (/opt/mosquitto) dan tabel incubators Laravel.

#define KODE_PERANGKAT "INC-001"

// User MQTT = KODE_PERANGKAT. Password dari /opt/mosquitto/device-INC-001.password.
#define PASSWORD_MQTT "lbmi12Kogf9p2yAK0zNJdX26"

// Sama dengan incubators.device_token di Laravel.
#define TOKEN_PERANGKAT "dev-token-inc-001"

// Fallback HTTP ke Laravel lokal. MQTT tetap jalur utama.
// Catatan: sertifikat iot-komdis-2.test diterbitkan mkcert, sedangkan CA di
// Incubator.ino adalah CA broker. Verifikasi TLS untuk HTTP akan gagal sampai
// CA web ikut ditanam. Biarkan http selama fallback belum dipakai.
// #define URL_HTTP "https://iot-komdis-2.test"
