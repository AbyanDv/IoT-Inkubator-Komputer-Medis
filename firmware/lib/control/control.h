#pragma once
#include "types.h"

// Pure PID + relay staging. No Arduino calls, so it compiles on the host and
// is unit tested without hardware (see host_test/run.sh).
namespace control {

struct State {
  float integralC = 0;
  float previousError = 0;
  float pidOutputPct = 0;
  bool hasPreviousError = false;
  bool heater1 = false;
  bool heater2 = false;
};

void reset(State &state);

// dtSeconds must be the real elapsed control period: the caller measures it, so
// the integral and derivative cannot jump after a stall.
void step(State &state, float objectC, bool objectValid, float dtSeconds, const model::Command &cmd);

}  // namespace control
