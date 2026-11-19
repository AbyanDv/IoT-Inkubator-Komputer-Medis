<?php

namespace App\Console\Commands;

use App\Services\MqttMessageHandler;
use Illuminate\Console\Command;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe';

    protected $description = 'Subscribe to device telemetry, events, and status over MQTT';

    private ?MqttClient $client = null;

    private bool $stopped = false;

    public function handle(MqttMessageHandler $handler): int
    {
        if (! config('mqtt-client.enabled')) {
            $this->error('MQTT is disabled. Set MQTT_ENABLED=true and configure the broker.');

            return self::FAILURE;
        }

        $prefix = trim((string) config('mqtt-client.topic_prefix', 'incubator'), '/');

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function (): void {
                $this->stopped = true;
                $this->client?->interrupt();
            });
            pcntl_signal(SIGTERM, function (): void {
                $this->stopped = true;
                $this->client?->interrupt();
            });
        }

        $this->info("Listening on {$prefix}/+/telemetry, events, and status.");

        while (! $this->stopped) {
            try {
                $this->client = MQTT::connection();

                foreach (['telemetry', 'events', 'status'] as $kind) {
                    $this->client->subscribe(
                        "{$prefix}/+/{$kind}",
                        function (string $topic, string $message) use ($handler): void {
                            try {
                                $handler->handle($topic, $message);
                            } catch (Throwable $exception) {
                                report($exception);
                            }
                        },
                        1,
                    );
                }

                $this->client->loop(true);
            } catch (Throwable $exception) {
                if ($this->stopped) {
                    break;
                }

                report($exception);
                $this->warn('MQTT connection lost; reconnecting in 5 seconds.');

                try {
                    MQTT::disconnect();
                } catch (Throwable) {
                    //
                }

                sleep(5);
            }
        }

        return self::SUCCESS;
    }
}
