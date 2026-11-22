<?php

namespace App\Http\Middleware;

use App\Support\Realtime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorisation des canaux privés (/broadcasting/auth) : introuvable (404) tant que le temps réel est
 * désactivé, comme si la route n'existait pas.
 */
final class EnsureRealtimeEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Realtime::enabled(), 404);

        return $next($request);
    }
}
