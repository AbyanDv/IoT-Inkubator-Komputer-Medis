<?php

namespace Tests\Feature;

use App\Models\Baby;
use App\Models\DeviceConfig;
use App\Models\DevicePairing;
use App\Models\Incubator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncubatorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_pair_and_unpair_baby_with_audit_records(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $baby = Baby::factory()->create();
        $incubator = Incubator::factory()->create();

        $this->actingAs($user)->post('/incubators/'.$incubator->id.'/pair', ['baby_id' => $baby->id])->assertRedirect();
        $this->assertDatabaseHas('device_pairings', ['incubator_id' => $incubator->id, 'baby_id' => $baby->id, 'status' => 'active']);
        $this->assertDatabaseHas('incubators', ['id' => $incubator->id, 'current_baby_id' => $baby->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incubator.paired', 'target_id' => $incubator->id]);

        $this->post('/incubators/'.$incubator->id.'/unpair')->assertRedirect();
        $this->assertDatabaseHas('device_pairings', ['incubator_id' => $incubator->id, 'status' => 'completed']);
        $this->assertDatabaseHas('incubators', ['id' => $incubator->id, 'current_baby_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incubator.unpaired', 'target_id' => $incubator->id]);
    }

    public function test_prevents_assigning_baby_to_second_incubator(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $baby = Baby::factory()->create();
        $first = Incubator::factory()->create(['current_baby_id' => $baby->id]);
        DevicePairing::factory()->create(['incubator_id' => $first->id, 'baby_id' => $baby->id, 'status' => 'active']);
        $second = Incubator::factory()->create();

        $this->actingAs($user)->post('/incubators/'.$second->id.'/pair', ['baby_id' => $baby->id])->assertUnprocessable();
        $this->assertDatabaseMissing('device_pairings', ['incubator_id' => $second->id, 'baby_id' => $baby->id]);
    }

    public function test_operator_and_viewer_cannot_pair_or_unpair(): void
    {
        $baby = Baby::factory()->create();
        $incubator = Incubator::factory()->create();

        foreach (['operator', 'viewer'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post('/incubators/'.$incubator->id.'/pair', ['baby_id' => $baby->id])->assertForbidden();
            $this->post('/incubators/'.$incubator->id.'/unpair')->assertForbidden();
        }
    }

    public function test_admin_and_super_admin_can_pair_and_unpair(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $baby = Baby::factory()->create();
            $incubator = Incubator::factory()->create();

            $this->actingAs($user)->post('/incubators/'.$incubator->id.'/pair', ['baby_id' => $baby->id])->assertRedirect();
            $this->post('/incubators/'.$incubator->id.'/unpair')->assertRedirect();
        }
    }

    public function test_config_versions_increment(): void
    {
        $incubator = Incubator::factory()->create();
        $config = DeviceConfig::factory()->create(['incubator_id' => $incubator->id, 'config_version' => 2, 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/incubators/'.$incubator->id.'/config', [
            'temperature_setpoint' => 34.5, 'pid_kp' => 2.1, 'pid_ki' => 0.2, 'pid_kd' => 0.4, 'fan_mode' => 'pwm',
        ])->assertRedirect();

        $this->assertDatabaseHas('device_configs', ['incubator_id' => $incubator->id, 'config_version' => 3, 'is_active' => true]);
        $this->assertDatabaseHas('device_configs', ['id' => $config->id, 'is_active' => false]);
    }

    public function test_incubator_pages_require_authentication(): void
    {
        $incubator = Incubator::factory()->create();

        $this->get('/incubators')->assertRedirect();
        $this->get('/incubators/'.$incubator->id)->assertRedirect();
    }
}
