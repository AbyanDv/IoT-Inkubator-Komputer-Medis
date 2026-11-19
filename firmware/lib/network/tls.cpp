#include "tls.h"

#include <LittleFS.h>

#include "config.h"

namespace {

constexpr const char *kCaPath = "/ca.crt";
// Long enough for the single-certificate chain this project ships; a CA bundle
// with several roots needs a bigger buffer or an external RAM allocation.
char gCaPem[2048] = {0};

}  // namespace

namespace tls {

void configure(WiFiClientSecure &client) {
  LittleFS.begin(true);
  File file = LittleFS.open(kCaPath, "r");
  if (!file) {
    // A missing certificate must not brick the device: fall back to an
    // unverified socket so telemetry still reaches the server, and say so.
    Serial.println("[tls] no CA at /ca.crt, skipping verification");
    client.setInsecure();
    return;
  }

  const size_t read = file.readBytes(gCaPem, sizeof(gCaPem) - 1);
  file.close();
  gCaPem[read] = '\0';

  if (TLS_VERIFY && read > 0) {
    client.setCACert(gCaPem);
  } else {
    client.setInsecure();
  }
}

}  // namespace tls
