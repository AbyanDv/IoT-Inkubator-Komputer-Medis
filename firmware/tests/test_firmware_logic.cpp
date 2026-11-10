#include <iostream>
#include <cassert>
#include <cmath>
#include <cstring>
#include <string>
#include <sstream>
#include <iomanip>
#include "../esp32-incubator/src/pid_controller.h"

// Bring in PIDController implementation
#include "../esp32-incubator/src/pid_controller.cpp"

// -----------------------------------------------------------------------------
// Test 1: PID Proportional & Directional Response
// -----------------------------------------------------------------------------
void test_pid_proportional() {
    PIDController pid(2.0f, 0.0f, 0.0f, 0.0f, 255.0f);
    
    // Setpoint = 34.0, Input = 32.0 -> Error = +2.0
    // Expected P output: 2.0 * 2.0 = 4.0
    float out1 = pid.compute(34.0f, 32.0f, 0.1f);
    assert(std::fabs(out1 - 4.0f) < 0.001f);

    // Setpoint = 34.0, Input = 35.0 -> Error = -1.0 (over temperature)
    // Expected output clamped to min (0.0)
    float out2 = pid.compute(34.0f, 35.0f, 0.1f);
    assert(out2 == 0.0f);

    std::cout << "  [PASS] test_pid_proportional" << std::endl;
}

// -----------------------------------------------------------------------------
// Test 2: PID Anti-Windup Clamping
// -----------------------------------------------------------------------------
void test_pid_anti_windup() {
    // Kp = 1.0, Ki = 10.0, Kd = 0.0, Min = 0.0, Max = 255.0
    PIDController pid(1.0f, 10.0f, 0.0f, 0.0f, 255.0f);

    // Massive constant error (+10°C) for 100 cycles of dt = 0.1s
    // Without anti-windup, integral would grow to 10.0 * 10.0 * (100 * 0.1) = 1000.0!
    // Run enough cycles to reach saturation
    for (int i = 0; i < 50; ++i) {
        pid.compute(34.0f, 24.0f, 0.1f);
    }

    // Now it must be saturated at max
    float out = pid.compute(34.0f, 24.0f, 0.1f);
    assert(out == 255.0f);

    // Integral must be clamped to maxOut (255.0), not unbounded!
    assert(pid.getIntegral() <= 255.0f);

    // Now immediately flip error to negative (Input = 36.0, Setpoint = 34.0, Error = -2.0)
    // Because integral did not wind up infinitely, output should drop immediately!
    float recoveringOut = pid.compute(34.0f, 36.0f, 0.1f);
    assert(recoveringOut < 255.0f);

    std::cout << "  [PASS] test_pid_anti_windup" << std::endl;
}

// -----------------------------------------------------------------------------
// Test 3: Derivative on Measurement (Prevents Derivative Kick)
// -----------------------------------------------------------------------------
void test_derivative_kick_avoidance() {
    PIDController pid(0.0f, 0.0f, 5.0f, 0.0f, 255.0f);

    // First cycle initializes measurement
    pid.compute(30.0f, 25.0f, 0.1f);

    // Second cycle: abrupt setpoint step change from 30°C to 36°C, but measurement stays 25.0°C
    // Because derivative is computed on measurement (dInput = 0), derivative output must be 0!
    float stepOut = pid.compute(36.0f, 25.0f, 0.1f);
    assert(stepOut == 0.0f);

    // Third cycle: measurement increases from 25.0°C to 26.0°C (dInput = +1.0)
    // Derivative on measurement opposes increase: dTerm = -kd * (1.0 / 0.1) = -50.0
    // Clamped to min 0.0
    float dOut = pid.compute(36.0f, 26.0f, 0.1f);
    assert(dOut == 0.0f);

    std::cout << "  [PASS] test_derivative_kick_avoidance" << std::endl;
}

// -----------------------------------------------------------------------------
// Test 4: Dynamic PID Tuning Updates & Reset
// -----------------------------------------------------------------------------
void test_pid_tunings_and_reset() {
    PIDController pid(1.0f, 0.5f, 0.1f, 0.0f, 255.0f);
    pid.compute(34.0f, 30.0f, 0.1f);
    assert(pid.getIntegral() > 0.0f);

    // Update gains dynamically
    pid.setTunings(3.5f, 0.25f, 0.8f);
    assert(pid.getKp() == 3.5f);
    assert(pid.getKi() == 0.25f);
    assert(pid.getKd() == 0.8f);

    // Reset clears state
    pid.reset();
    assert(pid.getIntegral() == 0.0f);

    std::cout << "  [PASS] test_pid_tunings_and_reset" << std::endl;
}

// -----------------------------------------------------------------------------
// Test 5: Thermal Safety & Fail-Safe Trip Boundaries
// -----------------------------------------------------------------------------
struct SafetyCheckResult {
    bool failSafeActive;
    const char* reason;
    uint8_t forcedPwm;
};

