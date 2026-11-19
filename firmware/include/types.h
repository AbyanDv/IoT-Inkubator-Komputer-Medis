#pragma once
#include <stdint.h>

// Plain-old-data structs only. Nothing here allocates, so the FreeRTOS queues
// copy by value and neither core ever holds a pointer into the other's state.
namespace model {

struct Reading {
  float objectC;
  float roomC;
  float humidity;
  bool dsOk;
  bool dhtOk;

  float setpoint;
  float pidOutput;

  bool fan;
  bool heater1;
  bool heater2;
  bool blueLight;

  bool tripped;
  bool wifiDown;
  uint32_t uptimeSeconds;
};

struct Command {
  float setpoint;
  float kp;
  float ki;
  float kd;
  bool blueLightEnabled;
  bool isPaired;
  char babyCode[16];
  // -1 means "no override": the control core decides. 0/1 forces the relay.
  int8_t overrideFan;
  int8_t overrideHeater1;
  int8_t overrideHeater2;
  int8_t overrideBlueLight;
  uint32_t receivedAtMs;
  uint32_t expiresAtMs;
};

enum class EventKind : uint8_t { SensorFault, Overheat, DeviceBoot, NetworkLost };

struct Event {
  EventKind kind;
};

}  // namespace model
