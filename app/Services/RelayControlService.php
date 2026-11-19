<?php

namespace App\Services;

use App\Models\Incubator;
use Illuminate\Support\Facades\Cache;

class RelayControlService
{
    /**
     * Relay keys shared by the dashboard, device payload, and firmware.
     *
     * @var array<int, string>
     */
    public const RELAYS = ['fan', 'heater_1', 'heater_2', 'blue_light'];

    /**
     * Get the active test overrides for an incubator.
     *
     * @return array<string, bool>
     */
    public function overrides(Incubator $incubator): array
    {
        $overrides = Cache::get($this->key($incubator), []);

        if (! is_array($overrides)) {
            return [];
        }

        return array_intersect_key($overrides, array_flip(self::RELAYS));
    }

    /**
     * Set one relay test override and return all active overrides.
     *
     * @return array<string, bool>
     */
    public function set(Incubator $incubator, string $relay, bool $on): array
    {
        $overrides = $this->overrides($incubator);
        $overrides[$relay] = $on;

        Cache::put($this->key($incubator), $overrides, now()->addMinutes(5));

        return $overrides;
    }

    /**
     * Remove every test override so the device returns to automatic control.
     */
    public function clear(Incubator $incubator): void
    {
        Cache::forget($this->key($incubator));
    }

    private function key(Incubator $incubator): string
    {
        return "incubator:{$incubator->id}:relay_overrides";
    }
}
