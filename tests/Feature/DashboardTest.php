<?php

namespace Tests\Feature;

use App\Livewire\Babies;
use App\Livewire\Dashboard;
use App\Livewire\History;
use App\Livewire\Login;
use App\Models\Baby;
use App\Models\Reading;
use App\Models\User;
use Database\Seeders\TemperatureProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TemperatureProfileSeeder::class);
    }

    protected function petugas(): User
    {
        return User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        foreach (['/', '/bayi', '/riwayat'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_authenticated_pages_render_full_layout(): void
    {
        $this->actingAs($this->petugas());

        foreach (['/', '/bayi', '/riwayat'] as $uri) {
            $this->get($uri)->assertOk()->assertSee('Inkubator Bayi');
        }
    }

    public function test_login_and_logout_work(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'salah')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'rahasia123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_baby_form_rejects_invalid_input(): void
    {
        $this->actingAs($this->petugas());

        Livewire::test(Babies::class)
            ->set('name', '')
            ->set('weight_grams', '100')
            ->call('save')
            ->assertHasErrors(['name', 'weight_grams']);

        $this->assertDatabaseCount('babies', 0);
    }

    public function test_only_one_baby_stays_active(): void
    {
        $this->actingAs($this->petugas());

        $first = Baby::create(['name' => 'A', 'weight_grams' => 1500, 'premature' => false]);
        $second = Baby::create(['name' => 'B', 'weight_grams' => 3000, 'premature' => false]);

        Livewire::test(Babies::class)->call('activate', $first->id);
        Livewire::test(Babies::class)->call('activate', $second->id);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(1, Baby::where('is_active', true)->count());
    }

    public function test_dashboard_shows_latest_reading(): void
    {
        $this->actingAs($this->petugas());

        $baby = Baby::create([
            'name' => 'Bayi Uji',
            'weight_grams' => 1500,
            'premature' => true,
            'is_active' => true,
            'incubator_started_at' => now(),
        ]);

        Reading::create([
            'baby_id' => $baby->id,
            'skin_temperature' => 36.5,
            'incubator_temperature' => 33.4,
            'humidity' => 58,
            'setpoint' => 34.7,
            'heaters' => 3,
            'buzzer' => false,
            'recorded_at' => now(),
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Bayi Uji')
            ->assertSee('36,5')
            ->assertSee('33,4')
            ->assertSee('58')
            ->assertSee('34,7')
            ->assertSee('3 dari 4 menyala')
            ->assertDontSee('Perangkat tidak mengirim data');
    }

    public function test_dashboard_shows_empty_state_without_active_baby(): void
    {
        $this->actingAs($this->petugas());

        Livewire::test(Dashboard::class)
            ->assertSee('Belum ada bayi di inkubator')
            ->assertSee('Buka halaman Bayi');
    }

    public function test_dashboard_warns_when_data_is_stale(): void
    {
        $this->actingAs($this->petugas());

        $baby = Baby::create([
            'name' => 'Bayi Lama',
            'weight_grams' => 2000,
            'premature' => false,
            'is_active' => true,
            'incubator_started_at' => now()->subHour(),
        ]);

        Reading::create([
            'baby_id' => $baby->id,
            'skin_temperature' => 36.4,
            'incubator_temperature' => 33.2,
            'humidity' => 45,
            'setpoint' => 33.3,
            'heaters' => 1,
            'buzzer' => true,
            'recorded_at' => now()->subSeconds(30),
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Perangkat tidak mengirim data')
            ->assertSee('Buzzer aktif')
            ->assertSee('Di luar target 50–60%');
    }

    public function test_history_filters_by_baby(): void
    {
        $this->actingAs($this->petugas());

        $baby = Baby::create(['name' => 'Bayi Riwayat', 'weight_grams' => 1700, 'premature' => false]);

        Reading::create([
            'baby_id' => $baby->id,
            'skin_temperature' => 36.6,
            'incubator_temperature' => 33.9,
            'humidity' => 55,
            'setpoint' => 34.1,
            'heaters' => 2,
            'buzzer' => false,
            'recorded_at' => now(),
        ]);

        Livewire::test(History::class)
            ->assertSee('Bayi Riwayat')
            ->assertSee('36,6')
            ->assertSee('2 / 4');
    }
}
