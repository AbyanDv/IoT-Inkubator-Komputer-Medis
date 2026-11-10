<?php

namespace Database\Factories;

use App\Models\Baby;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Baby>
 */
class BabyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'baby_code' => 'BAYI-'.$this->faker->unique()->numerify('###'),
            'name' => 'Bayi '.$this->faker->name(),
            'birth_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'gestational_age_weeks' => $this->faker->numberBetween(28, 40),
            'weight_grams' => $this->faker->numberBetween(1200, 3800),
            'length_cm' => $this->faker->randomFloat(1, 35, 52),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'notes' => $this->faker->optional()->sentence(),
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the baby simulation profile is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
