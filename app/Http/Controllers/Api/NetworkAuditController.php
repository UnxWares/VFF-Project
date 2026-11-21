<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SegmentAudit\NetworkAudits;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Audit', "Ce que VFF sait de la justesse de ses données, à l'échelle du réseau : tronçons et gares les plus mal notés, filtrables par ère, note, contrôle, raison, département et région.", weight: 3)]
class NetworkAuditController extends Controller
{
    /** Tronçons ou gares rendus par défaut, et au plus. */
    public const DEFAULT_LIMIT = NetworkAudits::DEFAULT_LIMIT;

    public const MAX_LIMIT = NetworkAudits::MAX_LIMIT;

    /** Exemple réel (ère 2030), un tronçon sur les 6 412 les plus mal notés. */
    public const SEGMENTS_EXAMPLE = [
        'era_year' => 2030,
        'total' => 6412,
        'limit' => 1,
        'offset' => 0,
        'items' => [[
            'line_slug' => 'rfn-405000',
            'line_code' => '405000',
            'line_name' => "Ligne d'Argentan à Granville",
            'segment_id' => 8821806,
            'status' => 'in_service',
            'traffic_type' => 'mixed',
            'trace_source' => 'bdtopo',
            'length_m' => 276,
            'grades' => ['classification' => 'good', 'placement' => 'poor', 'accuracy' => 'good', 'trace' => 'good'],
            'worst_grade' => 'poor',
            'score' => 80,
            'reasons' => ['classification' => ['sources_agree'], 'placement' => ['median_over_tolerance'], 'accuracy' => ['no_defect'], 'trace' => ['precise_source']],
            'audited_at' => '2026-11-21T00:43:46+01:00',
        ]],
    ];

    /** Exemple réel, une gare sur les gares en désaccord. */
    public const STATIONS_EXAMPLE = [
        'total' => 312,
        'limit' => 1,
        'offset' => 0,
        'items' => [[
            'station_id' => 1204,
            'slug' => 'argentan',
            'name' => 'Argentan',
            'department' => '61',
            'region' => 'Normandie',
            'status' => 'active',
            'position_source' => 'sncf',
            'placement_grade' => 'disagree',
            'families' => 4,
            'nearest_agreeing_m' => 160,
            'farthest_precise_m' => 240,
            'track_offset_m' => 12,
            'opening_grade' => 'sourced',
            'closure_grade' => 'not_applicable',
            'passenger' => 'open',
            'freight' => 'open',
            'checks' => ['far_from_track'],
            'sources' => ['sncf', 'osm', 'bdtopo'],
        ]],
    ];

