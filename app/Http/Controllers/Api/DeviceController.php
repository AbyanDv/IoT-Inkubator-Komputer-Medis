<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incubator;
use App\Services\DeviceIngestionService;
use App\Services\RelayControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceIngestionService $ingestion,
        private readonly RelayControlService $relays,
    ) {}

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
            'relay_overrides' => (object) $this->relays->overrides($this->incubator($request)),
        ]);
    }

    private function incubator(Request $request): Incubator
    {
        /** @var Incubator $incubator */
        $incubator = $request->attributes->get('incubator');

        return $incubator;
    }
}
