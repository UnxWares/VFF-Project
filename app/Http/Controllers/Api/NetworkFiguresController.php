<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NetworkFigures;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Époques', "Les ères de la carte (un instantané par décennie, de 1830 à aujourd'hui), leur présentation et les statistiques du réseau à chaque époque.", weight: 1)]
class NetworkFiguresController extends Controller
{
    /**
     * Le réseau en chiffres, décennie par décennie.
     *
     * Les agrégats de la page « Le réseau en chiffres », calculés par une commande puis gardés en cache
     * (`computed_at` donne la date du calcul). Une ligne par ère de la carte dans `decades`, de 1830 à aujourd'hui :
     *
     * - `year` et `current` : l'ère (instantané au 31 décembre de l'année ; l'ère courante est aujourd'hui) ;
     * - `from_year` : première année de la période que résument les flux de la ligne (événements et accidents),
     *   `null` pour la plus ancienne ; la période finit à `year` (jusqu'à aujourd'hui pour l'ère courante) ;
     * - `open_km` : lignes ouvertes, en kilomètres (tronçons de la carte, voies parallèles comprises, donc un peu
     *   plus que la longueur d'axe publiée par l'exploitant), dont `open_passenger_km` (trafic voyageurs ou mixte),
     *   `open_freight_km` (fret seul) et `open_unknown_km` (trafic non précisé) ;
     * - `closed_km` et `gone_km` : lignes fermées (voie conservée) et déposées, en kilomètres ;
     * - `electrified_km` et `electrified_share` : lignes ouvertes électrifiées (part entre 0 et 1) ;
     * - `removed_km` et `added_km` : kilomètres retirés du service et mis en service depuis l'ère précédente,
     *   ligne par ligne ;
     * - `stations` : gares par statut (`active`, `tourist`, `no_commercial`, `declassified`) ;
     * - `events` : événements datés de la période (`opened`, `reopened`, `closed` dont `closed_passenger` et
     *   `closed_freight`, `dismantled`) ; une date qui n'est qu'une année tirée du nom d'un tracé n'est pas comptée ;
     * - `accidents` : `count`, `deaths` (somme des bilans connus) et `deaths_known` (accidents au bilan connu).
     *
     * `tracks` donne la part de double voie du réseau d'aujourd'hui (SNCF Réseau, ODbL), ou `null` tant que le jeu
     * n'est pas importé ; `waves` nomme les périodes de plus forts retraits du service et de plus de réouvertures.
     */
    #[ApiResponse(200, 'Les chiffres du réseau par décennie (extrait).', examples: [[
        'data' => [
            'computed_at' => '2026-11-23T01:34:00+01:00',
            'decades' => [[
                'year' => 1930, 'current' => false, 'from_year' => 1921,
                'open_km' => 60499, 'open_passenger_km' => 60074, 'open_freight_km' => 168, 'open_unknown_km' => 257,
                'closed_km' => 789, 'gone_km' => 1840, 'electrified_km' => 1270, 'electrified_share' => 0.021,
                'removed_km' => 446, 'added_km' => 1879,
                'stations' => ['active' => 8573, 'tourist' => 4, 'no_commercial' => 23, 'declassified' => 10],
                'events' => ['opened' => 137, 'reopened' => 5, 'closed' => 51, 'closed_passenger' => 20, 'closed_freight' => 8, 'dismantled' => 8],
                'accidents' => ['count' => 112, 'deaths' => 463, 'deaths_known' => 80],
            ]],
            'tracks' => null,
            'waves' => ['closures' => [['year' => 1940, 'removed_km' => 9126, 'closed_events' => 400]], 'reopenings' => []],
        ],
        'links' => ['web' => 'https://vff-project.org/network', 'stats' => 'https://vff-project.org/api/v1/stats'],
    ]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(NetworkFigures $figures): JsonResponse
    {
        return response()->json([
            'data' => $figures->get(),
            'links' => ['web' => route('network.figures'), 'stats' => route('api.stats')],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
