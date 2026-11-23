<?php

namespace App\Http\Middleware;

use App\Models\Line;
use App\Services\Lines\LineSlugRedirects;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ancien slug d'une ligne renommée (database/data/line_slug_redirects.php) : redirection 301 permanente vers
 * la même adresse sous le nouveau slug, pour le site comme pour l'API REST.
 *
 * - Dans le chemin (`/lines/{line}`, `/api/v1/lines/{line}/…`, tracé `/lines/{slug}/portions/…`) : la liaison
 *   du modèle échoue (404), `fromMissingLine` (bootstrap/app.php) répond 301, sous-chemin et paramètres gardés.
 * - En paramètre `?line=` d'une liste ou d'un formulaire (accidents, contributions…) : `handle` répond 301.
 *
 * Seules les lectures (GET, HEAD) sont redirigées : un envoi de formulaire n'est jamais rejoué ailleurs.
 */
final class RedirectRetiredLineSlug
{
    /** Paramètres de route qui portent un slug de ligne. */
    private const ROUTE_PARAMETERS = ['line', 'slug'];

    public function handle(Request $request, Closure $next): Response
    {
        $old = $request->query('line');
        // Correspondances lues seulement quand un `?line=` est là : rien à charger pour les autres requêtes.
        if ($request->isMethodSafe() && is_string($old) && app(LineSlugRedirects::class)->isRetired($old)) {
            $slug = Line::redirectSlugFor($old);
            if ($slug !== null) {
                return new RedirectResponse($request->fullUrlWithQuery(['line' => $slug]), 301);
            }
        }

        return $next($request);
    }

    /** Réponse 301 à la place du 404 d'une ligne introuvable sous un ancien slug ; null sinon. */
    public static function fromMissingLine(NotFoundHttpException $e, Request $request): ?RedirectResponse
    {
        $missing = $e->getPrevious();
        $route = $request->route();
        if (! $missing instanceof ModelNotFoundException || $missing->getModel() !== Line::class
            || ! $request->isMethodSafe() || ! $route instanceof Route || $route->getName() === null) {
            return null;
        }
        foreach (self::ROUTE_PARAMETERS as $name) {
            $old = $route->originalParameter($name);
            $slug = is_string($old) ? Line::redirectSlugFor($old) : null;
            if ($slug === null) {
                continue;
            }
            $query = $request->getQueryString();
            $parameters = array_merge($route->originalParameters(), [$name => $slug]);

            return new RedirectResponse(route($route->getName(), $parameters).($query !== null ? '?'.$query : ''), 301);
        }

        return null;
    }
}
