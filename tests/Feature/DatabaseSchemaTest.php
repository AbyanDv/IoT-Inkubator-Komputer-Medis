<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Baby;
use App\Models\DeviceConfig;
use App\Models\DeviceEvent;
use App\Models\DevicePairing;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeders_populate_default_data_correctly(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Verify users
        $superAdmin = User::where('email', 'superadmin@incubator.local')->first();
        $this->assertNotNull($superAdmin);
        $this->assertEquals('super_admin', $superAdmin->role);
        $this->assertTrue($superAdmin->is_active);
        $this->assertTrue(Hash::check('password', $superAdmin->password));
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->isAdmin());
        $this->assertTrue($superAdmin->hasRole('super_admin'));

        $admin = User::where('email', 'admin@incubator.local')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('admin', $admin->role);
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasRole(['admin', 'super_admin']));

        $operator = User::where('email', 'operator@incubator.local')->first();
        $this->assertNotNull($operator);
        $this->assertEquals('operator', $operator->role);
        $this->assertFalse($operator->isAdmin());
        $this->assertTrue($operator->hasRole('operator'));

        $viewer = User::where('email', 'viewer@incubator.local')->first();
        $this->assertNotNull($viewer);
        $this->assertEquals('viewer', $viewer->role);
        $this->assertFalse($viewer->isAdmin());
        $this->assertTrue($viewer->hasRole('viewer'));

        // Verify babies
        $this->assertDatabaseHas('babies', [
            'baby_code' => 'BAYI-001',
            'name' => 'Bayi Ny. Siti',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('babies', [
            'baby_code' => 'BAYI-002',
            'name' => 'Bayi Ny. Dewi',
            'status' => 'active',
        ]);

        // Verify incubator & config
        $incubator = Incubator::where('device_code', 'INC-001')->first();
        $this->assertNotNull($incubator);
        $this->assertEquals('24:6F:28:01:02:03', $incubator->mac_address);
        $this->assertEquals('dev-token-inc-001', $incubator->device_token);

        $this->assertNotNull($incubator->activeConfig);
        $this->assertEquals(1, $incubator->activeConfig->config_version);
        $this->assertEquals('30.00', (string) $incubator->activeConfig->temperature_setpoint);
        $this->assertTrue($incubator->activeConfig->is_active);
    }

    public function test_user_relationships_and_helpers(): void
    {
        $user = User::factory()->superAdmin()->create();
        $baby = Baby::factory()->create(['created_by' => $user->id]);
        $incubator = Incubator::factory()->create();

        $pairing = DevicePairing::factory()->create([
            'incubator_id' => $incubator->id,
            'baby_id' => $baby->id,
            'paired_by_user_id' => $user->id,
        ]);

        $auditLog = AuditLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'pair_device',
        ]);

        $this->assertTrue($user->devicePairings->contains($pairing));
        $this->assertTrue($user->auditLogs->contains($auditLog));
        $this->assertSame($user->id, $pairing->user->id);
        $this->assertSame($user->id, $auditLog->user->id);
    }

    public function test_baby_relationships_and_casts(): void
    {
        $user = User::factory()->admin()->create();
        $baby = Baby::factory()->create([
            'created_by' => $user->id,
            'weight_grams' => 2500,
            'length_cm' => 48.5,
            'gestational_age_weeks' => 36,
            'birth_date' => '2026-11-01 10:00:00',
        ]);

        $incubator = Incubator::factory()->create([
            'current_baby_id' => $baby->id,
        ]);

        $pairing = DevicePairing::factory()->create([
            'incubator_id' => $incubator->id,
            'baby_id' => $baby->id,
            'status' => 'active',
            'paired_at' => Carbon::now(),
        ]);

        $telemetry = DeviceTelemetry::factory()->create([
            'incubator_id' => $incubator->id,
            'baby_id' => $baby->id,
        ]);

        $this->assertInstanceOf(Carbon::class, $baby->birth_date);
        $this->assertIsInt($baby->weight_grams);
        $this->assertIsInt($baby->gestational_age_weeks);
        $this->assertIsFloat($baby->length_cm);

        $this->assertSame($user->id, $baby->creator->id);
        $this->assertSame($pairing->id, $baby->currentPairing->id);
        $this->assertSame($incubator->id, $baby->currentIncubator->id);
        $this->assertTrue($baby->telemetries->contains($telemetry));
    }

    public function test_incubator_relationships_and_active_states(): void
    {
        $baby = Baby::factory()->create();
        $incubator = Incubator::factory()->create([
            'current_baby_id' => $baby->id,
            'is_active' => true,
            'last_heartbeat_at' => Carbon::now(),
            'last_config_version' => 2,
        ]);

        $pairing = DevicePairing::factory()->create([
            'incubator_id' => $incubator->id,
            'baby_id' => $baby->id,
            'status' => 'active',
        ]);

        $configV1 = DeviceConfig::factory()->create([
            'incubator_id' => $incubator->id,
            'config_version' => 1,
            'is_active' => false,
        ]);

        $configV2 = DeviceConfig::factory()->create([
            'incubator_id' => $incubator->id,
            'config_version' => 2,
            'is_active' => true,
        ]);

        $event = DeviceEvent::factory()->create([
            'incubator_id' => $incubator->id,
            'event_type' => 'TEMP_HIGH',
            'priority' => 'warning',
        ]);

        $telemetry = DeviceTelemetry::factory()->create([
            'incubator_id' => $incubator->id,
            'baby_id' => $baby->id,
        ]);

        $this->assertTrue($incubator->is_active);
        $this->assertInstanceOf(Carbon::class, $incubator->last_heartbeat_at);
        $this->assertSame($baby->id, $incubator->currentBaby->id);
        $this->assertSame($pairing->id, $incubator->activePairing->id);
        $this->assertSame($configV2->id, $incubator->activeConfig->id);
        $this->assertTrue($incubator->events->contains($event));
        $this->assertTrue($incubator->telemetries->contains($telemetry));
        $this->assertTrue($incubator->configs->contains($configV1));
        $this->assertTrue($incubator->configs->contains($configV2));
    }

    public function test_device_telemetry_casts_and_relations(): void
    {
        $incubator = Incubator::factory()->create();
        $baby = Baby::factory()->create();

        $telemetry = DeviceTelemetry::create([
            'incubator_id' => $incubator->id,
            'baby_id' => $baby->id,
            'temperature_object' => 36.45,
            'temperature_room' => 29.50,
            'humidity' => 60.15,
            'heater_status' => true,
            'heater_pwm' => 180,
            'fan_status' => true,
            'fan_pwm' => 100,
            'blue_led_status' => false,
            'raw_payload' => ['ds18b20' => 'OK', 'dht22' => 'OK'],
            'recorded_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);

        $this->assertSame('36.45', (string) $telemetry->temperature_object);
        $this->assertSame('29.50', (string) $telemetry->temperature_room);
        $this->assertSame('60.15', (string) $telemetry->humidity);
        $this->assertTrue($telemetry->heater_status);
        $this->assertSame(180, $telemetry->heater_pwm);
        $this->assertTrue($telemetry->fan_status);
        $this->assertSame(100, $telemetry->fan_pwm);
        $this->assertFalse($telemetry->blue_led_status);
        $this->assertIsArray($telemetry->raw_payload);
        $this->assertSame('OK', $telemetry->raw_payload['ds18b20']);
        $this->assertInstanceOf(Carbon::class, $telemetry->recorded_at);
        $this->assertInstanceOf(Carbon::class, $telemetry->created_at);

        $this->assertSame($incubator->id, $telemetry->incubator->id);
        $this->assertSame($baby->id, $telemetry->baby->id);
    }

    public function test_device_config_casts_and_relations(): void
    {
        $incubator = Incubator::factory()->create();
        $user = User::factory()->admin()->create();

        $config = DeviceConfig::create([
            'incubator_id' => $incubator->id,
            'config_version' => 2,
            'temperature_setpoint' => 32.50,
            'pid_kp' => 2.500,
            'pid_ki' => 0.150,
            'pid_kd' => 0.600,
            'fan_mode' => 'pwm',
            'blue_led_enabled' => true,
            'simulation_mode' => false,
            'is_active' => true,
            'applied_at' => Carbon::now(),
            'created_by' => $user->id,
        ]);

        $this->assertSame(2, $config->config_version);
        $this->assertSame('32.50', (string) $config->temperature_setpoint);
        $this->assertSame('2.500', (string) $config->pid_kp);
        $this->assertSame('0.150', (string) $config->pid_ki);
        $this->assertSame('0.600', (string) $config->pid_kd);
        $this->assertTrue($config->blue_led_enabled);
        $this->assertFalse($config->simulation_mode);
        $this->assertTrue($config->is_active);
        $this->assertInstanceOf(Carbon::class, $config->applied_at);

        $this->assertSame($incubator->id, $config->incubator->id);
        $this->assertSame($user->id, $config->user->id);
        $this->assertSame($user->id, $config->creator->id);
    }

    public function test_device_event_and_audit_log_casts_and_relations(): void
    {
        $incubator = Incubator::factory()->create();
        $user = User::factory()->operator()->create();

        $event = DeviceEvent::create([
            'incubator_id' => $incubator->id,
            'event_type' => 'SENSOR_ERROR',
            'priority' => 'danger',
            'message' => 'DS18B20 disconnected or read -127C',
            'payload' => ['raw_temp' => -127.0],
            'resolved_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);

        $auditLog = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'resolve_event',
            'target_type' => DeviceEvent::class,
            'target_id' => $event->id,
            'details' => ['resolution' => 'Replaced DS18B20 wire'],
            'ip_address' => '127.0.0.1',
            'created_at' => Carbon::now(),
        ]);

        $this->assertSame($incubator->id, $event->incubator->id);
        $this->assertIsArray($event->payload);
        $this->assertEquals(-127.0, $event->payload['raw_temp']);
        $this->assertInstanceOf(Carbon::class, $event->resolved_at);

        $this->assertSame($user->id, $auditLog->user->id);
        $this->assertIsArray($auditLog->details);
        $this->assertSame($event->id, $auditLog->target_id);
    }
}
