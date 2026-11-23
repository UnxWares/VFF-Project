<?php

namespace App\Http\Middleware;

use App\Models\Station;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ancien slug d'une gare (table station_redirects : fiche fondue dans une autre par l'action « merge » de
 * database/data/gares_corrections.php, ancien slug « osm-… ») : redirection 301 permanente vers la même
 * adresse sous le slug de la fiche gardée, pour toutes les routes `{station:slug}` (API REST : fiche, médias,
 * fréquentation, horaires…). La page `/stations/{slug}` a sa propre réponse (routes/web.php, `missing`).
 *
 * Seules les lectures (GET, HEAD) sont redirigées : un envoi n'est jamais rejoué ailleurs.
 */
final class RedirectMergedStationSlug
{
    /** Réponse 301 à la place du 404 d'une gare introuvable sous un ancien slug ; null sinon. */
    public static function fromMissingStation(NotFoundHttpException $e, Request $request): ?RedirectResponse
    {
        $missing = $e->getPrevious();
        $route = $request->route();
        if (! $missing instanceof ModelNotFoundException || $missing->getModel() !== Station::class
            || ! $request->isMethodSafe() || ! $route instanceof Route || $route->getName() === null) {
            return null;
        }
        $old = $route->originalParameter('station');
        $slug = is_string($old) ? Station::redirectSlugFor($old) : null;
        if ($slug === null) {
            return null;
        }
        $query = $request->getQueryString();

        return new RedirectResponse(route($route->getName(), array_merge($route->originalParameters(), ['station' => $slug])).($query !== null ? '?'.$query : ''), 301);
    }
}
