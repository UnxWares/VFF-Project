<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retire les protections qui empêchent l'intégration en iframe pour les routes
 * /embed/*. Les autres routes restent verrouillées par défaut.
 *
 * Garde une CSP `frame-ancestors *` permissive (essentielle pour les iframes
 * tiers) tout en conservant les autres restrictions si elles sont posées
 * ailleurs (à ce stade, on n'a pas encore de header CSP global).
 */
class AllowIframeEmbed
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->remove('X-Frame-Options');

        // Si une CSP existe déjà, on tente de remplacer/injecter frame-ancestors *.
        $csp = $response->headers->get('Content-Security-Policy');
        // Noms de directives CSP insensibles à la casse : « Frame-Ancestors 'self' »
        // doit être remplacé, pas doublé (le navigateur garderait le premier).
        if ($csp && stripos($csp, 'frame-ancestors') !== false) {
            $csp = preg_replace('/frame-ancestors[^;]*/i', 'frame-ancestors *', $csp);
        } else {
            $csp = trim(($csp ?? '').'; frame-ancestors *;', '; ');
        }
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
