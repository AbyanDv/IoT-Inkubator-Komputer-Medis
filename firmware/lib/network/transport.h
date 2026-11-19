#pragma once
#include <Arduino.h>

#include "types.h"

// Core 0 side: one Wi-Fi station, two transports over it, one JSON contract.
//
// MQTT is primary (one socket, retained sync, events, last-will status). HTTP
// `POST /api/devices/telemetry` is the fallback the PRD requires, and it
// piggybacks the same sync payload in the response.
//
// Both call protocol::apply() and push the resulting command through the bridge, so
// the control core never knows which transport delivered a change.
namespace transport {

void begin();

// Wi-Fi reconnect plus MQTT keepalive. Everything blocking lives here.
void maintain(uint32_t nowMs);

bool online();
bool mqttConnected();

void publishTelemetry(const model::Reading &reading);
void publishEvent(const char *kind, const char *priority);

/**
 * POSTs the reading and applies the piggybacked sync payload.
 *
 * @return false when nothing was applied; the last command stays in force.
 */
bool postTelemetry(uint32_t nowMs, const model::Reading &reading);

}  // namespace transport
