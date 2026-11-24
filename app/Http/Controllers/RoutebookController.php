<?php

namespace App\Http\Controllers;

use App\Services\NetworkGraph\RouteException;
use App\Services\Routebook\Routebook;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page « Livret de parcours » : /routebook?from=rennes&to=st-malo. Le livret est calculé côté serveur (même service
 * que `GET /api/v1/routebook`, en cache) : la page s'affiche complète au premier chargement, se partage par son
 * adresse et s'imprime. Sans gares, ou avec une seule, le formulaire seul.
 */
class RoutebookController extends Controller
{
    public function show(Request $request, Routebook $routebook): Response
    {
        $from = mb_substr(trim((string) $request->query('from', '')), 0, 200);
        $to = mb_substr(trim((string) $request->query('to', '')), 0, 200);

        $book = null;
        $error = null;
        if ($from !== '' && $to !== '') {
            try {
                $book = $routebook->between($from, $to);
            } catch (RouteException $e) {
                $error = ['error' => $e->errorCode] + array_intersect_key($e->details, array_flip(['side', 'query', 'station', 'candidates', 'era_year']));
            }
        }

        return Inertia::render('Routebook/Show', [
            'query' => ['from' => $from, 'to' => $to],
            'book' => $book,
            'error' => $error,
        ]);
    }
}
