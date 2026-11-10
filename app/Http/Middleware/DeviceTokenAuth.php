<?php

namespace App\Http\Middleware;

use App\Models\Incubator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceTokenAuth
{
    /**
     * Authenticate an ESP32 device via X-Device-Token header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Device-Token');

        if (! $token) {
            return response()->json(['error' => 'Device token required'], 401);
        }

        $incubator = Incubator::where('device_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $incubator) {
            return response()->json(['error' => 'Invalid device token'], 401);
        }

        $request->merge(['incubator' => $incubator]);

        return $next($request);
    }
}
