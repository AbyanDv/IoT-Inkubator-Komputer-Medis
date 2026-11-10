<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTelemetryRequest;
use App\Models\DeviceEvent;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Carbon;

class DeviceTelemetryController extends Controller
{
    /**
     * Ingest telemetry from ESP32 and piggyback config sync + pairing state.
     */
    public function store(StoreTelemetryRequest $request): JsonResponse
    {
        /** @var Incubator $incubator */
        $incubator = $request->attributes->get('incubator');
        $validated = $request->validated();

        $telemetry = DeviceTelemetry::create([
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

        $incubator->update([
            'last_heartbeat_at' => Carbon::now(),
            'status' => 'online',
        ]);

        $this->logSensorFaults($incubator, $validated['sensor_status']);

        return Response::json($this->buildResponse($incubator, $validated));
    }

    /**
     * Log device events for sensor faults.
     *
     * @param  array<string, bool>  $sensorStatus
     */
    private function logSensorFaults(Incubator $incubator, array $sensorStatus): void
    {
        foreach ($sensorStatus as $sensor => $isWorking) {
            if ($isWorking) {
                continue;
            }

            DeviceEvent::create([
                'incubator_id' => $incubator->id,
                'event_type' => 'sensor_fault',
                'priority' => 'danger',
                'message' => "Sensor {$sensor} fault detected",
                'payload' => ['sensor' => $sensor, 'status' => false],
            ]);
        }
    }

    /**
     * Build piggyback response with config sync and pairing state.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildResponse(Incubator $incubator, array $validated): array
    {
        $incubator->load(['currentBaby', 'activeConfig']);

        $isPaired = $incubator->current_baby_id !== null;
        $baby = $isPaired ? [
            'code' => $incubator->currentBaby->baby_code,
            'name' => $incubator->currentBaby->name,
        ] : null;

        $activeConfig = $incubator->activeConfig;
        $hasConfigUpdate = false;
        $serverConfigVersion = $validated['applied_config_version'];
        $configPayload = null;

        if ($activeConfig && $activeConfig->config_version > $validated['applied_config_version']) {
            $hasConfigUpdate = true;
            $serverConfigVersion = $activeConfig->config_version;
            $configPayload = [
                'version' => $activeConfig->config_version,
                'temperature_setpoint' => (float) $activeConfig->temperature_setpoint,
                'pid_kp' => (float) $activeConfig->pid_kp,
                'pid_ki' => (float) $activeConfig->pid_ki,
                'pid_kd' => (float) $activeConfig->pid_kd,
                'fan_mode' => $activeConfig->fan_mode,
                'blue_led_enabled' => $activeConfig->blue_led_enabled,
                'simulation_mode' => $activeConfig->simulation_mode,
            ];
        }

        return [
            'status' => 'success',
            'is_paired' => $isPaired,
            'baby' => $baby,
            'has_config_update' => $hasConfigUpdate,
            'server_config_version' => $serverConfigVersion,
            'config' => $configPayload,
        ];
    }
}
