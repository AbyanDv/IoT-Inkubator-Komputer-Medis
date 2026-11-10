<?php

namespace Database\Factories;

use App\Models\DeviceEvent;
use App\Models\Incubator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DeviceEvent>
 */
class DeviceEventFactory extends Factory
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
            'event_type' => $this->faker->randomElement(['SENSOR_DISCONNECT', 'TEMP_HIGH', 'TEMP_LOW', 'FAN_FAIL', 'WIFI_RECONNECT']),
            'priority' => $this->faker->randomElement(['info', 'warning', 'danger']),
            'message' => $this->faker->sentence(),
            'payload' => ['sample' => true],
            'resolved_at' => null,
            'created_at' => Carbon::now(),
        ];
    }
}
