#pragma once
#include <Arduino.h>

namespace display {
void begin();
void selfTest();

// Two 16-character lines. Identical content is not redrawn, so calling this
// every control loop is free.
void render(const String &line1, const String &line2);
}  // namespace display
