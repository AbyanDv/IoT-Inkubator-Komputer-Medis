#include "config.h"
#include "control.h"

#include <cstdio>

// Golden vectors for the PID + staging + fail-safe logic. This is the only
// place a heater decision is made, so it is the only place worth proving.
// Build and run: host_test/run.sh (plain g++, no ESP32 needed).
//
// Gains are the server defaults (kp=2.0 K/K, ki=0.1, kd=0.5) and
// PID_FULL_SCALE_C=5.0 C, so output% = (2.0 * error) * 20 = 40 * error.
namespace {

int gFailures = 0;

void expect(bool condition, const char *what) {
  if (!condition) {
    std::printf("FAIL %s\n", what);
    ++gFailures;
  }
}

void expectNear(float actual, float wanted, const char *what) {
  const float delta = actual - wanted;
  expect(delta < 0.05f && delta > -0.05f, what);
}

model::Command cmdWith(float setpoint) {
  model::Command cmd{};
  cmd.setpoint = setpoint;
  cmd.kp = 2.0f;
  cmd.ki = 0.1f;
  cmd.kd = 0.5f;
  cmd.blueLightEnabled = false;
  cmd.isPaired = false;
  cmd.overrideFan = -1;
  cmd.overrideHeater1 = -1;
  cmd.overrideHeater2 = -1;
  cmd.overrideBlueLight = -1;
  return cmd;
}

void testLargeErrorStagesBothHeaters() {
  control::State pid;
  control::reset(pid);
  control::step(pid, 30.0f, true, 0.0f, cmdWith(33.5f));
  expect(pid.heater1, "3.5K error turns heater 1 on");
  expect(pid.heater2, "3.5K error turns heater 2 on");
  expectNear(pid.pidOutputPct, 100.0f, "output saturates at full scale");
}

void testSensorFaultClearsHeaters() {
  control::State pid;
  control::reset(pid);
  control::step(pid, 30.0f, true, 1.0f, cmdWith(33.5f));
  control::step(pid, 30.0f, false, 1.0f, cmdWith(33.5f));
  expect(!pid.heater1 && !pid.heater2, "sensor fault clears both heaters");
  expectNear(pid.pidOutputPct, 0.0f, "sensor fault zeroes the output");
  expectNear(pid.integralC, 0.0f, "sensor fault resets the integral");
}

void testOverheatClearsHeaters() {
  control::State pid;
  control::reset(pid);
  control::step(pid, 39.0f, true, 1.0f, cmdWith(33.5f));
  expect(!pid.heater1 && !pid.heater2, "overheat clears both heaters");
}

void testImplausibleLowClearsHeaters() {
  control::State pid;
  control::reset(pid);
  control::step(pid, 10.0f, true, 1.0f, cmdWith(33.5f));
  expect(!pid.heater1 && !pid.heater2, "below TRIP_LOW_C clears both heaters");
}

void testHysteresis() {
  control::State pid;
  control::reset(pid);
  // error 0.25K -> 10%: above the 5% switch-on threshold of heater 1.
  control::step(pid, 30.0f, true, 0.0f, cmdWith(30.25f));
  expect(pid.heater1, "10% turns heater 1 on");
  expect(!pid.heater2, "10% keeps heater 2 off");
  // error 0.075K -> 3%: inside the band (5% - 3%), heater 1 holds.
  control::step(pid, 30.0f, true, 0.0f, cmdWith(30.075f));
  expect(pid.heater1, "3% holds heater 1 inside the hysteresis band");
  // error 0.025K -> 1%: below the band, heater 1 releases.
  control::step(pid, 30.0f, true, 0.0f, cmdWith(30.025f));
  expect(!pid.heater1, "1% releases heater 1");
}

void testSetpointClamp() {
  control::State pid;
  control::reset(pid);
  model::Command cmd = cmdWith(90.0f);
  control::step(pid, 40.0f, true, 0.0f, cmd);
  expect(!pid.heater1, "network setpoint clamped before control");
  expect(cmd.setpoint == 90.0f, "controller does not mutate the command");
}

void testIntegralAntiWindup() {
  control::State pid;
  control::reset(pid);
  for (int i = 0; i < 1000; ++i) {
    control::step(pid, 20.0f, true, 1.0f, cmdWith(33.5f));
  }
  expect(pid.pidOutputPct <= 100.0f, "output never exceeds full scale");
  expectNear(pid.integralC, PID_I_MAX_C, "integral stops at its clamp");
  control::step(pid, 34.0f, true, 1.0f, cmdWith(33.5f));
  expect(!pid.heater2, "no wind-up: heater 2 drops once above setpoint");
}

void testDerivativeDampsOvershoot() {
  control::State pid;
  control::reset(pid);
  model::Command cmd = cmdWith(33.5f);
  // Heating fast toward the setpoint: derivative must pull the output below
  // the proportional-only value.
  control::step(pid, 33.0f, true, 1.0f, cmd);
  const float rising = pid.pidOutputPct;
  control::reset(pid);
  control::step(pid, 33.0f, true, 1.0f, cmd);
  expect(rising <= pid.pidOutputPct + 0.01f, "derivative does not raise output on a fresh sample");
  control::step(pid, 33.3f, true, 1.0f, cmd);
  expect(pid.pidOutputPct < rising, "rising temperature lowers the output via kd");
}

}  // namespace

int main() {
  testLargeErrorStagesBothHeaters();
  testSensorFaultClearsHeaters();
  testOverheatClearsHeaters();
  testImplausibleLowClearsHeaters();
  testHysteresis();
  testSetpointClamp();
  testIntegralAntiWindup();
  testDerivativeDampsOvershoot();
  if (gFailures == 0) {
    std::printf("ALL PASS\n");
  }
  return gFailures == 0 ? 0 : 1;
}
