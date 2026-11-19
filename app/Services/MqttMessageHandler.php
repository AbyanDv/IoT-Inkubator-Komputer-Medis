<?php

namespace App\Services;

use App\Http\Requests\StoreTelemetryRequest;
use App\Models\Incubator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JsonException;
use Throwable;

class MqttMessageHandler
{
    public function __construct(
        private readonly DeviceIngestionService $ingestion,
        private readonly MqttPublisher $publisher,
    ) {}

    public function handle(string $topic, string $message): void
    {
        $prefix = trim((string) config('mqtt-client.topic_prefix', 'incubator'), '/');
        $parts = explode('/', $topic);

        if (count($parts) !== 3 || $parts[0] !== $prefix) {
            return;
        }

        [, $deviceCode, $kind] = $parts;

        $incubator = Incubator::query()
            ->where('device_code', $deviceCode)
            ->where('is_active', true)
            ->first();

        if (! $incubator) {
            Log::warning('MQTT message from unknown device.', ['topic' => $topic]);

            return;
        }

        try {
            $payload = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('MQTT message is not valid JSON.', [
                'topic' => $topic,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (! is_array($payload)) {
            Log::warning('MQTT message payload must be an object.', ['topic' => $topic]);

            return;
        }

        try {
            match ($kind) {
                'telemetry' => $this->handleTelemetry($incubator, $deviceCode, $payload),
                'events' => $this->handleEvent($incubator, $payload),
                'status' => $this->handleStatus($incubator, $payload),
                default => null,
            };
        } catch (Throwable $exception) {
            Log::error('MQTT message handling failed.', [
                'topic' => $topic,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleTelemetry(Incubator $incubator, string $deviceCode, array $payload): void
    {
        if (isset($payload['device_code']) && $payload['device_code'] !== $deviceCode) {
            Log::warning('MQTT telemetry device code does not match its topic.', [
                'device_code' => $deviceCode,
            ]);

            return;
        }

        $payload['device_code'] = $deviceCode;

        $validator = Validator::make($payload, StoreTelemetryRequest::payloadRules());

        if ($validator->fails()) {
            Log::warning('MQTT telemetry validation failed.', [
                'device_code' => $deviceCode,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        $response = $this->ingestion->recordTelemetry($incubator, $validator->validated());

        $this->publisher->publishSync($incubator, $response);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleEvent(Incubator $incubator, array $payload): void
    {
        $validator = Validator::make($payload, DeviceIngestionService::eventRules());

        if ($validator->fails()) {
            Log::warning('MQTT event validation failed.', [
                'device_code' => $incubator->device_code,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        $this->ingestion->recordEvent($incubator, $validator->validated());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleStatus(Incubator $incubator, array $payload): void
    {
        $validator = Validator::make($payload, ['online' => ['required', 'boolean']]);

        if ($validator->fails()) {
            return;
        }

        if ((bool) $validator->validated()['online']) {
            $this->ingestion->markOnline($incubator);
        } else {
            $this->ingestion->markOffline($incubator);
        }
    }
}
