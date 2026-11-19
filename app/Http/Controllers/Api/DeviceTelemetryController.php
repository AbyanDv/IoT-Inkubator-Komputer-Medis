<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTelemetryRequest;
use App\Models\Incubator;
use App\Services\DeviceIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class DeviceTelemetryController extends Controller
{
    public function __construct(private readonly DeviceIngestionService $ingestion) {}

    /**
     * Ingest telemetry from ESP32 and piggyback config sync + pairing state.
     */
    public function store(StoreTelemetryRequest $request): JsonResponse
    {
        /** @var Incubator $incubator */
        $incubator = $request->attributes->get('incubator');

        return Response::json($this->ingestion->recordTelemetry($incubator, $request->validated()));
    }
}
