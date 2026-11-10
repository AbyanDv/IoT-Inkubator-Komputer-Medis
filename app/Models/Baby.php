<?php

namespace App\Models;

use Database\Factories\BabyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'baby_code',
    'name',
    'birth_date',
    'gestational_age_weeks',
    'weight_grams',
    'length_cm',
    'gender',
    'notes',
    'status',
    'created_by',
])]
class Baby extends Model
{
    /** @use HasFactory<BabyFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'datetime',
            'gestational_age_weeks' => 'integer',
            'weight_grams' => 'integer',
            'length_cm' => 'float',
        ];
    }

    /**
     * Get all pairings for this baby.
     *
     * @return HasMany<DevicePairing, $this>
     */
    public function devicePairings(): HasMany
    {
        return $this->hasMany(DevicePairing::class);
    }

    /**
     * Get the active/current pairing for this baby.
     *
     * @return HasOne<DevicePairing, $this>
     */
    public function currentPairing(): HasOne
    {
        return $this->hasOne(DevicePairing::class)
            ->where('status', 'active')
            ->latestOfMany('paired_at');
    }

    /**
     * Get the current incubator housing this baby.
     *
     * @return HasOne<Incubator, $this>
     */
    public function currentIncubator(): HasOne
    {
        return $this->hasOne(Incubator::class, 'current_baby_id');
    }

    /**
     * Get all telemetry logs recorded while this baby was monitored.
     *
     * @return HasMany<DeviceTelemetry, $this>
     */
    public function telemetries(): HasMany
    {
        return $this->hasMany(DeviceTelemetry::class);
    }

    /**
     * Get the user who registered this baby profile.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
