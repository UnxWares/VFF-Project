<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SiteStats;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Époques', "Les ères de la carte (un instantané par décennie, de 1830 à aujourd'hui), leur présentation et les statistiques du réseau à chaque époque.", weight: 1)]
class StatsController extends Controller
{
    /**
     * Chiffres d'ensemble du site.
     *
     * Les compteurs et la courbe du réseau que montre la page d'accueil, avec
     * les mêmes valeurs :
     *
     * - `lines_count` : lignes recensées, en service ou disparues ;
     * - `stations_count` : gares et haltes recensées, ouvertes ou fermées ;
     * - `materials_count` : séries de matériel roulant ;
     * - `eras_count` : ères dont la carte a au moins un tronçon ;
     * - `total_km` : kilomètres de lignes exploitées aujourd'hui, d'après les
     *   formes de SNCF Réseau (longueur de l'axe de chaque ligne, une fois) ;
     * - `profile` : pour chaque ère, `year`, `km` (longueur des tronçons en
     *   service, en km arrondis), `lines` (nombre de lignes qui portent du
     *   trafic) et `current` (ère d'aujourd'hui).
     *
     * `profile[].km` additionne les tronçons de la carte VFF, voies parallèles
     * comprises : c'est un ordre de grandeur de l'étendue du réseau, un peu
     * supérieur au `total_km` de l'année courante. Pour le détail d'une ère
     * (km fermés, déposés, électrifiés, gares par statut), voir
     * `/eras/{year}` ; pour ce qui change entre deux ères, voir
     * `/eras/{year}/compare/{other}`.
     *
     * Les valeurs sont recalculées dès que les données du réseau changent
     * (cache de dix minutes au plus).
     */
    #[ApiResponse(200, 'Les chiffres du site (profil raccourci).', examples: [[
        'data' => [
            'lines_count' => 2705, 'stations_count' => 9363, 'materials_count' => 687, 'eras_count' => 21, 'total_km' => 27045,
            'profile' => [
                ['year' => 1930, 'km' => 58678, 'lines' => 1390, 'current' => false],
                ['year' => 1980, 'km' => 34201, 'lines' => 700, 'current' => false],
                ['year' => 2030, 'km' => 29173, 'lines' => 842, 'current' => true],
            ],
        ],
        'links' => ['eras' => 'https://vff-project.org/api/v1/eras', 'web' => 'https://vff-project.org'],
    ]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(SiteStats $stats): JsonResponse
    {
        return response()->json([
            'data' => $stats->counts() + ['profile' => $stats->profile()],
            'links' => ['eras' => route('api.eras.index'), 'web' => url('/')],
        ])->header('Cache-Control', 'public, max-age=600');
    }
}
