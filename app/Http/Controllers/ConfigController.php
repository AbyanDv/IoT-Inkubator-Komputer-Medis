<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Incubator;
use App\Services\MqttPublisher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View as ViewFacade;

class ConfigController extends Controller
{
    public function __construct(private readonly MqttPublisher $mqtt) {}

    public function edit(Incubator $incubator): View
    {
        return ViewFacade::make('incubators.config', [
            'incubator' => $incubator,
            'config' => $incubator->activeConfig,
        ]);
    }

    public function update(Request $request, Incubator $incubator): RedirectResponse
    {
        $validated = $request->validate([
            'temperature_setpoint' => ['required', 'numeric', 'between:28,37'],
            'pid_kp' => ['required', 'numeric', 'between:0,100'],
            'pid_ki' => ['required', 'numeric', 'between:0,100'],
            'pid_kd' => ['required', 'numeric', 'between:0,100'],
            'blue_led_enabled' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($incubator, $request, $validated): void {
            $incubator = Incubator::query()->lockForUpdate()->findOrFail($incubator->id);
            $requestUser = $request->user();
            $incubator->configs()->updateOrCreate([], [
                ...$validated,
                'blue_led_enabled' => $request->boolean('blue_led_enabled'),
                'created_by' => $requestUser?->id,
            ]);
            AuditLog::create([
                'user_id' => $requestUser?->id,
                'action' => 'incubator.config_updated',
                'target_type' => Incubator::class,
                'target_id' => $incubator->id,
                'details' => ['updated_at' => now()->toISOString()],
                'ip_address' => $request->ip(),
            ]);
        });

        $this->mqtt->publishSync($incubator->fresh());

        return Redirect::route('incubators.config.edit', $incubator)->with('status', 'Configuration saved.');
    }
}
