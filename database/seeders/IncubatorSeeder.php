<?php

namespace Database\Seeders;

use App\Models\DeviceConfig;
use App\Models\Incubator;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class IncubatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();

        $incubator = Incubator::updateOrCreate(
            ['device_code' => 'INC-001'],
            [
                'name' => 'Inkubator A1 (Laboratorium)',
                'mac_address' => '24:6F:28:01:02:03',
                'device_token' => 'dev-token-inc-001',
                'firmware_version' => '1.0.0',
                'status' => 'offline',
                'is_active' => true,
                'last_heartbeat_at' => null,
                'last_config_version' => 1,
                'current_baby_id' => null,
            ]
        );

        DeviceConfig::updateOrCreate(
            [
                'incubator_id' => $incubator->id,
                'config_version' => 1,
            ],
            [
                'temperature_setpoint' => 30.00,
                'pid_kp' => 2.000,
                'pid_ki' => 0.100,
                'pid_kd' => 0.500,
                'fan_mode' => 'pwm',
                'blue_led_enabled' => false,
                'simulation_mode' => true,
                'is_active' => true,
                'applied_at' => Carbon::now(),
                'created_by' => $admin?->id,
            ]
        );
    }
}
