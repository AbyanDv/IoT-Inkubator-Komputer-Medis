#include "bridge.h"

namespace {

// One slot each. A network stall must never queue stale samples: the producer
// overwrites, the consumer takes whatever is current.
QueueHandle_t gReading = nullptr;
QueueHandle_t gCommand = nullptr;
QueueHandle_t gEvent = nullptr;

}  // namespace

namespace bridge {

void begin() {
  gReading = xQueueCreate(1, sizeof(model::Reading));
  gCommand = xQueueCreate(1, sizeof(model::Command));
  gEvent = xQueueCreate(4, sizeof(model::Event));
}

void publishReading(const model::Reading &reading) { xQueueOverwrite(gReading, &reading); }

bool takeReading(model::Reading &out, uint32_t waitMs) {
  return xQueueReceive(gReading, &out, pdMS_TO_TICKS(waitMs)) == pdTRUE;
}

void publishCommand(const model::Command &command) { xQueueOverwrite(gCommand, &command); }

bool takeCommand(model::Command &out) {
  return xQueueReceive(gCommand, &out, 0) == pdTRUE;
}

void publishEvent(model::EventKind kind) {
  const model::Event event{kind};
  // Events are rare; a full queue drops the newest rather than blocking the
  // control core.
  xQueueSend(gEvent, &event, 0);
}

bool takeEvent(model::EventKind &out) {
  model::Event event{};
  if (xQueueReceive(gEvent, &event, 0) != pdTRUE) {
    return false;
  }
  out = event.kind;
  return true;
}

}  // namespace bridge
