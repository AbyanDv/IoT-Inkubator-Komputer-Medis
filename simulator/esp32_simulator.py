#!/usr/bin/env python3
"""Dummy ESP32 incubator simulator for the MQTT contract.

Environment variables:
  DEVICE_CODE         device code / MQTT username (default INC-001)
  MQTT_HOST           broker host (default 127.0.0.1)
  MQTT_PORT           broker port (default 8883)
  MQTT_TLS            "1" or "0" (default 1)
  MQTT_CA_CERT        CA certificate path for TLS verification
  MQTT_USERNAME       MQTT username (default DEVICE_CODE)
  MQTT_PASSWORD       MQTT password (required)
  MQTT_INSECURE       "1" to skip certificate verification (default 0)
  TELEMETRY_INTERVAL  seconds between readings (default 5)
  SIMULATE_FAULT      "1" to report both sensors as faulty
"""

from __future__ import annotations

import json
import math
import os
import random
import signal
import ssl
import sys
import time
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import paho.mqtt.client as mqtt


def load_env_file() -> None:
    """Load simulator/.env without requiring a specific shell."""
    env_file = Path(__file__).with_name(".env")
    if not env_file.exists():
        return

    for line in env_file.read_text().splitlines():
        key, separator, value = line.partition("=")
        key = key.strip()
        if key and separator and not key.startswith("#"):
            os.environ.setdefault(key, value.strip().strip("'\""))


load_env_file()


def env(name: str, default: str | None = None) -> str:
    value = os.getenv(name, default)
    if value is None or value == "":
        raise SystemExit(f"Missing required environment variable: {name}")
    return value


@dataclass
class DeviceState:
    temperature_setpoint: float = 36.5
    pid_kp: float = 2.0
    pid_ki: float = 0.1
    pid_kd: float = 0.5
    blue_led_enabled: bool = False
    is_paired: bool = False
    baby: dict[str, Any] | None = None
    relay_overrides: dict[str, bool] = field(default_factory=dict)


