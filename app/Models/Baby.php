<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'weight_grams', 'premature', 'incubator_started_at', 'is_active'])]
class Baby extends Model
{
    protected function casts(): array
    {
        return [
            'premature' => 'boolean',
            'is_active' => 'boolean',
            'incubator_started_at' => 'datetime',
        ];
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Shared validation rules for API and dashboard forms.
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'weight_grams' => ['required', 'integer', 'min:300', 'max:10000'],
            'premature' => ['required', 'boolean'],
        ];
    }

    /**
     * Put this baby in the incubator and release the previous occupant.
     */
    public function activate(): void
    {
        DB::transaction(function () {
            static::where('is_active', true)->update(['is_active' => false]);
            $this->update(['is_active' => true, 'incubator_started_at' => now()]);
        });
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false, 'incubator_started_at' => null]);
    }

    /**
     * Hours spent in the incubator. Age counts from admission, not birth.
     */
    public function ageInHours(?CarbonInterface $now = null): float
    {
        if (! $this->incubator_started_at) {
            return 0.0;
        }

        return max(0, abs(($now ?? now())->diffInSeconds($this->incubator_started_at)) / 3600);
    }
}
