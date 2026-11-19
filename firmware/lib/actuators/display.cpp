#include "display.h"

#include <LiquidCrystal_I2C.h>
#include <Wire.h>

#include "config.h"

namespace {

LiquidCrystal_I2C gLcd(LCD_I2C_ADDR, 16, 2);
String gLine1;
String gLine2;

void writeLine(uint8_t row, const String &text) {
  gLcd.setCursor(0, row);
  // Pad to 16 so a shorter string erases what was there before.
  String padded = text;
  while (padded.length() < 16) {
    padded += ' ';
  }
  gLcd.print(padded.substring(0, 16));
}

}  // namespace

namespace display {

void begin() {
  Wire.begin(LCD_SDA_PIN, LCD_SCL_PIN);
  gLcd.init();
  gLcd.backlight();
  gLcd.clear();
}

void selfTest() {
  gLcd.clear();
  gLcd.setCursor(0, 0);
  gLcd.print("INCUBATOR");
  gLcd.setCursor(0, 1);
  gLcd.print(FW_VERSION);
}

void render(const String &line1, const String &line2) {
  // The panel sits on a slow I2C bus; a redraw per loop would spend more time
  // on the display than on the control loop.
  if (line1 == gLine1 && line2 == gLine2) {
    return;
  }
  gLine1 = line1;
  gLine2 = line2;
  writeLine(0, line1);
  writeLine(1, line2);
}

}  // namespace display