class Simulator:
    def __init__(self) -> None:
        self.device_code = env("DEVICE_CODE", "INC-001")
        self.topic_prefix = env("MQTT_TOPIC_PREFIX", "incubator")
        self.host = env("MQTT_HOST", "127.0.0.1")
        self.port = int(env("MQTT_PORT", "8883"))
        self.interval = float(env("TELEMETRY_INTERVAL", "5"))
        self.simulate_fault = env("SIMULATE_FAULT", "0") == "1"
        self.state = DeviceState()
        self.running = True

        client_id = f"sim-{self.device_code.lower()}"
        self.client = mqtt.Client(
            callback_api_version=mqtt.CallbackAPIVersion.VERSION2,
            client_id=client_id,
            clean_session=True,
        )
        self.client.username_pw_set(
            env("MQTT_USERNAME", self.device_code),
            env("MQTT_PASSWORD"),
        )
        self.client.reconnect_delay_set(min_delay=1, max_delay=10)
        self.client.on_connect = self.on_connect
        self.client.on_message = self.on_message
        self.client.on_disconnect = self.on_disconnect

        status_topic = self.topic("status")
        self.client.will_set(
            status_topic,
            json.dumps({"online": False}),
            qos=1,
            retain=True,
        )

        if env("MQTT_TLS", "1") == "1":
            ca_cert = os.getenv("MQTT_CA_CERT")
            self.client.tls_set(
                ca_certs=ca_cert,
                tls_version=ssl.PROTOCOL_TLS_CLIENT,
            )
            if env("MQTT_INSECURE", "0") == "1":
                self.client.tls_insecure_set(True)

    def topic(self, suffix: str) -> str:
        return f"{self.topic_prefix}/{self.device_code}/{suffix}"

    def on_connect(
        self,
        client: mqtt.Client,
        userdata: Any,
        flags: mqtt.ConnectFlags,
        reason_code: mqtt.ReasonCode,
        properties: mqtt.Properties | None,
    ) -> None:
        if reason_code.is_failure:
            print(f"Broker rejected connection: {reason_code}", file=sys.stderr)
            return

        print(f"Connected to {self.host}:{self.port} as {self.device_code}")
        client.publish(
            self.topic("status"),
            json.dumps({"online": True}),
            qos=1,
            retain=True,
        )
        client.subscribe(self.topic("sync"), qos=1)
        client.publish(
            self.topic("events"),
            json.dumps(
                {
                    "event_type": "device_boot",
                    "priority": "info",
                    "message": "ESP32 simulator connected",
                    "payload": {"firmware": "sim-1.0"},
                }
            ),
            qos=1,
        )

    def on_disconnect(
        self,
        client: mqtt.Client,
        userdata: Any,
        flags: mqtt.DisconnectFlags,
        reason_code: mqtt.ReasonCode,
        properties: mqtt.Properties | None,
    ) -> None:
        if self.running:
            print(f"Disconnected ({reason_code}); reconnecting...", file=sys.stderr)

    def on_message(
        self,
        client: mqtt.Client,
        userdata: Any,
        message: mqtt.MQTTMessage,
    ) -> None:
        if message.topic != self.topic("sync"):
            return

        try:
            payload = json.loads(message.payload)
        except json.JSONDecodeError as error:
            print(f"Ignoring invalid sync payload: {error}", file=sys.stderr)
            return

        self.state.is_paired = bool(payload.get("is_paired"))
        self.state.baby = payload.get("baby")
        self.state.relay_overrides = {}

        overrides = payload.get("relay_overrides")
        if isinstance(overrides, dict):
            self.state.relay_overrides = {
                key: bool(value)
                for key, value in overrides.items()
                if key in {"fan", "heater_1", "heater_2", "blue_light"}
            }

        config = payload.get("config")
        if isinstance(config, dict):
            self.state.temperature_setpoint = float(config["temperature_setpoint"])
            self.state.pid_kp = float(config["pid_kp"])
            self.state.pid_ki = float(config["pid_ki"])
            self.state.pid_kd = float(config["pid_kd"])
            self.state.blue_led_enabled = bool(config["blue_led_enabled"])
            print(
                "Applied config: "
                f"setpoint={self.state.temperature_setpoint:.2f} "
                f"led={self.state.blue_led_enabled}"
            )
        else:
            print(
                "Sync: "
                f"paired={self.state.is_paired} "
                f"baby={self.state.baby} "
                "config=none"
            )

        if self.state.relay_overrides:
            print(f"Relay overrides: {self.state.relay_overrides}")

    def telemetry(self) -> dict[str, Any]:
        elapsed = time.monotonic()
        object_temperature = self.state.temperature_setpoint + 0.35 * math.sin(
            elapsed / 25
        ) + random.uniform(-0.08, 0.08)

        if self.simulate_fault:
            relays = {
                "fan": True,
                "heater_1": False,
                "heater_2": False,
                "blue_light": self.state.blue_led_enabled,
            }
            ds18b20_ok = False
            dht22_ok = False
        else:
            error = self.state.temperature_setpoint - object_temperature
            relays = {
                "fan": True,
                "heater_1": error > 0.05,
                "heater_2": error > 0.5,
                "blue_light": self.state.blue_led_enabled,
            }
            ds18b20_ok = True
            dht22_ok = True

        relays.update(self.state.relay_overrides)

        return {
            "device_code": self.device_code,
            "temperature_object": round(object_temperature, 2),
            "temperature_room": round(28.0 + random.uniform(-0.4, 0.4), 2),
            "humidity": round(55.0 + random.uniform(-2.0, 2.0), 2),
            "relays": relays,
            "sensor_status": {"ds18b20": ds18b20_ok, "dht22": dht22_ok},
            "timestamp": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        }

    def stop(self, *_args: Any) -> None:
        self.running = False

    def run(self) -> None:
        signal.signal(signal.SIGINT, self.stop)
        signal.signal(signal.SIGTERM, self.stop)

        self.client.connect(self.host, self.port, keepalive=30)
        self.client.loop_start()

        try:
            while self.running:
                payload = self.telemetry()
                result = self.client.publish(
                    self.topic("telemetry"),
                    json.dumps(payload),
                    qos=1,
                )
                result.wait_for_publish(timeout=5)
                print(
                    "Telemetry: "
                    f"object={payload['temperature_object']:.2f} "
                    f"humidity={payload['humidity']:.1f} "
                    f"relays={payload['relays']} "
                    f"paired={self.state.is_paired}"
                )
                time.sleep(self.interval)
        finally:
            self.client.publish(
                self.topic("status"),
                json.dumps({"online": False}),
                qos=1,
                retain=True,
            ).wait_for_publish(timeout=2)
            self.client.loop_stop()
            self.client.disconnect()
            print("Simulator stopped")


if __name__ == "__main__":
    Simulator().run()
