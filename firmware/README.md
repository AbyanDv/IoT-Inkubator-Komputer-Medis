# Firmware ESP32 — Smart Infant Incubator

Firmware for the ESP32 DevKit V1 that runs the incubator's real-time control
loop. **Every calculation runs on the ESP32**: the VPS only stores telemetry and
pushes config. If the network dies, the PID loop, staging, and fail-safe keep
working untouched.

- Platform: PlatformIO, Arduino framework, ESP32 dual-core.
- Sensors: DS18B20 (object, non-blocking), DHT22 (room + humidity).
- Actuators: 4 relays (fan, heater 1, heater 2, blue light), LCD I2C 16x2.
- Transports: MQTT over TLS (primary) + HTTPS piggyback (fallback), same JSON
  contract as `app/Services/DeviceIngestionService.php`.

## Wiring

| Signal        | ESP32 pin | Notes                          |
| ------------- | --------- | ------------------------------ |
| DS18B20 data  | GPIO 4    | 4.7 kΩ pull-up to 3.3 V        |
| DHT22 data    | GPIO 5    | 4.7 kΩ pull-up to 3.3 V        |
| Relay fan     | GPIO 25   | Opto-isolated board, active low |
| Relay heater 1| GPIO 26   |                                |
| Relay heater 2| GPIO 27   |                                |
| Relay blue    | GPIO 14   |                                |
| LCD SDA       | GPIO 21   | I2C, addr 0x27                 |
| LCD SCL       | GPIO 22   |                                |

Relay boards that are active-high: set `RELAY_ACTIVE_LOW=0` in
`platformio.ini`.

## Architecture

```
                 ESP32 (dual core, no shared mutable state)
 +--------------------------- Core 1 (PRO_CPU) ------------------+
 | controlLoop() every 100 ms:                                   |
 |   sensors::poll()  ── DS18B20 conversion runs off-core        |
 |   fail-safe check  ── trip is sticky, wins over everything    |
 |   control::step()  ── PID + relay staging (pure math)         |
 |   relays::apply()  ── overrides > PID, trip > overrides       |
 |   display::render() + bridge::publishReading()                |
 +---------------------------┬-----------------------------------+
 |                    FreeRTOS queue (1 slot, overwrite)          |
 +---------------------------┴-----------------------------------+
 |                 Core 0 (APP_CPU) — all blocking I/O           |
 | networkLoop():                                                |
 |   transport::maintain()  Wi-Fi reconnect + MQTT keepalive     |
 |   drainEvents()          fault events → MQTT + HTTP           |
 |   every TELEMETRY_INTERVAL_MS:                                |
 |     MQTT  publish incubator/INC-001/telemetry   (primary)     |
 |     HTTP  POST /api/devices/telemetry           (fallback)    |
 |        └─ response carries config + pairing + overrides       |
 |            → protocol::apply() → bridge::publishCommand()     |
 +---------------------------------------------------------------+
```

Why this split: a TLS handshake or Wi-Fi reconnect on Core 1 would delay a
heater decision by up to seconds. Core 1 never calls a blocking API.

## Config

One file, two layers:

- `include/config.h` — every knob, wrapped in `#ifndef`. Defaults live here;
  nothing else in the tree hardcodes a pin, threshold, or gain.
- `platformio.ini` `build_flags` — the only place per-device values go.

```ini
-DDEVICE_CODE="\"INC-002\""
-DWIFI_SSID="\"lab-2.4g\""
-DMQTT_PASSWORD=\\"${sysenv.MQTT_PASSWORD}\\"
-DTLS_VERIFY=1
```

Secrets (`MQTT_PASSWORD`, `DEVICE_TOKEN`) come from the shell, never from the
repo. CA certificate lives in `data/ca.crt` (LittleFS, uploaded once with
`pio run -t uploadfs`), so changing brokers costs an upload, not a recompile.

Control constants, tuned for a 28–37 °C incubator:

| Key                | Default | Meaning                                  |
| ------------------ | ------- | ---------------------------------------- |
| `DEFAULT_SETPOINT` | 33.5 °C | Local fallback before first sync         |
| `SETPOINT_MIN/MAX` | 28/37 °C| Clamp applied to any network setpoint    |
| `TRIP_HIGH_C`      | 38.5 °C | Overheat trip: heaters off, fan on       |
| `TRIP_LOW_C`       | 15.0 °C | Below this the probe is lying, not cold  |
| `HEATER_1_ON_PCT`  | 5 %     | Staging threshold, heater 1              |
| `HEATER_2_ON_PCT`  | 50 %    | Staging threshold, heater 2              |
| `HEATER_HYST_PCT`  | 3 %     | Release band, stops relay chatter        |
| `PID_FULL_SCALE_C` | 5.0 K   | Error that maps to 100 % output          |
| `PID_I_MIN/MAX_C`  | ±1.0 K  | Integral clamp (anti-windup)             |
| `OVERRIDE_TTL_MS`  | 60000   | Relay test override lifetime, no server  |

The server's `pid_kp/ki/kd` keep their K-per-K meaning: output % =
`(kp·e + ∫ki·e + kd·de/dt) · 100 / PID_FULL_SCALE_C`.

## Fail-safe rules (firmware only)

1. Sensor fault (`ds18b20: false`), overheat, or implausible low reading →
   heater 1 + heater 2 OFF, fan ON, LCD shows `FAIL-SAFE ON`.
2. Trip is sticky: heaters stay off until the device reboots. A blip must never
   re-arm heat on its own.
3. Relay test overrides from the dashboard expire `OVERRIDE_TTL_MS` after the
   last successful sync (PRD: 60 s dead-man).
4. Fail-safe wins over override; override wins over PID; PID wins over idle.

## Build & flash

```bash
cd firmware
pio run                  # compile + host tests are separate
pio run -t upload        # flash
pio run -t uploadfs      # upload data/ca.crt once
pio monitor              # 115200
```

Provision a device: register it in the dashboard, copy the issued
`device_token` into `-DDEVICE_TOKEN=...`, rebuild, flash.

## Host tests

`lib/control/control.cpp` has zero Arduino dependencies, so the PID, staging,
hysteresis, anti-windup, and trip logic run on the host compiler:

```bash
./host_test/run.sh    # plain g++, no ESP32, no PlatformIO
```

Golden vectors cover: both stages on a large error, sensor-fault and overheat
clearance, the 5 %/3 % hysteresis band, setpoint clamp, integral wind-up stop,
and derivative damping. CI runs this file before any board is flashed.

## Contract with Laravel

- Telemetry body = `StoreTelemetryRequest::payloadRules()` exactly.
- Sync body = `DeviceIngestionService::syncPayload()` exactly:
  `config`, `is_paired`, `baby.code`, `relay_overrides`.
- Absent keys keep the last-known value; a partial response can never reset the
  controller. `relay_overrides: {}` means "back to auto", which is why the
  parser treats an empty object as a deliberate clear, not a missing field.
- `timestamp` is not sent: Laravel stamps `recorded_at` itself.

## Safety boundary

Prototype firmware for research and teaching. Not a medical device. Not for
use on humans. Thermal trip thresholds are local and cannot be lowered from the
network.
