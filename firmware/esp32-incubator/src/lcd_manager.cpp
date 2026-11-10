#include "lcd_manager.h"
#include <string.h>
#include <stdio.h>

LCDManager::LCDManager()
    : _lcd(LCD_I2C_ADDR, LCD_COLS, LCD_ROWS),
      _lcdFound(false) {
    _lastLine1[0] = '\0';
    _lastLine2[0] = '\0';
}

bool LCDManager::begin() {
    // 1. Initialize I2C bus with configured SDA and SCL pins
    Wire.begin(PIN_I2C_SDA, PIN_I2C_SCL);

    // 2. Scan I2C address to verify LCD module presence
    Wire.beginTransmission(LCD_I2C_ADDR);
    if (Wire.endTransmission() == 0) {
        _lcdFound = true;
    } else {
        // Probe secondary standard address 0x3F
        Wire.beginTransmission(0x3F);
        if (Wire.endTransmission() == 0) {
            _lcd = LiquidCrystal_I2C(0x3F, LCD_COLS, LCD_ROWS);
            _lcdFound = true;
        }
    }

    if (_lcdFound) {
        _lcd.init();
        _lcd.backlight();
        _lcd.clear();
        writeLine(0, "INCUBATOR BOOT");
        writeLine(1, "INITIALIZING...");
    }

    return _lcdFound;
}

void LCDManager::writeLine(uint8_t row, const char* text) {
    if (!_lcdFound || row >= LCD_ROWS) return;

    // Format fixed 16-character buffer padded with trailing spaces to prevent flicker
    char buffer[LCD_COLS + 1];
    memset(buffer, ' ', LCD_COLS);
    buffer[LCD_COLS] = '\0';

    size_t len = strlen(text);
    if (len > LCD_COLS) len = LCD_COLS;
    memcpy(buffer, text, len);

    _lcd.setCursor(0, row);
    _lcd.print(buffer);
}

void LCDManager::update(float tempObj, float humidity, uint8_t heaterDuty,
                        bool failSafeActive, const char* failSafeReason,
                        bool isPaired, const char* babyCode, const char* deviceCode) {
    uint8_t heaterPct = (uint8_t)((heaterDuty * 100) / 255);

    // -------------------------------------------------------------
    // LINE 1 FORMATTING
    // -------------------------------------------------------------
    if (failSafeActive) {
        if (failSafeReason && strstr(failSafeReason, "OVERHEAT")) {
            snprintf(_lastLine1, sizeof(_lastLine1), "ALARM: OVERHEAT!");
        } else {
            snprintf(_lastLine1, sizeof(_lastLine1), "T:ERR SENSOR-FLT");
        }
    } else {
        // Standard specification line: "T:32.4C H:58% Htr:40%"
        snprintf(_lastLine1, sizeof(_lastLine1), "T:%.1fC H:%.0f%% Htr:%d%%",
                 tempObj, humidity, heaterPct);
    }

    // -------------------------------------------------------------
    // LINE 2 FORMATTING
    // -------------------------------------------------------------
    if (isPaired) {
        const char* code = (babyCode && strlen(babyCode) > 0) ? babyCode : "BAYI-001";
        snprintf(_lastLine2, sizeof(_lastLine2), "%s [PAIRED]", code);
    } else {
        const char* dev = (deviceCode && strlen(deviceCode) > 0) ? deviceCode : DEFAULT_DEVICE_CODE;
        snprintf(_lastLine2, sizeof(_lastLine2), "%s [STANDBY]", dev);
    }

    // Render to physical LCD if present
    if (_lcdFound) {
        writeLine(0, _lastLine1);
        writeLine(1, _lastLine2);
    }
}
