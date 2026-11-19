<?php

namespace Database\Factories;

use App\Models\DeviceConfig;
use App\Models\Incubator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceConfig>
 */
class DeviceConfigFactory extends Factory
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
            'temperature_setpoint' => 30.00,
            'pid_kp' => 2.000,
            'pid_ki' => 0.100,
            'pid_kd' => 0.500,
            'blue_led_enabled' => false,
            'created_by' => User::factory(),
        ];
    }
}
