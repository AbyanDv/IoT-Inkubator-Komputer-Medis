<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Incubator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View as ViewFacade;

class ConfigController extends Controller
{
    public function edit(Incubator $incubator): View
    {
        return ViewFacade::make('incubators.config', [
            'incubator' => $incubator,
            'config' => $incubator->configs()->latest('config_version')->first(),
        ]);
    }

    public function update(Request $request, Incubator $incubator): RedirectResponse
    {
        $validated = $request->validate([
            'temperature_setpoint' => ['required', 'numeric', 'between:20,40'],
            'pid_kp' => ['required', 'numeric', 'between:0,100'],
            'pid_ki' => ['required', 'numeric', 'between:0,100'],
            'pid_kd' => ['required', 'numeric', 'between:0,100'],
            'fan_mode' => ['required', 'in:pwm,auto'],
            'blue_led_enabled' => ['sometimes', 'boolean'],
            'simulation_mode' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($incubator, $request, $validated): void {
            $incubator = Incubator::query()->lockForUpdate()->findOrFail($incubator->id);
            $version = ((int) $incubator->configs()->max('config_version')) + 1;
            $incubator->configs()->where('is_active', true)->update(['is_active' => false]);
            $requestUser = $request->user();
            $incubator->configs()->create([
                ...$validated,
                'blue_led_enabled' => $request->boolean('blue_led_enabled'),
                'simulation_mode' => $request->boolean('simulation_mode'),
                'config_version' => $version,
                'is_active' => true,
                'created_by' => $requestUser?->id,
            ]);
            AuditLog::create([
                'user_id' => $requestUser?->id,
                'action' => 'incubator.config_updated',
                'target_type' => Incubator::class,
                'target_id' => $incubator->id,
                'details' => ['config_version' => $version],
                'ip_address' => $request->ip(),
            ]);
        });

        return Redirect::route('incubators.config.edit', $incubator)->with('status', 'Configuration saved.');
    }
}
