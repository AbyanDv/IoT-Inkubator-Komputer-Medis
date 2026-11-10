<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Baby;
use App\Models\DevicePairing;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IncubatorController extends Controller
{
    public function index(): View
    {
        return ViewFacade::make('incubators.index', [
            'incubators' => Incubator::with('currentBaby')->orderBy('name')->get(),
        ]);
    }

    public function show(Incubator $incubator): View
    {
        $incubator->load('currentBaby');
        $latest = $incubator->telemetries()->latest('created_at')->first();
        $history = $incubator->telemetries()->latest('created_at')->take(20)->get()->reverse()->values();

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return ViewFacade::make('incubators.show', [
            'incubator' => $incubator,
            'initialTelemetry' => $latest ? [
                'status' => 'success',
                'telemetry' => [
                    'temperature_object' => (float) $latest->temperature_object,
                    'temperature_room' => (float) $latest->temperature_room,
                    'humidity' => (float) $latest->humidity,
                    'heater_pwm' => $latest->heater_pwm,
                    'fan_pwm' => $latest->fan_pwm,
                    'recorded_at' => $latest->recorded_at?->toIso8601String(),
                ],
            ] : ['status' => 'no_data', 'telemetry' => null],
            'history' => $history->map(fn (DeviceTelemetry $item): array => [
                'temperature' => (float) $item->temperature_object,
                'time' => $item->recorded_at?->format('H:i:s') ?? $item->created_at?->format('H:i:s'),
            ]),
            'unassignedBabies' => Baby::whereDoesntHave('currentPairing')->orderBy('name')->get(),
            'canManagePairing' => $user?->hasRole(['admin', 'super_admin', 'viewer']) ?? false,
        ]);
    }

    public function pair(Request $request, Incubator $incubator): RedirectResponse
    {
        $validated = $request->validate([
            'baby_id' => ['required', 'integer', 'exists:babies,id'],
        ]);

        DB::transaction(function () use ($request, $incubator, $validated): void {
            $baby = Baby::query()->lockForUpdate()->findOrFail($validated['baby_id']);
            if ($incubator->fresh()->current_baby_id !== null) {
                throw ValidationException::withMessages([
                    'baby_id' => 'Incubator already has an assigned baby.',
                ]);
            }
            if ($baby->currentPairing()->exists()) {
                throw ValidationException::withMessages([
                    'baby_id' => 'Baby is already assigned to another incubator.',
                ]);
            }

            $requestUser = $request->user();
            $userId = $requestUser ? $requestUser->id : null;

            DevicePairing::create([
                'incubator_id' => $incubator->id,
                'baby_id' => $baby->id,
                'paired_by_user_id' => $userId,
                'paired_at' => Carbon::now(),
                'status' => 'active',
            ]);
            $incubator->update(['current_baby_id' => $baby->id]);
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'incubator.paired',
                'target_type' => Incubator::class,
                'target_id' => $incubator->id,
                'details' => ['baby_id' => $baby->id],
                'ip_address' => $request->ip(),
            ]);
        });

        return Redirect::route('incubators.show', $incubator)->with('status', 'Baby paired.');
    }

    public function unpair(Request $request, Incubator $incubator): RedirectResponse
    {
        DB::transaction(function () use ($request, $incubator): void {
            $incubator = Incubator::query()->lockForUpdate()->findOrFail($incubator->id);
            $pairing = $incubator->pairings()->where('status', 'active')->lockForUpdate()->first();
            if ($pairing) {
                $pairing->update(['status' => 'completed', 'unpaired_at' => Carbon::now()]);
            }
            $babyId = $incubator->current_baby_id;
            $incubator->update(['current_baby_id' => null]);
            $requestUser = $request->user();
            AuditLog::create([
                'user_id' => $requestUser?->id,
                'action' => 'incubator.unpaired',
                'target_type' => Incubator::class,
                'target_id' => $incubator->id,
                'details' => ['baby_id' => $babyId],
                'ip_address' => $request->ip(),
            ]);
        });

        return Redirect::route('incubators.show', $incubator)->with('status', 'Baby unpaired.');
    }
}
