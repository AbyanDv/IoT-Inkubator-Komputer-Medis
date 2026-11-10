#pragma once

#include <stdint.h>

#ifndef constrain
#define constrain(amt, low, high) ((amt) < (low) ? (low) : ((amt) > (high) ? (high) : (amt)))
#endif

/**
 * Robust PID Controller for Incubator Heater Regulation
 * Features:
 *   - Proportional-Integral-Derivative algorithm
 *   - Anti-windup clamping via conditional integration (prevents saturation windup)
 *   - Derivative on measurement (eliminates setpoint derivative kick)
 *   - Dynamic tuning updates (Kp, Ki, Kd)
 *   - Fast reset on sensor disconnect or safety trip
 */
class PIDController {
public:
    PIDController(float kp = 2.5f, float ki = 0.15f, float kd = 0.6f, float minOut = 0.0f, float maxOut = 255.0f);

    void setTunings(float kp, float ki, float kd);
    void setOutputLimits(float minOut, float maxOut);
    void reset();

    /**
     * Compute PID output
     * @param setpoint Target temperature (°C)
     * @param input Current sensor measurement (°C)
     * @param dt Elapsed time in seconds since last computation
     * @return Output clamped between minOut and maxOut (PWM duty cycle)
     */
    float compute(float setpoint, float input, float dt);

    float getKp() const { return _kp; }
    float getKi() const { return _ki; }
    float getKd() const { return _kd; }
    float getIntegral() const { return _integral; }
    float getLastOutput() const { return _lastOutput; }

private:
    float _kp;
    float _ki;
    float _kd;
    float _minOut;
    float _maxOut;

    float _integral;
    float _lastInput;
    float _lastOutput;
    bool _firstRun;
};
