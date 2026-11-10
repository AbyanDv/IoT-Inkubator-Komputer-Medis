<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'baby_id', 'skin_temperature', 'incubator_temperature', 'humidity',
    'setpoint', 'heaters', 'buzzer', 'recorded_at',
])]
class Reading extends Model
{
    protected function casts(): array
    {
        return [
            'skin_temperature' => 'float',
            'incubator_temperature' => 'float',
            'humidity' => 'integer',
            'setpoint' => 'float',
            'heaters' => 'integer',
            'buzzer' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }
}
