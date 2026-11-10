#include "telemetry_client.h"
#include <time.h>
#include <string.h>
#include <stdio.h>

TelemetryClient::TelemetryClient()
    : _lastWiFiCheckTime(0), _isConnecting(false) {
    strncpy(_ssid, DEFAULT_WIFI_SSID, sizeof(_ssid) - 1);
    strncpy(_password, DEFAULT_WIFI_PASS, sizeof(_password) - 1);
    strncpy(_serverUrl, DEFAULT_SERVER_URL, sizeof(_serverUrl) - 1);
    strncpy(_deviceToken, DEFAULT_DEVICE_TOKEN, sizeof(_deviceToken) - 1);
}

void TelemetryClient::begin(const char* ssid, const char* password, const char* serverUrl, const char* deviceToken) {
    if (ssid && strlen(ssid) > 0) strncpy(_ssid, ssid, sizeof(_ssid) - 1);
    if (password) strncpy(_password, password, sizeof(_password) - 1);
    if (serverUrl && strlen(serverUrl) > 0) strncpy(_serverUrl, serverUrl, sizeof(_serverUrl) - 1);
    if (deviceToken && strlen(deviceToken) > 0) strncpy(_deviceToken, deviceToken, sizeof(_deviceToken) - 1);

    WiFi.mode(WIFI_STA);
    WiFi.begin(_ssid, _password);
    _isConnecting = true;
    _lastWiFiCheckTime = millis();

    // Configure SNTP for valid timestamps if connected
    configTime(7 * 3600, 0, "pool.ntp.org", "time.google.com");
}

void TelemetryClient::maintainWiFi(unsigned long nowMs) {
    if (nowMs - _lastWiFiCheckTime < WIFI_RECONNECT_MS) {
        return;
    }
    _lastWiFiCheckTime = nowMs;

    wl_status_t status = WiFi.status();
    if (status != WL_CONNECTED) {
        if (!_isConnecting) {
            WiFi.disconnect();
            WiFi.begin(_ssid, _password);
            _isConnecting = true;
        }
    } else {
        _isConnecting = false;
    }
}

bool TelemetryClient::isWiFiConnected() const {
    return WiFi.status() == WL_CONNECTED;
}

void TelemetryClient::setServerUrl(const char* url) {
    if (url && strlen(url) > 0) {
        strncpy(_serverUrl, url, sizeof(_serverUrl) - 1);
    }
}

void TelemetryClient::setDeviceToken(const char* token) {
    if (token && strlen(token) > 0) {
        strncpy(_deviceToken, token, sizeof(_deviceToken) - 1);
    }
}

String TelemetryClient::serializeTelemetry(const TelemetrySnapshot& snapshot) {
#if ARDUINOJSON_VERSION_MAJOR >= 7
    JsonDocument doc;
#else
    DynamicJsonDocument doc(2048);
#endif

    doc["device_code"] = snapshot.device_code;
    doc["applied_config_version"] = snapshot.applied_config_version;
    doc["temperature_object"] = snapshot.temperature_object;
    doc["temperature_room"] = snapshot.temperature_room;
    doc["humidity"] = snapshot.humidity;
    doc["heater_pwm"] = snapshot.heater_pwm;
    doc["fan_pwm"] = snapshot.fan_pwm;
    doc["blue_led_status"] = snapshot.blue_led_status;

    JsonObject sensorStatus = doc["sensor_status"].to<JsonObject>();
    sensorStatus["ds18b20"] = snapshot.ds18b20_ok;
    sensorStatus["dht22"] = snapshot.dht22_ok;

    // Laravel validates timestamp as a date; omit it until SNTP supplies valid time.
    time_t now = time(nullptr);
    if (now > 1600000000) {
        char timeBuffer[36];
        struct tm timeinfo;
        gmtime_r(&now, &timeinfo);
        strftime(timeBuffer, sizeof(timeBuffer), "%Y-%m-%dT%H:%M:%SZ", &timeinfo);
        doc["timestamp"] = timeBuffer;
    }

    String jsonString;
    serializeJson(doc, jsonString);
    return jsonString;
}

