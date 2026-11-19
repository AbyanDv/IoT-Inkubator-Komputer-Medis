<?php

namespace App\Services;

use App\Models\Incubator;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

class MqttPublisher
{
    public function __construct(private readonly DeviceIngestionService $ingestion) {}

    /**
     * Publish the piggybacked sync payload. A failed publish must never fail
     * the HTTP request that triggered it.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function publishSync(Incubator $incubator, ?array $payload = null): void
    {
        if (! config('mqtt-client.enabled')) {
            return;
        }

        $payload ??= $this->ingestion->syncPayload($incubator);

        try {
            MQTT::publish(
                $this->topicFor($incubator->device_code, 'sync'),
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                true,
            );
        } catch (Throwable $exception) {
            Log::warning('MQTT sync publish failed.', [
                'device_code' => $incubator->device_code,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function topicFor(string $deviceCode, string $suffix): string
    {
        $prefix = trim((string) config('mqtt-client.topic_prefix', 'incubator'), '/');

        return "{$prefix}/{$deviceCode}/{$suffix}";
    }
}
