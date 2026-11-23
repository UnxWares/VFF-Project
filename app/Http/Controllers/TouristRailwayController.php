<?php

namespace App\Http\Controllers;

use App\Services\TouristRailways;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page /tourist-railways, « Chemins de fer touristiques et vélorails » : les exploitations actuelles et, à part, les
 * voies vertes sur ancienne voie (mêmes données que `GET /api/v1/tourist-railways`). Filtres et tri côté navigateur.
 */
class TouristRailwayController extends Controller
{
    public function index(TouristRailways $railways): Response
    {
        return Inertia::render('Network/TouristRailways', ['data' => $railways->get()]);
    }
}
