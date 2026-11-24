<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Rinf\RinfDataset;
use App\Services\TrackLayout\TrackLayout;
use App\Support\Search\PkQuery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineTrackLayoutController extends Controller
{
    /**
     * Schéma des voies d'une ligne.
     *
     * Toutes les voies de la ligne aujourd'hui, à la manière d'un « routebook » : voies principales numérotées
     * (voie 1, voie 2, voie unique), voies de gare, d'évitement et de service, liaisons, aiguillages, traversées,
     * signaux, heurtoirs (`buffer_stops`), bornes kilométriques (`milestones`, inscription dans `label`) et points
     * d'exploitation, dans l'ordre des points kilométriques (PK), avec une bande des vitesses et des déclivités.
     * Réseau d'aujourd'hui seulement (ère courante) : aucune voie n'est inventée pour le passé.
     *
     * **Sources**, par ordre de préférence (`source` de chaque objet, fiche dans `sources[]`) : `rinf`, le RINF
     * (registre de l'infrastructure de l'Agence de l'Union européenne pour les chemins de fer, données déclarées
     * par SNCF Réseau, graphe de connaissances de l'ERA) ; `osm`, OpenStreetMap (ODbL), là où le RINF ne dit rien ;
     * `vff`, données déjà dans VFF (PK sourcés des gares, vitesses et passages à niveau de SNCF Réseau, déclivités
     * du profil en long). Une source suivante ne fait que compléter : ses voies ne sont gardées que hors des
     * portions déjà décrites. `precision` vaut `exact` (position déclarée par la source), `derived` (calculée
     * par VFF à partir de la source) ou `approximate` (placée à peu près : une voie de gare du RINF, sans PK
     * propre, est centrée sur le PK de la gare, sur sa longueur déclarée).
     *
     * **PK** en mètres dans la numérotation de la ligne (celle de SNCF Réseau, que VFF suit pour les PK
     * sourcés des gares). `pk` donne l'étendue servie. `lane` est la rangée du schéma, calculée par le serveur :
     * 0 pour la voie 1 ou la voie unique, 1 pour la voie 2, puis les autres voies au-dessus (rangées négatives,
     * numéros impairs, côté voie 1) ou au-dessous (numéros pairs, côté voie 2) de leur voie mère, sans croisement
     * inutile. « up » = sens des PK croissants.
     *
     * **Couverture** (`coverage`) : longueur servie (`total_m`), longueur des voies décrites par le RINF
     * (`rinf_m`), par OSM (`osm_m`) et par VFF (`vff_m`), et portions sans donnée par famille (`missing[]`,
     * `what` = `tracks`, `switches`, `signals`, `speeds`, `gradients`). SNCF Réseau ne déclare au RINF ni
     * aiguillage ni signal : sans OSM, ces familles sont absentes sur toute la ligne.
     *
     * **Paramètres** : `from_pk`, `to_pk` (portion, en kilomètres : `120`, `120,5` ou `120+500` ; ou en mètres
     * par `from_pk_m`, `to_pk_m`) ; `kinds`, familles à servir séparées par des virgules (`operational_points`,
     * `tracks`, `switches`, `signals`, `crossings`, `buffer_stops`, `milestones`, `speeds`, `gradients` ; par défaut
     * toutes). Une ligne sans
     * donnée renvoie 200, des listes vides et `coverage`.
     *
     * **Licence** : RINF, © Agence de l'Union européenne pour les chemins de fer, dump officiel sous EUPL 1.2 ;
     * reproduisez la mention `attribution` de la fiche `rinf`. OpenStreetMap : ODbL, « © contributeurs
     * OpenStreetMap ». Données de SNCF Réseau : ODbL.
     *
     * @response array{
     *     line: array{code: ?string, slug: string, name: string},
     *     pk: array{start_m: ?int, end_m: ?int, start_label: ?string, end_label: ?string},
     *     sources: array<array{key: string, label: string, licence: string, dataset: ?string, fetched_at: ?string, url: ?string, attribution: ?string, licence_url: ?string}>,
     *     operational_points: array<array{id: string, name: string, kind: string, pk_m: int, uopid: ?string, station_slug: ?string, rinf_type: ?int, source: string, precision: string}>,
     *     tracks: array<array{id: string, ref: ?string, rinf_ref: ?string, rinf_id: ?string, kind: string, pk_from_m: int, pk_to_m: int, lane: int, op_id: ?string, station_slug: ?string, length_m: ?int, electrified: ?bool, platform: ?bool, direction: ?string, max_speed_kmh: ?int, source: string, precision: string}>,
     *     switches: array<array{id: string, ref: ?string, pk_m: int, kind: ?string, from_track: ?string, to_track: ?string, facing: ?string, source: string, precision: string}>,
     *     signals: array<array{id: string, ref: ?string, pk_m: int, kind: string, form: ?string, track: ?string, direction: ?string, source: string, precision: string}>,
     *     crossings: array<array{id: string, pk_m: int, kind: string, ref: ?string, use: ?string, tracks: ?array<string>, source: string, precision: ?string}>,
     *     buffer_stops: array<array{id: string, pk_m: int, track: ?string, source: string, precision: string}>,
     *     milestones: array<array{id: string, pk_m: int, label: ?string, source: string, precision: string}>,
     *     strip: array{
     *         speeds: array<array{pk_from_m: int, pk_to_m: int, kmh: int, source: string}>,
     *         gradients: array<array{pk_from_m: int, pk_to_m: int, permille: float, source: string, precision: string}>
     *     },
     *     coverage: array{total_m: int, rinf_m: int, osm_m: int, vff_m: int, missing: array<array{pk_from_m: int, pk_to_m: int, what: string}>}
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-579000')]
    #[QueryParameter('from_pk', 'Début de la portion, PK en kilomètres : `120`, `120,5` ou `120+500` (« + » encodé `%2B` dans une adresse). Par défaut : début de la ligne.', type: 'string', example: '17+000')]
    #[QueryParameter('to_pk', 'Fin de la portion, PK en kilomètres, même notation. Par défaut : fin de la ligne.', type: 'string', example: '18+000')]
    #[QueryParameter('from_pk_m', 'Début de la portion, PK en mètres (au lieu de `from_pk`).', type: 'int', example: 17000)]
    #[QueryParameter('to_pk_m', 'Fin de la portion, PK en mètres (au lieu de `to_pk`).', type: 'int', example: 18000)]
    #[QueryParameter('kinds', 'Familles à servir, séparées par des virgules : `operational_points`, `tracks`, `switches`, `signals`, `crossings`, `buffer_stops`, `milestones`, `speeds`, `gradients`. Par défaut : toutes.', type: 'string', example: 'tracks,operational_points')]
    #[ApiResponse(200, 'Schéma des voies de la ligne (listes vides et couverture si aucune source ne la décrit).', examples: [[
        'line' => ['code' => '579000', 'slug' => 'rfn-579000', 'name' => 'Ligne de Beillant à Angoulême'],
        'pk' => ['start_m' => 17095, 'end_m' => 17917, 'start_label' => '17+095', 'end_label' => '17+917'],
        'sources' => [RinfDataset::SOURCE_EXAMPLE],
        'operational_points' => [[
            'id' => 'rinf:op:FR0000003513', 'name' => 'Cognac', 'kind' => 'station', 'pk_m' => 17277, 'uopid' => 'FR0000003513',
            'station_slug' => 'cognac', 'rinf_type' => 10, 'source' => 'rinf', 'precision' => 'exact',
        ]],
        'tracks' => [[
            'id' => 'rinf:t:0d4c1e2f3a4b', 'ref' => 'VU', 'rinf_ref' => 'Voie UNIQUE de Beillant à St-Michel-sur-Charente', 'kind' => 'running',
            'pk_from_m' => 17277, 'pk_to_m' => 17917, 'lane' => 0, 'op_id' => null, 'length_m' => 640, 'electrified' => false,
            'platform' => false, 'direction' => 'both', 'max_speed_kmh' => 140, 'source' => 'rinf', 'precision' => 'exact',
        ], [
            'id' => 'rinf:t:5e6f7a8b9c0d', 'ref' => 'E', 'rinf_ref' => 'Voie E de Cognac', 'kind' => 'loop',
            'pk_from_m' => 17205, 'pk_to_m' => 17350, 'lane' => -1, 'op_id' => 'rinf:op:FR0000003513', 'length_m' => null, 'electrified' => null,
            'platform' => true, 'direction' => null, 'max_speed_kmh' => null, 'source' => 'rinf', 'precision' => 'approximate',
        ]],
        'switches' => [],
        'signals' => [],
        'crossings' => [],
        'buffer_stops' => [],
        'milestones' => [],
        'strip' => [
            'speeds' => [['pk_from_m' => 17277, 'pk_to_m' => 17917, 'kmh' => 140, 'source' => 'rinf']],
            'gradients' => [],
        ],
        'coverage' => ['total_m' => 822, 'rinf_m' => 822, 'osm_m' => 0, 'vff_m' => 0, 'missing' => [
            ['pk_from_m' => 17095, 'pk_to_m' => 17917, 'what' => 'switches'],
            ['pk_from_m' => 17095, 'pk_to_m' => 17917, 'what' => 'signals'],
            ['pk_from_m' => 17095, 'pk_to_m' => 17917, 'what' => 'gradients'],
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, 'Paramètre invalide (PK illisible, famille inconnue).')]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Line $line, Request $request, TrackLayout $layout): JsonResponse
    {
        $request->validate([
            'from_pk' => 'nullable|string|max:20',
            'to_pk' => 'nullable|string|max:20',
            'from_pk_m' => 'nullable|integer|between:-100000,5000000',
            'to_pk_m' => 'nullable|integer|between:-100000,5000000',
            'kinds' => 'nullable|string|max:200',
        ]);
        $bounds = [];
        foreach (['from', 'to'] as $end) {
            $metres = $request->filled("{$end}_pk_m") ? $request->integer("{$end}_pk_m") : null;
            if ($metres === null && $request->filled("{$end}_pk")) {
                $metres = PkQuery::parseValue((string) $request->input("{$end}_pk"));
                if ($metres === null) {
                    return response()->json(['message' => "The {$end}_pk field must be kilometres: 120, 120,5 or 120+500.",
                        'errors' => ["{$end}_pk" => ['Invalid pk.']]], 422);
                }
            }
            $bounds[$end] = $metres;
        }
        $kinds = null;
        if ($request->filled('kinds')) {
            $kinds = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('kinds')))));
            $unknown = array_diff($kinds, TrackLayout::KINDS);
            if ($unknown !== []) {
                return response()->json(['message' => 'Unknown kinds: '.implode(', ', $unknown).'. Allowed: '.implode(', ', TrackLayout::KINDS).'.',
                    'errors' => ['kinds' => ['Unknown kind.']]], 422);
            }
        }

        return response()->json($layout->forLine($line, $bounds['from'], $bounds['to'], $kinds))
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
