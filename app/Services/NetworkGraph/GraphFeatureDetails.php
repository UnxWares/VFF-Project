<?php

namespace App\Services\NetworkGraph;

use Illuminate\Support\Facades\DB;

/**
 * Attributs d'un élément du graphe d'une ère, affichés au clic sur la carte d'administration
 * (/validate/graph) : identifiants, ligne, longueur, PK, gares, sources. Lecture seule.
 *
 *   - node : nœud de la topologie, avec ses arêtes et l'écart entre leurs extrémités ;
 *   - edge : arête de la topologie, avec le tronçon d'origine (line_segments) : PK de ses deux
 *     bouts interpolés sur le tronçon quand celui-ci en a, source du tracé ; sans tracé, la
 *     distance entre ses deux nœuds (`nodes_gap_m`) ;
 *   - station_edge : arête gare-à-gare, avec les lignes empruntées dans l'ordre ;
 *   - station : gare de l'ère (une ligne dans station_eras ou un nœud du graphe de l'ère, sinon
 *     rien), avec son nœud et ses voisines ;
 *   - stop : arrêt d'une ligne (line_stops : rang, PK, statut).
 */
final class GraphFeatureDetails
{
    public const KINDS = ['node', 'edge', 'station_edge', 'station', 'stop'];

    /** Arêtes listées au plus pour un nœud, voisines au plus pour une gare. */
    private const NEIGHBOURS = 12;

