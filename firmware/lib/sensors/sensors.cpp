#include "sensors.h"

#include <DallasTemperature.h>
#include <DHT.h>
#include <OneWire.h>

#include "config.h"

namespace {

OneWire gWire(DS18B20_PIN);
DallasTemperature gDs(&gWire);
DHT gDht(DHT22_PIN, DHT22);

bool gDsConversionPending = false;
uint32_t gDsConversionStartedMs = 0;
uint32_t gDhtRequestedMs = 0;
uint32_t gDhtRetryAfterMs = 0;

// Dallas conversion time per resolution setting: 9..12 bit.
constexpr uint16_t kConversionMs[] = {94, 94, 188, 375, 750};

float gObjectC = 0;
bool gObjectOk = false;
float gRoomC = 0;
float gHumidity = 0;
bool gDhtOk = false;
uint16_t gPolls = 0;

}  // namespace

namespace sensors {

void begin() {
  gDs.setWaitForConversion(false);
  gDs.begin();
  gDs.setResolution(DS18B20_RESOLUTION);
  gDht.begin();
}

void poll(uint32_t nowMs) {
  ++gPolls;

  // DS18B20: request and read on separate polls, so the 375 ms conversion
  // never blocks the control loop.
  if (!gDsConversionPending) {
    gDs.requestTemperatures();
    gDsConversionPending = true;
    gDsConversionStartedMs = nowMs;
  } else if (nowMs - gDsConversionStartedMs >= kConversionMs[DS18B20_RESOLUTION]) {
    gDsConversionPending = false;
    const float value = gDs.getTempCByIndex(0);
    // -127 is a disconnected probe, 85 is the power-on register value. Neither
    // is a temperature this incubator can legitimately see.
    gObjectOk = value > TRIP_LOW_C - 5.0f && value < 84.0f;
    if (gObjectOk) {
      gObjectC = value;
    }
  }

  // DHT22 needs 2 s between reads; a failed read backs off so a missing sensor
  // does not stall the loop on repeated timeouts.
  if (gDhtRetryAfterMs != 0) {
    if (nowMs < gDhtRetryAfterMs) {
      return;
    }
    gDhtRetryAfterMs = 0;
  }
  if (nowMs - gDhtRequestedMs < 2500) {
    return;
  }
  gDhtRequestedMs = nowMs;

  const float room = gDht.readTemperature();
  const float humidity = gDht.readHumidity();
  if (isnan(room) || isnan(humidity) || room < 10.0f || room > 45.0f || humidity < 5.0f ||
      humidity > 99.0f) {
    gDhtOk = false;
    gDhtRetryAfterMs = nowMs + 30000;
    return;
  }
  gRoomC = room;
  gHumidity = humidity;
  gDhtOk = true;
}

float objectC() { return gObjectC; }
bool objectOk() { return gObjectOk; }
float roomC() { return gRoomC; }
float humidity() { return gHumidity; }
bool dhtReady() { return gDhtOk; }

bool warm() {
  // Wait for a first valid sample of each sensor, but give up after ~10 s so a
  // missing DHT cannot keep the device silent forever.
  return (gObjectOk && gDhtOk) || gPolls > 2000;
}

}  // namespace sensors
