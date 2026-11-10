#pragma once

#include "config.h"

class ActuatorManager {
public:
    ActuatorManager();

    void begin();

    /**
     * Set heater MOSFET PWM duty cycle (0-255).
     * If failSafeDisengaged is true, heater PWM is forced to 0 regardless of parameter.
     */
    void setHeaterPWM(uint8_t duty, bool failSafeDisengaged = false);

    /**
     * Set fan PWM duty cycle (0-255).
     */
    void setFanPWM(uint8_t duty);

    /**
     * Set phototherapy blue simulation LED state.
     */
    void setBlueLED(bool on);

    /**
     * Immediate hardware trip: zeroes heater PWM.
     */
    void emergencyShutdown();

    uint8_t getHeaterPWM() const { return _heaterPWM; }
    uint8_t getFanPWM() const { return _fanPWM; }
    bool getBlueLEDStatus() const { return _blueLED; }

    uint8_t getHeaterPercentage() const { return (uint8_t)((_heaterPWM * 100) / 255); }
    uint8_t getFanPercentage() const { return (uint8_t)((_fanPWM * 100) / 255); }

private:
    uint8_t _heaterPWM;
    uint8_t _fanPWM;
    bool _blueLED;
    void writeHeaterLEDC(uint8_t duty);
    void writeFanLEDC(uint8_t duty);
};
