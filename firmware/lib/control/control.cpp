#include "control.h"

#include <math.h>

#include "config.h"

namespace control {

namespace {

float clampf(float value, float low, float high) {
  if (value < low) {
    return low;
  }
  if (value > high) {
    return high;
  }
  return value;
}

// Hysteresis band: a stage switches off only once the output falls
// HEATER_HYST_PCT below its switch-on threshold, so a value sitting on the
// boundary cannot chatter the relay.
bool stage(float outputPct, float thresholdPct, bool current) {
  if (current) {
    return outputPct > thresholdPct - HEATER_HYST_PCT;
  }
  return outputPct >= thresholdPct;
}

void clear(State &state) {
  state.integralC = 0;
  state.pidOutputPct = 0;
  state.heater1 = false;
  state.heater2 = false;
}

}  // namespace

void reset(State &state) {
  clear(state);
  state.previousError = 0;
  state.hasPreviousError = false;
}

void step(State &state, float objectC, bool objectValid, float dtSeconds, const model::Command &cmd) {
  if (!objectValid) {
    // No trustworthy reading means no heat.
    clear(state);
    state.hasPreviousError = false;
    return;
  }

  // Both extremes are unsafe: too hot is an overheat, and a reading far below
  // any plausible incubator means the probe is lying, not that we should heat.
  if (objectC > TRIP_HIGH_C || objectC < TRIP_LOW_C) {
    clear(state);
    state.hasPreviousError = false;
    return;
  }

  const float setpoint = clampf(cmd.setpoint, SETPOINT_MIN, SETPOINT_MAX);
  const float error = setpoint - objectC;

  // The integral accumulates in kelvin, then the whole term is rescaled by
  // PID_FULL_SCALE_C so kp/ki/kd keep the meaning the server UI gives them.
  state.integralC = clampf(state.integralC + cmd.ki * error * dtSeconds, PID_I_MIN_C, PID_I_MAX_C);

  // The derivative is skipped on the first sample after a reset: dt is unknown
  // there and a fabricated slope would kick the heaters.
  float derivative = 0;
  if (state.hasPreviousError && dtSeconds > 0.0f) {
    derivative = (error - state.previousError) / dtSeconds;
  }
  state.previousError = error;
  state.hasPreviousError = true;

  const float output = clampf((cmd.kp * error + state.integralC + cmd.kd * derivative) *
                                  (100.0f / PID_FULL_SCALE_C),
                              0.0f, 100.0f);
  state.pidOutputPct = output;

  state.heater1 = stage(output, HEATER_1_ON_PCT, state.heater1);
  state.heater2 = stage(output, HEATER_2_ON_PCT, state.heater2);
}

}  // namespace control
