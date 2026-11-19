#!/usr/bin/env bash
# Runs the PID/staging/fail-safe golden vectors on the host compiler.
# No ESP32, no PlatformIO, no Arduino libraries: control.cpp is dependency-free
# by design, which is what makes this check cheap enough to actually run.
set -euo pipefail
cd "$(dirname "$0")/.."
g++ -std=c++17 -Wall -Wextra -Werror \
  -I include -I lib/control \
  host_test/main.cpp lib/control/control.cpp \
  -o /tmp/incubator-control-test
/tmp/incubator-control-test
