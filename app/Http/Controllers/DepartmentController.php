<?php

namespace App\Http\Controllers;

use App\Services\Departments\DepartmentFigures;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fiches « Le rail dans <département> » (/departments/{code}-{nom}) et tableau de tous les départements
 * (/departments) : les agrégats calculés par `vff:compute-department-figures` (cache, mêmes valeurs que
 * `GET /api/v1/departments/{code}/history`). Aucun calcul à l'affichage. L'adresse sans le nom, ou avec un nom
 * périmé, redirige (301) vers l'adresse canonique ; un département sans calcul est un 404. La carte arrive après le
 * premier affichage (prop différée).
 */
class DepartmentController extends Controller
{
    public function index(DepartmentFigures $figures): Response
    {
        return Inertia::render('Departments/Index', ['figures' => $figures->index()]);
    }

    public function show(string $slug, DepartmentFigures $figures): Response|RedirectResponse
    {
        $code = DepartmentFigures::codeOf($slug);
        $sheet = $code === null ? null : $figures->sheet($code, app()->getLocale());
        abort_if($sheet === null, 404);

        if ($slug !== $sheet['department']['slug']) {
            return redirect()->route('departments.show', $sheet['department']['slug'], 301);
        }

        // Carte des lignes et des gares (57 Ko sur le Rhône, plus bas que les séries et les dates) : prop différée,
        // demandée par la page juste après son premier affichage ; le nombre de gares de sa légende vient d'emblée.
        $map = $sheet['map'];

        return Inertia::render('Departments/Show', [
            ...array_diff_key($sheet, ['map' => true]),
            'map' => Inertia::defer(fn (): array => $map),
            'map_station_count' => count($map['stations']),
        ]);
    }
}
