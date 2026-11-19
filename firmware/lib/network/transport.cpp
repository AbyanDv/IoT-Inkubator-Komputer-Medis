#include "transport.h"

#include <HTTPClient.h>
#include <PubSubClient.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>

#include "bridge.h"
#include "config.h"
#include "protocol.h"
#include "tls.h"

namespace {

WiFiClientSecure gTls;
HTTPClient gHttp;
PubSubClient gMqtt(gTls);

char gTopicSync[64] = "";
char gTopicTelemetry[64] = "";
char gTopicEvents[64] = "";
char gTopicStatus[64] = "";

uint32_t gBackoffUntilMs = 0;
uint16_t gBackoffMs = 0;
uint16_t gHttpPort = 0;
String gHttpHost;

// Last command successfully delivered by either transport. A partial payload
// is merged onto this, so a response that omits `relay_overrides` cannot
// silently drop an active override.
model::Command gLastCommand{};

void topic(char *out, size_t size, const char *suffix) {
  snprintf(out, size, "%s/%s/%s", MQTT_TOPIC_PREFIX, DEVICE_CODE, suffix);
}

// Splits HTTP_BASE_URL once at boot: per-request String work costs heap churn.
void parseBaseUrl() {
  const char *url = HTTP_BASE_URL;
  const char *authority = strstr(url, "://");
  gHttpHost = String(authority == nullptr ? url : authority + 3);
  const bool tls = authority != nullptr && strncmp(url, "https", 5) == 0;
  const int colon = gHttpHost.indexOf(':');
  gHttpPort = colon < 0 ? (tls ? 443 : 80) : static_cast<uint16_t>(gHttpHost.substring(colon + 1).toInt());
  if (colon >= 0) {
    gHttpHost.remove(colon);
  }
}

void accept(const char *body) {
  protocol::apply(body, gLastCommand);
  gLastCommand.receivedAtMs = millis();
  gLastCommand.expiresAtMs = gLastCommand.receivedAtMs + OVERRIDE_TTL_MS;
  bridge::publishCommand(gLastCommand);
}

void onSync(char *topicName, byte *payload, unsigned int length) {
  (void)topicName;
  // Retained syncs are small; a stack buffer keeps the callback off the heap.
  char body[512] = {0};
  const size_t copy = length < sizeof(body) - 1 ? length : sizeof(body) - 1;
  memcpy(body, payload, copy);
  accept(body);
}

void scheduleBackoff(uint32_t nowMs) {
  gBackoffMs = gBackoffMs == 0 ? 2000 : static_cast<uint16_t>(min<uint32_t>(gBackoffMs * 2U, NETWORK_BACKOFF_MAX_MS));
  gBackoffUntilMs = nowMs + gBackoffMs;
}

}  // namespace

namespace transport {

void begin() {
  topic(gTopicSync, sizeof(gTopicSync), "sync");
  topic(gTopicTelemetry, sizeof(gTopicTelemetry), "telemetry");
  topic(gTopicEvents, sizeof(gTopicEvents), "events");
  topic(gTopicStatus, sizeof(gTopicStatus), "status");

  parseBaseUrl();
  gLastCommand = protocol::defaultCommand();

  tls::configure(gTls);

  gHttp.setTimeout(4000);
  gHttp.setReuse(true);

  gMqtt.setServer(MQTT_HOST, MQTT_PORT);
  gMqtt.setBufferSize(1024);
  gMqtt.setKeepAlive(MQTT_KEEPALIVE_S);
  gMqtt.setCallback(onSync);

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
}

bool online() { return WiFi.status() == WL_CONNECTED; }

bool mqttConnected() { return gMqtt.connected(); }

void maintain(uint32_t nowMs) {
  static uint32_t lastReconnectMs = 0;
  if (!online()) {
    if (nowMs - lastReconnectMs > 2000) {
      lastReconnectMs = nowMs;
      WiFi.reconnect();
    }
    return;
  }

  if (!gMqtt.connected()) {
    // A last will makes the broker report the device offline even on a power
    // cut, and a retained status means the dashboard reads the truth at once.
    const bool ok = gMqtt.connect(DEVICE_CODE, MQTT_USERNAME, MQTT_PASSWORD, gTopicStatus, 1, true,
                                  protocol::statusBody(false).c_str());
    if (ok) {
      gMqtt.subscribe(gTopicSync, 1);
    }
    return;
  }
  gMqtt.loop();
}

void publishTelemetry(const model::Reading &reading) {
  gMqtt.publish(gTopicTelemetry, protocol::telemetryBody(reading).c_str(), false);
}

void publishEvent(const char *kind, const char *priority) {
  if (!gMqtt.connected()) {
    return;
  }
  char body[192];
  snprintf(body, sizeof(body),
           "{\"event_type\":\"%s\",\"priority\":\"%s\",\"message\":\"%s\","
           "\"payload\":{\"device_code\":\"%s\",\"firmware\":\"%s\"}}",
           kind, priority, kind, DEVICE_CODE, FW_VERSION);
  gMqtt.publish(gTopicEvents, body, false);
}

bool postTelemetry(uint32_t nowMs, const model::Reading &reading) {
  if (nowMs < gBackoffUntilMs) {
    return false;
  }
  if (!gHttp.begin(gTls, gHttpHost, gHttpPort, "/api/devices/telemetry", gHttpPort == 443)) {
    scheduleBackoff(nowMs);
    return false;
  }
  gHttp.addHeader("Content-Type", "application/json");
  gHttp.addHeader("X-Device-Token", DEVICE_TOKEN);

  const int status = gHttp.POST(protocol::telemetryBody(reading));
  // The body must be read before end(): the keep-alive socket is reused and an
  // unread body would be thrown away with the connection.
  const String payload = gHttp.getString();
  gHttp.end();

  if (status != HTTP_CODE_OK) {
    scheduleBackoff(nowMs);
    return false;
  }

  accept(payload.c_str());
  gBackoffMs = 0;
  return true;
}

}  // namespace transport
