<?php

namespace App\Services;

use App\Models\DeviceConfig;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DeviceIngestionService
{
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
            'heater_status' => $validated['heater_status'] ?? $validated['heater_pwm'] > 0,
            'heater_pwm' => $validated['heater_pwm'],
            'fan_status' => $validated['fan_status'] ?? $validated['fan_pwm'] > 0,
            'fan_pwm' => $validated['fan_pwm'],
            'blue_led_status' => $validated['blue_led_status'],
            'raw_payload' => $validated,
            'recorded_at' => isset($validated['timestamp'])
                ? Carbon::parse($validated['timestamp'])
                : Carbon::now(),
        ]);

        $this->markOnline($incubator);
        $this->logSensorFaults($incubator, $validated['sensor_status']);
        $this->recordConfigAcknowledgement($incubator, (int) $validated['applied_config_version']);

        return $this->syncPayload($incubator, (int) $validated['applied_config_version']);
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
    public function syncPayload(Incubator $incubator, int $appliedConfigVersion): array
    {
        $incubator->load(['currentBaby', 'activeConfig']);

        $baby = $incubator->currentBaby ? [
            'code' => $incubator->currentBaby->baby_code,
            'name' => $incubator->currentBaby->name,
        ] : null;

        $activeConfig = $incubator->activeConfig;
        $hasConfigUpdate = false;
        $serverConfigVersion = $appliedConfigVersion;
        $configPayload = null;

        if ($activeConfig && $activeConfig->config_version > $appliedConfigVersion) {
            $hasConfigUpdate = true;
            $serverConfigVersion = $activeConfig->config_version;
            $configPayload = $this->configPayload($activeConfig);
        }

        return [
            'status' => 'success',
            'is_paired' => $baby !== null,
            'baby' => $baby,
            'has_config_update' => $hasConfigUpdate,
            'server_config_version' => $serverConfigVersion,
            'config' => $configPayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function configPayload(DeviceConfig $config): array
    {
        return [
            'version' => $config->config_version,
            'temperature_setpoint' => (float) $config->temperature_setpoint,
            'pid_kp' => (float) $config->pid_kp,
            'pid_ki' => (float) $config->pid_ki,
            'pid_kd' => (float) $config->pid_kd,
            'fan_mode' => $config->fan_mode,
            'blue_led_enabled' => $config->blue_led_enabled,
            'simulation_mode' => $config->simulation_mode,
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

    /**
     * Piggybacked ACK: telemetry carries the version the device actually runs.
     */
    private function recordConfigAcknowledgement(Incubator $incubator, int $appliedConfigVersion): void
    {
        if ($appliedConfigVersion <= $incubator->last_config_version) {
            return;
        }

        $incubator->update(['last_config_version' => $appliedConfigVersion]);
        $incubator->configs()
            ->where('config_version', $appliedConfigVersion)
            ->update(['applied_at' => Carbon::now()]);
    }
}