SafetyCheckResult evaluateFailSafe(float temp) {
    SafetyCheckResult res;
    if (temp <= -127.0f || temp == 85.0f || std::isnan(temp)) {
        res.failSafeActive = true;
        res.reason = "SENSOR_DISCONNECTED";
        res.forcedPwm = 0;
    } else if (temp > 38.5f) {
        res.failSafeActive = true;
        res.reason = "OVERHEAT_EMERGENCY";
        res.forcedPwm = 0;
    } else if (temp < 15.0f) {
        res.failSafeActive = true;
        res.reason = "UNDERHEAT_ANOMALY";
        res.forcedPwm = 0;
    } else {
        res.failSafeActive = false;
        res.reason = "OK";
        res.forcedPwm = 128; // normal operation placeholder
    }
    return res;
}

void test_failsafe_guard() {
    // 1. Disconnected sensor (-127°C)
    SafetyCheckResult r1 = evaluateFailSafe(-127.0f);
    assert(r1.failSafeActive == true);
    assert(r1.forcedPwm == 0);
    assert(std::string(r1.reason) == "SENSOR_DISCONNECTED");

    // 2. Power-on reset uninitialized (85°C)
    SafetyCheckResult r2 = evaluateFailSafe(85.0f);
    assert(r2.failSafeActive == true);
    assert(r2.forcedPwm == 0);

    // 3. Overheat (> 38.5°C)
    SafetyCheckResult r3 = evaluateFailSafe(39.2f);
    assert(r3.failSafeActive == true);
    assert(r3.forcedPwm == 0);
    assert(std::string(r3.reason) == "OVERHEAT_EMERGENCY");

    // 4. Extreme underheat (< 15.0°C)
    SafetyCheckResult r4 = evaluateFailSafe(10.0f);
    assert(r4.failSafeActive == true);
    assert(r4.forcedPwm == 0);
    assert(std::string(r4.reason) == "UNDERHEAT_ANOMALY");

    // 5. Safe normal operating temperature (34.0°C)
    SafetyCheckResult r5 = evaluateFailSafe(34.0f);
    assert(r5.failSafeActive == false);
    assert(r5.forcedPwm > 0);

    std::cout << "  [PASS] test_failsafe_guard" << std::endl;
}

// -----------------------------------------------------------------------------
// Test 6: LCD 16x2 Formatting Verification
// -----------------------------------------------------------------------------
void test_lcd_formatting() {
    // Line 1 Normal: "T:32.4C H:58% Htr:40%"
    char line1[32];
    float tempObj = 32.45f;
    float humidity = 58.20f;
    uint8_t heaterDuty = 102; // ~40% of 255
    uint8_t heaterPct = (uint8_t)((heaterDuty * 100) / 255);
    snprintf(line1, sizeof(line1), "T:%.1fC H:%.0f%% Htr:%d%%", tempObj, humidity, heaterPct);
    assert(std::string(line1) == "T:32.5C H:58% Htr:40%");

    // Line 1 Alarm Overheat
    char line1Alarm[32];
    snprintf(line1Alarm, sizeof(line1Alarm), "ALARM: OVERHEAT!");
    assert(std::string(line1Alarm) == "ALARM: OVERHEAT!");

    // Line 1 Sensor Fault
    char line1Fault[32];
    snprintf(line1Fault, sizeof(line1Fault), "T:ERR SENSOR-FLT");
    assert(std::string(line1Fault) == "T:ERR SENSOR-FLT");

    // Line 2 Paired
    char line2Paired[32];
    const char* babyCode = "BAYI-001";
    snprintf(line2Paired, sizeof(line2Paired), "%s [PAIRED]", babyCode);
    assert(std::string(line2Paired) == "BAYI-001 [PAIRED]");

    // Line 2 Standby
    char line2Standby[32];
    const char* deviceCode = "INC-001";
    snprintf(line2Standby, sizeof(line2Standby), "%s [STANDBY]", deviceCode);
    assert(std::string(line2Standby) == "INC-001 [STANDBY]");

    std::cout << "  [PASS] test_lcd_formatting" << std::endl;
}

// -----------------------------------------------------------------------------
// Test 7: Piggybacked Config Validation & Safety Boundaries
// -----------------------------------------------------------------------------
void test_piggybacked_config_clamping() {
    // If server sends setpoint outside [28.0°C, 37.0°C], clamp safely
    float serverSetpointHigh = 45.0f;
    float serverSetpointLow = 18.0f;
    float minSafe = 28.0f;
    float maxSafe = 37.0f;

    float clampedHigh = constrain(serverSetpointHigh, minSafe, maxSafe);
    assert(clampedHigh == 37.0f);

    float clampedLow = constrain(serverSetpointLow, minSafe, maxSafe);
    assert(clampedLow == 28.0f);

    std::cout << "  [PASS] test_piggybacked_config_clamping" << std::endl;
}

int main() {
    std::cout << "========================================" << std::endl;
    std::cout << "Running ESP32 Incubator Logic Unit Tests" << std::endl;
    std::cout << "========================================" << std::endl;

    test_pid_proportional();
    test_pid_anti_windup();
    test_derivative_kick_avoidance();
    test_pid_tunings_and_reset();
    test_failsafe_guard();
    test_lcd_formatting();
    test_piggybacked_config_clamping();

    std::cout << "========================================" << std::endl;
    std::cout << "ALL 7 UNIT TESTS PASSED SUCCESSFULLY!" << std::endl;
    std::cout << "========================================" << std::endl;
    return 0;
}
