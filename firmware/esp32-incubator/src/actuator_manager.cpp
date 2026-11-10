#include "actuator_manager.h"
#include <string.h>

#ifdef ESP_ARDUINO_VERSION_MAJOR
#if ESP_ARDUINO_VERSION >= ESP_ARDUINO_VERSION_VAL(3, 0, 0)
#define USE_ESP32_CORE_V3 1
#endif
#endif

ActuatorManager::ActuatorManager()
    : _heaterPWM(0), _fanPWM(0), _blueLED(false) {
}

void ActuatorManager::begin() {
    // 1. Configure Blue LED GPIO
    pinMode(PIN_BLUE_LED, OUTPUT);
    digitalWrite(PIN_BLUE_LED, LOW);

    // 2. Configure PWM for Heater and Fan via ESP32 LEDC peripheral
#ifdef USE_ESP32_CORE_V3
    ledcAttachChannel(PIN_HEATER_PWM, PWM_FREQ_HZ, PWM_RESOLUTION_BITS, PWM_HEATER_CHANNEL);
    ledcAttachChannel(PIN_FAN_PWM, PWM_FREQ_HZ, PWM_RESOLUTION_BITS, PWM_FAN_CHANNEL);
#else
    ledcSetup(PWM_HEATER_CHANNEL, PWM_FREQ_HZ, PWM_RESOLUTION_BITS);
    ledcAttachPin(PIN_HEATER_PWM, PWM_HEATER_CHANNEL);

    ledcSetup(PWM_FAN_CHANNEL, PWM_FREQ_HZ, PWM_RESOLUTION_BITS);
    ledcAttachPin(PIN_FAN_PWM, PWM_FAN_CHANNEL);
#endif

    // Ensure actuators start completely disengaged
    writeHeaterLEDC(0);
    writeFanLEDC(0);
}

void ActuatorManager::writeHeaterLEDC(uint8_t duty) {
#ifdef USE_ESP32_CORE_V3
    ledcWrite(PIN_HEATER_PWM, duty);
#else
    ledcWrite(PWM_HEATER_CHANNEL, duty);
#endif
}

void ActuatorManager::writeFanLEDC(uint8_t duty) {
#ifdef USE_ESP32_CORE_V3
    ledcWrite(PIN_FAN_PWM, duty);
#else
    ledcWrite(PWM_FAN_CHANNEL, duty);
#endif
}

void ActuatorManager::setHeaterPWM(uint8_t duty, bool failSafeDisengaged) {
    if (failSafeDisengaged) {
        _heaterPWM = 0;
    } else {
        _heaterPWM = duty;
    }
    writeHeaterLEDC(_heaterPWM);
}

void ActuatorManager::setFanPWM(uint8_t duty) {
    _fanPWM = duty;
    writeFanLEDC(_fanPWM);
}

void ActuatorManager::setBlueLED(bool on) {
    _blueLED = on;
    digitalWrite(PIN_BLUE_LED, on ? HIGH : LOW);
}

void ActuatorManager::emergencyShutdown() {
    _heaterPWM = 0;
    writeHeaterLEDC(0);
}
