#pragma once
#include <Arduino.h>

// DS18B20 + DHT22, both polled without blocking the control loop.
namespace sensors {

void begin();
void poll(uint32_t nowMs);

float objectC();
bool objectOk();
float roomC();
float humidity();
bool dhtReady();

// True once both sensors produced a sample, or after ~20 polls, so a missing
// sensor cannot starve the network task forever.
bool warm();

}  // namespace sensors
