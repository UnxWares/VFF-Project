<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoverageFigures;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Époques', "Les ères de la carte (un instantané par décennie, de 1830 à aujourd'hui), leur présentation et les statistiques du réseau à chaque époque.", weight: 1)]
class CoverageController extends Controller
{
    /**
     * Ce que sait l'encyclopédie : la couverture des données.
     *
     * Les mesures de la page « Ce que sait l'encyclopédie », calculées par une commande puis gardées en cache
     * (`computed_at` donne la date du calcul). Quatre ensembles, `lines`, `stations`, `accidents` et `materials`,
     * chacun avec une liste `metrics` ; une mesure donne :
     *
     * - `key` et `unit` : ce qui est mesuré (`lines`, `stations`, `accidents`, `series`, ou `km` pour le tracé) ;
     * - `available` : faux quand la table qui porte la donnée n'est pas encore importée (les autres champs valent alors 0) ;
     * - `total`, `done` et `share` : l'ensemble concerné, ce qui est renseigné et sa part (0 à 1, `null` sans ensemble) ;
     * - `open` : ce qui est renseigné à partir de sources sous licence ouverte écrite (`null` quand la mesure ne le dit pas) ;
     * - `levels` (dates d'ouverture et de fermeture seulement) : la précision de la date, `day`, `month`, `year` ou `none`.
     *
     * `accidents.by_decade` et `materials.by_decade` ventilent par décennie, `eras` donne par ère les lignes ouvertes
     * et les gares ouvertes dont l'ouverture est datée au jour ou au mois, et les kilomètres au tracé précis.
     */
    #[ApiResponse(200, 'La couverture des données (extrait).', examples: [[
        'data' => [
            'computed_at' => '2026-11-23T05:00:00+01:00',
            'lines' => ['metrics' => [[
                'key' => 'opening_dated', 'unit' => 'lines', 'available' => true, 'total' => 2690, 'done' => 1693, 'share' => 0.6294, 'open' => 1463,
                'levels' => [['level' => 'day', 'count' => 1200, 'share' => 0.4461, 'open' => 1000], ['level' => 'none', 'count' => 997, 'share' => 0.3706, 'open' => null]],
            ]]],
            'stations' => ['metrics' => []],
            'accidents' => ['metrics' => [], 'by_decade' => [['decade' => 1930, 'total' => 112, 'reviewed' => 9]]],
            'materials' => ['metrics' => [], 'by_decade' => [['decade' => 1950, 'total' => 40]]],
            'eras' => [['year' => 1930, 'current' => false, 'lines' => 700, 'lines_precise_opening' => 400, 'trace_km' => 60000, 'trace_precise_km' => 50000, 'stations' => 8500, 'stations_precise_opening' => 8000]],
        ],
        'links' => ['web' => 'https://vff-project.org/network/coverage', 'stats' => 'https://vff-project.org/api/v1/stats'],
    ]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(CoverageFigures $figures): JsonResponse
    {
        return response()->json([
            'data' => $figures->get(),
            'links' => ['web' => route('network.coverage'), 'stats' => route('api.stats')],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
