<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceConfig;
use App\Models\DeviceEvent;
use App\Models\Incubator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $incubator = $this->incubator($request);
        $this->markOnline($incubator);

        return Response::json(['status' => 'success', 'message' => 'Heartbeat acknowledged']);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => ['required', 'string', 'max:100'],
            'priority' => ['required', Rule::in(['info', 'warning', 'danger'])],
            'message' => ['required', 'string', 'max:65535'],
            'payload' => ['nullable', 'array'],
        ]);

        $this->incubator($request)->events()->create($validated);

        return Response::json(['status' => 'success'], 201);
    }

    public function config(Request $request): JsonResponse
    {
        $incubator = $this->incubator($request);
        $config = $incubator->activeConfig;

        return Response::json([
            'status' => 'success',
            'config' => $config ? $this->configPayload($config) : null,
        ]);
    }

    public function acknowledge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'config_version' => ['required', 'integer', 'min:0'],
        ]);
        $incubator = $this->incubator($request);
        $incubator->update(['last_config_version' => $validated['config_version']]);
        $incubator->configs()
            ->where('config_version', $validated['config_version'])
            ->update(['applied_at' => Carbon::now()]);

        return Response::json(['status' => 'success']);
    }

    private function incubator(Request $request): Incubator
    {
        /** @var Incubator $incubator */
        $incubator = $request->attributes->get('incubator');

        return $incubator;
    }

    private function markOnline(Incubator $incubator): void
    {
        $incubator->update(['last_heartbeat_at' => Carbon::now(), 'status' => 'online']);
    }

    /** @return array<string, mixed> */
    private function configPayload(DeviceConfig $config): array
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
}
