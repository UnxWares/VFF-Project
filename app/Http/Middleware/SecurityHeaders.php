<?php

namespace App\Http\Middleware;

use App\Support\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité du groupe web.
 *
 *   - X-Frame-Options: SAMEORIGIN : aucune page n'est intégrable par un site
 *     tiers (clickjacking sur la connexion, le profil, la modération…), SAUF
 *     les routes /embed/* faites pour ça (cf. AllowIframeEmbed).
 *   - X-Content-Type-Options: nosniff, Referrer-Policy : partout.
 *   - Content-Security-Policy-Report-Only (ou Content-Security-Policy quand CSP_ENFORCE=true) sur
 *     les pages HTML, avec un nonce par requête pour les scripts (ContentSecurityPolicy,
 *     config/csp.php), et Reporting-Endpoints pour les rapports.
 *
 * Les en-têtes déjà posés par un middleware plus spécifique sont conservés.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce de la requête : posé par Vite sur ses balises, lu par les vues (Vite::cspNonce()).
        $nonce = Vite::useCspNonce();

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

        $header = ContentSecurityPolicy::headerName();
        if (str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html') && ! $response->headers->has($header)) {
            $response->headers->set($header, ContentSecurityPolicy::build($request, $nonce));
            if (config('csp.report')) {
                $response->headers->set('Reporting-Endpoints', ContentSecurityPolicy::reportingEndpoints($request));
            }
        }

        return $response;
    }
}
