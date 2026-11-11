<?php

namespace Tests\Feature;

use App\Models\Baby;
use App\Models\DeviceConfig;
use App\Models\DevicePairing;
use App\Models\Incubator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BabyAndPairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_baby_and_invalid_payload_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/babies', [
            'baby_code' => 'BABY-100', 'name' => 'Test Baby', 'birth_date' => '2026-10-01',
            'gestational_age_weeks' => 32, 'weight_grams' => 1800, 'length_cm' => 42,
            'gender' => 'female', 'notes' => 'Simulation',
        ])->assertRedirect();
        $this->assertDatabaseHas('babies', ['baby_code' => 'BABY-100', 'status' => 'active', 'created_by' => $admin->id]);
        $this->from('/babies')->post('/babies', [])->assertRedirect('/babies')->assertSessionHasErrors(['baby_code', 'name', 'birth_date']);
    }

    public function test_baby_and_incubator_pages_expose_crud_and_device_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $baby = Baby::factory()->create();
        $incubator = Incubator::factory()->create([
            'status' => 'online',
            'last_heartbeat_at' => Carbon::now(),
        ]);

        $this->actingAs($admin)->get('/babies')
            ->assertOk()->assertSee(route('babies.store'))->assertSee(route('babies.show', $baby));
        $this->get(route('babies.show', $baby))
            ->assertOk()->assertSee(route('babies.update', $baby))->assertSee(route('babies.destroy', $baby))->assertSee('name="baby_code"', false);
        $this->put(route('babies.update', $baby), [
            'baby_code' => $baby->baby_code, 'name' => 'Updated baby', 'birth_date' => '2026-10-01',
            'gestational_age_weeks' => 32, 'weight_grams' => 1800, 'length_cm' => 42, 'gender' => 'female',
        ])->assertRedirect(route('babies.show', $baby));

        $this->get('/incubators')->assertOk()->assertSee('Last heartbeat')->assertSee($incubator->name);
        $this->get(route('incubators.show', $incubator))
            ->assertOk()->assertSee('Live telemetry')->assertSee('Baby assignment')->assertSee('Device configuration');
        $this->get(route('incubators.config.edit', $incubator))
            ->assertOk()->assertSee(route('incubators.config.update', $incubator))->assertSee('temperature_setpoint');
    }

    public function test_baby_status_filter_rejects_undefined_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/babies?status=unknown')
            ->assertRedirect('/')
            ->assertSessionHasErrors('status');
    }

    public function test_pairing_is_atomic_and_rejects_baby_already_paired(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $baby = Baby::factory()->create();
        $incubator = Incubator::factory()->create();
        $this->actingAs($admin)->post(route('incubators.pair', $incubator), ['baby_id' => $baby->id])->assertRedirect();
        $this->assertDatabaseHas('device_pairings', ['incubator_id' => $incubator->id, 'baby_id' => $baby->id, 'status' => 'active']);
        $this->assertDatabaseHas('incubators', ['id' => $incubator->id, 'current_baby_id' => $baby->id]);
        $other = Incubator::factory()->create();
        $this->from(route('incubators.show', $other))->post(route('incubators.pair', $other), ['baby_id' => $baby->id])->assertRedirect(route('incubators.show', $other))->assertSessionHasErrors('baby_id');
        $this->assertDatabaseMissing('device_pairings', ['incubator_id' => $other->id, 'baby_id' => $baby->id]);
    }

    public function test_unpairing_completes_pairing_and_preserves_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $baby = Baby::factory()->create();
        $incubator = Incubator::factory()->create(['current_baby_id' => $baby->id]);
        $pairing = DevicePairing::factory()->create(['incubator_id' => $incubator->id, 'baby_id' => $baby->id, 'status' => 'active']);
        $this->actingAs($admin)->post(route('incubators.unpair', $incubator))->assertRedirect();
        $this->assertDatabaseHas('device_pairings', ['id' => $pairing->id, 'status' => 'completed']);
        $this->assertNotNull($pairing->fresh()->unpaired_at);
        $this->assertDatabaseHas('incubators', ['id' => $incubator->id, 'current_baby_id' => null]);
    }

    public function test_config_is_restricted_to_admin_roles(): void
    {
        $incubator = Incubator::factory()->create();

        foreach (['operator', 'viewer'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('incubators.config.edit', $incubator))->assertForbidden();
            $this->actingAs($user)->post(route('incubators.config.update', $incubator), [
                'temperature_setpoint' => 34.5, 'pid_kp' => 2.1, 'pid_ki' => 0.2, 'pid_kd' => 0.4, 'fan_mode' => 'pwm',
            ])->assertForbidden();
        }
    }

    public function test_config_update_increments_version_and_audits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $incubator = Incubator::factory()->create();
        DeviceConfig::factory()->create(['incubator_id' => $incubator->id, 'config_version' => 4, 'is_active' => true]);
        $this->actingAs($admin)->post(route('incubators.config.update', $incubator), [
            'temperature_setpoint' => 34.5, 'pid_kp' => 2.1, 'pid_ki' => 0.2, 'pid_kd' => 0.4, 'fan_mode' => 'pwm',
        ])->assertRedirect();
        $this->assertDatabaseHas('device_configs', ['incubator_id' => $incubator->id, 'config_version' => 5, 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incubator.config_updated', 'target_id' => $incubator->id]);
    }

    public function test_completing_paired_baby_closes_pairing_and_clears_incubator(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $baby = Baby::factory()->create();
        $incubator = Incubator::factory()->create(['current_baby_id' => $baby->id]);
        $pairing = DevicePairing::factory()->create(['incubator_id' => $incubator->id, 'baby_id' => $baby->id, 'status' => 'active']);

        $this->actingAs($admin)->delete(route('babies.destroy', $baby))->assertRedirect();

        $this->assertDatabaseHas('babies', ['id' => $baby->id, 'status' => 'completed']);
        $this->assertDatabaseHas('device_pairings', ['id' => $pairing->id, 'status' => 'completed']);
        $this->assertDatabaseHas('incubators', ['id' => $incubator->id, 'current_baby_id' => null]);
    }

    public function test_live_telemetry_endpoint_returns_latest_record_and_paired_baby(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $baby = Baby::factory()->create();
        $incubator = Incubator::factory()->create(['current_baby_id' => $baby->id]);
        $incubator->telemetries()->create(['temperature_object' => 33, 'temperature_room' => 25, 'humidity' => 40, 'heater_pwm' => 10, 'fan_pwm' => 20, 'recorded_at' => '2026-01-01 00:00:00']);
        $incubator->telemetries()->create(['temperature_object' => 34, 'temperature_room' => 25, 'humidity' => 41, 'heater_pwm' => 11, 'fan_pwm' => 21, 'recorded_at' => '2026-01-01 00:01:00']);
        $this->actingAs($admin)->getJson(route('api.incubators.live-telemetry', $incubator))
            ->assertOk()->assertJsonPath('telemetry.temperature_object', 34)->assertJsonPath('baby.name', $baby->name);
    }
}
