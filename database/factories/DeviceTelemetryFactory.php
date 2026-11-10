<?php

namespace Database\Factories;

use App\Models\Baby;
use App\Models\DeviceTelemetry;
use App\Models\Incubator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DeviceTelemetry>
 */
class DeviceTelemetryFactory extends Factory
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
            'temperature_object' => $this->faker->randomFloat(2, 35.5, 37.5),
            'temperature_room' => $this->faker->randomFloat(2, 28.0, 32.0),
            'humidity' => $this->faker->randomFloat(2, 50.0, 70.0),
            'heater_status' => true,
            'heater_pwm' => $this->faker->numberBetween(0, 255),
            'fan_status' => true,
            'fan_pwm' => $this->faker->numberBetween(50, 150),
            'blue_led_status' => false,
            'raw_payload' => [
                'sensor_status' => ['ds18b20' => 'OK', 'dht22' => 'OK'],
            ],
            'recorded_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ];
    }
}
