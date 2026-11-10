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

    /**
     * @return array<string, mixed>
     */
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
        $response = $this->postJson('/api/devices/telemetry', $this->validPayload());

        $response->assertStatus(401)
            ->assertJson(['error' => 'Device token required']);
    }

    public function test_401_when_invalid_token(): void
    {
        $response = $this->postJson('/api/devices/telemetry', $this->validPayload(), [
            'X-Device-Token' => 'wrong-token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid device token']);
    }

    public function test_401_when_incubator_inactive(): void
    {
        $this->incubator->update(['is_active' => false]);

        $response = $this->postJson('/api/devices/telemetry', $this->validPayload(), [
            'X-Device-Token' => 'test-token-abc123',
        ]);

        $response->assertStatus(401);
    }

    public function test_stores_telemetry_and_updates_heartbeat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10T12:00:00Z'));

        $response = $this->postJson('/api/devices/telemetry', $this->validPayload(), [
            'X-Device-Token' => 'test-token-abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'is_paired' => false,
                'baby' => null,
            ]);

        $this->assertDatabaseHas('device_telemetries', [
            'incubator_id' => $this->incubator->id,
            'temperature_object' => 36.5,
            'humidity' => 55.0,
            'heater_pwm' => 180,
        ]);

        $this->incubator->refresh();
        $this->assertEquals('online', $this->incubator->status);
        $this->assertNotNull($this->incubator->last_heartbeat_at);

        Carbon::setTestNow();
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

        $response = $this->postJson('/api/devices/telemetry', $this->validPayload([
            'applied_config_version' => 2,
        ]), [
            'X-Device-Token' => 'test-token-abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'has_config_update' => true,
                'server_config_version' => 3,
            ]);

        $this->assertNotNull($response->json('config'));
        $this->assertEquals(3, $response->json('config.version'));
        $this->assertEquals(36.50, $response->json('config.temperature_setpoint'));
    }

    public function test_no_config_update_when_version_current(): void
    {
        DeviceConfig::factory()->create([
            'incubator_id' => $this->incubator->id,
            'config_version' => 2,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/devices/telemetry', $this->validPayload([
            'applied_config_version' => 2,
        ]), [
            'X-Device-Token' => 'test-token-abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'has_config_update' => false,
                'config' => null,
            ]);
    }

    public function test_paired_baby_included_in_response(): void
    {
        $baby = Baby::factory()->create([
            'baby_code' => 'BB-001',
            'name' => 'Baby Test',
        ]);
        $this->incubator->update(['current_baby_id' => $baby->id]);

        $response = $this->postJson('/api/devices/telemetry', $this->validPayload(), [
            'X-Device-Token' => 'test-token-abc123',
        ]);

        $response->assertOk()
            ->assertJson([
                'is_paired' => true,
                'baby' => [
                    'code' => 'BB-001',
                    'name' => 'Baby Test',
                ],
            ]);

        // Telemetry linked to baby
        $this->assertDatabaseHas('device_telemetries', [
            'incubator_id' => $this->incubator->id,
            'baby_id' => $baby->id,
        ]);
    }

    public function test_sensor_fault_creates_device_event(): void
    {
        $response = $this->postJson('/api/devices/telemetry', $this->validPayload([
            'sensor_status' => ['ds18b20' => false, 'dht22' => true],
        ]), [
            'X-Device-Token' => 'test-token-abc123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('device_events', [
            'incubator_id' => $this->incubator->id,
            'event_type' => 'sensor_fault',
            'priority' => 'danger',
        ]);

        $event = DeviceEvent::where('incubator_id', $this->incubator->id)->first();
        $this->assertStringContainsString('ds18b20', $event->message);
    }

    public function test_multiple_sensor_faults_create_multiple_events(): void
    {
        $this->postJson('/api/devices/telemetry', $this->validPayload([
            'sensor_status' => ['ds18b20' => false, 'dht22' => false],
        ]), [
            'X-Device-Token' => 'test-token-abc123',
        ]);

        $this->assertEquals(2, DeviceEvent::where('incubator_id', $this->incubator->id)->count());
    }

    public function test_validation_rejects_invalid_data(): void
    {
        $response = $this->postJson('/api/devices/telemetry', [
            'device_code' => '',
            'temperature_object' => 'not-a-number',
            'humidity' => 200,
        ], [
            'X-Device-Token' => 'test-token-abc123',
        ]);

        $response->assertStatus(422);
    }

    public function test_live_telemetry_returns_latest(): void
    {
        DeviceTelemetry::factory()->create([
            'incubator_id' => $this->incubator->id,
            'temperature_object' => 35.0,
            'created_at' => Carbon::now()->subMinutes(5),
        ]);

        $latest = DeviceTelemetry::factory()->create([
            'incubator_id' => $this->incubator->id,
            'temperature_object' => 36.5,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->getJson("/api/incubators/{$this->incubator->id}/live-telemetry");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'telemetry' => [
                    'temperature_object' => 36.5,
                ],
            ]);
    }

    public function test_live_telemetry_no_data(): void
    {
        $response = $this->getJson("/api/incubators/{$this->incubator->id}/live-telemetry");

        $response->assertOk()
            ->assertJson([
                'status' => 'no_data',
                'telemetry' => null,
            ]);
    }
}
