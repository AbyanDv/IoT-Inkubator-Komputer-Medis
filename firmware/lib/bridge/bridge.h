#pragma once
#include <Arduino.h>
#include <freertos/FreeRTOS.h>
#include <freertos/queue.h>

#include "types.h"

// The only channel between the two cores. Everything crossing it is a value
// copy of a plain struct, so no lock is needed and no pointer can dangle.
namespace bridge {

void begin();

void publishReading(const model::Reading &reading);
bool takeReading(model::Reading &out, uint32_t waitMs);

// Network core -> control core. One slot: the newest command wins.
void publishCommand(const model::Command &command);
bool takeCommand(model::Command &out);

void publishEvent(model::EventKind kind);
bool takeEvent(model::EventKind &out);

}  // namespace bridge
