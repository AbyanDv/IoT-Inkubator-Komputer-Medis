<?php

namespace Tests\Feature;

use App\Models\Baby;
use App\Support\IncubatorRules;
use Database\Seeders\TemperatureProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncubatorRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TemperatureProfileSeeder::class);
    }

    protected function babyAt(float $ageHours, int $weight, bool $premature = false): Baby
    {
        return new Baby([
            'name' => 'Test',
            'weight_grams' => $weight,
            'premature' => $premature,
            'incubator_started_at' => now()->subMinutes((int) ($ageHours * 60)),
        ]);
    }

    public function test_weight_band_boundaries(): void
    {
        $now = now();

        // 0-6h band: <=1500g, 1501-2500g, >2500g
        $this->assertSame(34.7, IncubatorRules::setpoint($this->babyAt(1, 1500), $now));
        $this->assertSame(33.3, IncubatorRules::setpoint($this->babyAt(1, 1501), $now));
        $this->assertSame(33.3, IncubatorRules::setpoint($this->babyAt(1, 2500), $now));
        $this->assertSame(32.9, IncubatorRules::setpoint($this->babyAt(1, 2501), $now));
    }

    public function test_age_bands_and_clamping(): void
    {
        $now = now();

        // 10h falls back to the 0-6h row.
        $this->assertSame(34.7, IncubatorRules::setpoint($this->babyAt(10, 1500), $now));
        // 30h uses the 24-36h row.
        $this->assertSame(34.5, IncubatorRules::setpoint($this->babyAt(30, 1500), $now));
        // 80h uses the 72-96h row.
        $this->assertSame(34.5, IncubatorRules::setpoint($this->babyAt(80, 1500), $now));
        // 5 days uses the 4-12d row.
        $this->assertSame(33.5, IncubatorRules::setpoint($this->babyAt(120, 1500), $now));
        // Beyond 12 days keeps the last row.
        $this->assertSame(33.5, IncubatorRules::setpoint($this->babyAt(1000, 1500), $now));
    }

    public function test_premature_heavy_baby_uses_middle_band(): void
    {
        $now = now();

        $this->assertSame(33.3, IncubatorRules::setpoint($this->babyAt(1, 3000, premature: true), $now));
        $this->assertSame(32.9, IncubatorRules::setpoint($this->babyAt(1, 3000, premature: false), $now));
        // Premature at or below the ceiling changes nothing.
        $this->assertSame(33.3, IncubatorRules::setpoint($this->babyAt(1, 2500, premature: true), $now));
    }

    public function test_heater_stages(): void
    {
        $setpoint = 34.0;

        $this->assertSame(4, IncubatorRules::heaters(33.0, $setpoint, 0));
        $this->assertSame(3, IncubatorRules::heaters(33.3, $setpoint, 0));
        $this->assertSame(2, IncubatorRules::heaters(33.6, $setpoint, 0));
        $this->assertSame(1, IncubatorRules::heaters(33.9, $setpoint, 0));
        $this->assertSame(0, IncubatorRules::heaters(34.3, $setpoint, 0));
    }

    public function test_heater_hysteresis_delays_drop_only(): void
    {
        $setpoint = 34.0;

        // Rising is immediate: diff -0.2 still means 2 heaters even from 1.
        $this->assertSame(2, IncubatorRules::heaters(33.8, $setpoint, 1));
        // Falling waits for the extra 0.1: diff -0.15 keeps 2, diff -0.05 drops to 1.
        $this->assertSame(2, IncubatorRules::heaters(33.85, $setpoint, 2));
        $this->assertSame(1, IncubatorRules::heaters(33.95, $setpoint, 2));
        // Getting colder still adds heaters at once.
        $this->assertSame(3, IncubatorRules::heaters(33.4, $setpoint, 2));
    }

    public function test_buzzer_limits(): void
    {
        $setpoint = 34.0;

        $this->assertFalse(IncubatorRules::buzzer(35.0, $setpoint));
        $this->assertTrue(IncubatorRules::buzzer(35.1, $setpoint));
        $this->assertFalse(IncubatorRules::buzzer(32.0, $setpoint));
        $this->assertTrue(IncubatorRules::buzzer(31.9, $setpoint));
    }
}
