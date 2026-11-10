<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => $this->faker->randomElement(['login', 'pair_device', 'unpair_device', 'update_config', 'export_data']),
            'target_type' => 'App\Models\Incubator',
            'target_id' => $this->faker->numberBetween(1, 10),
            'details' => ['reason' => 'Routine test'],
            'ip_address' => $this->faker->ipv4(),
            'created_at' => Carbon::now(),
        ];
    }
}
