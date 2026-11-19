<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use App\Services\RelayControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class LiveTelemetryController extends Controller
{
    public function __construct(private readonly RelayControlService $relays) {}

    /**
     * Return latest telemetry for dashboard polling.
     */
    public function show(Incubator $incubator): JsonResponse
    {
        $telemetry = DeviceTelemetry::where('incubator_id', $incubator->id)
            ->latest('recorded_at')
            ->first();

        if (! $telemetry) {
            return Response::json([
                'status' => 'no_data',
                'incubator' => [
                    'id' => $incubator->id,
                    'device_code' => $incubator->device_code,
                    'name' => $incubator->name,
                    'status' => $incubator->status,
                ],
                'telemetry' => null,
                'relay_overrides' => (object) $this->relays->overrides($incubator),
                'baby' => null,
            ]);
        }

        $baby = $incubator->current_baby_id
            ? $incubator->currentBaby
            : null;

        return Response::json([
            'status' => 'success',
            'incubator' => [
                'id' => $incubator->id,
                'device_code' => $incubator->device_code,
                'name' => $incubator->name,
                'status' => $incubator->status,
                'last_heartbeat_at' => $incubator->last_heartbeat_at?->toIso8601String(),
            ],
            'telemetry' => [
                'temperature_object' => (float) $telemetry->temperature_object,
                'temperature_room' => (float) $telemetry->temperature_room,
                'humidity' => (float) $telemetry->humidity,
                'relays' => [
                    'fan' => (bool) $telemetry->fan_status,
                    'heater_1' => (bool) $telemetry->heater_1_status,
                    'heater_2' => (bool) $telemetry->heater_2_status,
                    'blue_light' => (bool) $telemetry->blue_led_status,
                ],
                'recorded_at' => $telemetry->recorded_at?->toIso8601String(),
                'sensor_status' => $telemetry->raw_payload['sensor_status'] ?? null,
            ],
            'relay_overrides' => (object) $this->relays->overrides($incubator),
            'baby' => $baby ? [
                'code' => $baby->baby_code,
                'name' => $baby->name,
            ] : null,
        ]);
    }
}
