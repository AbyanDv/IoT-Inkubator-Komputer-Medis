<?php

namespace App\Models;

use Database\Factories\DevicePairingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incubator_id',
    'baby_id',
    'paired_by_user_id',
    'paired_at',
    'unpaired_at',
    'notes',
    'status',
])]
class DevicePairing extends Model
{
    /** @use HasFactory<DevicePairingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paired_at' => 'datetime',
            'unpaired_at' => 'datetime',
        ];
    }

    /**
     * Get the incubator involved in this pairing.
     *
     * @return BelongsTo<Incubator, $this>
     */
    public function incubator(): BelongsTo
    {
        return $this->belongsTo(Incubator::class);
    }

    /**
     * Get the baby involved in this pairing.
     *
     * @return BelongsTo<Baby, $this>
     */
    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }

    /**
     * Get the user who performed this pairing.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paired_by_user_id');
    }
}
