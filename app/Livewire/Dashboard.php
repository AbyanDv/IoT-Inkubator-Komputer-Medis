<?php

namespace App\Livewire;

use App\Models\Baby;
use App\Support\IncubatorRules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** Seconds without a new reading before the device counts as silent. */
    public const STALE_AFTER_SECONDS = 10;

    public function render()
    {
        $baby = Baby::active();
        $reading = $baby?->readings()->latest('recorded_at')->first();

        return view('livewire.dashboard', [
            'baby' => $baby,
            'reading' => $reading,
            'setpoint' => $reading?->setpoint ?? ($baby ? IncubatorRules::setpoint($baby) : null),
            'ageHours' => $baby?->ageInHours(),
            'secondsAgo' => $reading ? (int) $reading->recorded_at->diffInSeconds(now()) : null,
            'stale' => $reading ? $reading->recorded_at->diffInSeconds(now()) > self::STALE_AFTER_SECONDS : true,
            'humidityOk' => $reading ? IncubatorRules::humidityOk($reading->humidity) : true,
        ]);
    }
}
