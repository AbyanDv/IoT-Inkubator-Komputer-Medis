<?php

namespace App\Models;

use Database\Factories\DeviceConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incubator_id',
    'temperature_setpoint',
    'pid_kp',
    'pid_ki',
    'pid_kd',
    'blue_led_enabled',
    'created_by',
])]
class DeviceConfig extends Model
{
    /** @use HasFactory<DeviceConfigFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature_setpoint' => 'decimal:2',
            'pid_kp' => 'decimal:3',
            'pid_ki' => 'decimal:3',
            'pid_kd' => 'decimal:3',
            'blue_led_enabled' => 'boolean',
        ];
    }

    /**
     * Get the incubator this configuration belongs to.
     *
     * @return BelongsTo<Incubator, $this>
     */
    public function incubator(): BelongsTo
    {
        return $this->belongsTo(Incubator::class);
    }

    /**
     * Get the user who configured this setting.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias for user() to clarify authorship.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
