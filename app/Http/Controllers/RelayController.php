<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Incubator;
use App\Services\MqttPublisher;
use App\Services\RelayControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class RelayController extends Controller
{
    public function __construct(
        private readonly RelayControlService $relays,
        private readonly MqttPublisher $mqtt,
    ) {}

    public function update(Request $request, Incubator $incubator): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'relay' => ['required', 'string', Rule::in(RelayControlService::RELAYS)],
            'on' => ['required', 'boolean'],
        ]);

        $overrides = $this->relays->set($incubator, $validated['relay'], $request->boolean('on'));

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'incubator.relay_test',
            'target_type' => Incubator::class,
            'target_id' => $incubator->id,
            'details' => [
                'relay' => $validated['relay'],
                'on' => $request->boolean('on'),
            ],
            'ip_address' => $request->ip(),
        ]);

        $this->mqtt->publishSync($incubator->fresh());

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'overrides' => (object) $overrides,
            ]);
        }

        return Redirect::back()->with('status', 'Relay test command sent.');
    }

    public function auto(Request $request, Incubator $incubator): JsonResponse|RedirectResponse
    {
        $this->relays->clear($incubator);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'incubator.relays_auto',
            'target_type' => Incubator::class,
            'target_id' => $incubator->id,
            'details' => [],
            'ip_address' => $request->ip(),
        ]);

        $this->mqtt->publishSync($incubator->fresh());

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'overrides' => (object) [],
            ]);
        }

        return Redirect::back()->with('status', 'Automatic relay control restored.');
    }
}
