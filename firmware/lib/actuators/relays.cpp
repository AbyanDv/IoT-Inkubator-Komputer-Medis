#include "relays.h"

#include <Arduino.h>

#include "config.h"

namespace {

struct Pin {
  uint8_t pin;
  bool on;
};

// Exclusive actions only, in one table: adding a relay means one row, and
// forceSafe() cannot forget one.
Pin gRelays[] = {
    {RELAY_FAN_PIN, false},
    {RELAY_HEATER_1_PIN, false},
    {RELAY_HEATER_2_PIN, false},
    {RELAY_BLUE_LIGHT_PIN, false},
};

// Active-low boards pull the coil when the pin is LOW. Driving the inactive
// level before pinMode is what stops the power-on click.
void write(uint8_t pin, bool on) {
  digitalWrite(pin, (RELAY_ACTIVE_LOW ? !on : on) ? HIGH : LOW);
}

}  // namespace

namespace relays {

void begin() {
  for (Pin &relay : gRelays) {
    write(relay.pin, false);
    pinMode(relay.pin, OUTPUT);
    write(relay.pin, false);
  }
}

void apply(const model::Reading &r) {
  const bool states[] = {r.fan, r.heater1, r.heater2, r.blueLight};
  for (size_t i = 0; i < sizeof(gRelays) / sizeof(gRelays[0]); ++i) {
    write(gRelays[i].pin, states[i]);
  }
}

void forceSafe() {
  // Fan on, heaters off: the fan row is the exception because circulation is
  // the safe state for a sealed incubator.
  write(RELAY_FAN_PIN, true);
  write(RELAY_HEATER_1_PIN, false);
  write(RELAY_HEATER_2_PIN, false);
}

}  // namespace relays
