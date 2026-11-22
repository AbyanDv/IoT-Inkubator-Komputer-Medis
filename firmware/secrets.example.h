#pragma once
// Salin file ini menjadi secrets.h di folder yang sama (tidak masuk git).
//
// Incubator.ino menyertakannya otomatis bila ada, jadi tidak perlu mengedit
// kode. Nilai di sini menang atas default di dalam .ino.

// Rahasia dari dashboard device.
#define PASSWORD_MQTT "isi-password-broker"
#define TOKEN_PERANGKAT "isi-device-token"

// Identitas per device, kalau berbeda dari default.
// #define KODE_PERANGKAT "INC-002"
// #define SSID_WIFI "lab-2.4g"
// #define PASSWORD_WIFI "rahasia-wifi"

// Alamat broker / server, kalau bukan default.
// #define HOST_MQTT "41.216.180.77"
// #define URL_HTTP "https://iot-komdis-2.test"

// Board relay active-low (pin LOW = relay nyala). Default .ino active-high.
// #define RELAY_AKTIF_RENDAH 1

// LCD dengan alamat I2C 0x3F.
// #define ALAMAT_LCD 0x3F
