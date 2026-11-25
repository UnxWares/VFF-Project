<?php

namespace App\Services\Routebook;

use App\Models\Line;
use App\Services\NetworkGraph\RouteException;
use App\Services\PathfindingService;
use App\Services\TrackLayout\TrackLayout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Livret de parcours (« routebook ») entre deux gares, sur le réseau d'aujourd'hui (ère courante, 2030) : le plus
 * court chemin du graphe gare à gare de VFF (PathfindingService, mode voyageurs, sans voies associatives), découpé
 * en morceaux de ligne (RouteLegs), chaque morceau calé sur les PK de sa ligne (LinePointPk) puis garni du schéma
 * des voies de la portion parcourue (TrackLayout, celui de `GET /api/v1/lines/{line}/track-layout`), le tout mis bout à
 * bout dans le sens de marche (RoutebookAssembler). Le départ et l'arrivée sont au centre de leur point
 * d'exploitation : le PK de rattachement de la gare à sa ligne.
 *
 * Une seule implémentation pour l'API REST (`GET /api/v1/routebook`), GraphQL (`routebook`), le serveur MCP
 * (outil `routebook`) et la page `/routebook`.
 */
final class Routebook
{
    /** Version du contenu : à changer quand la forme de la réponse ou le calcul change (clé du cache). */
    public const FORMAT = 2;

    public const CACHE_TTL = 3600;

    /**
     * Écart (m) cherché entre le PK de rattachement d'une gare de bout de portion et le point d'exploitation de la source
     * qui la nomme (Lille-Flandres : PK 0+654 de 289000 pour la gare, 0+021 pour le point du RINF).
     */
    public const END_POINT_SEARCH_M = 2000;

    public function __construct(
        private readonly PathfindingService $pathfinding,
        private readonly LinePointPk $pk,
        private readonly TrackLayout $layouts,
    ) {}

    /**
     * Livret gardé en cache (par gares demandées et date de calcul du graphe de l'ère courante).
     *
     * @return array<string, mixed>
     *
     * @throws RouteException
     */
    public function between(string $from, string $to): array
    {
        $built = (string) (DB::table('network_graph_builds')
            ->join('eras', 'eras.id', '=', 'network_graph_builds.era_id')
            ->where('eras.is_current', true)->value('network_graph_builds.built_at') ?? '');
        $key = sprintf('routebook:%d:%s', self::FORMAT, md5(mb_strtolower(trim($from)).'|'.mb_strtolower(trim($to)).'|'.$built));

        return Cache::remember($key, self::CACHE_TTL, fn (): array => $this->build($from, $to));
    }

