<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@incubator.local',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ],
            [
                'name' => 'Administrator',
                'email' => 'admin@incubator.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ],
            [
                'name' => 'Incubator Operator',
                'email' => 'operator@incubator.local',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'is_active' => true,
            ],
            [
                'name' => 'Incubator Viewer',
                'email' => 'viewer@incubator.local',
                'password' => Hash::make('password'),
                'role' => 'viewer',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
