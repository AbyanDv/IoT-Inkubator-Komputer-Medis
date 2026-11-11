<?php

namespace App\Http\Middleware;

use App\Models\Incubator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DeviceTokenAuth
{
    /**
     * Authenticate an ESP32 device via X-Device-Token header.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $token = $request->header('X-Device-Token') ?? $request->bearerToken();

        if (! $token) {
            return Response::json(['error' => 'Device token required'], 401);
        }

        $incubator = Incubator::where('device_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $incubator) {
            return Response::json(['error' => 'Invalid device token'], 401);
        }

        $request->attributes->set('incubator', $incubator);

        return $next($request);
    }
}
