#include <Arduino.h>

#include "app.h"

namespace {

TaskHandle_t gControlTask = nullptr;
TaskHandle_t gNetworkTask = nullptr;

void controlTask(void *parameter) {
  (void)parameter;
  for (;;) {
    app::controlLoop();
    delay(1);
  }
}

void networkTask(void *parameter) {
  (void)parameter;
  for (;;) {
    app::networkLoop();
    delay(1);
  }
}

}  // namespace

void setup() {
  app::setup();
  // Core 1 owns the real-time path; Core 0 owns every blocking network call, so
  // a TLS handshake can never delay a heater decision.
  xTaskCreatePinnedToCore(controlTask, "control", 4096, nullptr, 2, &gControlTask, 1);
  xTaskCreatePinnedToCore(networkTask, "network", 8192, nullptr, 1, &gNetworkTask, 0);
}

void loop() {
  // Both cores run their own tasks; this one only keeps the watchdog fed.
  delay(1000);
}
