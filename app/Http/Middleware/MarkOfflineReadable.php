<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déclare au service worker (resources/js/pwa) qu'une page peut être gardée dans le navigateur pour être
 * relue hors ligne : en-tête `X-VFF-Offline: allow`. Sans cet en-tête, le service worker ne range rien.
 *
 * Seulement une page publique de consultation (liste ROUTES : ni compte, ni contribution, ni validation,
 * ni administration), demandée en GET par un visiteur non connecté, rendue en entier (pas de rechargement
 * partiel ni de préchargement), en réponse 200 et sans message de session (confirmation, erreur de
 * formulaire). Une telle page ne contient aucune donnée propre à une personne : une copie ne peut pas
 * montrer la session de quelqu'un d'autre.
 */
final class MarkOfflineReadable
{
    public const HEADER = 'X-VFF-Offline';

    public const ALLOW = 'allow';

    /** Pages de consultation lisibles hors ligne (noms de routes de routes/web.php). */
    public const ROUTES = [
        'home',
        'whoarewe',
        'maps',
        'lines.index',
        'lines.show',
        'stations.index',
        'stations.show',
        'materials.index',
        'materials.show',
        'accidents.index',
        'accidents.show',
        'donate',
        'legal',
        'privacy',
        'use-conditions',
        'accessibility',
        'credits.tech',
        'credits.media',
    ];

    /** En-têtes d'un rechargement partiel ou d'un préchargement Inertia : réponse incomplète, jamais gardée. */
    private const PARTIAL_HEADERS = [
        'X-Inertia-Partial-Component',
        'X-Inertia-Partial-Data',
        'X-Inertia-Partial-Except',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($this->isOfflineReadable($request, $response)) {
            $response->headers->set(self::HEADER, self::ALLOW);
        }

        return $response;
    }

    private function isOfflineReadable(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== Response::HTTP_OK) {
            return false;
        }
        if (! $request->routeIs(...self::ROUTES) || $request->user() !== null) {
            return false;
        }
        if ($request->header('Purpose') === 'prefetch') {
            return false;
        }
        foreach (self::PARTIAL_HEADERS as $header) {
            if ($request->headers->has($header)) {
                return false;
            }
        }

        return ! $this->hasSessionMessage($request);
    }

    /** Confirmation, erreur ou erreurs de formulaire flashées pour cette page : propres à ce visiteur. */
    private function hasSessionMessage(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }
        $session = $request->session();

        return $session->has('success') || $session->has('error') || $session->has('errors');
    }
}