bool TelemetryClient::parseTelemetryResponse(const String& jsonResponse, TelemetryResponse& outResponse) {
#if ARDUINOJSON_VERSION_MAJOR >= 7
    JsonDocument doc;
#else
    DynamicJsonDocument doc(4096);
#endif

    DeserializationError error = deserializeJson(doc, jsonResponse);
    if (error) {
        outResponse.success = false;
        snprintf(outResponse.errorMessage, sizeof(outResponse.errorMessage), "JSON_ERR: %s", error.c_str());
        return false;
    }

    outResponse.success = true;
    outResponse.is_paired = doc["is_paired"] | false;

    if (outResponse.is_paired && doc["baby"].is<JsonObject>()) {
        const char* bCode = doc["baby"]["code"] | "";
        const char* bName = doc["baby"]["name"] | "";
        strncpy(outResponse.baby_code, bCode, sizeof(outResponse.baby_code) - 1);
        strncpy(outResponse.baby_name, bName, sizeof(outResponse.baby_name) - 1);
    } else {
        outResponse.baby_code[0] = '\0';
        outResponse.baby_name[0] = '\0';
    }

    outResponse.config_update.has_update = doc["has_config_update"] | false;
    outResponse.config_update.server_config_version = doc["server_config_version"] | 0;

    if (outResponse.config_update.has_update && doc["config"].is<JsonObject>()) {
        JsonObject cfg = doc["config"];
        outResponse.config_update.server_config_version = cfg["version"] | outResponse.config_update.server_config_version;
        
        float sp = cfg["temperature_setpoint"] | TEMP_SETPOINT_DEFAULT;
        if (sp < TEMP_SETPOINT_MIN) sp = TEMP_SETPOINT_MIN;
        if (sp > TEMP_SETPOINT_MAX) sp = TEMP_SETPOINT_MAX;
        outResponse.config_update.temperature_setpoint = sp;

        outResponse.config_update.pid_kp = cfg["pid_kp"] | DEFAULT_PID_KP;
        outResponse.config_update.pid_ki = cfg["pid_ki"] | DEFAULT_PID_KI;
        outResponse.config_update.pid_kd = cfg["pid_kd"] | DEFAULT_PID_KD;

        const char* mode = cfg["fan_mode"] | "auto";
        if (strcmp(mode, "auto") != 0 && strcmp(mode, "pwm") != 0) {
            mode = "auto";
        }
        strncpy(outResponse.config_update.fan_mode, mode, sizeof(outResponse.config_update.fan_mode) - 1);

        uint16_t fanPwm = cfg["fan_pwm_manual"] | 0;
        outResponse.config_update.fan_pwm_manual = fanPwm > 255 ? 255 : (uint8_t)fanPwm;
        outResponse.config_update.blue_led_enabled = cfg["blue_led_enabled"] | false;
        uint32_t telemetryInterval = cfg["telemetry_interval_sec"] | DEFAULT_TELEMETRY_SEC;
        if (telemetryInterval < 3) telemetryInterval = 3;
        if (telemetryInterval > 5) telemetryInterval = 5;
        outResponse.config_update.telemetry_interval_sec = telemetryInterval;
    }

    return true;
}

TelemetryResponse TelemetryClient::sendTelemetry(const TelemetrySnapshot& snapshot) {
    TelemetryResponse response;
    memset(&response, 0, sizeof(response));

    if (!isWiFiConnected()) {
        response.success = false;
        response.httpCode = -1;
        strncpy(response.errorMessage, "WIFI_NOT_CONNECTED", sizeof(response.errorMessage) - 1);
        return response;
    }

    HTTPClient http;
    bool isHttps = (strncmp(_serverUrl, "https://", 8) == 0);

    bool beginOk = false;
    WiFiClient plainClient;
    WiFiClientSecure secureClient;

    if (isHttps) {
        // TLS certificate validation requires a provisioned CA; fail closed until configured.
        response.success = false;
        response.httpCode = -3;
        strncpy(response.errorMessage, "TLS_CA_NOT_CONFIGURED", sizeof(response.errorMessage) - 1);
        return response;
    } else {
        beginOk = http.begin(plainClient, _serverUrl);
    }

    if (!beginOk) {
        response.success = false;
        response.httpCode = -2;
        strncpy(response.errorMessage, "HTTP_BEGIN_FAILED", sizeof(response.errorMessage) - 1);
        return response;
    }

    http.setTimeout(HTTP_REQUEST_TIMEOUT_MS);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-Device-Token", _deviceToken);

    String payload = serializeTelemetry(snapshot);
    int httpCode = http.POST(payload);
    response.httpCode = httpCode;

    if (httpCode == HTTP_CODE_OK || httpCode == HTTP_CODE_CREATED) {
        String responseBody = http.getString();
        parseTelemetryResponse(responseBody, response);
    } else {
        response.success = false;
        snprintf(response.errorMessage, sizeof(response.errorMessage), "HTTP_%d", httpCode);
    }

    http.end();
    return response;
}
