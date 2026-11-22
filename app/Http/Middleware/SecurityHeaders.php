<?php

namespace App\Http\Middleware;

use App\Support\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité de toutes les réponses de l'application (pile globale : pages web, API REST,
 * GraphQL, MCP, dictionnaires, fond de carte, tracés, erreurs).
 *
 *   - X-Frame-Options: SAMEORIGIN : aucune page n'est intégrable par un site
 *     tiers (clickjacking sur la connexion, le profil, la modération…), SAUF
 *     les routes /embed/* faites pour ça (cf. AllowIframeEmbed).
 *   - X-Content-Type-Options: nosniff, Referrer-Policy, Permissions-Policy : partout.
 *   - Strict-Transport-Security : en production et sur toute requête en HTTPS (jamais en HTTP local).
 *   - Content-Security-Policy-Report-Only (ou Content-Security-Policy quand CSP_ENFORCE=true) sur
 *     les pages HTML, avec un nonce par requête pour les scripts (ContentSecurityPolicy,
 *     config/csp.php), et Reporting-Endpoints pour les rapports.
 *
 * Les en-têtes déjà posés par un middleware plus spécifique sont conservés.
 */
class SecurityHeaders
{
    /**
     * Fonctions du navigateur refusées à la page et à ses cadres. La géolocalisation reste permise à la
     * page elle-même (bouton « me localiser » de la carte), le plein écran aussi (par défaut). `bluetooth` et
     * `interest-cohort` ne sont plus reconnus par les navigateurs (avertissement à chaque page) : retirés.
     */
    public const PERMISSIONS_POLICY = 'camera=(), microphone=(), payment=(), usb=(), serial=(), '
        .'midi=(), accelerometer=(), gyroscope=(), magnetometer=(), geolocation=(self)';

    /** Un an, sans includeSubDomains (les sous-domaines de l'association ne sont pas tous en HTTPS). */
    public const HSTS = 'max-age=31536000';

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

        if (! $response->headers->has('Permissions-Policy')) {
            $response->headers->set('Permissions-Policy', self::PERMISSIONS_POLICY);
        }
        if (($request->isSecure() || app()->isProduction()) && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
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
