#include "sensor_manager.h"
#include <string.h>
#include <math.h>

SensorManager::SensorManager()
    : _oneWire(PIN_DS18B20),
      _sensors(&_oneWire),
      _dht(PIN_DHT22, DHT22),
      _tempObject(0.0f),
      _tempRoom(0.0f),
      _humidity(0.0f),
      _ds18b20State(DS18B20State::DISCONNECTED),
      _dhtState(DHTState::READ_ERROR),
      _failSafeActive(true),
      _lastConvRequestTime(0),
      _lastValidReadTime(0),
      _lastDHTSampleTime(0),
      _conversionPending(false) {
    strncpy(_failSafeReason, "BOOT_INITIALIZING", sizeof(_failSafeReason) - 1);
}

void SensorManager::begin() {
    // 1. Initialize DS18B20 OneWire bus
    _sensors.begin();
    // Critical: Disable blocking conversion so PID control loop never stalls
    _sensors.setWaitForConversion(false);
    _sensors.setResolution(10); // 10-bit resolution (~187.5ms conversion time)

    // Request initial non-blocking temperature conversion
    _sensors.requestTemperatures();
    _lastConvRequestTime = millis();
    _conversionPending = true;

    // 2. Initialize DHT22
    _dht.begin();
    _lastDHTSampleTime = millis();
}

bool SensorManager::update(unsigned long nowMs) {
    // -------------------------------------------------------------
    // A. NON-BLOCKING DS18B20 SAMPLING & FAIL-SAFE EVALUATION
    // -------------------------------------------------------------
    if (_conversionPending && (nowMs - _lastConvRequestTime >= DS18B20_CONV_DELAY_MS)) {
        float rawTemp = _sensors.getTempCByIndex(0);

        // Immediate Disconnection & Hardware Error Checks
        if (rawTemp <= DS18B20_DISCONNECTED_C || rawTemp == DS18B20_POWER_ON_RESET || isnan(rawTemp)) {
            _ds18b20State = DS18B20State::DISCONNECTED;
            _failSafeActive = true;
            strncpy(_failSafeReason, "SENSOR_DISCONNECTED", sizeof(_failSafeReason) - 1);
        } else if (rawTemp > TEMP_OVERHEAT_LIMIT) {
            // Overheat Emergency Guard: temp > 38.5°C
            _tempObject = rawTemp;
            _ds18b20State = DS18B20State::OVERHEAT;
            _failSafeActive = true;
            strncpy(_failSafeReason, "OVERHEAT_EMERGENCY", sizeof(_failSafeReason) - 1);
            _lastValidReadTime = nowMs;
        } else if (rawTemp < TEMP_UNDERHEAT_LIMIT) {
            // Underheat Abnormal Guard: temp < 15.0°C
            _tempObject = rawTemp;
            _ds18b20State = DS18B20State::UNDERHEAT;
            _failSafeActive = true;
            strncpy(_failSafeReason, "UNDERHEAT_ANOMALY", sizeof(_failSafeReason) - 1);
            _lastValidReadTime = nowMs;
        } else {
            // Valid Reading
            _tempObject = rawTemp;
            _ds18b20State = DS18B20State::OK;
            _failSafeActive = false;
            _failSafeReason[0] = '\0';
            _lastValidReadTime = nowMs;
        }

        // Trigger next non-blocking conversion for the next cycle
        _sensors.requestTemperatures();
        _lastConvRequestTime = nowMs;
        _conversionPending = true;
    }

    // Safety Watchdog: If no valid conversion occurred within timeout window
    if (_lastValidReadTime == 0 || (nowMs - _lastValidReadTime > DS18B20_TIMEOUT_MS)) {
        if (!_failSafeActive) {
            _failSafeActive = true;
            _ds18b20State = DS18B20State::READ_ERROR;
            strncpy(_failSafeReason, "SENSOR_TIMEOUT", sizeof(_failSafeReason) - 1);
        }
    }

    // -------------------------------------------------------------
    // B. NON-BLOCKING DHT22 SAMPLING (Interval: 2000ms)
    // -------------------------------------------------------------
    if (nowMs - _lastDHTSampleTime >= DHT_SAMPLE_INTERVAL_MS) {
        float h = _dht.readHumidity();
        float tr = _dht.readTemperature();

        if (isnan(h) || isnan(tr)) {
            _dhtState = DHTState::READ_ERROR;
        } else {
            _tempRoom = tr;
            _humidity = h;
            _dhtState = DHTState::OK;
        }
        _lastDHTSampleTime = nowMs;
    }

    return _failSafeActive;
}

const char* SensorManager::getDS18B20StatusString() const {
    switch (_ds18b20State) {
        case DS18B20State::OK: return "OK";
        case DS18B20State::DISCONNECTED: return "DISCONNECTED";
        case DS18B20State::OVERHEAT: return "OVERHEAT";
        case DS18B20State::UNDERHEAT: return "UNDERHEAT";
        case DS18B20State::READ_ERROR: return "ERROR";
        default: return "UNKNOWN";
    }
}

const char* SensorManager::getDHT22StatusString() const {
    switch (_dhtState) {
        case DHTState::OK: return "OK";
        case DHTState::READ_ERROR: return "ERROR";
        default: return "UNKNOWN";
    }
}