    /** @return array<string, mixed>|null */
    public function find(int $eraId, string $kind, int $id): ?array
    {
        return match ($kind) {
            'node' => $this->node($eraId, $id),
            'edge' => $this->edge($eraId, $id),
            'station_edge' => $this->stationEdge($eraId, $id),
            'station' => $this->station($eraId, $id),
            'stop' => $this->stop($eraId, $id),
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function node(int $eraId, int $id): ?array
    {
        $n = DB::selectOne(<<<'SQL'
            SELECT n.id, n.kind, n.station_id, ST_X(n.geom) AS lng, ST_Y(n.geom) AS lat, s.slug, s.name
            FROM network_nodes n LEFT JOIN stations s ON s.id = n.station_id
            WHERE n.era_id = ? AND n.id = ?
            SQL, [$eraId, $id]);
        if ($n === null) {
            return null;
        }
        $edges = DB::select(<<<'SQL'
            SELECT e.id, e.length_m, e.is_connector, l.id AS line_id, l.slug, l.name, l.code,
                   ST_Distance(CASE WHEN e.from_node_id = ? THEN ST_StartPoint(e.geom) ELSE ST_EndPoint(e.geom) END::geography,
                               n.geom::geography) AS offset_m
            FROM network_edges e
            JOIN network_nodes n ON n.id = ?
            LEFT JOIN lines l ON l.id = e.line_id
            WHERE e.era_id = ? AND (e.from_node_id = ? OR e.to_node_id = ?)
            ORDER BY e.id
            SQL, [$id, $id, $eraId, $id, $id]);
        $spread = DB::scalar(<<<'SQL'
            SELECT ST_MaxDistance(ST_Transform(ST_Collect(p), 2154), ST_Transform(ST_Collect(p), 2154))
            FROM (
                SELECT ST_StartPoint(geom) AS p FROM network_edges WHERE era_id = ? AND from_node_id = ? AND geom IS NOT NULL
                UNION ALL
                SELECT ST_EndPoint(geom) FROM network_edges WHERE era_id = ? AND to_node_id = ? AND geom IS NOT NULL
            ) x
            SQL, [$eraId, $id, $eraId, $id]);

        return [
            'kind' => 'node',
            'id' => (int) $n->id,
            'node_kind' => $n->kind,
            'lng' => (float) $n->lng,
            'lat' => (float) $n->lat,
            'station' => $n->station_id !== null ? ['id' => (int) $n->station_id, 'slug' => $n->slug, 'name' => $n->name] : null,
            'degree' => count($edges),
            'spread_m' => $spread !== null ? round((float) $spread, 2) : null,
            'edges' => array_map(fn (object $e): array => [
                'id' => (int) $e->id,
                'length_m' => (int) $e->length_m,
                'connector' => (bool) $e->is_connector,
                'offset_m' => $e->offset_m !== null ? round((float) $e->offset_m, 2) : null,
                'line' => self::line($e),
            ], array_slice($edges, 0, self::NEIGHBOURS)),
        ];
    }

    /** @return array<string, mixed>|null */
    private function edge(int $eraId, int $id): ?array
    {
        $e = DB::selectOne(<<<'SQL'
            SELECT e.id, e.from_node_id, e.to_node_id, e.segment_id, e.length_m, e.status, e.is_connector, e.is_heritage,
                   e.traffic_type, e.network, e.gauge_mm,
                   CASE WHEN e.geom IS NULL THEN NULL ELSE ST_Length(e.geom::geography) END AS measured_m,
                   CASE WHEN e.geom IS NULL
                        THEN (SELECT ST_Distance(a.geom::geography, b.geom::geography)
                              FROM network_nodes a, network_nodes b WHERE a.id = e.from_node_id AND b.id = e.to_node_id) END AS nodes_gap_m,
                   l.id AS line_id, l.slug, l.name, l.code,
                   ls.source, ls.source_trace_id, ls.osm_way_id, ls.pk_start_m, ls.pk_end_m,
                   CASE WHEN ls.geom IS NOT NULL AND e.geom IS NOT NULL
                        THEN ST_LineLocatePoint(ls.geom, ST_StartPoint(e.geom)) END AS f_start,
                   CASE WHEN ls.geom IS NOT NULL AND e.geom IS NOT NULL
                        THEN ST_LineLocatePoint(ls.geom, ST_EndPoint(e.geom)) END AS f_end
            FROM network_edges e
            LEFT JOIN lines l ON l.id = e.line_id
            LEFT JOIN line_segments ls ON ls.id = e.segment_id
            WHERE e.era_id = ? AND e.id = ?
            SQL, [$eraId, $id]);
        if ($e === null) {
            return null;
        }

        return [
            'kind' => 'edge',
            'id' => (int) $e->id,
            'from_node_id' => (int) $e->from_node_id,
            'to_node_id' => (int) $e->to_node_id,
            'segment_id' => $e->segment_id !== null ? (int) $e->segment_id : null,
            'line' => self::line($e),
            'length_m' => (int) $e->length_m,
            'measured_m' => $e->measured_m !== null ? round((float) $e->measured_m, 1) : null,
            'nodes_gap_m' => $e->nodes_gap_m !== null ? round((float) $e->nodes_gap_m, 2) : null,
            'status' => $e->status,
            'connector' => (bool) $e->is_connector,
            'heritage' => (bool) $e->is_heritage,
            'traffic_type' => $e->traffic_type,
            'network' => $e->network,
            'gauge_mm' => $e->gauge_mm !== null ? (int) $e->gauge_mm : null,
            'pk' => self::pkRange($e->pk_start_m, $e->pk_end_m, $e->f_start, $e->f_end),
            'source' => $e->source,
            'source_trace_id' => $e->source_trace_id !== null ? (int) $e->source_trace_id : null,
            'osm_way_id' => $e->osm_way_id !== null ? (int) $e->osm_way_id : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function stationEdge(int $eraId, int $id): ?array
    {
        $e = DB::selectOne(<<<'SQL'
            SELECT se.id, se.length_m, se.skipped_count, se.non_passenger_m, se.heritage_m, se.gap_joins,
                   se.line_ids::text AS line_ids, se.line_lengths::text AS line_lengths,
                   a.id AS a_id, a.slug AS a_slug, a.name AS a_name, b.id AS b_id, b.slug AS b_slug, b.name AS b_name,
                   ST_Distance(a.geom::geography, b.geom::geography) AS direct_m
            FROM station_edges se
            JOIN stations a ON a.id = se.from_station_id
            JOIN stations b ON b.id = se.to_station_id
            WHERE se.era_id = ? AND se.id = ?
            SQL, [$eraId, $id]);
        if ($e === null) {
            return null;
        }
        $ids = self::pgInts($e->line_ids);
        $lengths = self::pgInts($e->line_lengths);
        $lines = DB::table('lines')->whereIn('id', $ids)->get(['id', 'slug', 'name', 'code'])->keyBy('id');

        return [
            'kind' => 'station_edge',
            'id' => (int) $e->id,
            'from' => ['id' => (int) $e->a_id, 'slug' => $e->a_slug, 'name' => $e->a_name],
            'to' => ['id' => (int) $e->b_id, 'slug' => $e->b_slug, 'name' => $e->b_name],
            'length_m' => (int) $e->length_m,
            'direct_m' => round((float) $e->direct_m, 1),
            'skipped_count' => (int) $e->skipped_count,
            'non_passenger_m' => (int) $e->non_passenger_m,
            'heritage_m' => (int) $e->heritage_m,
            'gap_joins' => (int) $e->gap_joins,
            'lines' => array_values(array_filter(array_map(function (int $lineId, int $i) use ($lines, $lengths): ?array {
                $l = $lines->get($lineId);

                return $l === null ? null : [
                    'id' => $lineId, 'slug' => (string) $l->slug, 'name' => (string) $l->name, 'code' => $l->code,
                    'length_m' => $lengths[$i] ?? null,
                ];
            }, $ids, array_keys($ids)))),
        ];
    }

    /** @return array<string, mixed>|null */
    private function station(int $eraId, int $id): ?array
    {
        $s = DB::selectOne(<<<'SQL'
            SELECT s.id, s.slug, s.name, s.uic_code, s.provenance, s.position_source, s.sources_count,
                   ST_X(s.geom) AS lng, ST_Y(s.geom) AS lat, se.status, se.uncertain, se.station_id IS NOT NULL AS in_era,
                   (SELECT n.id FROM network_nodes n WHERE n.era_id = ? AND n.station_id = s.id ORDER BY n.id LIMIT 1) AS node_id
            FROM stations s
            LEFT JOIN station_eras se ON se.station_id = s.id AND se.era_id = ?
            WHERE s.id = ?
            SQL, [$eraId, $eraId, $id]);
        // Une gare qui n'existe pas à cette ère (ni statut, ni nœud) n'a pas de fiche ici.
        if ($s === null || (! $s->in_era && $s->node_id === null)) {
            return null;
        }
        $neighbours = DB::select(<<<'SQL'
            SELECT o.id, o.slug, o.name, x.length_m
            FROM (
                SELECT to_station_id AS other, length_m FROM station_edges WHERE era_id = ? AND from_station_id = ?
                UNION ALL
                SELECT from_station_id, length_m FROM station_edges WHERE era_id = ? AND to_station_id = ?
            ) x JOIN stations o ON o.id = x.other
            ORDER BY x.length_m, o.id
            SQL, [$eraId, $id, $eraId, $id]);

        return [
            'kind' => 'station',
            'id' => (int) $s->id,
            'slug' => $s->slug,
            'name' => $s->name,
            'uic_code' => $s->uic_code,
            'status' => $s->status,
            'uncertain' => (bool) $s->uncertain,
            'lng' => (float) $s->lng,
            'lat' => (float) $s->lat,
            'node_id' => $s->node_id !== null ? (int) $s->node_id : null,
            // Source de chaque attribut de la gare (jsonb : { "geom": "sncf", "name": "osm", … }).
            'provenance' => is_string($s->provenance) ? json_decode($s->provenance, true) : null,
            'position_source' => $s->position_source,
            'sources_count' => $s->sources_count !== null ? (int) $s->sources_count : null,
            'neighbours' => array_map(fn (object $o): array => [
                'id' => (int) $o->id, 'slug' => $o->slug, 'name' => $o->name, 'length_m' => (int) $o->length_m,
            ], array_slice($neighbours, 0, self::NEIGHBOURS)),
            'neighbour_count' => count($neighbours),
        ];
    }

    /** @return array<string, mixed>|null */
    private function stop(int $eraId, int $id): ?array
    {
        $r = DB::selectOne(<<<'SQL'
            SELECT ls.id, ls.position, ls.pk_m, ls.effective_status, ls.is_terminal,
                   l.id AS line_id, l.slug, l.name, l.code, s.id AS s_id, s.slug AS s_slug, s.name AS s_name,
                   (SELECT count(*) FROM line_stops o WHERE o.line_id = ls.line_id AND o.era_id = ls.era_id) AS stops
            FROM line_stops ls
            JOIN lines l ON l.id = ls.line_id
            JOIN stations s ON s.id = ls.station_id
            WHERE ls.era_id = ? AND ls.id = ?
            SQL, [$eraId, $id]);
        if ($r === null) {
            return null;
        }

        return [
            'kind' => 'stop',
            'id' => (int) $r->id,
            'line' => self::line($r),
            'station' => ['id' => (int) $r->s_id, 'slug' => $r->s_slug, 'name' => $r->s_name],
            'position' => (int) $r->position,
            'stops' => (int) $r->stops,
            'pk_m' => (int) $r->pk_m,
            'status' => $r->effective_status,
            'terminal' => (bool) $r->is_terminal,
        ];
    }

    /**
     * PK des deux bouts d'une arête, interpolés sur le tronçon d'origine (pk_start_m au début du
     * tracé, pk_end_m à la fin), en mètres ; null si le tronçon n'a pas de PK.
     *
     * @return array{start_m: int, end_m: int}|null
     */
    public static function pkRange(mixed $pkStart, mixed $pkEnd, mixed $fStart, mixed $fEnd): ?array
    {
        if ($pkStart === null || $pkEnd === null || $fStart === null || $fEnd === null) {
            return null;
        }
        $a = (float) $pkStart;
        $span = (float) $pkEnd - $a;

        return [
            'start_m' => (int) round($a + $span * (float) $fStart),
            'end_m' => (int) round($a + $span * (float) $fEnd),
        ];
    }

    /** @return array{id: int, slug: string, name: string, code: string|null}|null */
    private static function line(object $row): ?array
    {
        if (($row->line_id ?? null) === null) {
            return null;
        }

        return ['id' => (int) $row->line_id, 'slug' => (string) $row->slug, 'name' => (string) $row->name, 'code' => $row->code];
    }

    /** @return list<int> */
    private static function pgInts(?string $value): array
    {
        $value = trim((string) $value, '{}');

        return $value === '' ? [] : array_map('intval', explode(',', $value));
    }
}
