<?php

namespace Tests\Feature;

use App\Models\DeviceConfig;
use App\Models\Incubator;
use App\Models\User;
use App\Services\MqttMessageHandler;
use App\Services\MqttPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MqttIngestionTest extends TestCase
{
    use RefreshDatabase;

    private Incubator $incubator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->incubator = Incubator::factory()->create([
            'device_code' => 'INC-001',
            'status' => 'offline',
        ]);
    }

    /** @return array<string, mixed> */
    private function telemetry(array $overrides = []): array
    {
        return array_merge([
            'device_code' => 'INC-001',
            'applied_config_version' => 1,
            'temperature_object' => 36.5,
            'temperature_room' => 28.1,
            'humidity' => 55.0,
            'heater_pwm' => 180,
            'fan_pwm' => 120,
            'blue_led_status' => true,
            'sensor_status' => ['ds18b20' => true, 'dht22' => true],
            'timestamp' => '2026-11-10T10:00:00Z',
        ], $overrides);
    }

    private function handle(string $kind, array $payload): void
    {
        $this->app->make(MqttMessageHandler::class)->handle(
            "incubator/INC-001/{$kind}",
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    public function test_telemetry_is_stored_and_sync_payload_is_published(): void
    {
        $published = null;

        $publisher = Mockery::mock(MqttPublisher::class);
        $publisher->shouldReceive('publishSync')
            ->once()
            ->withArgs(function (Incubator $incubator, array $payload) use (&$published): bool {
                $published = $payload;

                return $incubator->is($this->incubator);
            });
        $this->app->instance(MqttPublisher::class, $publisher);

        $this->handle('telemetry', $this->telemetry());

        $this->assertDatabaseHas('device_telemetries', [
            'incubator_id' => $this->incubator->id,
            'temperature_object' => 36.5,
            'humidity' => 55.0,
        ]);
        $this->assertSame('online', $this->incubator->fresh()->status);
        $this->assertSame('success', $published['status']);
        $this->assertFalse($published['has_config_update']);
        $this->assertNull($published['config']);
    }

    public function test_telemetry_publishes_new_config_when_device_is_stale(): void
    {
        DeviceConfig::factory()->create([
            'incubator_id' => $this->incubator->id,
            'config_version' => 3,
            'is_active' => true,
            'temperature_setpoint' => 36.50,
        ]);

        $published = null;

        $publisher = Mockery::mock(MqttPublisher::class);
        $publisher->shouldReceive('publishSync')
            ->once()
            ->withArgs(function (Incubator $incubator, array $payload) use (&$published): bool {
                $published = $payload;

                return true;
            });
        $this->app->instance(MqttPublisher::class, $publisher);

        $this->handle('telemetry', $this->telemetry(['applied_config_version' => 2]));

        $this->assertTrue($published['has_config_update']);
        $this->assertSame(3, $published['server_config_version']);
        $this->assertSame(3, $published['config']['version']);
    }

    public function test_telemetry_acknowledges_applied_config_version(): void
    {
        DeviceConfig::factory()->create([
            'incubator_id' => $this->incubator->id,
            'config_version' => 3,
            'is_active' => true,
        ]);

        $this->handle('telemetry', $this->telemetry(['applied_config_version' => 3]));

        $this->assertSame(3, $this->incubator->fresh()->last_config_version);
        $this->assertNotNull(
            DeviceConfig::query()
                ->where('incubator_id', $this->incubator->id)
                ->where('config_version', 3)
                ->first()
                ->applied_at,
        );
    }

    public function test_telemetry_with_mismatched_device_code_is_ignored(): void
    {
        $this->handle('telemetry', $this->telemetry(['device_code' => 'INC-999']));

        $this->assertDatabaseCount('device_telemetries', 0);
    }

    public function test_invalid_telemetry_is_ignored(): void
    {
        $this->handle('telemetry', $this->telemetry(['temperature_object' => 'hot']));

        $this->assertDatabaseCount('device_telemetries', 0);
    }

    public function test_device_event_is_stored(): void
    {
        $this->handle('events', [
            'event_type' => 'watchdog_reset',
            'priority' => 'warning',
            'message' => 'Watchdog reset',
            'payload' => ['reason' => 'timeout'],
        ]);

        $this->assertDatabaseHas('device_events', [
            'incubator_id' => $this->incubator->id,
            'event_type' => 'watchdog_reset',
            'priority' => 'warning',
        ]);
    }

    public function test_status_offline_marks_incubator_offline(): void
    {
        $this->incubator->update(['status' => 'online']);

        $this->handle('status', ['online' => false]);

        $this->assertSame('offline', $this->incubator->fresh()->status);
    }

    public function test_unknown_device_is_ignored(): void
    {
        $handler = $this->app->make(MqttMessageHandler::class);

        $handler->handle(
            'incubator/INC-999/telemetry',
            json_encode($this->telemetry(), JSON_THROW_ON_ERROR),
        );

        $this->assertDatabaseCount('device_telemetries', 0);
    }

    public function test_config_update_publishes_sync(): void
    {
        $publisher = Mockery::mock(MqttPublisher::class);
        $publisher->shouldReceive('publishSync')->once();
        $this->app->instance(MqttPublisher::class, $publisher);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('incubators.config.update', $this->incubator), [
                'temperature_setpoint' => 36.5,
                'pid_kp' => 2,
                'pid_ki' => 0.1,
                'pid_kd' => 0.5,
                'fan_mode' => 'pwm',
                'blue_led_enabled' => true,
                'simulation_mode' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('device_configs', [
            'incubator_id' => $this->incubator->id,
            'config_version' => 1,
        ]);
    }
}
