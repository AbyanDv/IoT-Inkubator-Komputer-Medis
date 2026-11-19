<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incubator;
use App\Services\DeviceIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;

class DeviceController extends Controller
{
    public function __construct(private readonly DeviceIngestionService $ingestion) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $this->ingestion->markOnline($this->incubator($request));

        return Response::json(['status' => 'success', 'message' => 'Heartbeat acknowledged']);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $request->validate(DeviceIngestionService::eventRules());

        $this->ingestion->recordEvent($this->incubator($request), $validated);

        return Response::json(['status' => 'success'], 201);
    }

    public function config(Request $request): JsonResponse
    {
        $config = $this->incubator($request)->activeConfig;

        return Response::json([
            'status' => 'success',
            'config' => $config ? $this->ingestion->configPayload($config) : null,
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
}
