<?php

namespace Tests\Feature;

use App\Models\Baby;
use App\Models\User;
use Database\Seeders\TemperatureProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TemperatureProfileSeeder::class);
        config(['incubator.api_key' => 'test-key']);
    }

    protected function petugas(): static
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        return $this;
    }

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_guest_cannot_reach_baby_endpoints(): void
    {
        $this->getJson('/api/babies')->assertUnauthorized();
    }

    public function test_baby_crud_and_validation(): void
    {
        $this->petugas();

        $id = $this->postJson('/api/babies', [
            'name' => 'Bayi Satu',
            'weight_grams' => 1800,
            'premature' => true,
        ])->assertCreated()->json('id');

        $this->getJson("/api/babies/{$id}")->assertOk()->assertJsonPath('name', 'Bayi Satu');

        $this->putJson("/api/babies/{$id}", [
            'name' => 'Bayi Satu',
            'weight_grams' => 1900,
            'premature' => false,
        ])->assertOk()->assertJsonPath('weight_grams', 1900);

        $this->postJson('/api/babies', ['name' => '', 'weight_grams' => 0, 'premature' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'weight_grams', 'premature']);

        $this->deleteJson("/api/babies/{$id}")->assertOk();
        $this->assertDatabaseCount('babies', 0);
    }

    public function test_only_one_baby_is_active(): void
    {
        $this->petugas();

        $first = Baby::create(['name' => 'A', 'weight_grams' => 1500, 'premature' => false]);
        $second = Baby::create(['name' => 'B', 'weight_grams' => 3000, 'premature' => false]);

        $this->postJson("/api/babies/{$first->id}/activate")->assertOk();
        $this->postJson("/api/babies/{$second->id}/activate")->assertOk();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertNotNull($second->fresh()->incubator_started_at);

        $this->postJson("/api/babies/{$second->id}/deactivate")->assertOk();
        $this->assertFalse($second->fresh()->is_active);
    }

    public function test_esp_rejects_bad_key(): void
    {
        $this->postJson('/api/readings', [
            'skin_temperature' => 36.5,
            'incubator_temperature' => 33.0,
            'humidity' => 55,
        ])->assertUnauthorized();

        $this->withHeader('X-Api-Key', 'wrong-key')->postJson('/api/readings', [
            'skin_temperature' => 36.5,
            'incubator_temperature' => 33.0,
            'humidity' => 55,
        ])->assertUnauthorized()->assertJsonPath('message', 'Invalid API key.');
    }

    public function test_esp_without_active_baby_turns_everything_off(): void
    {
        $this->withHeader('X-Api-Key', 'test-key')->postJson('/api/readings', [
            'skin_temperature' => 36.5,
            'incubator_temperature' => 33.0,
            'humidity' => 55,
        ])->assertOk()->assertJson([
            'setpoint' => null,
            'heaters' => 0,
            'buzzer' => false,
        ]);

        $this->assertDatabaseCount('readings', 0);
    }

    public function test_esp_reply_and_history(): void
    {
        $this->petugas();

        $baby = Baby::create([
            'name' => 'Bayi',
            'weight_grams' => 1500,
            'premature' => false,
            'is_active' => true,
            'incubator_started_at' => now(),
        ]);

        // 1500g, 0-6h -> setpoint 34.7. At 33.5 the gap is -1.2 so 4 heaters.
        $this->withHeader('X-Api-Key', 'test-key')->postJson('/api/readings', [
            'skin_temperature' => 36.5,
            'incubator_temperature' => 33.5,
            'humidity' => 55,
        ])->assertOk()->assertJson([
            'setpoint' => 34.7,
            'heaters' => 4,
            'buzzer' => false,
            'humidity_ok' => true,
        ]);

        $this->assertDatabaseHas('readings', [
            'baby_id' => $baby->id,
            'heaters' => 4,
            'buzzer' => false,
        ]);

        $this->getJson("/api/babies/{$baby->id}/readings/latest")
            ->assertOk()
            ->assertJsonPath('setpoint', 34.7);

        $this->getJson("/api/babies/{$baby->id}/readings?limit=5")->assertOk()->assertJsonCount(1);
    }
}
