<?php

namespace App\Support;

use App\Models\Baby;
use App\Models\TemperatureProfile;
use Carbon\CarbonInterface;

/**
 * Every control decision the incubator makes. Numbers live in config/incubator.php.
 */
class IncubatorRules
{
    /**
     * Target temperature for the baby's weight band and time in the incubator.
     */
    public static function setpoint(Baby $baby, ?CarbonInterface $now = null): float
    {
        $weight = $baby->weight_grams;
        $ceiling = config('incubator.premature_weight_ceiling_grams');

        // Premature and heavier than the ceiling: use the warmer middle band.
        if ($baby->premature && $weight > $ceiling) {
            $weight = $ceiling;
        }

        $profile = TemperatureProfile::query()
            ->where('weight_min_grams', '<=', $weight)
            ->where('weight_max_grams', '>=', $weight)
            ->where('age_min_hours', '<=', $baby->ageInHours($now))
            ->orderByDesc('age_min_hours')
            ->firstOrFail();

        return round(($profile->temp_min + $profile->temp_max) / 2, 1);
    }

    /**
     * Heater count from the gap between incubator temperature and setpoint.
     * Rising goes up at once; falling waits for the extra hysteresis margin.
     */
    public static function heaters(float $incubatorTemperature, float $setpoint, int $previous): int
    {
        $diff = $incubatorTemperature - $setpoint;
        $target = static::stageFor($diff);

        if ($target < $previous) {
            $target = max($target, static::stageFor($diff - (float) config('incubator.hysteresis')));
        }

        return $target;
    }

    public static function buzzer(float $incubatorTemperature, float $setpoint): bool
    {
        return $incubatorTemperature > $setpoint + config('incubator.buzzer_high_offset')
            || $incubatorTemperature < $setpoint + config('incubator.buzzer_low_offset');
    }

    public static function humidityOk(int $humidity): bool
    {
        return $humidity >= config('incubator.humidity_min')
            && $humidity <= config('incubator.humidity_max');
    }

    protected static function stageFor(float $diff): int
    {
        foreach (config('incubator.heater_stages') as $stage) {
            if ($diff <= $stage['below_or_equal']) {
                return $stage['heaters'];
            }
        }

        return 0;
    }
}
