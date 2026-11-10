<?php

namespace Tests\Feature;

use App\Models\Baby;
use App\Models\DeviceConfig;
use App\Models\DeviceEvent;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private Incubator $incubator;

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'device_code' => $this->incubator->device_code,
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->incubator = Incubator::factory()->create([
            'device_token' => 'test-token-abc123',
            'status' => 'offline',
        ]);
    }

    public function test_401_when_no_token(): void
    {
        $this->postJson('/api/devices/telemetry', $this->validPayload())
            ->assertUnauthorized()
            ->assertJson(['error' => 'Device token required']);
    }

    public function test_401_when_invalid_token(): void
    {
        $this->postJson('/api/devices/telemetry', $this->validPayload(), ['X-Device-Token' => 'wrong-token'])
            ->assertUnauthorized()
            ->assertJson(['error' => 'Invalid device token']);
    }

    public function test_bearer_token_authenticates_device(): void
    {
        $this->withToken('test-token-abc123')->postJson('/api/devices/heartbeat')
            ->assertOk();
    }

    public function test_401_when_incubator_inactive(): void
    {
        $this->incubator->update(['is_active' => false]);

        $this->postJson('/api/devices/telemetry', $this->validPayload(), ['X-Device-Token' => 'test-token-abc123'])
            ->assertUnauthorized();
    }

    public function test_heartbeat_marks_incubator_online(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10T12:00:00Z'));

        $this->postJson('/api/devices/heartbeat', [], ['X-Device-Token' => 'test-token-abc123'])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame('online', $this->incubator->fresh()->status);
        $this->assertSame('2026-11-10 12:00:00', $this->incubator->fresh()->last_heartbeat_at->toDateTimeString());
        Carbon::setTestNow();
    }

    public function test_stores_telemetry_and_updates_heartbeat(): void
    {
        $this->postJson('/api/devices/telemetry', $this->validPayload(), ['X-Device-Token' => 'test-token-abc123'])
            ->assertOk()
            ->assertJson(['status' => 'success', 'is_paired' => false, 'baby' => null]);

        $this->assertDatabaseHas('device_telemetries', [
            'incubator_id' => $this->incubator->id,
            'temperature_object' => 36.5,
            'humidity' => 55.0,
            'heater_pwm' => 180,
        ]);
        $this->assertSame('online', $this->incubator->fresh()->status);
        $this->assertNotNull($this->incubator->fresh()->last_heartbeat_at);
    }

    public function test_config_sync_when_version_stale(): void
    {
        DeviceConfig::factory()->create([
            'incubator_id' => $this->incubator->id,
            'config_version' => 3,
            'is_active' => true,
            'temperature_setpoint' => 36.50,
            'pid_kp' => 2.000,
            'pid_ki' => 0.500,
            'pid_kd' => 0.100,
        ]);

        $this->postJson('/api/devices/telemetry', $this->validPayload(['applied_config_version' => 2]), ['X-Device-Token' => 'test-token-abc123'])
            ->assertOk()
            ->assertJson(['has_config_update' => true, 'server_config_version' => 3])
            ->assertJsonPath('config.version', 3)
            ->assertJsonPath('config.temperature_setpoint', 36.5);
    }

    public function test_no_config_update_when_version_current(): void
    {
        DeviceConfig::factory()->create(['incubator_id' => $this->incubator->id, 'config_version' => 2, 'is_active' => true]);

        $this->postJson('/api/devices/telemetry', $this->validPayload(['applied_config_version' => 2]), ['X-Device-Token' => 'test-token-abc123'])
            ->assertOk()
            ->assertJson(['has_config_update' => false, 'config' => null]);
    }

    public function test_paired_baby_is_in_response_and_telemetry(): void
    {
        $baby = Baby::factory()->create(['baby_code' => 'BB-001', 'name' => 'Baby Test']);
        $this->incubator->update(['current_baby_id' => $baby->id]);

        $this->postJson('/api/devices/telemetry', $this->validPayload(), ['X-Device-Token' => 'test-token-abc123'])
            ->assertOk()
            ->assertJson(['is_paired' => true, 'baby' => ['code' => 'BB-001', 'name' => 'Baby Test']]);

        $this->assertDatabaseHas('device_telemetries', ['incubator_id' => $this->incubator->id, 'baby_id' => $baby->id]);
    }

    public function test_device_event_is_logged(): void
    {
        $this->postJson('/api/devices/events', [
            'event_type' => 'watchdog_reset',
            'priority' => 'warning',
            'message' => 'Watchdog reset',
            'payload' => ['reason' => 'timeout'],
        ], ['X-Device-Token' => 'test-token-abc123'])->assertCreated();

        $this->assertDatabaseHas('device_events', [
            'incubator_id' => $this->incubator->id,
            'event_type' => 'watchdog_reset',
            'priority' => 'warning',
            'message' => 'Watchdog reset',
        ]);
    }

    public function test_config_fetch_and_acknowledgment(): void
    {
        DeviceConfig::factory()->create(['incubator_id' => $this->incubator->id, 'config_version' => 2, 'is_active' => true]);
        $headers = ['X-Device-Token' => 'test-token-abc123'];

        $this->getJson('/api/devices/config', $headers)
            ->assertOk()
            ->assertJsonPath('config.version', 2);
        $this->postJson('/api/devices/ack', ['config_version' => 2], $headers)->assertOk();

        $this->assertSame(2, $this->incubator->fresh()->last_config_version);
        $this->assertNotNull(DeviceConfig::query()->where('incubator_id', $this->incubator->id)->first()->applied_at);
    }

    public function test_validation_rejects_invalid_telemetry(): void
    {
        $this->postJson('/api/devices/telemetry', ['temperature_object' => 'not-a-number'], ['X-Device-Token' => 'test-token-abc123'])
            ->assertUnprocessable();
    }

    public function test_sensor_fault_creates_device_event(): void
    {
        $this->postJson('/api/devices/telemetry', $this->validPayload([
            'sensor_status' => ['ds18b20' => false, 'dht22' => true],
        ]), ['X-Device-Token' => 'test-token-abc123'])->assertOk();

        $this->assertDatabaseHas('device_events', [
            'incubator_id' => $this->incubator->id,
            'event_type' => 'sensor_fault',
            'priority' => 'danger',
        ]);
    }

    public function test_live_telemetry_returns_latest_and_no_data(): void
    {
        $this->getJson("/api/incubators/{$this->incubator->id}/live-telemetry")
            ->assertOk()
            ->assertJson(['status' => 'no_data', 'telemetry' => null]);

        DeviceTelemetry::factory()->create([
            'incubator_id' => $this->incubator->id,
            'temperature_object' => 36.5,
            'created_at' => Carbon::now(),
        ]);

        $this->getJson("/api/incubators/{$this->incubator->id}/live-telemetry")
            ->assertOk()
            ->assertJsonPath('telemetry.temperature_object', 36.5);
    }
}
