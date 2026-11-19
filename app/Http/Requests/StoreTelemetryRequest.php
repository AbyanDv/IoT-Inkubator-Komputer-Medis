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
        return self::payloadRules();
    }

    /**
     * Shared rules for HTTP and MQTT telemetry ingestion.
     *
     * @return array<string, array<int, string>>
     */
    public static function payloadRules(): array
    {
        return [
            'device_code' => ['required', 'string', 'max:20'],
            'temperature_object' => ['required', 'numeric', 'between:-10,60'],
            'temperature_room' => ['required', 'numeric', 'between:-10,60'],
            'humidity' => ['required', 'numeric', 'between:0,100'],
            'relays' => ['required', 'array'],
            'relays.fan' => ['required', 'boolean'],
            'relays.heater_1' => ['required', 'boolean'],
            'relays.heater_2' => ['required', 'boolean'],
            'relays.blue_light' => ['required', 'boolean'],
            'sensor_status' => ['required', 'array'],
            'sensor_status.ds18b20' => ['required', 'boolean'],
            'sensor_status.dht22' => ['required', 'boolean'],
            'timestamp' => ['nullable', 'date'],
        ];
    }
}
