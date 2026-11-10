<?php

namespace Database\Factories;

use App\Models\Incubator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Incubator>
 */
class IncubatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_code' => 'INC-'.$this->faker->unique()->numerify('###'),
            'name' => 'Inkubator '.$this->faker->city(),
            'mac_address' => $this->faker->unique()->macAddress(),
            'device_token' => 'dev_tok_'.Str::random(32),
            'firmware_version' => '1.0.0',
            'status' => 'offline',
            'is_active' => true,
            'last_heartbeat_at' => null,
            'last_config_version' => 1,
            'current_baby_id' => null,
        ];
    }

    /**
     * Indicate that the incubator is online.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'online',
            'last_heartbeat_at' => Carbon::now(),
        ]);
    }
}
