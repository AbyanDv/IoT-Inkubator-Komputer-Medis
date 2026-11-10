<?php

namespace App\Models;

use Database\Factories\IncubatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'device_code',
    'name',
    'mac_address',
    'device_token',
    'firmware_version',
    'status',
    'is_active',
    'last_heartbeat_at',
    'last_config_version',
    'current_baby_id',
])]
class Incubator extends Model
{
    /** @use HasFactory<IncubatorFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'last_config_version' => 'integer',
        ];
    }

    /**
     * Get the currently paired baby.
     *
     * @return BelongsTo<Baby, $this>
     */
    public function currentBaby(): BelongsTo
    {
        return $this->belongsTo(Baby::class, 'current_baby_id');
    }

    /**
     * Get all pairings for this incubator.
     *
     * @return HasMany<DevicePairing, $this>
     */
    public function pairings(): HasMany
    {
        return $this->hasMany(DevicePairing::class);
    }

    /**
     * Get the active pairing for this incubator.
     *
     * @return HasOne<DevicePairing, $this>
     */
    public function activePairing(): HasOne
    {
        return $this->hasOne(DevicePairing::class)
            ->where('status', 'active')
            ->latestOfMany('paired_at');
    }

    /**
     * Get all telemetry logs recorded by this incubator.
     *
     * @return HasMany<DeviceTelemetry, $this>
     */
    public function telemetries(): HasMany
    {
        return $this->hasMany(DeviceTelemetry::class);
    }

    /**
     * Get all configuration versions for this incubator.
     *
     * @return HasMany<DeviceConfig, $this>
     */
    public function configs(): HasMany
    {
        return $this->hasMany(DeviceConfig::class);
    }

    /**
     * Get the currently active configuration for this incubator.
     *
     * @return HasOne<DeviceConfig, $this>
     */
    public function activeConfig(): HasOne
    {
        return $this->hasOne(DeviceConfig::class)
            ->where('is_active', true)
            ->latestOfMany('config_version');
    }

    /**
     * Get all events logged for this incubator.
     *
     * @return HasMany<DeviceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(DeviceEvent::class);
    }
}
