<?php

namespace Database\Seeders;

use App\Models\TemperatureProfile;
use Illuminate\Database\Seeder;

class TemperatureProfileSeeder extends Seeder
{
    /**
     * Setpoint table from the clinical guideline. Rows are age bands,
     * each covering the three weight bands.
     */
    public function run(): void
    {
        $rows = [
            // age_min_hours, [min, max] for <=1500g, 1501-2500g, >2500g
            [0, [[34.0, 35.4], [32.8, 33.8], [32.0, 33.8]]],
            [24, [[34.0, 35.0], [31.6, 33.6], [30.7, 33.5]]],
            [72, [[34.0, 35.0], [31.1, 33.2], [29.8, 32.8]]],
            [96, [[33.0, 34.0], [31.0, 33.2], [29.0, 32.6]]],
        ];

        $bands = [[0, 1500], [1501, 2500], [2501, 65000]];

        TemperatureProfile::query()->delete();

        foreach ($rows as [$age, $temperatures]) {
            foreach ($bands as $i => [$minWeight, $maxWeight]) {
                [$min, $max] = $temperatures[$i];

                TemperatureProfile::create([
                    'age_min_hours' => $age,
                    'weight_min_grams' => $minWeight,
                    'weight_max_grams' => $maxWeight,
                    'temp_min' => $min,
                    'temp_max' => $max,
                ]);
            }
        }
    }
}
