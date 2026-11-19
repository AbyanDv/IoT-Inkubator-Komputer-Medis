#pragma once
#include <Arduino.h>

#include "types.h"

// The wire contract with Laravel, in one place: both transports (HTTP response
// body and MQTT sync payload) parse the same JSON, and both build the same
// telemetry body. A server change that breaks this file fails host_test.
namespace protocol {

model::Command defaultCommand();

// Applies a piggybacked sync body onto `cmd`. Absent keys keep the value
// already in `cmd`, so a partial payload cannot reset the controller.
void apply(const char *body, model::Command &cmd);

// Telemetry body for POST /api/devices/telemetry and
// incubator/<device>/telemetry. Identical shape on both paths.
String telemetryBody(const model::Reading &reading);

// Status body for the retained incubator/<device>/status topic.
String statusBody(bool online);

}  // namespace protocol
