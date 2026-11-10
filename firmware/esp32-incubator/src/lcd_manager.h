#pragma once

#include "config.h"

#if __has_include(<Wire.h>)
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#endif

class LCDManager {
public:
    LCDManager();

    bool begin();

    /**
     * Update LCD display lines based on sensor measurements, actuator status, and pairing state.
     */
    void update(float tempObj, float humidity, uint8_t heaterDuty,
                bool failSafeActive, const char* failSafeReason,
                bool isPaired, const char* babyCode, const char* deviceCode);

    const char* getLine1() const { return _lastLine1; }
    const char* getLine2() const { return _lastLine2; }
    bool isConnected() const { return _lcdFound; }

private:
    LiquidCrystal_I2C _lcd;
    bool _lcdFound;
    char _lastLine1[32];
    char _lastLine2[32];

    void writeLine(uint8_t row, const char* text);
};
