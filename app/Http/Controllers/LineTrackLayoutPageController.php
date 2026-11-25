<?php

namespace App\Http\Controllers;

use App\Models\Line;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Schéma des voies d'une ligne en plein écran (page `/lines/{slug}/voies`, Pages/Lines/Tracks.svelte) : voies,
 * aiguillages, signaux, bande des PK, vitesses et déclivités, tableau de parcours. La page ne porte que
 * l'identité de la ligne ; le schéma est lu par l'API (`GET /api/v1/lines/{line}/track-layout`), comme la section
 * repliée de la fiche : une seule requête (la ligne) pour la page. `?pk=` (mètres) ouvre le schéma sur ce PK : lien
 * « Schéma de toute la ligne » de la page des voies d'une gare.
 */
class LineTrackLayoutPageController extends Controller
{
    public function show(Line $line, Request $request): Response
    {
        $pk = filter_var($request->query('pk'), FILTER_VALIDATE_INT, ['options' => ['min_range' => -100000, 'max_range' => 5000000]]);

        return Inertia::render('Lines/Tracks', [
            'line' => [
                'slug' => $line->slug,
                'code' => $line->code,
                'name' => $line->name,
            ],
            'start_pk' => $pk === false ? null : $pk,
        ]);
    }
}
