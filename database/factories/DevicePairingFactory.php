<?php

namespace Database\Factories;

use App\Models\Baby;
use App\Models\DevicePairing;
use App\Models\Incubator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DevicePairing>
 */
class DevicePairingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incubator_id' => Incubator::factory(),
            'baby_id' => Baby::factory(),
            'paired_by_user_id' => User::factory(),
            'paired_at' => Carbon::now(),
            'unpaired_at' => null,
            'notes' => $this->faker->optional()->sentence(),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the pairing is completed/unpaired.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'unpaired_at' => Carbon::now(),
        ]);
    }
}
