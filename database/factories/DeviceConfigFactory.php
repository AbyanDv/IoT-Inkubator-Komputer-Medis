<?php

namespace Database\Factories;

use App\Models\DeviceConfig;
use App\Models\Incubator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
            'config_version' => 1,
            'temperature_setpoint' => 30.00,
            'pid_kp' => 2.000,
            'pid_ki' => 0.100,
            'pid_kd' => 0.500,
            'fan_mode' => 'pwm',
            'blue_led_enabled' => false,
            'simulation_mode' => true,
            'is_active' => true,
            'applied_at' => Carbon::now(),
            'created_by' => User::factory(),
        ];
    }
}
