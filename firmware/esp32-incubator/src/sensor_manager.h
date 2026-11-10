#pragma once

#include "config.h"

#if __has_include(<OneWire.h>)
#include <OneWire.h>
#include <DallasTemperature.h>
#include <DHT.h>
#endif

enum class DS18B20State {
    OK,
    DISCONNECTED,
    OVERHEAT,
    UNDERHEAT,
    READ_ERROR
};

enum class DHTState {
    OK,
    READ_ERROR
};

class SensorManager {
public:
    SensorManager();

    void begin();
    
    /**
     * Poll sensors in a non-blocking manner.
     * Must be called periodically from Core 1 real-time loop.
     * @return true if fail-safe is tripped (e.g. sensor disconnected or overheat)
     */
    bool update(unsigned long nowMs);

    float getTemperatureObject() const { return _tempObject; }
    float getTemperatureRoom() const { return _tempRoom; }
    float getHumidity() const { return _humidity; }

    bool isFailSafeActive() const { return _failSafeActive; }
    const char* getFailSafeReason() const { return _failSafeReason; }

    const char* getDS18B20StatusString() const;
    const char* getDHT22StatusString() const;

    bool isDS18B20Ok() const { return _ds18b20State == DS18B20State::OK; }
    bool isDHT22Ok() const { return _dhtState == DHTState::OK; }

private:
    OneWire _oneWire;
    DallasTemperature _sensors;
    DHT _dht;

    float _tempObject;
    float _tempRoom;
    float _humidity;

    DS18B20State _ds18b20State;
    DHTState _dhtState;

    bool _failSafeActive;
    char _failSafeReason[32];

    unsigned long _lastConvRequestTime;
    unsigned long _lastValidReadTime;
    unsigned long _lastDHTSampleTime;
    bool _conversionPending;
};
