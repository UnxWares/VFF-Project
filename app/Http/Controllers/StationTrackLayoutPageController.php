<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Voies d'une gare (page `/stations/{slug}/voies`, Pages/Stations/Tracks.svelte) : le schéma des voies de chaque ligne
 * autour de la gare et ses quais. La page ne porte que l'identité de la gare ; le plan est lu par l'API
 * (`GET /api/v1/stations/{station}/track-layout`), comme la section de la fiche : une seule requête (la gare).
 */
class StationTrackLayoutPageController extends Controller
{
    public function show(Station $station): Response
    {
        return Inertia::render('Stations/Tracks', [
            'station' => [
                'slug' => $station->slug,
                'name' => $station->name,
                'city' => $station->city,
                'department_code' => $station->department_code,
            ],
        ]);
    }
}
