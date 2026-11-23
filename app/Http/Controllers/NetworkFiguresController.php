<?php

namespace App\Http\Controllers;

use App\Services\NetworkFigures;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page /network, « Le réseau en chiffres » : les agrégats calculés par `vff:compute-network-figures`
 * (cache, mêmes valeurs que `GET /api/v1/stats/network`). Aucun calcul à l'affichage tant que le cache est rempli.
 */
class NetworkFiguresController extends Controller
{
    public function index(NetworkFigures $figures): Response
    {
        return Inertia::render('Network/Figures', ['figures' => $figures->get()]);
    }
}
