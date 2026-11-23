<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\SegmentAudit\AuditPage;
use App\Services\SegmentAudit\SegmentAudits;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineAuditController extends Controller
{
    /** Tronçons rendus par défaut, et au plus (une ligne longue en compte plusieurs centaines, avec leurs preuves). */
    public const DEFAULT_LIMIT = AuditPage::DEFAULT_LIMIT;

    public const MAX_LIMIT = AuditPage::MAX_LIMIT;

    /** Exemple réel (Argentan - Granville, ère 2030, audit du 2026-11-21), un tronçon et des avis raccourcis. */
    public const EXAMPLE = [
        'line' => ['slug' => 'rfn-405000', 'code' => '405000', 'name' => "Ligne d'Argentan à Granville"],
        'era_year' => 2030,
        'audited_at' => '2026-11-21T00:43:46+01:00',
        'rule_version' => '2026-11-21.1',
        'grades' => ['classification' => 'good', 'placement' => 'good', 'accuracy' => 'good', 'trace' => 'good'],
        'worst_grade' => 'good',
        'score' => 0,
        'length_m' => 130870,
        'shares' => [
            'classification' => ['good' => 1.0],
            'placement' => ['good' => 1.0],
            'accuracy' => ['good' => 0.999, 'fair' => 0.001],
            'trace' => ['good' => 0.982, 'fair' => 0.018],
        ],
        'line_checks' => ['reasons' => ['no_defect'], 'length_ratio' => 0.999, 'official_source' => 'line', 'official_length_m' => 131000,
            'reference_length_m' => 130888, 'station_order_inversions' => 0],
        'segments_total' => 412,
        'limit' => 1,
        'offset' => 0,
        'segments' => [[
            'segment_id' => 8821806,
            'status' => 'in_service',
            'traffic_type' => 'mixed',
            'trace_source' => 'bdtopo',
            'length_m' => 276,
            'grades' => ['classification' => 'good', 'placement' => 'good', 'accuracy' => 'good', 'trace' => 'good'],
            'worst_grade' => 'good',
            'score' => 0,
            'evidence' => [
                'classification' => [
                    'reasons' => ['sources_agree'],
                    'actual' => ['status' => 'in_service', 'traffic' => 'mixed', 'heritage' => false],
                    'rules' => [],
                    'verdicts' => [
                        ['source' => 'wikipedia', 'kind' => 'events', 'expected' => ['class' => 'open', 'traffic' => 'mixed'], 'agrees' => true, 'why' => null,
                            'near_date' => false, 'weak' => false, 'unlocated_end' => false, 'priority' => 4, 'events' => [['id' => 2611, 'type' => 'opening', 'date' => '1866-01-01']],
                            'first_opening_year' => 1866],
                        ['source' => 'sncf', 'kind' => 'current', 'expected' => ['class' => 'open', 'traffic' => null], 'agrees' => true, 'why' => null,
                            'near_date' => false, 'weak' => false, 'priority' => 3, 'observed' => ['statut' => 'Exploitée']],
                    ],
                ],
                'placement' => ['reasons' => ['within_tolerance'], 'reference' => 'osm', 'same_source' => false, 'coverage' => 1, 'median_m' => 1.13, 'p95_m' => 2.36,
                    'references' => ['osm' => ['points' => 6, 'found' => 6, 'median_m' => 1.13, 'p95_m' => 2.36], 'bdtopo' => ['points' => 6, 'found' => 6, 'median_m' => 0, 'p95_m' => 0]]],
                'accuracy' => ['reasons' => ['no_defect'], 'max_turn_deg' => 2.1, 'hairpins' => 0, 'kinks' => 0, 'junction_gaps' => [], 'holes' => [], 'stations' => []],
                'trace' => ['reasons' => ['precise_source'], 'source' => 'bdtopo', 'precision_m' => 2.5, 'precision_class' => 'high', 'precise_coverage' => 1],
            ],
        ]],
    ];

    /**
     * Audit des tronçons d'une ligne.
     *
     * Quatre notes par tronçon, pour une ère, avec leurs preuves : la passe automatique de
     * vérification de VFF (commande `vff:audit-segments`, méthode dans `docs/AUDIT-TRONCONS.md`).
     * Les notes valent `good` (sûr), `fair` (à revoir), `poor` (faux ou contredit) ou `unknown`
     * (rien pour juger). `worst_grade` est la plus mauvaise des quatre, `score` va de 0 (tout sûr)
     * à 100 (tout faux), pondéré par la longueur pour la ligne. Les tronçons sont rangés du plus
     * mal noté au mieux noté ; leur géométrie est celle de `GET /api/v1/lines/{line}/segments`
     * (même `segment_id`, tant que le moteur n'a pas recalculé la ligne).
     *
     * **Classement** (`classification`) : l'état du tronçon (`status`, `traffic_type`) comparé à
     * chaque source datée. Une chronologie par source d'événements (Wikipédia, lignes-oubliées,
     * OpenHistoricalMap, Wikidata…), avec les règles de la carte (état au 31 décembre de l'ère,
     * au jour même pour 2030) ; pour l'ère courante, aussi le statut SNCF Réseau, l'état de la voie
     * dans la BD TOPO de l'IGN, la couche OpenStreetMap et l'état relevé par lignes-oubliées. Chaque
     * avis (`verdicts[]`) donne l'état attendu (`expected.class` : `open`, `closed`, `gone`,
     * `not_open`, `in_place`), s'il est d'accord, pourquoi il ne l'est pas (`why`), s'il change à
     * trois ans près de la date (`near_date`) et s'il est faible (`weak` : estimation, décision
     * administrative tardive, Wikidata, observation non rattachée à la ligne, ou observation seule
     * contre deux autres, `outvoted`). `poor` quand une source fiable contredit sans accord d'une
     * source au moins aussi fiable, ou quand « voyageurs seuls » apparaît hors d'une LGV. Raisons
     * (`reasons`) : `sources_agree`, `source_contradicts`, `sources_disagree`, `weak_contradiction`,
     * `weak_sources_only`, `no_source`, `rule_passenger_only_off_lgv`, `rule_track_unconfirmed`,
     * `observation_outvoted` (une seule observation actuelle, BD TOPO, SNCF « déclassée vendue » ou étiquette
     * OSM contre le registre de SNCF Réseau, contre deux d'accord), `continuous_service` (ère passée : aucune fin datée de la ligne ne touche le
     * tronçon à cette ère, tronçon sûr aujourd'hui).
     *
     * **Placement** (`placement`) : un point tous les 50 m du tronçon, distance à la voie la plus
     * proche d'une source DIFFÉRENTE de celle du tracé (BD TOPO, OpenStreetMap, tracés relevés sur
     * les photographies aériennes anciennes de l'IGN) à moins de 50 m : médiane `median_m` et 95e
     * centile `p95_m`, en mètres. Cible 1 m, tolérance 5 m. Une voie rattachée à une autre ligne ne
     * sert jamais de référence (`references.<source>.all` : même mesure sur toutes les voies,
     * `references.<source>.own` : voies de la ligne seules, jusqu'à 60 m). Raisons : `within_target`,
     * `within_tolerance`, `p95_over_tolerance`, `median_over_tolerance`, `partial_coverage`,
     * `no_track_nearby`, `no_reference`, `abroad`, `only_same_source`, `underground` (tronçon tout en
     * tunnel, non mesuré), `underground_excluded` (points en tunnel hors de la mesure, `references.<source>.underground`
     * les compte), et pour un tronçon qui ne colle
     * qu'à la voie d'une autre ligne : `on_other_line_track` (sa propre voie passe à 8 à 60 m :
     * `poor` pour un tronçon fermé ou déposé ou à une ère passée, `good` pour un tronçon en service
     * aujourd'hui, la raison signalant le cas à relire), `shared_track`
     * (`good`, aucune voie propre à côté : tronc commun ou embranchement posé sur la voie de départ).
     *
     * **Exactitude** (`accuracy`) : virage le plus fort sur 40 m (`max_turn_deg`), allers-retours
     * (plus de 150°), coudes (plus de 60°), écarts aux jonctions (bout libre à 0,05 à 10 m du tracé
     * d'une autre ligne), trous face à un autre bout de la ligne (0,5 à 500 m), gares de la ligne
     * avec leur distance au tronçon. `line_checks` ajoute la longueur du tracé de référence
     * comparée à la longueur officielle et les inversions de l'ordre des gares le long des PK.
     *
     * **Tracé** (`trace`) : source du dessin et précision connue (`precision_m` : BD TOPO 2,5 m,
     * OpenStreetMap environ 2 m, formes SNCF généralisées jusqu'à 165 m). Raisons : `precise_source`,
     * `recalibrated` (placement sûr), `confirmed_by_precise_source` (une voie BD TOPO ou OSM
     * indépendante le suit sur 80 % des points, médiane ≤ 1 m, 95e centile ≤ 5 m), `medium_source`,
     * `precise_trace_available`, `best_available`, `unknown_source`, `unused_trace_in_gap`.
     *
     * **Pagination des tronçons** : `limit` tronçons au plus (50 par défaut, 200 au plus) à partir
     * du rang `offset` (0 par défaut), dans l'ordre du plus mal noté au mieux noté. `segments_total`
     * donne le nombre de tronçons après le filtre `grade` ; la page suivante commence à
     * `offset + limit` tant que ce rang est inférieur à `segments_total`. La synthèse de la ligne
     * (`grades`, `score`, `shares`, `line_checks`) porte toujours sur tous les tronçons.
     *
     * Ère sans audit, ou ligne pas encore auditée : 404 avec `error` = `audit_not_computed`.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     era_year: int, audited_at: string, rule_version: string,
     *     grades: array{classification: string, placement: string, accuracy: string, trace: string},
     *     worst_grade: string, score: int, length_m: int,
     *     shares: array<string, array<string, float>>,
     *     line_checks: array<string, mixed>,
     *     segments_total: int, limit: int, offset: int,
     *     segments: array<array{segment_id: int, status: string, traffic_type: string, trace_source: ?string, length_m: int,
     *         grades: array{classification: string, placement: string, accuracy: string, trace: string},
     *         worst_grade: string, score: int, evidence: array<string, mixed>}>
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-405000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030.", type: 'int', example: 2030)]
    #[QueryParameter('grade', 'Ne garder que les tronçons dont la plus mauvaise note vaut `good`, `fair`, `poor` ou `unknown`. Par défaut : tous.', type: 'string', example: 'poor')]
    #[QueryParameter('limit', 'Nombre de tronçons au plus (1 à 200), les plus mal notés d’abord.', type: 'int', default: 50, example: 50)]
    #[QueryParameter('offset', 'Rang du premier tronçon rendu, à partir de 0 (page suivante : `offset + limit`).', type: 'int', default: 0, example: 0)]
    #[ApiResponse(200, 'Audit de la ligne à cette ère, avec une page de tronçons.', examples: [self::EXAMPLE])]
    #[ApiResponse(404, 'Ligne inconnue, ou audit pas encore calculé pour cette ligne et cette ère (`error` = `audit_not_computed`).', examples: [ErrorExamples::LINE_NOT_FOUND, ['error' => 'audit_not_computed', 'message' => "L'audit de cette ligne n'est pas encore calculé pour cette ère."]])]
    #[ApiResponse(422, 'Paramètre invalide (`era` hors de 1830 à 2030, `grade` inconnu, `limit` hors de 1 à 200, `offset` négatif).', examples: [['message' => 'The selected grade is invalid.', 'errors' => ['grade' => ['The selected grade is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Line $line, Request $request, SegmentAudits $audits): JsonResponse
    {
        $valid = $request->validate([
            'era' => ['nullable', 'integer', 'between:1830,2030'],
            'grade' => ['nullable', 'string', 'in:good,fair,poor,unknown'],
            'limit' => ['nullable', 'integer', 'between:1,'.self::MAX_LIMIT],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);
        $audit = $audits->forLine($line, (int) ($valid['era'] ?? 2030));
        if ($audit === null) {
            return response()->json([
                'error' => 'audit_not_computed',
                'message' => "L'audit de cette ligne n'est pas encore calculé pour cette ère.",
            ], 404);
        }
        $audit = AuditPage::apply($audit, $valid['grade'] ?? null, isset($valid['limit']) ? (int) $valid['limit'] : null, isset($valid['offset']) ? (int) $valid['offset'] : null);

        return response()->json($audit)->header('Cache-Control', 'public, max-age=3600');
    }
}
