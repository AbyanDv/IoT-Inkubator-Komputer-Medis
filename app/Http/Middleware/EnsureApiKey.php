<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the ESP32 endpoint with the shared secret from .env.
 */
class EnsureApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('incubator.api_key');
        $given = (string) $request->header('X-Api-Key', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        return $next($request);
    }
}
