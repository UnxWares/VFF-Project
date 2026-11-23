<?php

namespace App\Http\Controllers;

use App\Services\Materials\MaterialTimeline;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Frise du matériel roulant (`/materials/timeline`) : les séries à plat, regroupées et filtrées par la page. */
class MaterialTimelineController extends Controller
{
    public function index(Request $request, MaterialTimeline $timeline): Response
    {
        $all = $timeline->all();
        // Série à repérer (lien « Voir dans la frise » de sa fiche) : seulement un slug connu, sinon ignoré.
        $focus = $request->query('serie');
        $focus = is_string($focus) && in_array($focus, array_column($all['data'], 'slug'), true) ? $focus : null;

        return Inertia::render('Materials/Timeline', [
            'rows' => $all['data'],
            'meta' => $all['meta'],
            'focus' => $focus,
            'groups' => array_keys(MaterialTimeline::GROUPS),
        ]);
    }
}
