#pragma once

#include <stdint.h>
#include <stddef.h>
#include <stdbool.h>
#include <string.h>
#include <math.h>
#include <stdio.h>

#if __has_include(<Arduino.h>)
#include <Arduino.h>
#else

// ==========================================
// Host-side Clangd / LSP Language Stubs
// Used only when toolchain is not active
// ==========================================

#define HIGH 1
#define LOW 0
#define INPUT 0
#define OUTPUT 1
#define DHT22 22
#define WL_CONNECTED 3
#define HTTP_CODE_OK 200
#define HTTP_CODE_CREATED 201
#define pdTRUE 1
#define pdFALSE 0
#define pdPASS 1
#define portMAX_DELAY 0xFFFFFFFF

typedef uint32_t TickType_t;
typedef void* QueueHandle_t;
typedef void* SemaphoreHandle_t;
typedef void* TaskHandle_t;
typedef int wl_status_t;
typedef void (*TaskFunction_t)(void*);

#define pdMS_TO_TICKS(ms) ((TickType_t)(ms))

inline unsigned long millis() { return 0; }
inline void delay(unsigned long) {}
inline void pinMode(uint8_t, uint8_t) {}
inline void digitalWrite(uint8_t, uint8_t) {}
inline void ledcSetup(uint8_t, uint32_t, uint8_t) {}
inline void ledcAttachPin(uint8_t, uint8_t) {}
inline void ledcWrite(uint8_t, uint32_t) {}

inline TickType_t xTaskGetTickCount() { return 0; }
inline void vTaskDelay(TickType_t) {}
inline void vTaskDelayUntil(TickType_t*, TickType_t) {}
inline void vTaskDelete(TaskHandle_t) {}
inline int BaseType_t_stub(void) { return 0; }

inline QueueHandle_t xQueueCreate(uint32_t, uint32_t) { return (QueueHandle_t)1; }
inline SemaphoreHandle_t xSemaphoreCreateMutex() { return (SemaphoreHandle_t)1; }
inline int xSemaphoreTake(SemaphoreHandle_t, TickType_t) { return 1; }
inline int xSemaphoreGive(SemaphoreHandle_t) { return 1; }
inline int xQueueSend(QueueHandle_t, const void*, TickType_t) { return 1; }
inline int xQueueReceive(QueueHandle_t, void*, TickType_t) { return 1; }
inline int xTaskCreatePinnedToCore(TaskFunction_t, const char*, uint32_t, void*, uint32_t, TaskHandle_t*, int) { return 1; }

class HardwareSerialStub {
public:
    void begin(unsigned long) {}
    void println(const char*) {}
    void printf(const char*, ...) {}
};
extern HardwareSerialStub Serial;

class String {
public:
    String() {}
    String(const char*) {}
    String(float, int) {}
    const char* c_str() const { return ""; }
    size_t length() const { return 0; }
};

class OneWire {
public:
    OneWire(uint8_t) {}
};

class DallasTemperature {
public:
    DallasTemperature(OneWire*) {}
    void begin() {}
    void setWaitForConversion(bool) {}
    void setResolution(uint8_t) {}
    void requestTemperatures() {}
    float getTempCByIndex(int) { return 34.0f; }
};

class DHT {
public:
    DHT(uint8_t, uint8_t) {}
    void begin() {}
    float readHumidity() { return 55.0f; }
    float readTemperature() { return 28.0f; }
};

class TwoWireStub {
public:
    void begin(int, int) {}
    void beginTransmission(uint8_t) {}
    int endTransmission() { return 0; }
};
extern TwoWireStub Wire;

class LiquidCrystal_I2C {
public:
    LiquidCrystal_I2C(uint8_t, uint8_t, uint8_t) {}
    void init() {}
    void backlight() {}
    void clear() {}
    void setCursor(uint8_t, uint8_t) {}
    void print(const char*) {}
};

class WiFiStub {
public:
    void mode(int) {}
    void begin(const char*, const char*) {}
    void disconnect() {}
    wl_status_t status() { return WL_CONNECTED; }
};
extern WiFiStub WiFi;
#define WIFI_STA 1

class WiFiClient {};
class WiFiClientSecure : public WiFiClient {
public:
    void setInsecure() {}
};

class HTTPClient {
public:
    bool begin(WiFiClient&, const char*) { return true; }
    void setTimeout(uint32_t) {}
    void addHeader(const char*, const char*) {}
    int POST(const String&) { return 200; }
    String getString() { return "{}"; }
    void end() {}
};

inline void configTime(long, int, const char*, const char* = nullptr) {}

class JsonVariantStub {
public:
    template <typename T>
    bool is() const { return false; }
    template <typename T>
    T to() { return T(); }
    template <typename T>
    T operator|(T def) const { return def; }
    JsonVariantStub operator[](const char*) const { return JsonVariantStub(); }
    JsonVariantStub operator[](const char*) { return JsonVariantStub(); }
    template <typename T>
    JsonVariantStub& operator=(const T&) { return *this; }
    bool isNull() const { return true; }
};

class JsonObject : public JsonVariantStub {
public:
    JsonObject() {}
    JsonObject(const JsonVariantStub&) {}
};

class JsonDocument : public JsonVariantStub {
public:
    JsonDocument() {}
    JsonDocument(size_t) {}
};
typedef JsonDocument DynamicJsonDocument;

class DeserializationError {
public:
    operator bool() const { return false; }
    const char* c_str() const { return "Ok"; }
};

inline DeserializationError deserializeJson(JsonDocument&, const String&) { return DeserializationError(); }
inline size_t serializeJson(const JsonDocument&, String&) { return 0; }
inline String serialized(const String& s) { return s; }

#endif
