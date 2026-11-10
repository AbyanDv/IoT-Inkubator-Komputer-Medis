<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTelemetryRequest extends FormRequest
{
    /**
     * Device API requests are pre-authenticated by middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for ESP32 telemetry payload.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_code' => ['required', 'string', 'max:20'],
            'applied_config_version' => ['required', 'integer', 'min:0'],
            'temperature_object' => ['required', 'numeric', 'between:-10,60'],
            'temperature_room' => ['required', 'numeric', 'between:-10,60'],
            'humidity' => ['required', 'numeric', 'between:0,100'],
            'heater_pwm' => ['required', 'integer', 'between:0,255'],
            'fan_pwm' => ['required', 'integer', 'between:0,255'],
            'blue_led_status' => ['required', 'boolean'],
            'sensor_status' => ['required', 'array'],
            'sensor_status.ds18b20' => ['required', 'boolean'],
            'sensor_status.dht22' => ['required', 'boolean'],
            'timestamp' => ['nullable', 'date'],
        ];
    }
}