    /**
     * Livret calculé (sans cache).
     *
     * @return array<string, mixed>
     *
     * @throws RouteException
     */
    public function build(string $from, string $to): array
    {
        ['era' => $era, 'build' => $build, 'path' => $path] = $this->pathfinding->findPath($from, $to);
        $stationIds = $path['stations'];

        $stations = [];
        foreach (DB::select('SELECT id, slug, name, ST_X(geom) AS lng, ST_Y(geom) AS lat FROM stations WHERE id = ANY(?::bigint[])',
            [self::pgArray($stationIds)]) as $s) {
            $stations[(int) $s->id] = $s;
        }
        $edgeRows = [];
        foreach (DB::select('SELECT id, from_station_id, line_id, length_m, line_ids, line_lengths FROM station_edges WHERE id = ANY(?::bigint[])',
            [self::pgArray($path['edges'])]) as $e) {
            $edgeRows[(int) $e->id] = $e;
        }

        // Portions de ligne de chaque arête, dans le sens de marche.
        $steps = [];
        foreach ($path['edges'] as $k => $edgeId) {
            $e = $edgeRows[$edgeId];
            $parts = array_map(null, self::parsePgArray($e->line_ids), self::parsePgArray($e->line_lengths));
            if ((int) $e->from_station_id !== $stationIds[$k]) {
                $parts = array_reverse($parts);
            }
            $steps[] = $parts !== [] ? $parts : [[(int) $e->line_id, (int) $e->length_m]];
        }
        $split = RouteLegs::split($steps);

        /** @var array<int, Line> $lines */
        $lines = Line::query()->whereIn('id', array_unique(array_column($split, 'line_id')))->get()->keyBy('id')->all();

        // Points de changement de ligne en pleine voie, sur l'arête qui les porte.
        $junctions = $this->junctionPoints($split, $steps, $path['edges'], $stationIds);

        // PK des deux bouts de chaque morceau sur sa ligne.
        $points = [];
        foreach ($split as $i => $leg) {
            foreach (['from', 'to'] as $end) {
                $index = $leg[$end.'_index'];
                $station = $index !== null ? $stations[$stationIds[$index]] : null;
                $point = $station !== null ? ['lng' => (float) $station->lng, 'lat' => (float) $station->lat] : ($junctions["{$i}:{$end}"] ?? null);
                if ($point === null) {
                    continue;
                }
                $points["{$i}:{$end}"] = ['line_id' => $leg['line_id'], 'code' => $lines[$leg['line_id']]->code ?? null,
                    'station_id' => $station !== null ? (int) $station->id : null] + $point;
            }
        }
        $pks = $this->pk->locate($points);

        $legs = [];
        $layouts = [];
        foreach ($split as $i => $leg) {
            $line = $lines[$leg['line_id']] ?? null;
            if ($line === null) {
                continue;
            }
            $pkFrom = $pks["{$i}:from"] ?? null;
            $pkTo = $pks["{$i}:to"] ?? null;
            $direction = RouteLegs::direction($pkFrom['pk_m'] ?? null, $pkTo['pk_m'] ?? null);
            $end = function (string $side) use ($leg, $stations, $stationIds): array {
                $index = $leg[$side.'_index'];
                if ($index === null) {
                    return ['kind' => 'junction', 'slug' => null, 'name' => null];
                }
                $s = $stations[$stationIds[$index]];

                return ['kind' => 'station', 'slug' => (string) $s->slug, 'name' => (string) $s->name];
            };
            $legs[] = [
                'index' => $i,
                'line' => ['code' => $line->code, 'slug' => (string) $line->slug, 'name' => (string) $line->name],
                'from' => $end('from'),
                'to' => $end('to'),
                'length_m' => $leg['length_m'],
                'route_from_m' => $leg['route_from_m'],
                'route_to_m' => $leg['route_to_m'],
                'route_from_km' => round($leg['route_from_m'] / 1000, 3),
                'route_to_km' => round($leg['route_to_m'] / 1000, 3),
                'pk_from_m' => $pkFrom['pk_m'] ?? null,
                'pk_to_m' => $pkTo['pk_m'] ?? null,
                'pk_precision' => self::weakest($pkFrom['precision'] ?? null, $pkTo['precision'] ?? null),
                'direction' => $direction,
            ];
            $layouts[$i] = $direction !== null ? $this->layouts->forLine($line, $pkFrom['pk_m'], $pkTo['pk_m']) : null;
            if ($layouts[$i] !== null && $layouts[$i]['operational_points'] !== []) {
                $layouts[$i]['operational_points'] = [...$layouts[$i]['operational_points'],
                    ...$this->endStationPoints($line, $layouts[$i]['operational_points'], [[$legs[count($legs) - 1]['from'], $pkFrom['pk_m']], [$legs[count($legs) - 1]['to'], $pkTo['pk_m']]])];
            }
        }

        $book = RoutebookAssembler::assemble($legs, $layouts);
        foreach ($legs as &$leg) {
            $layout = $layouts[$leg['index']];
            $leg['has_detail'] = $layout !== null && ($layout['tracks'] !== [] || $layout['switches'] !== []);
            unset($leg['route_from_m'], $leg['route_to_m']);
        }
        unset($leg);

        $first = $stations[$stationIds[0]];
        $last = $stations[$stationIds[count($stationIds) - 1]];
        $distance = $split === [] ? 0 : $split[count($split) - 1]['route_to_m'];

        return [
            'from' => ['slug' => (string) $first->slug, 'name' => (string) $first->name],
            'to' => ['slug' => (string) $last->slug, 'name' => (string) $last->name],
            'era_year' => (int) $era->year,
            'distance_m' => $distance,
            'distance_km' => round($distance / 1000, 1),
            'objects' => RoutebookAssembler::objectCount($book),
            'legs' => $legs,
            'line_changes' => self::lineChanges($legs),
            ...$book,
            'graph' => ['built_at' => $build->built_at],
        ];
    }

    /**
     * Points d'exploitation des gares de départ et d'arrivée d'une portion que son schéma n'a pas, parce que la source
     * les place hors de la portion (au-delà du PK de rattachement de la gare, END_POINT_SEARCH_M au plus) : lus à part,
     * pour que RoutebookAssembler les place au bout de la portion. Rien n'est lu quand le schéma a déjà un point de la gare.
     *
     * @param  list<array<string, mixed>>  $points  points d'exploitation du schéma de la portion
     * @param  list<array{0: array{kind: string, slug: ?string, name: ?string}, 1: int}>  $ends  bout (gare ou bifurcation) et son PK
     * @return list<array<string, mixed>>
     */
    private function endStationPoints(Line $line, array $points, array $ends): array
    {
        $known = array_flip(array_filter(array_map(fn (array $o): ?string => $o['station_slug'] ?? null, $points)));
        $out = [];
        foreach ($ends as [$end, $pk]) {
            $slug = $end['kind'] === 'station' ? $end['slug'] : null;
            if ($slug === null || isset($known[$slug])) {
                continue;
            }
            $around = $this->layouts->forLine($line, $pk - self::END_POINT_SEARCH_M, $pk + self::END_POINT_SEARCH_M, ['operational_points']);
            foreach ($around['operational_points'] as $o) {
                if (($o['station_slug'] ?? null) === $slug) {
                    $out[] = $o;
                    $known[$slug] = true;
                }
            }
        }

        return $out;
    }

