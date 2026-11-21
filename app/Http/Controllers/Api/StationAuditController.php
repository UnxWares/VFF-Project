<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\StationAudit\StationAuditQuery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
class StationAuditController extends Controller
{
    /**
     * Audit d'une gare : placement et dates.
     *
     * Ce que VFF sait de la justesse d'une gare, d'après la dernière passe
     * d'audit (`vff:audit-stations`) et les dates trouvées dans Wikidata. Rien
     * n'y est corrigé : l'audit compare les sources entre elles.
     *
     * **Note de placement** (`placement.grade`) : les positions de la gare
     * données par chaque famille de sources (SNCF, OpenStreetMap, BD TOPO de
     * l'IGN, Wikidata, OpenHistoricalMap, listes de gares posées par leur point
     * kilométrique) sont comparées à la position retenue par VFF.
     * `agree` : une source indépendante à moins de 20 m et aucune contradiction ;
     * `close` : la plus proche entre 20 et 100 m ; `isolated` : aucune source
     * indépendante proche (une seule famille, ou seulement des positions
     * grossières) ; `disagree` : une source précise à plus de 100 m, ou une
     * source grossière (Wikidata, liste calée à plus de 50 m près) à plus de
     * 1 km, sauf si c'est la seule source lointaine et qu'une autre famille est
     * à 50 m au plus (contrôle `outlier_source`) ; `none` : aucune position de source. Les écarts sont en mètres :
     * `nearest_agreeing_m` (source indépendante la plus proche),
     * `farthest_precise_m` (source précise la plus éloignée), `track_offset_m`
     * (écart à la voie la plus proche des lignes de la gare). Pour une zone de
     * gare de la BD TOPO, l'écart est mesuré jusqu'au contour (0 si la gare
     * est dedans).
     *
     * **Positions** (`placement.positions`) : une par source, avec
     * `distance_m`, `track_m`, `precise` et `retained` (famille de la
     * position retenue). `lon` et `lat` ne sont donnés que si la source est
     * redistribuable (`provenance.license_status` vaut `verified`) ; sinon
     * `null`, l'écart reste donné.
     *
     * **Dates** (`dates`) : `opening_grade` et `closure_grade` valent
     * `sourced` (relevée dans une source), `estimated` (déduite par VFF des
     * lignes, des tracés ou des ères), `unknown`, ou `not_applicable` (gare
     * encore ouverte). `services.passenger` et `services.freight` : état du
     * jour (`open`, `closed`, `unknown`), date de fin (`closed_on`, à lire avec
     * `closed_precision`), source et note.
     *
     * **Contrôles** (`checks`) : `closure_before_opening`,
     * `opening_before_lines` (ouverture plus d'un an avant la première
     * desserte de ses lignes), `open_after_lines`,
     * `freight_closed_before_opening`, `passenger_after_closure`,
     * `wikidata_differs` (une date Wikidata contredit VFF de plus d'un an),
     * `far_from_track` (plus de 50 m de la voie), `retained_outvoted`, `opening_before_map`,
     * `era_passenger_mismatch`, `closed_without_date`, `freight_only_undated`,
     * `outlier_source` (une seule source lointaine, écartée de la note parce qu'une autre
     * famille est à 50 m au plus : sinon la gare serait `disagree`),
     * `closure_sncf_referential` (fermeture Wikidata qui ne cite que le référentiel SNCF des
     * gares de voyageurs, notée `estimated` ; le détail donne les autres dates connues),
     * chacun avec un `detail`.
     *
     * **Dates candidates** (`date_candidates`) : dates de mise en service
     * (P1619) et de fermeture (P3999) de Wikidata, avec `precision` (`day`,
     * `month`, `year`, `decade`, `century`), rang, qualificatifs et
     * références, comparées à la date publiée (`comparison` : `same`,
     * `more_precise`, `less_precise`, `differs`, `new`, et `delta_days`).
     * `would_replace` dit si la règle de rang proposée (texte officiel >
     * Wikipédia et Wikidata > lignes-oubliées, OHM, OSM > estimation) la
     * préférerait ; elle n'est pas appliquée.
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par la recherche (ex. `argentan`).', example: 'argentan')]
    #[ApiResponse(200, "L'audit de la gare d'Argentan (extrait).", examples: [[
        'data' => [
            'placement' => [
                'grade' => 'agree', 'families' => 4, 'nearest_agreeing_m' => 16, 'farthest_precise_m' => 21, 'track_offset_m' => 4,
                'positions' => [[
                    'source' => 'osm', 'family' => 'osm', 'ref' => 'n13239075826', 'lon' => -0.0257105, 'lat' => 48.7384017, 'distance_m' => 21,
                    'track_m' => 0, 'precision_m' => null, 'precise' => true, 'retained' => false,
                    'provenance' => ['provider' => 'osm', 'name' => 'OpenStreetMap', 'license' => 'ODbL 1.0 (Open Database License)', 'license_status' => 'verified'],
                ]],
            ],
            'dates' => [
                'opening_grade' => 'sourced', 'closure_grade' => 'not_applicable',
                'services' => [
                    'passenger' => ['state' => 'open', 'closed_on' => null, 'closed_precision' => null, 'source' => null, 'grade' => 'not_applicable'],
                    'freight' => ['state' => 'open', 'closed_on' => null, 'closed_precision' => null, 'source' => null, 'grade' => 'not_applicable'],
                ],
            ],
            'checks' => [],
            'date_candidates' => [[
                'kind' => 'opening', 'date' => '1866-01-01', 'precision' => 'year', 'rank' => 'normal', 'qualifiers' => [], 'references' => [],
                'vff_date' => '1866-01-01', 'vff_precision' => 'year', 'vff_source' => 'wikidata', 'comparison' => 'same', 'delta_days' => 0,
                'would_replace' => false, 'source' => 'wikidata', 'source_ref' => 'Q3095666#P1619#BED566A7-7044-4912-BDDA-4329894716AC', 'source_url' => 'https://www.wikidata.org/wiki/Q3095666#P1619',
                'provenance' => ['provider' => 'wikidata', 'name' => 'Wikidata', 'license' => 'CC0 1.0', 'license_status' => 'verified'],
            ]],
            'rule_version' => '2026-11-21',
            'audited_at' => '2026-11-21T00:49:37+01:00',
        ],
    ]])]
    #[ApiResponse(404, 'Gare inconnue (slug introuvable), ou gare pas encore auditée (le corps le dit dans `message`).', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Station $station, StationAuditQuery $audits): JsonResponse
    {
        $audit = $audits->forStation((int) $station->id);
        if ($audit === null) {
            return response()->json(['message' => "Cette gare n'a pas encore été auditée."], 404);
        }

        return response()->json(['data' => $audit])->header('Cache-Control', 'public, max-age=300');
    }
}
