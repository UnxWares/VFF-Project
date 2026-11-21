<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Services\NetworkStats;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

#[Group('Époques', "Les ères de la carte (un instantané par décennie, de 1830 à aujourd'hui), leur présentation et les statistiques du réseau à chaque époque.", weight: 1)]
class EraController extends Controller
{
    /**
     * Liste des ères.
     *
     * Les ères de la carte, de la plus ancienne à la plus récente : chacune
     * est un instantané du réseau au **31/12** de son année (`snapshot_date`).
     * L'ère courante (`is_current`, 2030) décrit le réseau **d'aujourd'hui**,
     * recalculé au jour même : son `snapshot_date` vaut `null`.
     *
     * `label` et `description` présentent la période, en français (textes de
     * VFF). `has_data` vaut `false` pour une ère dont la carte n'a encore
     * aucun tronçon. Les liens mènent à la fiche de l'ère (avec ses
     * statistiques), à ses GeoJSON, à ses tuiles vectorielles et à son export
     * GTFS.
     */
    #[ApiResponse(200, 'Les ères (extrait).', examples: [[
        'data' => [[
            'year' => 1930, 'label' => "L'apogée du réseau", 'is_current' => false, 'snapshot_date' => '1930-12-31', 'has_data' => true,
            'description' => "Le réseau atteint son extension maximale : plus de 40 000 km de lignes d'intérêt général…",
            'links' => ['self' => 'https://vff-project.org/api/v1/eras/1930', 'map' => 'https://vff-project.org/maps?era=1930', 'lines' => 'https://vff-project.org/api/v1/eras/1930/lines.geojson', 'stations' => 'https://vff-project.org/api/v1/eras/1930/stations.geojson', 'gtfs' => 'https://vff-project.org/api/v1/eras/1930/gtfs.zip'],
        ], [
            'year' => 2030, 'label' => "Le réseau aujourd'hui", 'is_current' => true, 'snapshot_date' => null, 'has_data' => true,
            'description' => "État actuel du réseau, tenu à jour jusqu'à fin 2030…",
            'links' => ['self' => 'https://vff-project.org/api/v1/eras/2030', 'map' => 'https://vff-project.org/maps?era=2030', 'lines' => 'https://vff-project.org/api/v1/eras/2030/lines.geojson', 'stations' => 'https://vff-project.org/api/v1/eras/2030/stations.geojson', 'gtfs' => 'https://vff-project.org/api/v1/eras/2030/gtfs.zip'],
        ]],
    ]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(): JsonResponse
    {
        // Ères qui ont au moins un tronçon : une recherche par index par ère (12 ms) au lieu d'un DISTINCT sur
        // toute la table des tronçons (70 à 340 ms). Même ensemble d'ères.
        $mapped = array_flip(array_map('intval', DB::table('eras')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('line_segments')->whereColumn('line_segments.era_id', 'eras.id'))
            ->pluck('id')->all()));

        return response()->json([
            'data' => Era::orderBy('year')->get()->map(fn (Era $era): array => $this->present($era, isset($mapped[$era->id])))->all(),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Une ère et les statistiques du réseau.
     *
     * L'ère et l'état du réseau à cette époque, d'après la carte VFF (mêmes
     * chiffres que la carte et l'accueil) :
     *
     * - `open_km` : longueur en service (voyageurs, fret ou associatif),
     *   `closed_km` : fermée, voie en place, `gone_km` : déposée ou effacée,
     *   `electrified_km` : électrifiée et en service, en km arrondis ;
     * - `open_km_by_traffic` : longueur en service par trafic (`mixed`,
     *   `passenger`, `freight`, `heritage` associatif, `unknown`) ;
     * - `active_lines` : nombre de lignes portant du trafic ;
     * - `stations_by_status` : nombre de gares par statut à cette ère
     *   (`active`, `tourist`, `no_commercial`, `declassified`).
     *
     * Les longueurs sont la somme des tronçons de la carte (voies parallèles
     * d'une même ligne comprises) : un ordre de grandeur, pas le kilométrage
     * officiel. Provenance des tracés : OpenStreetMap et SNCF (ODbL), IGN
     * (Licence Ouverte 2.0), OpenHistoricalMap (CC0), archeoferroviaire
     * (usage non commercial) ; voir `/sources`.
     */
    #[PathParameter('year', "Année de l'ère : une décennie de 1830 à 2030 (1 à 4 chiffres).", example: 1930)]
    #[ApiResponse(200, "L'ère 1930 et ses statistiques.", examples: [[
        'data' => [
            'year' => 1930, 'label' => "L'apogée du réseau", 'is_current' => false, 'snapshot_date' => '1930-12-31', 'has_data' => true,
            'description' => "Le réseau atteint son extension maximale : plus de 40 000 km de lignes d'intérêt général…",
            'stats' => [
                'open_km' => 58678, 'closed_km' => 778, 'gone_km' => 1780, 'electrified_km' => 1494, 'active_lines' => 1390,
                'open_km_by_traffic' => ['mixed' => 58253, 'unknown' => 258, 'freight' => 168],
                'stations_by_status' => ['active' => 8418, 'declassified' => 33, 'no_commercial' => 21, 'tourist' => 3],
            ],
            'links' => ['self' => 'https://vff-project.org/api/v1/eras/1930', 'map' => 'https://vff-project.org/maps?era=1930', 'lines' => 'https://vff-project.org/api/v1/eras/1930/lines.geojson', 'stations' => 'https://vff-project.org/api/v1/eras/1930/stations.geojson', 'gtfs' => 'https://vff-project.org/api/v1/eras/1930/gtfs.zip'],
        ],
    ]])]
    #[ApiResponse(404, 'Ère inconnue (année qui n\'est pas une décennie de la carte).', examples: [ErrorExamples::ERA_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(int $year, NetworkStats $stats): JsonResponse
    {
        $era = Era::where('year', $year)->first();
        if ($era === null) {
            return response()->json(ErrorExamples::eraNotFound($year), 404);
        }
        $hasData = DB::table('line_segments')->where('era_id', $era->id)->exists();

        return response()->json([
            'data' => array_slice($this->present($era, $hasData), 0, -1, true)
                + ['stats' => $stats->eraProfile($era->id), 'links' => $this->links($era)],
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /** @return array<string, mixed> */
    private function present(Era $era, bool $hasData): array
    {
        return [
            'year' => $era->year,
            'label' => $era->label,
            'is_current' => $era->is_current,
            'snapshot_date' => $era->is_current ? null : sprintf('%04d-12-31', $era->year),
            'has_data' => $hasData,
            'description' => $era->description,
            'links' => $this->links($era),
        ];
    }

    /** @return array<string, string> */
    private function links(Era $era): array
    {
        return [
            'self' => route('api.eras.show', $era->year),
            'map' => url('/maps?era='.$era->year),
            'lines' => route('api.eras.lines', $era->year),
            'stations' => route('api.eras.stations', $era->year),
            'gtfs' => route('api.eras.gtfs', $era->year),
        ];
    }
}