    /**
     * Changements de ligne : entre deux morceaux consécutifs, à une gare ou en pleine voie.
     *
     * @param  list<array<string, mixed>>  $legs
     * @return list<array{route_km: float, from_line: ?string, to_line: ?string, at: array{kind: string, slug: ?string, name: ?string}}>
     */
    public static function lineChanges(array $legs): array
    {
        $out = [];
        for ($i = 1; $i < count($legs); $i++) {
            $out[] = [
                'route_km' => $legs[$i]['route_from_km'],
                'from_line' => $legs[$i - 1]['line']['slug'],
                'to_line' => $legs[$i]['line']['slug'],
                'at' => $legs[$i]['from'],
            ];
        }

        return $out;
    }

    /**
     * Coordonnées des bouts de morceau hors gare (changement de ligne en pleine voie), en une requête : le point est
     * pris sur le tracé de l'arête gare à gare, à la distance parcourue depuis sa gare de départ (mesurée en Lambert 93,
     * en mètres : une fraction de la longueur en degrés déplacerait le point sur un tracé qui tourne) ; un tracé en
     * plusieurs morceaux (lacune) est remplacé par la corde entre les deux gares, ramenée sur le tracé.
     *
     * @param  list<array<string, mixed>>  $split
     * @param  list<list<array{0: int, 1: int}>>  $steps
     * @param  list<int>  $edgeIds
     * @param  list<int>  $stationIds
     * @return array<string, array{lng: float, lat: float}>
     */
    private function junctionPoints(array $split, array $steps, array $edgeIds, array $stationIds): array
    {
        $rows = [];
        $bindings = [];
        foreach ($split as $i => $leg) {
            foreach (['from', 'to'] as $end) {
                $at = $leg[$end.'_at'];
                if ($at === null) {
                    continue;
                }
                $edgeLength = array_sum(array_map(fn (array $p): int => (int) $p[1], $steps[$at['edge']]));
                $fraction = $edgeLength > 0 ? max(0.0, min(1.0, $at['offset_m'] / $edgeLength)) : 0.0;
                $rows[] = '(?::text, ?::bigint, ?::bigint, ?::bigint, ?::float8)';
                array_push($bindings, "{$i}:{$end}", $edgeIds[$at['edge']], $stationIds[$at['edge']], $stationIds[$at['edge'] + 1], $fraction);
            }
        }
        if ($rows === []) {
            return [];
        }
        $values = implode(', ', $rows);
        $out = [];
        foreach (DB::select(<<<SQL
            WITH q(k, edge_id, a_id, b_id, f) AS (VALUES {$values})
            SELECT q.k, ST_X(x.pt) AS lng, ST_Y(x.pt) AS lat
            FROM q
            JOIN station_edges e ON e.id = q.edge_id
            JOIN stations a ON a.id = q.a_id
            JOIN stations b ON b.id = q.b_id
            CROSS JOIN LATERAL (SELECT ST_Transform(e.geom, 2154) AS g, ST_Transform(a.geom, 2154) AS pa, ST_Transform(b.geom, 2154) AS pb) m
            CROSS JOIN LATERAL (
                SELECT ST_Transform(CASE
                    WHEN ST_NumGeometries(m.g) = 1 THEN ST_LineInterpolatePoint(
                        CASE WHEN ST_Distance(ST_StartPoint(ST_GeometryN(m.g, 1)), m.pa) <= ST_Distance(ST_EndPoint(ST_GeometryN(m.g, 1)), m.pa)
                             THEN ST_GeometryN(m.g, 1) ELSE ST_Reverse(ST_GeometryN(m.g, 1)) END, q.f)
                    ELSE ST_ClosestPoint(m.g, ST_LineInterpolatePoint(ST_MakeLine(m.pa, m.pb), q.f))
                END, 4326) AS pt
            ) x
            WHERE e.geom IS NOT NULL
            SQL, $bindings) as $r) {
            $out[(string) $r->k] = ['lng' => (float) $r->lng, 'lat' => (float) $r->lat];
        }

        return $out;
    }

    /** Précision la plus faible des deux bouts (exact > derived > approximate ; null si un PK manque). */
    public static function weakest(?string $a, ?string $b): ?string
    {
        if ($a === null || $b === null) {
            return null;
        }
        $rank = ['exact' => 0, 'derived' => 1, 'approximate' => 2];

        return ($rank[$a] ?? 2) >= ($rank[$b] ?? 2) ? $a : $b;
    }

    /** @param  list<int>  $values */
    private static function pgArray(array $values): string
    {
        return '{'.implode(',', array_map('intval', $values)).'}';
    }

    /** @return list<int> */
    private static function parsePgArray(?string $value): array
    {
        $value = trim((string) $value, '{}');

        return $value === '' ? [] : array_map('intval', explode(',', $value));
    }
}
