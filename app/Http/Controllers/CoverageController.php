<?php

namespace App\Http\Controllers;

use App\Services\CoverageFigures;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page /network/coverage, « Ce que sait l'encyclopédie » : les mesures de couverture calculées par
 * `vff:compute-coverage-figures` (cache, mêmes valeurs que `GET /api/v1/stats/coverage`). Aucun calcul à
 * l'affichage tant que le cache est rempli.
 */
class CoverageController extends Controller
{
    public function index(CoverageFigures $figures): Response
    {
        return Inertia::render('Network/Coverage', ['coverage' => $figures->get()]);
    }
}
