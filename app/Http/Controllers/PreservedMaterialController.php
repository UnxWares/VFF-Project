<?php

namespace App\Http\Controllers;

use App\Services\Materials\PreservedRollingStock;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Matériel préservé » (`/materials/preserved`) : les lieux où on peut voir du matériel roulant conservé (musées,
 * associations, lignes touristiques, sites) avec leurs pièces, d'après database/data/preserved_rolling_stock.php.
 * Mêmes valeurs que `GET /api/v1/materials/preserved` ; filtres et recherche dans le navigateur.
 */
class PreservedMaterialController extends Controller
{
    public function index(PreservedRollingStock $preserved): Response
    {
        return Inertia::render('Materials/Preserved', [
            'venues' => $preserved->venues(),
            'figures' => $preserved->figures(),
            'types' => PreservedRollingStock::VENUE_TYPES,
            'sources' => PreservedRollingStock::SOURCES,
        ]);
    }
}
