#pragma once
// Salin file ini menjadi secrets.h di folder yang sama (tidak masuk git).
//
// Incubator.ino menyertakannya otomatis bila ada, jadi tidak perlu mengedit
// kode. Nilai di sini menang atas default di dalam .ino.

// Rahasia dari dashboard device.
#define MQTT_PASSWORD "isi-password-broker"
#define DEVICE_TOKEN "isi-device-token"

// Identitas per device, kalau berbeda dari default.
// #define DEVICE_CODE "INC-002"
// #define WIFI_SSID "lab-2.4g"
// #define WIFI_PASSWORD "rahasia-wifi"

// Alamat broker / server, kalau bukan default.
// #define MQTT_HOST "41.216.180.77"
// #define HTTP_BASE_URL "https://iot-komdis-2.test"

// Board relay active-high: aktifkan ini.
// #define RELAY_ACTIVE_LOW 0

// LCD dengan alamat I2C 0x3F.
// #define LCD_I2C_ADDR 0x3F
