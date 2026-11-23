<?php

namespace App\Services\Lines;

use App\Enums\SignallingFamily;
use App\Models\Era;
use App\Models\LevelCrossing;
use App\Models\Line;
use App\Services\LevelCrossings\LineLevelCrossings;
use App\Services\LineStations;
use App\Services\Signalling\SignallingRanges;
use App\Services\Sncf\LineInfrastructure;
use App\Services\Speed\LineSpeeds;
use App\Services\Structures\LineStructures;
use App\Services\Traction\LineElectrificationHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Schéma de ligne, à la manière des schémas d'exploitation : la ligne déroulée par point kilométrique (PK)
 * avec ses gares et haltes, ses bifurcations vers les autres lignes, ses passages à niveau, ses ouvrages
 * d'art, et quatre bandes parallèles (nombre de voies, électrification, signalisation, vitesse maximale),
 * à une ère donnée. Une seule lecture pour la fiche de ligne (section chargée à la demande) et l'API REST
 * (`GET /api/v1/lines/{line}/schematic`).
 *
 * Rien n'est inventé : chaque couche vient du service qui la publie déjà ailleurs (mêmes chiffres que les
 * sections de la fiche) et dit si elle est renseignée à l'ère demandée (`available`). Les jeux de SNCF Réseau
 * (voies, électrification, passages à niveau) décrivent le réseau d'aujourd'hui : ils ne valent que pour
 * l'ère courante. La signalisation et les vitesses sont lues par ère (`line_signalling`, `line_speed_ranges`),
 * les ouvrages selon les ères où la ligne les franchit, les gares selon leur état à l'ère (station_eras).
 *
 * Bifurcations : nœuds du graphe de l'ère (vff:build-network-graph) où une arête d'une autre ligne touche une
 * arête de celle-ci. Leur PK est lu sur la forme SNCF de la ligne (ST_LineLocatePoint sur le tronçon le plus
 * proche, à MAX_SNAP_M au plus), sinon interpolé entre les deux gares qui l'encadrent ; il est toujours
 * approché (`pk_approx`). Une bifurcation à moins de STATION_ATTACH_M d'une gare est rattachée à la gare
 * (`connections`), les nœuds voisins d'une même ligne (voies parallèles, triangle) sont réunis dans
 * MERGE_M. Une ère sans graphe pour la ligne (ligne fermée à l'ère choisie) n'a pas de bifurcation.
 *
 * Mise en cache : par ligne, ère et date de construction du graphe de l'ère, pour CACHE_TTL secondes.
 */
final class LineSchematic
{
    /** Version du contenu : à changer quand la forme de la réponse ou le calcul change. */
    public const FORMAT = 3;

    public const CACHE_TTL = 6 * 3600;

    /** Distance maximale (m) entre un nœud de bifurcation et la forme SNCF de la ligne pour y lire un PK. */
    public const MAX_SNAP_M = 600;

    /** Bifurcation rattachée à une gare de la ligne à moins de cette distance de PK (m). */
    public const STATION_ATTACH_M = 400;

    /** Nœuds d'une même ligne voisine réunis en une bifurcation à moins de cette distance de PK (m). */
    public const MERGE_M = 1500;

    /** Ligne déjà nommée à une gare : pas répétée à une bifurcation à moins de cette distance de PK (m). */
    public const STATION_REPEAT_M = 3000;

    /** Méthodes de rattachement dont le PK est celui d'une source (SNCF Réseau, schéma, article) : PK sûr. */
    public const CALIBRATED = ['sncf_pk', 'source_pk'];

    /** Statuts de gare comptés comme ouverts (voyageurs, touristique, fret seul). */
    public const OPEN = ['active', 'tourist', 'no_commercial'];

    public function __construct(
        private readonly LineStations $stations,
        private readonly LineInfrastructure $infrastructure,
        private readonly LineSpeeds $speeds,
        private readonly SignallingRanges $signalling,
        private readonly LineElectrificationHistory $electrification,
        private readonly LineLevelCrossings $crossings,
        private readonly LineStructures $structures,
    ) {}

    /**
     * Schéma gardé en cache.
     *
     * @return array<string, mixed>
     */
    public function forLine(Line $line, Era $era): array
    {
        $built = (string) (DB::table('network_graph_builds')->where('era_id', $era->id)->value('built_at') ?? '');
        $key = sprintf('line-schematic:%d:%d:%d:%s', self::FORMAT, $line->id, $era->id, md5($built.'|'.$line->updated_at));

        return Cache::remember($key, self::CACHE_TTL, fn (): array => $this->build($line, $era));
    }

    /**
     * Schéma calculé (sans cache).
     *
     * @return array<string, mixed>
     */
    public function build(Line $line, Era $era): array
    {
        $year = (int) $era->year;
        $current = (bool) $era->is_current;
        $all = $this->stations->forLine((int) $line->id);

        $stations = [];
        foreach ($all['stations'] as $s) {
            if ($s['kind'] !== 'station' || $s['pk_m'] === null) {
                continue;
            }
            $status = $s['eras'][$year]['status'] ?? null;
            if ($status === null) {
                continue;
            }
            $stations[] = [
                'id' => $s['id'],
                'slug' => $s['slug'],
                'name' => $s['name'],
                'type' => $s['type'] === 'halte' ? 'halt' : 'station',
                'pk_m' => $s['pk_m'],
                'pk_label' => $s['pk_label'],
                // PK projeté sur le tracé (ni PK de SNCF Réseau ni PK d'une source) : approché.
                'pk_approx' => ! in_array($s['link_method'], self::CALIBRATED, true),
                'status' => $status,
                'open' => in_array($status, self::OPEN, true),
                'is_terminal' => $s['is_terminal'],
                'connections' => [],
                'lng' => $s['lng'],
                'lat' => $s['lat'],
            ];
        }

        [$stations, $junctions] = $this->junctions($line, $era, $stations);
        foreach ($stations as &$s) {
            unset($s['lng'], $s['lat']);
        }
        unset($s);

        $code = (string) ($line->code ?? '');
        $tracks = $current ? $this->tracks((int) $line->id) : [];
        $elec = $current && $code !== '' ? $this->electrificationRanges($code) : [];
        $signalling = $this->signallingRanges((int) $line->id, (int) $era->id);
        $speeds = $this->speedRanges((int) $line->id, (int) $era->id);
        $crossings = $current ? $this->levelCrossings($line) : [];
        $structures = $this->structureItems((int) $line->id, $year);

        $pks = array_merge(
            array_column($stations, 'pk_m'),
            array_column($junctions, 'pk_m'),
            ...array_map(fn (array $r): array => [$r['pk_start_m'], $r['pk_end_m']], [...$tracks, ...$elec, ...$signalling, ...$speeds]),
        );

        return [
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'era_year' => $year,
            'is_current_era' => $current,
            'eras' => $this->eras($all),
            'extent' => $pks === [] ? null : [
                'pk_start_m' => (int) min($pks), 'pk_end_m' => (int) max($pks),
                'pk_start_label' => (string) LineStations::pkLabel((int) min($pks)),
                'pk_end_label' => (string) LineStations::pkLabel((int) max($pks)),
            ],
            'stations' => $stations,
            'junctions' => $junctions,
            'level_crossings' => ['available' => $current && $code !== '', 'items' => $crossings],
            'structures' => ['available' => true, 'items' => $structures],
            'bands' => [
                'tracks' => ['available' => $tracks !== [], 'as_of' => $current ? 'today' : null, 'ranges' => $tracks],
                'electrification' => ['available' => $elec !== [], 'as_of' => $current ? 'today' : null, 'ranges' => $elec],
                'signalling' => ['available' => $signalling !== [], 'ranges' => $signalling],
                'speed' => ['available' => $speeds !== [], 'ranges' => $speeds],
            ],
            'provenance' => self::provenance(),
        ];
    }

    /**
     * Ères de la ligne : nombre de gares connues et ouvertes à chacune (le sélecteur ne propose que les ères
     * où la ligne a au moins une gare, plus l'ère courante).
     *
     * @param  array{eras: list<array{year: int, is_current: bool}>, stations: list<array<string, mixed>>}  $all
     * @return list<array{year: int, is_current: bool, stations: int, open: int}>
     */
    private function eras(array $all): array
    {
        $out = [];
        foreach ($all['eras'] as $e) {
            $known = 0;
            $open = 0;
            foreach ($all['stations'] as $s) {
                $status = $s['kind'] === 'station' ? ($s['eras'][$e['year']]['status'] ?? null) : null;
                if ($status !== null) {
                    $known++;
                    $open += in_array($status, self::OPEN, true) ? 1 : 0;
                }
            }
            if ($known > 0 || $e['is_current']) {
                $out[] = ['year' => $e['year'], 'is_current' => $e['is_current'], 'stations' => $known, 'open' => $open];
            }
        }

        return $out;
    }

    /**
     * Bifurcations de la ligne à l'ère, et lignes en correspondance aux gares.
     *
     * @param  list<array<string, mixed>>  $stations
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function junctions(Line $line, Era $era, array $stations): array
    {
        $rows = DB::select(<<<'SQL'
            WITH mine AS (
                SELECT from_node_id AS n FROM network_edges WHERE era_id = :era AND line_id = :line AND NOT is_connector
                UNION
                SELECT to_node_id FROM network_edges WHERE era_id = :era2 AND line_id = :line2 AND NOT is_connector
            ), touching AS (
                SELECT DISTINCT m.n, e.line_id
                FROM mine m
                JOIN network_edges e ON e.era_id = :era3 AND (e.from_node_id = m.n OR e.to_node_id = m.n)
                WHERE e.line_id IS NOT NULL AND e.line_id <> :line3 AND NOT e.is_connector
            )
            SELECT t.n, t.line_id, l.slug, l.code, l.name, nn.station_id,
                   ST_X(nn.geom) AS lng, ST_Y(nn.geom) AS lat, snap.pk
            FROM touching t
            JOIN network_nodes nn ON nn.id = t.n
            JOIN lines l ON l.id = t.line_id
            LEFT JOIN LATERAL (
                SELECT g.pk_debut_m + ST_LineLocatePoint(g.geom, nn.geom) * (g.pk_fin_m - g.pk_debut_m) AS pk
                FROM sncf_line_geometries g
                WHERE g.code_ligne = :code AND g.pk_debut_m IS NOT NULL AND g.pk_fin_m IS NOT NULL
                  AND ST_DWithin(g.geom::geography, nn.geom::geography, :snap)
                ORDER BY g.geom <-> nn.geom
                LIMIT 1
            ) snap ON true
            ORDER BY t.n, l.name
            LIMIT 2000
        SQL, [
            'era' => $era->id, 'line' => $line->id, 'era2' => $era->id, 'line2' => $line->id,
            'era3' => $era->id, 'line3' => $line->id, 'code' => (string) ($line->code ?? ''), 'snap' => self::MAX_SNAP_M,
        ]);

        $byStationId = [];
        foreach ($stations as $i => $s) {
            $byStationId[$s['id']] = $i;
        }

        $points = [];
        foreach ($rows as $r) {
            $ref = ['slug' => (string) $r->slug, 'code' => $r->code, 'name' => (string) $r->name];
            $sid = $r->station_id !== null ? (int) $r->station_id : null;
            if ($sid !== null && isset($byStationId[$sid])) {
                $stations[$byStationId[$sid]]['connections'][$ref['slug']] = $ref;

                continue;
            }
            $pk = $r->pk !== null ? (int) round((float) $r->pk) : self::interpolate($stations, (float) $r->lng, (float) $r->lat);
            if ($pk === null) {
                continue;
            }
            $points[] = ['pk_m' => $pk, 'line' => $ref];
        }

        // Rattachement aux gares proches, puis réunion des nœuds voisins d'une même ligne.
        $byPk = $stations;
        usort($byPk, fn (array $a, array $b): int => $a['pk_m'] <=> $b['pk_m']);
        $junctions = [];
        usort($points, fn (array $a, array $b): int => $a['pk_m'] <=> $b['pk_m']);
        foreach ($points as $p) {
            $near = self::nearestStation($byPk, $p['pk_m']);
            if ($near !== null && abs($near['pk_m'] - $p['pk_m']) <= self::STATION_ATTACH_M) {
                $stations[$byStationId[$near['id']]]['connections'][$p['line']['slug']] = $p['line'];

                continue;
            }
            $merged = false;
            foreach ($junctions as &$j) {
                if (isset($j['lines'][$p['line']['slug']]) && abs($j['pk_m'] - $p['pk_m']) <= self::MERGE_M) {
                    $merged = true;
                    break;
                }
            }
            unset($j);
            if ($merged) {
                continue;
            }
            // Même PK (à 150 m près) qu'une bifurcation déjà vue : une seule bifurcation vers plusieurs lignes.
            $last = array_key_last($junctions);
            if ($last !== null && abs($junctions[$last]['pk_m'] - $p['pk_m']) <= 150) {
                $junctions[$last]['lines'][$p['line']['slug']] = $p['line'];

                continue;
            }
            $junctions[] = ['pk_m' => $p['pk_m'], 'lines' => [$p['line']['slug'] => $p['line']]];
        }

        $stationSlugs = [];
        foreach ($stations as $s) {
            foreach ($s['connections'] as $slug => $_) {
                $stationSlugs[$slug][] = $s['pk_m'];
            }
        }
        $out = [];
        foreach ($junctions as $j) {
            // Une ligne déjà en correspondance à une gare toute proche n'est pas répétée en pleine voie.
            $lines = array_filter($j['lines'], function (array $l) use ($stationSlugs, $j): bool {
                foreach ($stationSlugs[$l['slug']] ?? [] as $pk) {
                    if (abs($pk - $j['pk_m']) <= self::STATION_REPEAT_M) {
                        return false;
                    }
                }

                return true;
            });
            if ($lines === []) {
                continue;
            }
            $out[] = [
                'pk_m' => $j['pk_m'],
                'pk_label' => (string) LineStations::pkLabel($j['pk_m']),
                'pk_approx' => true,
                'lines' => array_values($lines),
            ];
        }
        foreach ($stations as &$s) {
            $s['connections'] = array_values($s['connections']);
        }
        unset($s);

        return [$stations, $out];
    }

    /**
     * PK d'un point hors de la forme SNCF : interpolé entre les deux gares consécutives qu'il sépare le mieux
     * (somme des distances la plus proche de l'écart entre les deux gares).
     *
     * @param  list<array<string, mixed>>  $stations
     */
    public static function interpolate(array $stations, float $lng, float $lat): ?int
    {
        usort($stations, fn (array $a, array $b): int => $a['pk_m'] <=> $b['pk_m']);
        $best = null;
        $bestScore = INF;
        for ($i = 0; $i + 1 < count($stations); $i++) {
            [$a, $b] = [$stations[$i], $stations[$i + 1]];
            $da = LineStations::metres($lng, $lat, (float) $a['lng'], (float) $a['lat']);
            $db = LineStations::metres($lng, $lat, (float) $b['lng'], (float) $b['lat']);
            $ab = LineStations::metres((float) $a['lng'], (float) $a['lat'], (float) $b['lng'], (float) $b['lat']);
            $score = $da + $db - $ab;
            if ($score < $bestScore && $da + $db > 0) {
                $bestScore = $score;
                $best = (int) round($a['pk_m'] + ($b['pk_m'] - $a['pk_m']) * $da / ($da + $db));
            }
        }

        // Un point loin de toute paire de gares (plus de 2 km de détour) n'est pas placé.
        return $bestScore <= 2000 ? $best : null;
    }

    /**
     * @param  list<array<string, mixed>>  $sorted  gares triées par PK
     * @return array<string, mixed>|null
     */
    private static function nearestStation(array $sorted, int $pk): ?array
    {
        $best = null;
        foreach ($sorted as $s) {
            if ($best === null || abs($s['pk_m'] - $pk) < abs($best['pk_m'] - $pk)) {
                $best = $s;
            }
        }

        return $best;
    }

    /** @return list<array{pk_start_m: int, pk_end_m: int, tracks: ?int, regime: string, banalised: bool}> */
    private function tracks(int $lineId): array
    {
        $out = [];
        foreach ($this->infrastructure->forLine($lineId)['tracks']['portions'] as $p) {
            if ($p['pk_start_m'] === null || $p['pk_end_m'] === null) {
                continue;
            }
            $out[] = [
                'pk_start_m' => min($p['pk_start_m'], $p['pk_end_m']),
                'pk_end_m' => max($p['pk_start_m'], $p['pk_end_m']),
                'tracks' => $p['tracks'],
                'regime' => $p['regime'],
                'banalised' => $p['banalised'],
            ];
        }
        usort($out, fn (array $a, array $b): int => $a['pk_start_m'] <=> $b['pk_start_m']);

        return $out;
    }

    /** @return list<array{pk_start_m: int, pk_end_m: int, voltage_v: ?int, system: string}> */
    private function electrificationRanges(string $code): array
    {
        return array_map(fn (array $r): array => [
            'pk_start_m' => $r['pk_start_m'], 'pk_end_m' => $r['pk_end_m'], 'voltage_v' => $r['voltage_v'], 'system' => $r['system'],
        ], $this->electrification->ranges($code));
    }

    /** @return list<array{pk_start_m: int, pk_end_m: int, signalling: string, family: ?string, systems: list<string>, train_protection: list<string>}> */
    private function signallingRanges(int $lineId, int $eraId): array
    {
        return array_map(fn (array $r): array => [
            'pk_start_m' => $r['pk_start_m'], 'pk_end_m' => $r['pk_end_m'], 'signalling' => $r['signalling'],
            'family' => SignallingFamily::fromCode($r['signalling'])?->value,
            'systems' => $r['signalling_systems'], 'train_protection' => $r['train_protection'],
        ], $this->signalling->forLine($lineId, $eraId, withoutBlockSystems: true));
    }

    /** @return list<array{pk_start_m: int, pk_end_m: int, max_speed_kmh: int}> */
    private function speedRanges(int $lineId, int $eraId): array
    {
        return array_map(fn (array $r): array => [
            'pk_start_m' => $r['pk_start_m'], 'pk_end_m' => $r['pk_end_m'], 'max_speed_kmh' => $r['max_speed_kmh'],
        ], $this->speeds->ranges($lineId, $eraId));
    }

    /** @return list<array{pk_m: int, pk_label: string, name: ?string, group: ?string}> */
    private function levelCrossings(Line $line): array
    {
        return $this->crossings->list($line)
            ->filter(fn (LevelCrossing $c): bool => $c->pk_m !== null)
            ->map(fn (LevelCrossing $c): array => [
                'pk_m' => (int) $c->pk_m,
                'pk_label' => (string) LevelCrossing::pkLabel((int) $c->pk_m),
                'name' => $c->libelle,
                'group' => $c->group?->value,
            ])->values()->all();
    }

    /** @return list<array{id: int, kind: string, name: ?string, length_m: ?int, pk_start_m: int, pk_end_m: int}> */
    private function structureItems(int $lineId, int $year): array
    {
        $out = [];
        foreach ($this->structures->forLine($lineId)['items'] as $s) {
            if ($s['pk_start_m'] === null || ! in_array($year, $s['era_years'], true)) {
                continue;
            }
            $out[] = [
                'id' => $s['id'], 'kind' => $s['kind'], 'name' => $s['name'], 'length_m' => $s['length_m'],
                'pk_start_m' => (int) min($s['pk_start_m'], $s['pk_end_m'] ?? $s['pk_start_m']),
                'pk_end_m' => (int) max($s['pk_start_m'], $s['pk_end_m'] ?? $s['pk_start_m']),
            ];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function provenance(): array
    {
        return [
            'stations' => 'VFF (gares et leur état par ère, d’après SNCF Réseau, Wikidata et les sources citées sur chaque fiche de gare)',
            'junctions' => 'Graphe du réseau de l’ère construit par VFF ; PK lu sur les formes de SNCF Réseau (ODbL), approché',
            'tracks' => 'SNCF Réseau Open Data, « regime-dexploitation-des-lignes » (ODbL), réseau d’aujourd’hui',
            'electrification' => 'SNCF Réseau Open Data, « liste-des-lignes-electrifiees » (ODbL), réseau d’aujourd’hui',
            'signalling' => 'SNCF Réseau Open Data, modes de cantonnement et lignes équipées du KVB (ODbL)',
            'speed' => 'SNCF Réseau Open Data, « vitesse-maximale-nominale-sur-ligne » (ODbL)',
            'level_crossings' => 'SNCF Réseau Open Data, « liste-des-passages-a-niveau » (ODbL), réseau d’aujourd’hui',
            'structures' => LineStructures::ATTRIBUTION,
        ];
    }
}
