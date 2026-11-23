<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WhatWasHereRequest;
use App\Mcp\Support\Attribution;
use App\Mcp\Support\WikipediaExcerpts;
use App\Services\Map\WhatWasHere;
use App\Services\MapDataCache;
use App\Support\Monitoring\PositionlessError;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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
     * **Version courte** (`summary=1`, page « Autour de moi » `/nearby`) : rayon jusqu'à 10 000 m, 15 lignes et 20 gares
     * au plus, une `history` par ligne (années d'ouverture, de fermeture aux voyageurs, au fret, de fermeture, de
     * dépose), les années d'ouverture et de fermeture des gares, et la `commune` du point ; sans signalisation,
     * matériel, événements ni accidents. Même case de 0,001 degré ; la réponse n'est gardée ni dans le cache du serveur
     * ni dans les caches partagés (`Cache-Control: private, no-store`), et une erreur de calcul est journalisée sans la
     * position (ni requête SQL ni coordonnée dans le message).
     *
     * **Droits** : pour un tracé qui ne vient pas d'une source redistribuable, `geometry_withheld` vaut `true`,
     * `distance_m` est `null` et `pk_m` est arrondi à 100 m. Les faits d'une source à usage non commercial ne
     * sont jamais servis.
     */
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
        $summary = $request->boolean('summary');

        $compute = function () use ($lat, $lng, $year, $radius, $lang, $summary): array {
            $result = $this->here->around(['lat' => $lat, 'lng' => $lng, 'year' => $year, 'radius_m' => $radius, 'max_lines' => $summary ? WhatWasHere::SUMMARY_MAX_LINES : 6, 'summary' => $summary], $lang);
            if ($result === null) {
                abort(404, (string) trans('mcp.errors.era_not_found', ['year' => $year], $lang));
            }

            return WikipediaExcerpts::strip($result['payload']) + ['attribution' => $this->attribution->block($result['providers'], $lang)];
        };

        // « Autour de moi » : le point est la position de l'internaute (arrondie). Jamais rangée dans le cache du
        // serveur (sa clé contiendrait la case) ni dans un cache partagé, ni recopiée dans le journal par une erreur
        // (le message d'une erreur SQL contient les valeurs de la requête) : PositionlessError la remplace.
        if ($summary) {
            try {
                $payload = $compute();
            } catch (HttpExceptionInterface $e) {
                throw $e;
            } catch (Throwable $e) {
                throw PositionlessError::from($e);
            }

            return new Response(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 200, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return $this->cache->json($request, "here:{$year}:{$lat}:{$lng}:{$radius}:{$lang}", $compute);
    }
}
