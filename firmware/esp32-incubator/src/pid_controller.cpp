#include "pid_controller.h"

PIDController::PIDController(float kp, float ki, float kd, float minOut, float maxOut)
    : _kp(kp), _ki(ki), _kd(kd), _minOut(minOut), _maxOut(maxOut),
      _integral(0.0f), _lastInput(0.0f), _lastOutput(0.0f), _firstRun(true) {
}

void PIDController::setTunings(float kp, float ki, float kd) {
    if (kp < 0.0f) kp = 0.0f;
    if (ki < 0.0f) ki = 0.0f;
    if (kd < 0.0f) kd = 0.0f;
    _kp = kp;
    _ki = ki;
    _kd = kd;
}

void PIDController::setOutputLimits(float minOut, float maxOut) {
    if (minOut >= maxOut) return;
    _minOut = minOut;
    _maxOut = maxOut;
    if (_integral > _maxOut) _integral = _maxOut;
    else if (_integral < _minOut) _integral = _minOut;
}

void PIDController::reset() {
    _integral = 0.0f;
    _lastInput = 0.0f;
    _lastOutput = 0.0f;
    _firstRun = true;
}

float PIDController::compute(float setpoint, float input, float dt) {
    if (dt <= 0.0001f) {
        dt = 0.1f; // Fallback to 100ms default period
    }

    float error = setpoint - input;

    // 1. Proportional term
    float pTerm = _kp * error;

    // 2. Derivative term on measurement (negative derivative of input)
    // Avoids derivative kick when setpoint abruptly changes
    float dTerm = 0.0f;
    if (!_firstRun) {
        float dInput = (input - _lastInput);
        dTerm = -_kd * (dInput / dt);
    } else {
        _firstRun = false;
    }
    _lastInput = input;

    // 3. Integral term with Anti-Windup Clamping (Back-Calculation)
    _integral += (_ki * error * dt);

    // Initial boundary check on integral
    if (_integral > _maxOut) _integral = _maxOut;
    else if (_integral < _minOut) _integral = _minOut;

    // 4. Compute unconstrained output
    float output = pTerm + _integral + dTerm;

    // 5. Anti-Windup Clamping:
    // If output saturates, clamp integral to saturation limit so it can unwind immediately
    if (output > _maxOut) {
        if (error > 0.0f) {
            float maxAllowedIntegral = _maxOut - pTerm - dTerm;
            if (_integral > maxAllowedIntegral) {
                _integral = maxAllowedIntegral;
                if (_integral < _minOut) _integral = _minOut;
            }
        }
        output = _maxOut;
    } else if (output < _minOut) {
        if (error < 0.0f) {
            float minAllowedIntegral = _minOut - pTerm - dTerm;
            if (_integral < minAllowedIntegral) {
                _integral = minAllowedIntegral;
                if (_integral > _maxOut) _integral = _maxOut;
            }
        }
        output = _minOut;
    }

    _lastOutput = output;
    return output;
}
