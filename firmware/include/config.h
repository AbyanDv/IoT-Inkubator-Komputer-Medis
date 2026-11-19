#pragma once
// Firmware defaults. Every value can be overridden at build time with
// `-DKEY=value` in platformio.ini, so a fleet is provisioned per environment
// without touching this file.

// ---------------------------------------------------------------- identity ---
#ifndef DEVICE_CODE
#define DEVICE_CODE "INC-001"
#endif

#ifndef WIFI_SSID
#define WIFI_SSID "iot-komdis"
#endif

#ifndef WIFI_PASSWORD
#define WIFI_PASSWORD "iotkomdis123"
#endif

// MQTT username is the device code; the broker ACL grants only the
// incubator/<device_code>/* topics. See README for the broker ACL template.
#ifndef MQTT_HOST
#define MQTT_HOST "41.216.180.77"
#endif

#ifndef MQTT_PORT
#define MQTT_PORT 8883
#endif

#ifndef MQTT_USERNAME
#define MQTT_USERNAME DEVICE_CODE
#endif

#ifndef MQTT_PASSWORD
#define MQTT_PASSWORD ""
#endif

// TLS: 1 verifies the broker and API against MQTT_CA_PEM in ca_cert.h.
// 0 skips verification, which is the only way to talk to a broker whose chain
// you cannot pin yet. Keep 1 in production.
#ifndef TLS_VERIFY
#define TLS_VERIFY 1
#endif

#ifndef HTTP_BASE_URL
#define HTTP_BASE_URL "http://iot-komdis-2.test"
#endif

#ifndef DEVICE_TOKEN
#define DEVICE_TOKEN ""
#endif

// A fleet shares one build path; only the topic prefix changes between the
// local broker and production. Kept identical to config/mqtt-client.php.
//
// MQTT keepalive. The broker drops the device after 1.5x this without traffic;
// 30 s keeps a power-cycled device visible as offline within a minute.
#ifndef MQTT_KEEPALIVE_S
#define MQTT_KEEPALIVE_S 30
#endif

#ifndef MQTT_TOPIC_PREFIX
#define MQTT_TOPIC_PREFIX "incubator"
#endif

// ------------------------------------------------------------------- timing ---
#ifndef TELEMETRY_INTERVAL_MS
#define TELEMETRY_INTERVAL_MS 5000
#endif

// Relay test overrides survive only this long across a network blackout.
#ifndef OVERRIDE_TTL_MS
#define OVERRIDE_TTL_MS 60000
#endif

#ifndef NETWORK_BACKOFF_MAX_MS
#define NETWORK_BACKOFF_MAX_MS 30000
#endif

// ------------------------------------------------------------------- control ---
#ifndef DEFAULT_SETPOINT
#define DEFAULT_SETPOINT 33.5f
#endif

// Safety clamp on any setpoint that arrives from the network.
#ifndef SETPOINT_MIN
#define SETPOINT_MIN 28.0f
#endif
#ifndef SETPOINT_MAX
#define SETPOINT_MAX 37.0f
#endif

// Trip thresholds (object sensor). Above this both heaters cut out; below the
// low limit the environment is implausible and the trip fires as well.
#ifndef TRIP_HIGH_C
#define TRIP_HIGH_C 38.5f
#endif
#ifndef TRIP_LOW_C
#define TRIP_LOW_C 15.0f
#endif

// Full hysteresis on the PID output so staging never chatters at a boundary.
#ifndef HEATER_1_ON_PCT
#define HEATER_1_ON_PCT 5.0f
#endif
#ifndef HEATER_2_ON_PCT
#define HEATER_2_ON_PCT 50.0f
#endif
#ifndef HEATER_HYST_PCT
#define HEATER_HYST_PCT 3.0f
#endif

// Full-scale error, in K, that maps to 100% PID output. The server's gains are
// in K-per-K (kp=2.0 in the seeder), so without this the output would never
// reach the 50% heater-2 stage.
#ifndef PID_FULL_SCALE_C
#define PID_FULL_SCALE_C 5.0f
#endif

// Integral clamp, in K of accumulated error. Small on purpose: the heater can
// only bring the object to the setpoint, so a large integral overshoots.
#ifndef PID_I_MIN_C
#define PID_I_MIN_C -1.0f
#endif
#ifndef PID_I_MAX_C
#define PID_I_MAX_C 1.0f
#endif

// ---------------------------------------------------------------- sensors ---
#ifndef DS18B20_PIN
#define DS18B20_PIN 4
#endif
#ifndef DHT22_PIN
#define DHT22_PIN 5
#endif
#ifndef DS18B20_RESOLUTION
#define DS18B20_RESOLUTION 11
#endif

// ---------------------------------------------------------------- actuators ---
#ifndef RELAY_ACTIVE_LOW
#define RELAY_ACTIVE_LOW 1
#endif
#ifndef RELAY_FAN_PIN
#define RELAY_FAN_PIN 25
#endif
#ifndef RELAY_HEATER_1_PIN
#define RELAY_HEATER_1_PIN 26
#endif
#ifndef RELAY_HEATER_2_PIN
#define RELAY_HEATER_2_PIN 27
#endif
#ifndef RELAY_BLUE_LIGHT_PIN
#define RELAY_BLUE_LIGHT_PIN 14
#endif

#ifndef LCD_I2C_ADDR
#define LCD_I2C_ADDR 0x27
#endif
#ifndef LCD_SDA_PIN
#define LCD_SDA_PIN 21
#endif
#ifndef LCD_SCL_PIN
#define LCD_SCL_PIN 22
#endif

// ------------------------------------------------------------------- debug ---
#ifndef FW_VERSION
#define FW_VERSION "1.0.0"
#endif
#ifndef SERIAL_BAUD
#define SERIAL_BAUD 115200
#endif
