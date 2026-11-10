<?php

namespace App\Models;

use Database\Factories\DeviceTelemetryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incubator_id',
    'baby_id',
    'temperature_object',
    'temperature_room',
    'humidity',
    'heater_status',
    'heater_pwm',
    'fan_status',
    'fan_pwm',
    'blue_led_status',
    'raw_payload',
    'recorded_at',
    'created_at',
])]
class DeviceTelemetry extends Model
{
    /** @use HasFactory<DeviceTelemetryFactory> */
    use HasFactory;

    /**
     * Disable updated_at timestamp since telemetry rows are immutable.
     */
    const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature_object' => 'decimal:2',
            'temperature_room' => 'decimal:2',
            'humidity' => 'decimal:2',
            'heater_status' => 'boolean',
            'heater_pwm' => 'integer',
            'fan_status' => 'boolean',
            'fan_pwm' => 'integer',
            'blue_led_status' => 'boolean',
            'raw_payload' => 'array',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the incubator that produced this telemetry.
     *
     * @return BelongsTo<Incubator, $this>
     */
    public function incubator(): BelongsTo
    {
        return $this->belongsTo(Incubator::class);
    }

    /**
     * Get the baby monitored during this telemetry recording.
     *
     * @return BelongsTo<Baby, $this>
     */
    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }
}
