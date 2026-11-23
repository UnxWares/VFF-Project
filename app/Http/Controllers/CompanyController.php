<?php

namespace App\Http\Controllers;

use App\Services\Companies\CompanyFigures;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fiches des compagnies de chemins de fer (/companies, /companies/{slug}) : les agrégats calculés par
 * `vff:compute-company-figures` (cache, mêmes valeurs que `GET /api/v1/companies`). Aucun calcul à l'affichage ;
 * une compagnie sans calcul est un 404.
 */
class CompanyController extends Controller
{
    public function index(CompanyFigures $figures): Response
    {
        return Inertia::render('Companies/Index', ['figures' => $figures->index(app()->getLocale())]);
    }

    public function show(string $slug, CompanyFigures $figures): Response
    {
        $sheet = $figures->sheet($slug, app()->getLocale());
        abort_if($sheet === null, 404);
        // La page n'emporte que les premières lignes ; le reste et le tracé sont lus à part (lines.json, map.json).
        $sheet['lines_total'] = count($sheet['lines']);
        $sheet['lines'] = array_slice($sheet['lines'], 0, CompanyFigures::LINES_SHOWN);

        return Inertia::render('Companies/Show', $sheet);
    }

    /** Tracé simplifié du réseau (cache), chargé par la page à l'approche de la section carte. */
    public function map(string $slug, CompanyFigures $figures): JsonResponse
    {
        $map = $figures->sheet($slug, app()->getLocale()) === null ? null : $figures->map($slug);
        abort_if($map === null, 404);

        return response()->json($map)->header('Cache-Control', 'public, max-age=600');
    }

    /** Les lignes au-delà des premières, chargées à l'ouverture du bloc replié. */
    public function lines(string $slug, CompanyFigures $figures): JsonResponse
    {
        $sheet = $figures->sheet($slug, app()->getLocale());
        abort_if($sheet === null, 404);

        return response()->json(array_slice($sheet['lines'], CompanyFigures::LINES_SHOWN))->header('Cache-Control', 'public, max-age=600');
    }
}
