<?php

namespace App\Http\Controllers;

use App\Models\Baby;
use App\Support\IncubatorRules;
use Illuminate\Http\Request;

class EspController extends Controller
{
    /**
     * ESP32 posts sensor data every 2 seconds and gets the control reply.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'skin_temperature' => ['required', 'numeric', 'between:20,45'],
            'incubator_temperature' => ['required', 'numeric', 'between:20,45'],
            'humidity' => ['required', 'integer', 'between:0,100'],
        ]);

        $baby = Baby::active();

        // Nobody in the incubator: keep everything off, nothing to record.
        if (! $baby) {
            return response()->json([
                'setpoint' => null,
                'heaters' => 0,
                'buzzer' => false,
            ]);
        }

        $setpoint = IncubatorRules::setpoint($baby);

        $previousHeaters = $baby->readings()->latest('id')->value('heaters') ?? 1;

        $heaters = IncubatorRules::heaters($data['incubator_temperature'], $setpoint, $previousHeaters);
        $buzzer = IncubatorRules::buzzer($data['incubator_temperature'], $setpoint);

        $baby->readings()->create([
            'skin_temperature' => $data['skin_temperature'],
            'incubator_temperature' => $data['incubator_temperature'],
            'humidity' => $data['humidity'],
            'setpoint' => $setpoint,
            'heaters' => $heaters,
            'buzzer' => $buzzer,
            'recorded_at' => now(),
        ]);

        return response()->json([
            'setpoint' => $setpoint,
            'heaters' => $heaters,
            'buzzer' => $buzzer,
            'humidity_ok' => IncubatorRules::humidityOk($data['humidity']),
        ]);
    }
}
