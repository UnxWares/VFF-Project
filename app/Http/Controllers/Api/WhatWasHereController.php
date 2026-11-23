<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WhatWasHereRequest;
use App\Mcp\Support\Attribution;
use App\Mcp\Support\WikipediaExcerpts;
use App\Services\Map\WhatWasHere;
use App\Services\MapDataCache;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\Response;

#[Group('Cartographie par ère', "Le réseau tel qu'il était à la fin d'une décennie : tracés et gares en GeoJSON, tuiles vectorielles MVT pour MapLibre.", weight: 1)]
class WhatWasHereController extends Controller
{
    public function __construct(private readonly WhatWasHere $here, private readonly Attribution $attribution, private readonly MapDataCache $cache) {}

    /**
     * Qu'y avait-il ici ?
     *
     * Autour d'un point et pour une année : les lignes présentes à l'époque (état à l'ère, point kilométrique,
     * gare la plus proche, électrification, signalisation, matériel daté, événements de l'année), les gares
     * proches avec leur état et les accidents de l'année. Même contenu que l'outil MCP `what_was_here`.
     *
     * **Mise en cache** : le point est ramené au centre de sa case de 0,001 degré (environ 100 m) ; la
     * réponse (`point`) décrit ce centre, et deux appels d'une même case et d'une même année donnent la même
     * réponse. L'année est ramenée à l'ère qui la contient (1937 donne 1930) pour l'état des lignes ; les
     * événements et les accidents utilisent l'année demandée. Sans `year` : aujourd'hui.
     *
     * **Droits** : pour un tracé qui ne vient pas d'une source redistribuable, `geometry_withheld` vaut `true`,
     * `distance_m` est `null` et `pk_m` est arrondi à 100 m. Les faits d'une source à usage non commercial ne
     * sont jamais servis.
     */
    #[QueryParameter('lat', 'Latitude WGS 84 en degrés décimaux.', required: true, type: 'number', example: 48.39)]
    #[QueryParameter('lng', 'Longitude WGS 84 en degrés décimaux.', required: true, type: 'number', example: -4.49)]
    #[QueryParameter('year', "Année de 1800 à 2100 (aujourd'hui par défaut).", type: 'integer', example: 1930)]
    #[QueryParameter('radius_m', 'Rayon de recherche en mètres, 100 à 1 000 (400 par défaut).', type: 'integer', example: 400)]
    #[QueryParameter('lang', 'Langue des libellés : fr (défaut), en ou de.', type: 'string', example: 'fr')]
    #[ApiResponse(200, 'Lignes, gares, événements et accidents autour du point.', examples: [[
        'point' => ['lat' => 48.8765, 'lng' => 2.3245, 'radius_m' => 400],
        'year' => 1930,
        'era' => ['year' => 1930, 'is_current' => false, 'snapshot' => '1930-12-31'],
        'lines' => [[
            'slug' => 'rfn-001000', 'name' => 'Ligne de Paris-Saint-Lazare au Havre', 'code' => '001000', 'distance_m' => 12,
            'nearest_station' => ['slug' => 'paris-saint-lazare', 'name' => 'Paris-Saint-Lazare', 'distance_m' => 40],
            'pk_m' => 300, 'status' => 'in_service', 'traffic' => 'mixed', 'geometry_withheld' => false, 'events' => [],
        ]],
        'stations' => [['slug' => 'paris-saint-lazare', 'name' => 'Paris-Saint-Lazare', 'city' => 'Paris', 'distance_m' => 40, 'status' => 'active']],
        'accidents' => [],
        'links' => ['map' => 'https://vff-project.org/maps?era=1930&center=2.32450,48.87650&zoom=15'],
        'attribution' => ['notice' => '...', 'sources' => []],
    ]])]
    #[ApiResponse(422, 'Point absent ou hors bornes.')]
    public function show(WhatWasHereRequest $request): Response
    {
        [$lat, $lng] = $request->cell();
        $year = (int) $request->query('year', date('Y'));
        $radius = (int) $request->query('radius_m', WhatWasHereRequest::DEFAULT_RADIUS_M);
        $lang = (string) $request->query('lang', 'fr');

        return $this->cache->json(
            $request,
            "here:{$year}:{$lat}:{$lng}:{$radius}:{$lang}",
            function () use ($lat, $lng, $year, $radius, $lang): array {
                $result = $this->here->around(['lat' => $lat, 'lng' => $lng, 'year' => $year, 'radius_m' => $radius, 'max_lines' => 6], $lang);
                if ($result === null) {
                    abort(404, (string) trans('mcp.errors.era_not_found', ['year' => $year], $lang));
                }

                return WikipediaExcerpts::strip($result['payload']) + ['attribution' => $this->attribution->block($result['providers'], $lang)];
            },
        );
    }
}
