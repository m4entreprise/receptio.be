<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateRealtimeInternalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.realtime.internal_token');

        if (! filled($expectedToken)) {
            Log::error('realtime.internal_token_missing', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            abort(503, 'Realtime internal token is not configured.');
        }

        $providedToken = $request->bearerToken() ?: $request->header('X-Realtime-Token');

        if (! is_string($providedToken) || ! hash_equals($expectedToken, $providedToken)) {
            Log::warning('realtime.internal_token_invalid', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            abort(401);
        }

        return $next($request);
    }
}
