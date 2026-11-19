#pragma once
#include <WiFiClientSecure.h>

// Feeds the CA bundle to a secure client.
//
// The certificate lives in LittleFS (`data/ca.crt`, uploaded once with
// `pio run -t uploadfs`), not in a compiled-in string: swapping brokers then
// costs an upload, not a recompile, and the PEM no longer sits in git history.
namespace tls {
void configure(WiFiClientSecure &client);
}  // namespace tls
