<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use Illuminate\Http\JsonResponse;

class LiveTelemetryController extends Controller
{
    /**
     * Return latest telemetry for dashboard polling.
     */
    public function show(Incubator $incubator): JsonResponse
    {
        $telemetry = DeviceTelemetry::where('incubator_id', $incubator->id)
            ->latest('created_at')
            ->first();

        if (! $telemetry) {
            return response()->json([
                'status' => 'no_data',
                'incubator' => [
                    'id' => $incubator->id,
                    'device_code' => $incubator->device_code,
                    'name' => $incubator->name,
                    'status' => $incubator->status,
                ],
                'telemetry' => null,
                'baby' => null,
            ]);
        }

        $baby = $incubator->current_baby_id
            ? $incubator->currentBaby
            : null;

        return response()->json([
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
                'heater_pwm' => $telemetry->heater_pwm,
                'fan_pwm' => $telemetry->fan_pwm,
                'blue_led_status' => $telemetry->blue_led_status,
                'recorded_at' => $telemetry->recorded_at?->toIso8601String(),
            ],
            'baby' => $baby ? [
                'code' => $baby->baby_code,
                'name' => $baby->name,
            ] : null,
        ]);
    }
}
