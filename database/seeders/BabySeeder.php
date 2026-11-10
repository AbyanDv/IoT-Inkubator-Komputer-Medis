<?php

namespace Database\Seeders;

use App\Models\Baby;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BabySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();

        Baby::updateOrCreate(
            ['baby_code' => 'BAYI-001'],
            [
                'name' => 'Bayi Ny. Siti',
                'birth_date' => Carbon::now()->subDays(3),
                'gestational_age_weeks' => 34,
                'weight_grams' => 2100,
                'length_cm' => 45.0,
                'gender' => 'male',
                'notes' => 'Simulasi perawatan prematuritas sedang.',
                'status' => 'active',
                'created_by' => $admin?->id,
            ]
        );

        Baby::updateOrCreate(
            ['baby_code' => 'BAYI-002'],
            [
                'name' => 'Bayi Ny. Dewi',
                'birth_date' => Carbon::now()->subDays(5),
                'gestational_age_weeks' => 32,
                'weight_grams' => 1850,
                'length_cm' => 42.5,
                'gender' => 'female',
                'notes' => 'Simulasi perawatan berat lahir rendah (BBLR).',
                'status' => 'active',
                'created_by' => $admin?->id,
            ]
        );
    }
}
