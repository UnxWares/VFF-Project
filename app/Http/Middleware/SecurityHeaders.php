<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité du groupe web.
 *
 *   - X-Frame-Options: SAMEORIGIN : aucune page n'est intégrable par un site
 *     tiers (clickjacking sur la connexion, le profil, la modération…), SAUF
 *     les routes /embed/* faites pour ça (cf. AllowIframeEmbed).
 *   - X-Content-Type-Options: nosniff, Referrer-Policy : partout.
 *
 * Les en-têtes déjà posés par un middleware plus spécifique sont conservés.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->routeIs('embed.*') && ! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        if (! $response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        return $response;
    }
}
