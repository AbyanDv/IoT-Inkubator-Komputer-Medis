#pragma once
#include <Arduino.h>

namespace app {
void setup();

// Core 1: sensors, PID, fail-safe, relays, LCD, queue publish. Never blocks.
void controlLoop();

// Core 0: Wi-Fi supervision, telemetry publish (MQTT, HTTP fallback), event
// drain. Every blocking call lives here.
void networkLoop();
}  // namespace app
