<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Baby;
use App\Models\DevicePairing;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use App\Models\User;
use App\Services\MqttPublisher;
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
    public function __construct(private readonly MqttPublisher $mqtt) {}

    public function index(): View
    {
        return ViewFacade::make('incubators.index', [
            'incubators' => Incubator::with(['currentBaby', 'latestTelemetry', 'activeConfig'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Incubator $incubator): View
    {
        $incubator->load(['currentBaby', 'activeConfig']);
        $latest = $incubator->telemetries()->latest('created_at')->first();
        $history = $incubator->telemetries()->latest('created_at')->take(20)->get()->reverse()->values();

        /** @var User|null $user */
        $user = Auth::user();

        return ViewFacade::make('incubators.show', [
            'incubator' => $incubator,
            'config' => $incubator->activeConfig,
            'initialTelemetry' => $latest ? [
                'status' => 'success',
                'telemetry' => [
                    'temperature_object' => (float) $latest->temperature_object,
                    'temperature_room' => (float) $latest->temperature_room,
                    'humidity' => (float) $latest->humidity,
                    'heater_pwm' => $latest->heater_pwm,
                    'fan_pwm' => $latest->fan_pwm,
                    'blue_led_status' => $latest->blue_led_status,
                    'recorded_at' => $latest->recorded_at?->toIso8601String(),
                    'sensor_status' => $latest->raw_payload['sensor_status'] ?? null,
                ],
            ] : ['status' => 'no_data', 'telemetry' => null],
            'history' => $history->map(fn (DeviceTelemetry $item): array => [
                'temperature' => (float) $item->temperature_object,
                'temperature_room' => (float) $item->temperature_room,
                'humidity' => (float) $item->humidity,
                'time' => $item->recorded_at?->toIso8601String() ?? $item->created_at?->toIso8601String(),
            ]),
            'events' => $incubator->events()->latest('created_at')->take(6)->get(),
            'unassignedBabies' => Baby::where('status', 'active')
                ->whereDoesntHave('currentPairing')
                ->whereDoesntHave('currentIncubator')
                ->orderBy('name')
                ->get(),
            'canManagePairing' => $user?->hasRole(['admin', 'super_admin']) ?? false,
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
            if ($baby->status !== 'active') {
                throw ValidationException::withMessages([
                    'baby_id' => 'Only active babies can be assigned.',
                ]);
            }
            if ($baby->currentPairing()->exists() || $baby->currentIncubator()->exists()) {
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

        $this->mqtt->publishSync($incubator->fresh());

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

        $this->mqtt->publishSync($incubator->fresh());

        return Redirect::route('incubators.show', $incubator)->with('status', 'Baby unpaired.');
    }
}