    /**
     * Audit des tronçons de tout le réseau.
     *
     * Les tronçons audités d'une ère (passe `vff:audit-segments`, méthode dans `docs/AUDIT-TRONCONS.md`),
     * de toutes les lignes ensemble : le moyen de trouver où les données sont le moins sûres. Chaque
     * tronçon donne sa ligne (`line_slug`, `line_code`, `line_name`), son état, ses quatre notes
     * (`grades` : `classification`, `placement`, `accuracy`, `trace`, chacune `good`, `fair`, `poor` ou
     * `unknown`), la plus mauvaise (`worst_grade`), son `score` de 0 (tout sûr) à 100 (tout faux), et les
     * codes de raison de chaque contrôle (`reasons`). Les preuves complètes d'un tronçon sont dans
     * `GET /api/v1/lines/{line}/audit`.
     *
     * **Filtres** (combinés par ET) : `grade` (sans `check`, la plus mauvaise note du tronçon ; avec
     * `check`, la note de ce contrôle), `reason` (code de raison, ex. `on_other_line_track` ; limité au
     * contrôle `check` s'il est donné), `status` (état du tronçon), `department` et `region` (lignes qui
     * passent dans le territoire), `line` (slug ou code à 6 chiffres), `q` (nom de ligne, sans accents).
     * **Tri** (`sort`) : `worst` (score décroissant, par défaut), `length` (les plus longs d'abord),
     * `line` (code de ligne). **Pagination** : `limit` tronçons (50 par défaut, 200 au plus) à partir du
     * rang `offset` ; `total` donne le nombre de tronçons après les filtres, la page suivante commence à
     * `offset + limit` tant que ce rang est inférieur à `total`. Une ère sans audit donne `total` à 0.
     */
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030.", type: 'int', example: 2030)]
    #[QueryParameter('grade', 'Note : `good`, `fair`, `poor` ou `unknown`. Sans `check`, la plus mauvaise des quatre notes du tronçon.', type: 'string', example: 'poor')]
    #[QueryParameter('check', 'Contrôle dont on filtre la note : `classification`, `placement`, `accuracy` ou `trace`. Sert à `grade` et à `reason`.', type: 'string', example: 'placement')]
    #[QueryParameter('reason', 'Code de raison (ex. `on_other_line_track`, `median_over_tolerance`, `source_contradicts`), dans le contrôle `check` s\'il est donné, sinon dans l\'un des quatre.', type: 'string', example: 'on_other_line_track')]
    #[QueryParameter('status', 'État du tronçon : `in_service`, `single_track`, `service_track`, `construction`, `abandoned`, `dismantled` ou `razed`.', type: 'string', example: 'abandoned')]
    #[QueryParameter('department', 'Code INSEE du département (`01` à `95`, `2A`, `2B`, `971` à `976`) : tronçons des lignes qui y passent.', type: 'string', example: '61')]
    #[QueryParameter('region', 'Code INSEE de la région (`11`, `24`, `27`, `28`, `32`, `44`, `52`, `53`, `75`, `76`, `84`, `93`, `94`) : tronçons des lignes de ses départements.', type: 'string', example: '28')]
    #[QueryParameter('line', 'Une seule ligne : slug (`rfn-405000`) ou code SNCF Réseau à 6 chiffres.', type: 'string', example: 'rfn-405000')]
    #[QueryParameter('q', 'Texte cherché dans le nom de la ligne, casse et accents ignorés, 120 caractères au plus.', type: 'string', example: 'Granville')]
    #[QueryParameter('sort', 'Tri : `worst` (le plus mal noté d\'abord, par défaut), `length` (le plus long d\'abord) ou `line` (code de ligne).', type: 'string', default: 'worst', example: 'worst')]
    #[QueryParameter('limit', 'Nombre de tronçons au plus (1 à 200).', type: 'int', default: 50, example: 50)]
    #[QueryParameter('offset', 'Rang du premier tronçon rendu, à partir de 0 (page suivante : `offset + limit`).', type: 'int', default: 0, example: 0)]
    #[ApiResponse(200, 'Une page de tronçons audités, avec le nombre total après les filtres.', examples: [self::SEGMENTS_EXAMPLE])]
    #[ApiResponse(422, 'Paramètre invalide (`grade`, `check`, `status`, `department`, `region` ou `sort` inconnu, `limit` hors de 1 à 200, `offset` négatif).', examples: [['message' => 'The selected grade is invalid.', 'errors' => ['grade' => ['The selected grade is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function segments(Request $request, NetworkAudits $audits): JsonResponse
    {
        $this->normalize($request);
        $valid = $request->validate(NetworkAudits::segmentRules());

        return response()->json($audits->segments($valid, $this->int($valid, 'limit'), $this->int($valid, 'offset')))
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Audit des gares de tout le réseau.
     *
     * Les gares auditées (passe `vff:audit-stations`, méthode dans `docs/AUDIT-GARES.md`) : la note de
     * placement (`placement_grade` : `agree`, `close`, `isolated`, `disagree` ou `none`), les écarts en
     * mètres entre la position retenue et les autres sources (`nearest_agreeing_m`, `farthest_precise_m`,
     * `track_offset_m`), les notes des dates d'ouverture et de fermeture (`opening_grade`,
     * `closure_grade` : `sourced`, `estimated`, `unknown`, `not_applicable`), l'état voyageurs et fret
     * (`passenger`, `freight`), les contrôles croisés en échec (`checks`) et les familles de sources
     * (`sources`). Aucune coordonnée n'est donnée ici : elles figurent, pour les sources redistribuables,
     * dans `GET /api/v1/stations/{station}/audit`. L'audit des gares n'a pas d'ère : une passe par gare.
     *
     * **Filtres** (combinés par ET) : `grade` (note de placement), `check` (contrôle en échec),
     * `opening` et `closure` (notes des dates), `department` et `region` (territoire de la gare), `line`
     * (gare desservie par cette ligne), `q` (nom, sans accents). **Tri** (`sort`) : `spread` (écart à la
     * source précise la plus éloignée, par défaut), `track` (écart à la voie), `name`. **Pagination** :
     * `limit` gares (50 par défaut, 200 au plus) à partir du rang `offset` ; `total` donne le nombre de
     * gares après les filtres.
     */
    #[QueryParameter('grade', 'Note de placement : `agree`, `close`, `isolated`, `disagree` ou `none`. Par défaut : toutes.', type: 'string', example: 'disagree')]
    #[QueryParameter('check', 'Contrôle croisé en échec, ex. `far_from_track`, `wikidata_differs`, `closure_before_opening`, `outlier_source`.', type: 'string', example: 'far_from_track')]
    #[QueryParameter('opening', "Note de la date d'ouverture : `sourced`, `estimated`, `unknown` ou `not_applicable`.", type: 'string', example: 'estimated')]
    #[QueryParameter('closure', 'Note de la date de fermeture : `sourced`, `estimated`, `unknown` ou `not_applicable` (gare ouverte).', type: 'string', example: 'unknown')]
    #[QueryParameter('department', 'Code INSEE du département de la gare (`01` à `95`, `2A`, `2B`, `971` à `976`).', type: 'string', example: '61')]
    #[QueryParameter('region', 'Code INSEE de la région de la gare (`11`, `24`, `27`, `28`, `32`, `44`, `52`, `53`, `75`, `76`, `84`, `93`, `94`).', type: 'string', example: '28')]
    #[QueryParameter('line', 'Gares desservies par cette ligne : slug (`rfn-405000`) ou code à 6 chiffres.', type: 'string', example: 'rfn-405000')]
    #[QueryParameter('q', 'Texte cherché dans le nom de la gare, casse et accents ignorés, 120 caractères au plus.', type: 'string', example: 'Argentan')]
    #[QueryParameter('sort', 'Tri : `spread` (écart à la source précise la plus éloignée, par défaut), `track` (écart à la voie) ou `name`.', type: 'string', default: 'spread', example: 'spread')]
    #[QueryParameter('limit', 'Nombre de gares au plus (1 à 200).', type: 'int', default: 50, example: 50)]
    #[QueryParameter('offset', 'Rang de la première gare rendue, à partir de 0 (page suivante : `offset + limit`).', type: 'int', default: 0, example: 0)]
    #[ApiResponse(200, 'Une page de gares auditées, avec le nombre total après les filtres.', examples: [self::STATIONS_EXAMPLE])]
    #[ApiResponse(422, 'Paramètre invalide (`grade`, `check`, `opening`, `closure`, `department`, `region` ou `sort` inconnu, `limit` hors de 1 à 200, `offset` négatif).', examples: [['message' => 'The selected grade is invalid.', 'errors' => ['grade' => ['The selected grade is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function stations(Request $request, NetworkAudits $audits): JsonResponse
    {
        $this->normalize($request);
        $valid = $request->validate(NetworkAudits::stationRules());

        return response()->json($audits->stations($valid, $this->int($valid, 'limit'), $this->int($valid, 'offset')))
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /** Codes de département en majuscules (`2a` accepté), comme les autres listes. */
    private function normalize(Request $request): void
    {
        if (is_string($request->query('department'))) {
            $request->merge(['department' => strtoupper($request->query('department'))]);
        }
    }

    /** @param  array<string, mixed>  $valid */
    private function int(array $valid, string $key): ?int
    {
        return isset($valid[$key]) ? (int) $valid[$key] : null;
    }
}
