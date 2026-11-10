<?php

namespace App\Models;

use Database\Factories\DeviceEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incubator_id',
    'event_type',
    'priority',
    'message',
    'payload',
    'resolved_at',
    'created_at',
])]
class DeviceEvent extends Model
{
    /** @use HasFactory<DeviceEventFactory> */
    use HasFactory;

    /**
     * Disable updated_at timestamp since event logs are append-only.
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
            'payload' => 'array',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the incubator where this event occurred.
     *
     * @return BelongsTo<Incubator, $this>
     */
    public function incubator(): BelongsTo
    {
        return $this->belongsTo(Incubator::class);
    }
}
