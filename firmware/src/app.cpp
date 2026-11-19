#include "app.h"

#include "bridge.h"
#include "config.h"
#include "control.h"
#include "display.h"
#include "relays.h"
#include "sensors.h"
#include "protocol.h"
#include "transport.h"
#include "types.h"

namespace {

// Owned by the control core. Core 0 never touches it directly: a new command
// crosses the queue, so there is no concurrent write to this struct.
model::Command gCommand{};

// Sticky until an operator re-arms: a momentary sensor blip must not let the
// heaters come back on their own.
bool gTripped = false;

// Relay overrides expire OVERRIDE_TTL_MS after the last successful sync, so a
// lost network returns the incubator to automatic control.
void expireOverrides(uint32_t nowMs) {
  if (gCommand.expiresAtMs == 0 || nowMs <= gCommand.expiresAtMs) {
    return;
  }
  gCommand.overrideFan = -1;
  gCommand.overrideHeater1 = -1;
  gCommand.overrideHeater2 = -1;
  gCommand.overrideBlueLight = -1;
  gCommand.expiresAtMs = 0;
}

void enterTrip(model::EventKind kind) {
  if (gTripped) {
    return;
  }
  gTripped = true;
  bridge::publishEvent(kind);
  relays::forceSafe();
}

String lcdLine1(const model::Reading &r) {
  char line[17] = {0};
  if (!r.dsOk) {
    snprintf(line, sizeof(line), "SENSOR ERROR");
  } else if (r.tripped) {
    snprintf(line, sizeof(line), "TRIP %5.1fC", r.objectC);
  } else {
    snprintf(line, sizeof(line), "OBJ%5.1fC RH%4.1f", r.objectC, r.humidity);
  }
  return String(line);
}

String lcdLine2(const model::Reading &r) {
  char line[17] = {0};
  if (r.tripped) {
    snprintf(line, sizeof(line), "FAIL-SAFE ON");
  } else if (r.wifiDown) {
    snprintf(line, sizeof(line), "NO NET PID OK");
  } else if (gCommand.isPaired) {
    snprintf(line, sizeof(line), "%-16.16s", gCommand.babyCode);
  } else {
    snprintf(line, sizeof(line), "%s STANDBY", DEVICE_CODE);
  }
  return String(line);
}

void applyActuators(model::Reading &reading, const control::State &pid) {
  if (gTripped) {
    // Fail-safe output: circulation on, no heat.
    reading.fan = true;
    reading.heater1 = false;
    reading.heater2 = false;
  } else {
    reading.fan = gCommand.overrideFan >= 0 ? gCommand.overrideFan > 0 : true;
    reading.heater1 = gCommand.overrideHeater1 >= 0 ? gCommand.overrideHeater1 > 0 : pid.heater1;
    reading.heater2 = gCommand.overrideHeater2 >= 0 ? gCommand.overrideHeater2 > 0 : pid.heater2;
  }
  reading.blueLight =
      gCommand.overrideBlueLight >= 0 ? gCommand.overrideBlueLight > 0 : gCommand.blueLightEnabled;
}

void drainEvents() {
  model::EventKind kind;
  while (bridge::takeEvent(kind)) {
    switch (kind) {
      case model::EventKind::SensorFault:
        transport::publishEvent("sensor_fault", "danger");
        break;
      case model::EventKind::Overheat:
        transport::publishEvent("overheat", "danger");
        break;
      case model::EventKind::NetworkLost:
        transport::publishEvent("network_lost", "warning");
        break;
      default:
        transport::publishEvent("device_boot", "info");
        break;
    }
  }
}

}  // namespace

namespace app {

void setup() {
  Serial.begin(SERIAL_BAUD);

  display::begin();
  display::selfTest();

  sensors::begin();
  relays::begin();
  bridge::begin();

  gCommand = protocol::defaultCommand();
  transport::begin();

  bridge::publishEvent(model::EventKind::DeviceBoot);
}

void controlLoop() {
  static control::State pid;
  static uint32_t lastStepMs = 0;
  static bool hadWifi = false;

  const uint32_t nowMs = millis();

  // A command arriving from either transport is applied before this sample, so
  // a setpoint change takes effect on the very next control step.
  model::Command incoming{};
  if (bridge::takeCommand(incoming)) {
    gCommand = incoming;
  }

  sensors::poll(nowMs);

  const uint32_t intervalMs = nowMs - lastStepMs;
  if (intervalMs < 100) {
    return;
  }
  const float dtSeconds = intervalMs / 1000.0f;
  lastStepMs = nowMs;

  if (!sensors::warm()) {
    return;
  }

  const float objectC = sensors::objectC();
  const bool objectValid = sensors::objectOk();

  // Fail-safe before anything else: it wins over PID and over every override.
  if (!objectValid) {
    enterTrip(model::EventKind::SensorFault);
  } else if (objectC > TRIP_HIGH_C || objectC < TRIP_LOW_C) {
    enterTrip(model::EventKind::Overheat);
  }

  expireOverrides(nowMs);

  if (!gTripped) {
    control::step(pid, objectC, objectValid, dtSeconds, gCommand);
  }

  model::Reading reading{};
  reading.objectC = objectC;
  reading.roomC = sensors::roomC();
  reading.humidity = sensors::humidity();
  reading.dsOk = objectValid;
  reading.dhtOk = sensors::dhtReady();
  reading.setpoint = gCommand.setpoint;
  reading.pidOutput = pid.pidOutputPct;
  reading.tripped = gTripped;
  reading.wifiDown = !transport::online();
  reading.uptimeSeconds = nowMs / 1000;
  applyActuators(reading, pid);

  relays::apply(reading);
  bridge::publishReading(reading);
  display::render(lcdLine1(reading), lcdLine2(reading));

  const bool hasWifi = transport::online();
  if (hadWifi && !hasWifi) {
    bridge::publishEvent(model::EventKind::NetworkLost);
  }
  hadWifi = hasWifi;
}

void networkLoop() {
  const uint32_t nowMs = millis();
  transport::maintain(nowMs);
  drainEvents();

  // The control core publishes a reading every 100 ms; the server wants one
  // every TELEMETRY_INTERVAL_MS. Take the newest and drop the rest, so a burst
  // after a stall still sends only one sample.
  static uint32_t lastPublishMs = 0;
  if (nowMs - lastPublishMs < TELEMETRY_INTERVAL_MS) {
    return;
  }
  lastPublishMs = nowMs;

  model::Reading reading{};
  if (!bridge::takeReading(reading, 0) || !transport::online()) {
    return;
  }

  // MQTT is the cheaper path (one open socket, no TLS handshake per request).
  // HTTP stays the fallback so config and pairing still arrive when the broker
  // is down.
  if (transport::mqttConnected()) {
    transport::publishTelemetry(reading);
    return;
  }

  transport::postTelemetry(nowMs, reading);
}

}  // namespace app
