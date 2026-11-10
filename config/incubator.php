<?php

return [
    // Shared secret ESP32 sends in the X-Api-Key header. Empty disables the device endpoint.
    'api_key' => env('ESP_API_KEY'),

    // diff = incubator temperature - setpoint. Lowest matching stage wins.
    'heater_stages' => [
        ['below_or_equal' => -1.0, 'heaters' => 4],
        ['below_or_equal' => -0.5, 'heaters' => 3],
        ['below_or_equal' => -0.2, 'heaters' => 2],
        ['below_or_equal' => 0.2, 'heaters' => 1],
    ],

    // Heaters only drop a stage once diff passes the boundary by this much.
    'hysteresis' => 0.1,

    'buzzer_high_offset' => 1.0,
    'buzzer_low_offset' => -2.0,

    'humidity_min' => 50,
    'humidity_max' => 60,

    // Premature babies above this weight use the warmer middle column.
    'premature_weight_ceiling_grams' => 2500,
];
