<?php

namespace App\Services;

use App\Models\DeviceConfig;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DeviceIngestionService
{
    public function __construct(private readonly RelayControlService $relays) {}

    /**
     * Persist telemetry and return the piggybacked sync payload.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function recordTelemetry(Incubator $incubator, array $validated): array
    {
        DeviceTelemetry::create([
            'incubator_id' => $incubator->id,
            'baby_id' => $incubator->current_baby_id,
            'temperature_object' => $validated['temperature_object'],
            'temperature_room' => $validated['temperature_room'],
            'humidity' => $validated['humidity'],
            'fan_status' => $validated['relays']['fan'],
            'heater_1_status' => $validated['relays']['heater_1'],
            'heater_2_status' => $validated['relays']['heater_2'],
            'blue_led_status' => $validated['relays']['blue_light'],
            'raw_payload' => $validated,
            'recorded_at' => isset($validated['timestamp'])
                ? Carbon::parse($validated['timestamp'])
                : Carbon::now(),
        ]);

        $this->markOnline($incubator);
        $this->logSensorFaults($incubator, $validated['sensor_status']);

        return $this->syncPayload($incubator);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function recordEvent(Incubator $incubator, array $validated): void
    {
        $incubator->events()->create($validated);
    }

    public function markOnline(Incubator $incubator): void
    {
        $incubator->update([
            'last_heartbeat_at' => Carbon::now(),
            'status' => 'online',
        ]);
    }

    public function markOffline(Incubator $incubator): void
    {
        $incubator->update(['status' => 'offline']);
    }

    /**
     * Build the same config/pairing response for HTTP and MQTT.
     *
     * @return array<string, mixed>
     */
    public function syncPayload(Incubator $incubator): array
    {
        $incubator->load(['currentBaby', 'activeConfig']);

        $baby = $incubator->currentBaby ? [
            'code' => $incubator->currentBaby->baby_code,
            'name' => $incubator->currentBaby->name,
        ] : null;

        $overrides = $this->relays->overrides($incubator);

        return [
            'status' => 'success',
            'is_paired' => $baby !== null,
            'baby' => $baby,
            'config' => $incubator->activeConfig
                ? $this->configPayload($incubator->activeConfig)
                : null,
            'relay_overrides' => $overrides === [] ? null : (object) $overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function configPayload(DeviceConfig $config): array
    {
        return [
            'temperature_setpoint' => (float) $config->temperature_setpoint,
            'kp' => (float) $config->pid_kp,
            'ki' => (float) $config->pid_ki,
            'kd' => (float) $config->pid_kd,
            'blue_led_enabled' => $config->blue_led_enabled,
        ];
    }

    /**
     * Shared validation rules for device events.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function eventRules(): array
    {
        return [
            'event_type' => ['required', 'string', 'max:100'],
            'priority' => ['required', Rule::in(['info', 'warning', 'danger'])],
            'message' => ['required', 'string', 'max:65535'],
            'payload' => ['nullable', 'array'],
        ];
    }

    /**
     * @param  array<string, bool>  $sensorStatus
     */
    private function logSensorFaults(Incubator $incubator, array $sensorStatus): void
    {
        foreach ($sensorStatus as $sensor => $isWorking) {
            if ($isWorking) {
                continue;
            }

            $incubator->events()->create([
                'event_type' => 'sensor_fault',
                'priority' => 'danger',
                'message' => "Sensor {$sensor} fault detected",
                'payload' => ['sensor' => $sensor, 'status' => false],
            ]);
        }
    }
}
