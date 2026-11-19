#include "protocol.h"

#include <ArduinoJson.h>

#include "config.h"

namespace {

int8_t flag(JsonObject overrides, const char *key) {
  if (!overrides[key].is<bool>()) {
    return -1;
  }
  return overrides[key].as<bool>() ? 1 : 0;
}

}  // namespace

namespace protocol {

model::Command defaultCommand() {
  model::Command cmd{};
  cmd.setpoint = DEFAULT_SETPOINT;
  cmd.kp = 2.0f;
  cmd.ki = 0.1f;
  cmd.kd = 0.5f;
  cmd.blueLightEnabled = false;
  cmd.isPaired = false;
  cmd.babyCode[0] = '\0';
  cmd.overrideFan = -1;
  cmd.overrideHeater1 = -1;
  cmd.overrideHeater2 = -1;
  cmd.overrideBlueLight = -1;
  cmd.receivedAtMs = 0;
  cmd.expiresAtMs = 0;
  return cmd;
}

void apply(const char *body, model::Command &cmd) {
  StaticJsonDocument<512> doc;
  if (deserializeJson(doc, body) != DeserializationError::Ok) {
    return;
  }

  JsonObject config = doc["config"].as<JsonObject>();
  if (!config.isNull()) {
    cmd.setpoint = config["temperature_setpoint"] | cmd.setpoint;
    cmd.kp = config["pid_kp"] | cmd.kp;
    cmd.ki = config["pid_ki"] | cmd.ki;
    cmd.kd = config["pid_kd"] | cmd.kd;
    cmd.blueLightEnabled = config["blue_led_enabled"] | cmd.blueLightEnabled;
  }

  cmd.isPaired = doc["is_paired"] | cmd.isPaired;
  JsonObject baby = doc["baby"].as<JsonObject>();
  const char *code = baby.isNull() ? "" : baby["code"].as<const char *>();
  strncpy(cmd.babyCode, code == nullptr ? "" : code, sizeof(cmd.babyCode) - 1);
  cmd.babyCode[sizeof(cmd.babyCode) - 1] = '\0';

  // The server always sends the object, empty when no override is active, so
  // an empty object is a deliberate "back to auto".
  JsonObject overrides = doc["relay_overrides"].as<JsonObject>();
  if (!overrides.isNull()) {
    cmd.overrideFan = flag(overrides, "fan");
    cmd.overrideHeater1 = flag(overrides, "heater_1");
    cmd.overrideHeater2 = flag(overrides, "heater_2");
    cmd.overrideBlueLight = flag(overrides, "blue_light");
  }
}

String telemetryBody(const model::Reading &r) {
  StaticJsonDocument<384> doc;
  doc["device_code"] = DEVICE_CODE;
  // -127 is what the DS18B20 reports on a disconnected probe; the server stores
  // it verbatim and flags the fault from sensor_status.
  // Two decimals on the wire: the sensor resolution is 0.0625 C, and Laravel
  // stores a decimal(5,2).
  doc["temperature_object"] = r.dsOk ? roundf(r.objectC * 100.0f) / 100.0f : -127.0f;
  doc["temperature_room"] = roundf(r.roomC * 100.0f) / 100.0f;
  doc["humidity"] = roundf(r.humidity * 10.0f) / 10.0f;
  JsonObject relayJson = doc.createNestedObject("relays");
  relayJson["fan"] = r.fan;
  relayJson["heater_1"] = r.heater1;
  relayJson["heater_2"] = r.heater2;
  relayJson["blue_light"] = r.blueLight;
  JsonObject sensorJson = doc.createNestedObject("sensor_status");
  sensorJson["ds18b20"] = r.dsOk;
  sensorJson["dht22"] = r.dhtOk;
  // No timestamp: the field is nullable server-side and Carbon::now() is a
  // better clock than an ESP32 that has never seen an NTP reply.
  doc["uptime_s"] = r.uptimeSeconds;
  String body;
  serializeJson(doc, body);
  return body;
}

String statusBody(bool online) {
  StaticJsonDocument<64> doc;
  doc["online"] = online;
  String body;
  serializeJson(doc, body);
  return body;
}

}  // namespace protocol
