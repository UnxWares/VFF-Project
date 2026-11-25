<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Rinf\RinfDataset;
use App\Services\TrackLayout\StationTrackLayout;
use App\Services\TrackLayout\TrackLayout;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
class StationTrackLayoutController extends Controller
{
    /**
     * Voies d'une gare.
     *
     * Le plan des voies de la gare aujourd'hui, ligne par ligne : pour chaque ligne qui passe par la gare et dont les voies
     * sont décrites autour d'elle, le schéma des voies de la ligne (même contenu et mêmes règles que
     * `GET /api/v1/lines/{line}/track-layout`) sur une fenêtre autour de la gare (`window`, PK en mètres de la ligne) :
     * voies numérotées (voies à quai, voies principales, voies de service), aiguillages, traversées, heurtoirs, signaux,
     * points d'exploitation, bande des vitesses et des déclivités, chaque objet avec sa provenance (`source`, `precision`).
     * Une gare de bifurcation a un bloc par ligne (`lines[]`), la ligne qui a le plus de voies d'abord ; chaque ligne garde
     * ses propres PK. Réseau d'aujourd'hui seulement : aucune voie n'est inventée pour le passé.
     *
     * **Fenêtre** d'une ligne : les voies propres à la gare sur cette ligne (voies d'OpenStreetMap rattachées à la gare
     * hors voies principales, voies des points d'exploitation de la gare au RINF sur leur longueur déclarée) et son PK
     * (`station_pk_m`, provenance dans `station_pk_source`), élargies de 200 m de chaque côté (aiguillages des têtes de
     * gare), entre 500 m et 2 500 m du PK de la gare.
     *
     * **Quais** (`platforms[]`, un bord de quai par voie : un quai central entre deux voies en a deux) : le RINF donne
     * la longueur utile (`length_m`) et la hauteur (`height_mm`) des bords de quai des points d'exploitation de la gare ;
     * la « Liste des quais » de SNCF Réseau (ODbL) donne leurs PK sur la ligne. Un bord connu des deux sources a la
     * longueur et la hauteur du RINF et les PK de SNCF Réseau (`pk_source` `sncf`, `precision` `exact`) ; un bord que le
     * RINF seul connaît est placé autour du PK de la gare sur sa voie (`pk_source` `vff`, `precision` `approximate`) ou
     * sans PK si sa voie n'est pas dessinée ; un bord que SNCF Réseau seul connaît (`source` `sncf`) n'a pas de hauteur.
     * `track` : identifiant de la voie bordée dans le schéma de sa ligne (`lines[].track_layout.tracks[].id`), nul quand
     * elle n'y est pas trouvée.
     *
     * `operational_points` : points d'exploitation du RINF rattachés à la gare (UOPID). `sources` : sources de tous les
     * blocs et des quais, avec licence et mention à reproduire (`attribution`). `counts` : lignes, voies, aiguillages,
     * signaux, traversées, heurtoirs (somme des fenêtres) et bords de quai. Une gare sans voie décrite renvoie 200,
     * `lines` vide (et ses quais s'ils sont connus).
     *
     * **Paramètres** : `line` (une seule ligne : slug ou code SNCF Réseau à 6 chiffres) ; `kinds`, familles du schéma
     * de chaque ligne, séparées par des virgules (comme pour une ligne ; `kinds=operational_points` donne un aperçu léger :
     * fenêtres, points d'exploitation, `counts` et quais, sans les voies).
     *
     * **Licence** : RINF, © Agence de l'Union européenne pour les chemins de fer, dump officiel sous EUPL 1.2 ;
     * OpenStreetMap, ODbL ; SNCF Réseau, ODbL. Reproduisez la mention `attribution` de chaque source.
     *
     * @response array{
     *     station: array{slug: string, name: string, uic_code: ?string},
     *     operational_points: array<array{uopid: string, name: string, source: string}>,
     *     lines: array<array{line: array{code: ?string, slug: string, name: string}, station_pk_m: ?int, station_pk_label: ?string, station_pk_source: ?string, window: array{from_m: int, to_m: int}, track_layout: array<string, mixed>}>,
     *     platforms: array<array{id: string, ref: string, track_ref: string, line_code: ?string, track: ?string, pk_from_m: ?int, pk_to_m: ?int, length_m: ?int, height_mm: ?int, source: string, pk_source: ?string, precision: ?string}>,
     *     sources: array<array{key: string, label: string, licence: string, dataset: ?string, fetched_at: ?string, url: ?string, attribution: ?string, licence_url: ?string}>,
     *     counts: array{lines: int, tracks: int, switches: int, signals: int, crossings: int, buffer_stops: int, platforms: int}
     * }
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par l\'annuaire ou la recherche.', example: 'cognac')]
    #[QueryParameter('line', 'Une seule ligne : slug (`rfn-579000`) ou code SNCF Réseau à 6 chiffres (`579000`). Par défaut : toutes les lignes de la gare.', type: 'string', example: '579000')]
    #[QueryParameter('kinds', 'Familles du schéma de chaque ligne, séparées par des virgules : `operational_points`, `tracks`, `switches`, `signals`, `crossings`, `buffer_stops`, `milestones`, `speeds`, `gradients`. Par défaut : toutes.', type: 'string', example: 'tracks,switches,operational_points')]
    #[ApiResponse(200, 'Voies de la gare, ligne par ligne, et ses quais (`lines` vide si aucune source ne décrit ses voies).', examples: [[
        'station' => ['slug' => 'cognac', 'name' => 'Cognac', 'uic_code' => '87491449'],
        'operational_points' => [['uopid' => 'FR0000003513', 'name' => 'Cognac', 'source' => 'rinf']],
        'lines' => [[
            'line' => ['code' => '579000', 'slug' => 'rfn-579000', 'name' => 'Ligne de Beillant à Angoulême'],
            'station_pk_m' => 17277, 'station_pk_label' => '17+277', 'station_pk_source' => 'sncf',
            'window' => ['from_m' => 16777, 'to_m' => 18201],
            'track_layout' => ['line' => ['code' => '579000', 'slug' => 'rfn-579000', 'name' => 'Ligne de Beillant à Angoulême'],
                'pk' => ['start_m' => 16777, 'end_m' => 18201, 'start_label' => '16+777', 'end_label' => '18+201'],
                'sources' => [RinfDataset::SOURCE_EXAMPLE], 'operational_points' => [], 'tracks' => [], 'switches' => [], 'signals' => [],
                'crossings' => [], 'buffer_stops' => [], 'milestones' => [], 'strip' => ['speeds' => [], 'gradients' => []],
                'coverage' => ['total_m' => 1424, 'rinf_m' => 1424, 'osm_m' => 1424, 'vff_m' => 0, 'missing' => []],
                'counts' => ['operational_points' => 4, 'tracks' => 30, 'switches' => 25, 'signals' => 0, 'crossings' => 1, 'buffer_stops' => 11, 'milestones' => 0]],
        ]],
        'platforms' => [[
            'id' => 'rinf:pe:9ff33f643f3e', 'ref' => 'Quai VE', 'track_ref' => 'E', 'line_code' => '579000', 'track' => 'osm:way:352597545',
            'pk_from_m' => 17240, 'pk_to_m' => 17450, 'length_m' => 145, 'height_mm' => 300, 'source' => 'rinf', 'pk_source' => 'sncf', 'precision' => 'exact',
        ]],
        'sources' => [RinfDataset::SOURCE_EXAMPLE],
        'counts' => ['lines' => 1, 'tracks' => 30, 'switches' => 25, 'signals' => 0, 'crossings' => 1, 'buffer_stops' => 11, 'platforms' => 2],
    ]])]
    #[ApiResponse(404, 'Gare inconnue.', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(422, 'Paramètre invalide (famille inconnue).')]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Station $station, Request $request, StationTrackLayout $layout): JsonResponse
    {
        $request->validate([
            'line' => 'nullable|string|max:100',
            'kinds' => 'nullable|string|max:200',
        ]);
        $kinds = null;
        if ($request->filled('kinds')) {
            $kinds = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('kinds')))));
            $unknown = array_diff($kinds, TrackLayout::KINDS);
            if ($unknown !== []) {
                return response()->json(['message' => 'Unknown kinds: '.implode(', ', $unknown).'. Allowed: '.implode(', ', TrackLayout::KINDS).'.',
                    'errors' => ['kinds' => ['Unknown kind.']]], 422);
            }
        }
        $line = $request->filled('line') ? (string) $request->input('line') : null;

        return response()->json($layout->forStation($station, $kinds, $line))
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
