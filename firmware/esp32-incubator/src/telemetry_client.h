#pragma once

#include "config.h"

#if __has_include(<WiFi.h>)
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#endif

struct TelemetrySnapshot {
    char device_code[16];
    uint32_t applied_config_version;
    float temperature_object;
    float temperature_room;
    float humidity;
    uint8_t heater_pwm;
    uint8_t fan_pwm;
    bool blue_led_status;
    bool ds18b20_ok;
    bool dht22_ok;
    char ds18b20_status[16];
    char dht22_status[16];
    uint32_t uptime_ms;
};

struct PiggybackedConfigUpdate {
    bool has_update;
    uint32_t server_config_version;
    float temperature_setpoint;
    float pid_kp;
    float pid_ki;
    float pid_kd;
    char fan_mode[16];
    uint8_t fan_pwm_manual;
    bool blue_led_enabled;
    uint32_t telemetry_interval_sec;
};

struct TelemetryResponse {
    bool success;
    int httpCode;
    bool is_paired;
    char baby_code[32];
    char baby_name[64];
    PiggybackedConfigUpdate config_update;
    char errorMessage[64];
};

class TelemetryClient {
public:
    TelemetryClient();

    void begin(const char* ssid = DEFAULT_WIFI_SSID,
               const char* password = DEFAULT_WIFI_PASS,
               const char* serverUrl = DEFAULT_SERVER_URL,
               const char* deviceToken = DEFAULT_DEVICE_TOKEN);

    /**
     * Check and maintain Wi-Fi connection without blocking.
     */
    void maintainWiFi(unsigned long nowMs);

    bool isWiFiConnected() const;

    /**
     * Serialize telemetry snapshot into JSON payload matching PRD v1.2 specification.
     */
    static String serializeTelemetry(const TelemetrySnapshot& snapshot);

    /**
     * Send telemetry POST request and parse piggybacked response.
     */
    TelemetryResponse sendTelemetry(const TelemetrySnapshot& snapshot);

    /**
     * Parse server response payload.
     */
    static bool parseTelemetryResponse(const String& jsonResponse, TelemetryResponse& outResponse);

    void setServerUrl(const char* url);
    void setDeviceToken(const char* token);

private:
    char _ssid[32];
    char _password[64];
    char _serverUrl[128];
    char _deviceToken[64];

    unsigned long _lastWiFiCheckTime;
    bool _isConnecting;
};
