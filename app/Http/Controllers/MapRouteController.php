<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\RouteController;

/**
 * Itinéraires pour la carte du site (`/maps/route`, `/maps/route/compare`) : mêmes paramètres,
 * mêmes réponses et mêmes erreurs que `GET /api/v1/route` et `/api/v1/route/compare`, mais avec
 * le tracé complet du trajet. Comme les tuiles vectorielles, ces réponses servent à l'affichage
 * de la carte : ce ne sont pas des points d'accès de l'API (pas de documentation publique) et
 * la règle des droits des tracés (LineTraceExport) y est levée.
 */
class MapRouteController extends RouteController
{
    protected bool $forDisplay = true;
}
