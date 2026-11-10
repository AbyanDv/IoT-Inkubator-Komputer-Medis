<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TemperatureProfileSeeder::class);

        User::firstOrCreate(
            ['email' => 'petugas@example.com'],
            ['name' => 'Petugas', 'password' => 'password'],
        );
    }
}
