#pragma once

#include <stdint.h>
#include <stddef.h>
#include "arduino_compat.h"

/**
 * =============================================================================
 * Smart Infant Incubator - ESP32 Firmware Configuration
 * 
 * Hardware: ESP32 DevKit V1
 * Real-Time Dual-Core FreeRTOS Architecture:
 *   - Core 1: Autonomous Real-Time Control Loop (PID, Sensors, PWM, LCD)
 *   - Core 0: Network & Telemetry (Wi-Fi, HTTP/HTTPS Client, Piggybacked Config)
 * =============================================================================
 */

// ==========================================
// 1. PIN DEFINITIONS (ESP32 DevKit V1)
// ==========================================
// DS18B20 OneWire Sensor (Infant body/object simulation temperature)
#define PIN_DS18B20              4   // OneWire Data pin (requires external 4.7kΩ pull-up to 3.3V)

// DHT22 Environmental Sensor (Chamber ambient temperature & relative humidity)
#define PIN_DHT22                16  // DHT22 Data pin (requires external 10kΩ pull-up to 3.3V)

// Actuators
#define PIN_HEATER_PWM           18  // Logic-Level N-Channel MOSFET Gate for Dummy Heater
#define PIN_FAN_PWM              19  // MOSFET Gate or PWM control for DC Circulation Fan
#define PIN_BLUE_LED             2   // Blue LED Indicator (Phototherapy simulation)

// I2C 16x2 Character LCD Display (PCFL8574 I2C backpack)
#define PIN_I2C_SDA              21  // ESP32 default hardware I2C SDA
#define PIN_I2C_SCL              22  // ESP32 default hardware I2C SCL
#define LCD_I2C_ADDR             0x27 // Default address (alternate: 0x3F)
#define LCD_COLS                 16
#define LCD_ROWS                 2

// ==========================================
// 2. PWM HARDWARE SETTINGS (LEDC)
// ==========================================
#define PWM_FREQ_HZ              5000 // 5 kHz PWM frequency (eliminates audible coil whine)
#define PWM_RESOLUTION_BITS      8    // 8-bit resolution (Duty Cycle: 0 to 255)
#define PWM_HEATER_CHANNEL       0    // LEDC Channel 0 for Heater MOSFET
#define PWM_FAN_CHANNEL          1    // LEDC Channel 1 for Fan MOSFET

// ==========================================
// 3. CLINICAL SAFETY & FAIL-SAFE LIMITS
// ==========================================
#define TEMP_SETPOINT_DEFAULT    34.0f // Default incubator target temperature (°C)
#define TEMP_SETPOINT_MIN        28.0f // Hard minimum allowable setpoint (°C)
#define TEMP_SETPOINT_MAX        37.0f // Hard maximum allowable setpoint (°C)

// Hardware Fail-Safe Trip Thresholds
#define TEMP_OVERHEAT_LIMIT      38.5f // Immediate trip: shut off heater PWM if object temp > 38.5°C
#define TEMP_UNDERHEAT_LIMIT     15.0f // Sensor anomaly trip: if object temp < 15.0°C

// DS18B20 Error Constants
#define DS18B20_DISCONNECTED_C   -127.0f // OneWire disconnection reading
#define DS18B20_POWER_ON_RESET   85.0f   // Dallas power-on reset state if read prematurely
#define DS18B20_TIMEOUT_MS       2500    // Maximum allowable sensor staleness before fail-safe trip

// ==========================================
// 4. PID CONTROLLER TUNING DEFAULTS
// ==========================================
#define DEFAULT_PID_KP           2.5f   // Proportional gain
#define DEFAULT_PID_KI           0.15f  // Integral gain
#define DEFAULT_PID_KD           0.6f   // Derivative gain
#define PID_OUT_MIN              0.0f   // Minimum PWM output
#define PID_OUT_MAX              255.0f // Maximum PWM output (8-bit)
#define PID_ANTI_WINDUP_MIN      0.0f
#define PID_ANTI_WINDUP_MAX      255.0f

// ==========================================
// 5. TIMING & LOOP INTERVALS
// ==========================================
#define CONTROL_LOOP_PERIOD_MS   100    // Core 1 real-time control tick (10 Hz)
#define DS18B20_CONV_DELAY_MS    200    // Non-blocking conversion wait time (10-bit: ~187.5ms)
#define DHT_SAMPLE_INTERVAL_MS   2000   // Minimum interval between DHT22 samples (2.0s)
#define LCD_REFRESH_INTERVAL_MS  500    // Display refresh rate (2 Hz)
#define DEFAULT_TELEMETRY_SEC    3      // Telemetry transmission interval (seconds)
#define WIFI_RECONNECT_MS        5000   // Wi-Fi reconnection check period
#define HTTP_REQUEST_TIMEOUT_MS  4000   // Maximum network client timeout (ms)

// ==========================================
// 6. DEFAULT DEVICE & NETWORK CONFIGURATION
// ==========================================
#define DEFAULT_DEVICE_CODE      "INC-001"
#define DEFAULT_WIFI_SSID        "Incubator-WiFi"
#define DEFAULT_WIFI_PASS        "password123"
#define DEFAULT_SERVER_URL       "http://192.168.1.100/api/devices/telemetry"
#define DEFAULT_DEVICE_TOKEN     "sec_dev_tok_93847298374"
#define FIRMWARE_VERSION         "1.0.0"

// ==========================================
// 7. FREERTOS DUAL-CORE CONFIGURATION
// ==========================================
#define TASK_CONTROL_CORE        1      // Core 1: Pro-CPU dedicated to real-time control
#define TASK_CONTROL_PRIORITY    5      // High priority
#define TASK_CONTROL_STACK_SIZE  8192

#define TASK_NETWORK_CORE        0      // Core 0: App-CPU dedicated to networking & TLS
#define TASK_NETWORK_PRIORITY    2      // Normal/Medium priority
#define TASK_NETWORK_STACK_SIZE  8192

#define TELEMETRY_QUEUE_LENGTH   5      // Maximum buffered telemetry snapshots
