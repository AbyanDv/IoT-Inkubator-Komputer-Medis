<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['age_min_hours', 'weight_min_grams', 'weight_max_grams', 'temp_min', 'temp_max'])]
class TemperatureProfile extends Model
{
    protected function casts(): array
    {
        return [
            'age_min_hours' => 'integer',
            'weight_min_grams' => 'integer',
            'weight_max_grams' => 'integer',
            'temp_min' => 'float',
            'temp_max' => 'float',
        ];
    }
}
