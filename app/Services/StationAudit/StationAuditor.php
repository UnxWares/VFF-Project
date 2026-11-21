<?php

namespace App\Services\StationAudit;

use App\Support\FrenchDepartments;
use App\Support\StationNames;
use Illuminate\Support\Facades\DB;

/**
 * Passe d'audit des gares (docs/AUDIT-GARES.md) : lit les tables du moteur sans les modifier et
 * écrit une ligne par gare dans `station_audits`.
 *
 * Pour chaque gare :
 *   - positions comparées : chaque position de `station_sources` rattachée à la gare (SNCF, OSM,
 *     Wikidata, OHM, listes par PK) et les zones de gare de la BD TOPO de même nom à moins de 1 km
 *     ou qui contiennent la gare ; écart à la position retenue et à la voie la plus proche de ses
 *     lignes (tracés de référence, `line_reference_geometries`) ;
 *   - note de placement (PlacementGrader) ;
 *   - notes des dates et des services, contrôles croisés (DateAuditor), avec les dates candidates
 *     de `station_date_candidates` déjà importées.
 *
 * Seules écritures : `station_audits` (upsert par gare) et des tables temporaires de session.
 */
class StationAuditor
{
    public const RULE_VERSION = '2026-11-21';

    /**
     * Rayon de recherche d'une zone BD TOPO homonyme. Réglé à 1 km sur le tirage du 21/11 : à 3 km, la gare
     * principale d'une ville était prise pour une petite gare de même nom (Béthune-Rivage, Sens-St-Clément).
     */
    private const BDTOPO_NAME_RADIUS_M = 1000;

    private const CHUNK = 400;

    private bool $tempReady = false;

    /** @var array<string, true> dalles BD TOPO déjà chargées dans la table temporaire */
    private array $loadedTiles = [];

    public function __construct(private readonly BdtopoStationZones $bdtopo) {}

    /**
     * @param  list<int>  $stationIds
     * @param  callable(int): void|null  $progress  appelée après chaque lot avec le nombre de gares traitées
     * @return list<array<string, mixed>> lignes calculées (forme de station_audits, positions décodées)
     */
    public function audit(array $stationIds, bool $withBdtopo = true, bool $offline = false, bool $write = true, ?callable $progress = null): array
    {
        $this->prepareTemp();
        $rows = [];
        foreach (array_chunk($stationIds, self::CHUNK) as $chunk) {
            $batch = $this->auditChunk($chunk, $withBdtopo, $offline);
            if ($write) {
                $this->write($batch);
            }
            array_push($rows, ...$batch);
            if ($progress !== null) {
                $progress(count($chunk));
            }
        }

        return $rows;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function auditChunk(array $ids, bool $withBdtopo, bool $offline): array
    {
        $in = '{'.implode(',', $ids).'}';
        $stations = DB::select(<<<'SQL'
            SELECT s.id, s.name, s.status, s.department_code, s.position_source,
                   s.opened_at::text AS opened_at, s.opened_precision, s.provenance->>'opened_at' AS opened_source,
                   s.closed_at::text AS closed_at, s.closed_precision, s.provenance->>'closed_at' AS closed_source,
                   (s.provenance->>'geom_precision_m')::numeric AS geom_precision_m,
                   ST_X(s.geom) AS lon, ST_Y(s.geom) AS lat
            FROM stations s WHERE s.id = ANY(?::bigint[]) ORDER BY s.id
            SQL, [$in]);
        if ($stations === []) {
            return [];
        }

        if ($withBdtopo) {
            $this->loadBdtopo(array_map(fn (object $s): array => [(float) $s->lon, (float) $s->lat], $stations), $offline);
        }
        $this->loadNames($ids);

        $positions = $this->sourcePositions($in);
        if ($withBdtopo) {
            foreach ($this->bdtopoPositions($in) as $id => $list) {
                array_push($positions[$id], ...$list);
            }
        }
        $track = [];
        foreach (DB::select(<<<'SQL'
            SELECT s.id, round(min(ST_Distance(s.geom::geography, ST_ClosestPoint(g.geom, s.geom)::geography))) AS track_m
            FROM stations s JOIN station_lines sl ON sl.station_id = s.id
            JOIN line_reference_geometries g ON g.line_id = sl.line_id
            WHERE s.id = ANY(?::bigint[]) GROUP BY s.id
            SQL, [$in]) as $t) {
            $track[(int) $t->id] = $t->track_m;
        }
        $events = [];
        foreach (DB::select(<<<'SQL'
            SELECT station_id, type, event_date::text AS date, date_precision AS precision, source, is_inferred AS inferred
            FROM station_events WHERE station_id = ANY(?::bigint[]) ORDER BY station_id, event_date
            SQL, [$in]) as $e) {
            $events[(int) $e->station_id][] = ['type' => $e->type, 'date' => $e->date, 'precision' => $e->precision, 'source' => $e->source, 'inferred' => (bool) $e->inferred];
        }
        $lines = [];
        foreach (DB::select(<<<'SQL'
            SELECT station_id, count(*) AS n, min(first_year) AS first_year, max(last_year) AS last_year,
                   count(*) FILTER (WHERE last_year IS NULL) AS open
            FROM station_lines WHERE station_id = ANY(?::bigint[]) GROUP BY station_id
            SQL, [$in]) as $l) {
            $lines[(int) $l->station_id] = ['count' => (int) $l->n, 'first_year' => $l->first_year !== null ? (int) $l->first_year : null,
                'last_year' => $l->last_year !== null ? (int) $l->last_year : null, 'open' => (int) $l->open];
        }
        $eras = [];
        foreach (DB::select(<<<'SQL'
            SELECT se.station_id, e.year, se.status FROM station_eras se JOIN eras e ON e.id = se.era_id
            WHERE se.station_id = ANY(?::bigint[]) ORDER BY se.station_id, e.year
            SQL, [$in]) as $e) {
            $eras[(int) $e->station_id][] = ['year' => (int) $e->year, 'status' => (string) $e->status];
        }
        $freight = DB::table('station_sources')->whereRaw('station_id = ANY(?::bigint[])', [$in])->where('source', 'sncf')
            ->whereNotNull('freight')->groupBy('station_id')->selectRaw('station_id, bool_or(freight) AS f')->pluck('f', 'station_id');
        $candidates = [];
        foreach (DB::table('station_date_candidates')->whereRaw('station_id = ANY(?::bigint[])', [$in])
            ->get(['station_id', 'kind', 'comparison', 'delta_days', 'date', 'precision']) as $c) {
            $candidates[(int) $c->station_id][] = ['kind' => $c->kind, 'comparison' => $c->comparison,
                'delta_days' => $c->delta_days !== null ? (int) $c->delta_days : null, 'date' => (string) $c->date, 'precision' => $c->precision];
        }

        $now = now();
        $rows = [];
        foreach ($stations as $s) {
            $id = (int) $s->id;
            $list = $positions[$id] ?? [];
            $placement = PlacementGrader::grade($list, $s->position_source);
            $trackM = isset($track[$id]) ? (int) $track[$id] : null;
            $dates = DateAuditor::audit([
                'status' => (string) $s->status,
                'opened_at' => $s->opened_at, 'opened_precision' => $s->opened_precision, 'opened_source' => $s->opened_source,
                'closed_at' => $s->closed_at, 'closed_precision' => $s->closed_precision, 'closed_source' => $s->closed_source,
                'events' => $events[$id] ?? [],
                'lines' => $lines[$id] ?? ['count' => 0, 'first_year' => null, 'last_year' => null, 'open' => 0],
                'freight_flag' => isset($freight[$id]) ? (bool) $freight[$id] : null,
                'candidates' => $candidates[$id] ?? [],
                'track_offset_m' => $trackM,
                'today_year' => (int) $now->year,
                'eras' => $eras[$id] ?? [],
            ]);
            $retainedFamily = PlacementGrader::family($s->position_source);
            $checks = $dates->checks;
            if ($placement->outvoted) {
                $checks[] = ['code' => 'retained_outvoted', 'detail' => "{$placement->farthestPreciseM} m"];
            }
            $rows[] = [
                'station_id' => $id,
                'placement_grade' => $placement->grade->value,
                'families' => $placement->families,
                'nearest_agreeing_m' => $placement->nearestAgreeingM,
                'farthest_precise_m' => $placement->farthestPreciseM,
                'track_offset_m' => $trackM,
                'positions' => array_map(fn (SourcePosition $p): array => $p->toArray($p->family() === $retainedFamily), $list),
                'opening_grade' => $dates->opening->value,
                'closure_grade' => $dates->closure->value,
                'services' => $dates->services,
                'checks' => $checks,
                'checks_count' => count($checks),
                'region_code' => $s->department_code !== null ? FrenchDepartments::regionCode((string) $s->department_code) : null,
                'rule_version' => self::RULE_VERSION,
                'audited_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * Positions de station_sources rattachées aux gares, avec l'écart à la position retenue et à
     * la voie. Précision annoncée d'une position de liste : properties.precision_m.
     *
     * @return array<int, list<SourcePosition>>
     */
    private function sourcePositions(string $in): array
    {
        $out = array_fill_keys(array_map('intval', explode(',', trim($in, '{}'))), []);
        foreach (DB::select(<<<'SQL'
            SELECT ss.station_id, ss.source, ss.source_ref, ss.name, ST_X(ss.geom) AS lon, ST_Y(ss.geom) AS lat,
                   round(ST_Distance(ss.geom::geography, s.geom::geography)) AS d,
                   (SELECT round(min(ST_Distance(ss.geom::geography, ST_ClosestPoint(g.geom, ss.geom)::geography)))
                    FROM station_lines sl JOIN line_reference_geometries g ON g.line_id = sl.line_id
                    WHERE sl.station_id = ss.station_id) AS track_m,
                   CASE WHEN (ss.properties->>'precision_m') ~ '^[0-9.]+$' THEN round((ss.properties->>'precision_m')::numeric) END AS precision_m,
                   COALESCE(ss.properties->>'geocoding' = 'station', false) AS derived
            FROM station_sources ss JOIN stations s ON s.id = ss.station_id
            WHERE ss.station_id = ANY(?::bigint[])
            ORDER BY ss.station_id, ss.source, ss.source_ref
            SQL, [$in]) as $r) {
            $out[(int) $r->station_id][] = new SourcePosition(
                (string) $r->source, (string) $r->source_ref, (float) $r->lon, (float) $r->lat,
                $r->d !== null ? (int) $r->d : null, $r->track_m !== null ? (int) $r->track_m : null,
                $r->name, $r->precision_m !== null ? (int) $r->precision_m : null, (bool) $r->derived,
            );
        }

        return $out;
    }

    /**
     * Zones BD TOPO de même nom (une des formes normalisées du nom de la gare ou de ses sources) à
     * moins de 1 km, ou qui contiennent la gare. Position montrée : un point de la zone
     * (ST_PointOnSurface) ; écart : distance de la gare au contour (0 dedans).
     *
     * @return array<int, list<SourcePosition>>
     */
    private function bdtopoPositions(string $in): array
    {
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT s.id AS station_id, z.cleabs, z.toponyme, ST_X(ST_PointOnSurface(z.geom)) AS lon, ST_Y(ST_PointOnSurface(z.geom)) AS lat,
                   round(ST_Distance(s.geom::geography, z.geom::geography)) AS d,
                   (SELECT round(min(ST_Distance(ST_PointOnSurface(z.geom)::geography, ST_ClosestPoint(g.geom, ST_PointOnSurface(z.geom))::geography)))
                    FROM station_lines sl JOIN line_reference_geometries g ON g.line_id = sl.line_id
                    WHERE sl.station_id = s.id) AS track_m
            FROM stations s
            JOIN tmp_audit_bdtopo z ON ST_DWithin(z.geom::geography, s.geom::geography, ?)
            WHERE s.id = ANY(?::bigint[])
              AND (ST_Intersects(z.geom, s.geom)
                   OR EXISTS (SELECT 1 FROM tmp_audit_names n WHERE n.station_id = s.id AND n.name_norm = z.name_norm))
            ORDER BY s.id, d
            SQL, [self::BDTOPO_NAME_RADIUS_M, $in]) as $r) {
            $out[(int) $r->station_id][] = new SourcePosition(
                'bdtopo', (string) $r->cleabs, (float) $r->lon, (float) $r->lat,
                (int) $r->d, $r->track_m !== null ? (int) $r->track_m : null, $r->toponyme,
            );
        }

        return $out;
    }

    private function prepareTemp(): void
    {
        if ($this->tempReady) {
            return;
        }
        DB::statement('CREATE TEMP TABLE IF NOT EXISTS tmp_audit_bdtopo (cleabs text PRIMARY KEY, name_norm text, toponyme text, nature text, geom geometry(Geometry, 4326))');
        DB::statement('CREATE INDEX IF NOT EXISTS tmp_audit_bdtopo_geom ON tmp_audit_bdtopo USING gist (geom)');
        DB::statement('CREATE TEMP TABLE IF NOT EXISTS tmp_audit_names (station_id bigint, name_norm text)');
        $this->tempReady = true;
    }

    /** @param list<array{0: float, 1: float}> $points */
    private function loadBdtopo(array $points, bool $offline): void
    {
        foreach (BdtopoStationZones::tiles($points) as [$west, $south]) {
            $key = "{$west}:{$south}";
            if (isset($this->loadedTiles[$key])) {
                continue;
            }
            $this->loadedTiles[$key] = true;
            foreach (array_chunk($this->bdtopo->tile($west, $south, $offline), 200) as $zones) {
                $values = [];
                $bindings = [];
                foreach ($zones as $z) {
                    $values[] = '(?, ?, ?, ?, ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)))';
                    array_push($bindings, $z['cleabs'], StationNames::normalize($z['toponyme']), $z['toponyme'], $z['nature'], json_encode($z['geometry']));
                }
                DB::insert('INSERT INTO tmp_audit_bdtopo (cleabs, name_norm, toponyme, nature, geom) VALUES '.implode(', ', $values).' ON CONFLICT (cleabs) DO NOTHING', $bindings);
            }
        }
    }

    /**
     * Formes normalisées du nom de chaque gare et des noms de ses sources (comparaison avec les
     * noms de la BD TOPO, « Gare de … » et « Halte ferroviaire de … » retirés).
     *
     * @param  list<int>  $ids
     */
    private function loadNames(array $ids): void
    {
        DB::statement('TRUNCATE tmp_audit_names');
        $names = DB::table('stations')->whereIn('id', $ids)->pluck('name', 'id')->all();
        $rows = [];
        foreach ($names as $id => $name) {
            $rows[(int) $id.'|'.StationNames::normalize((string) $name)] = ['station_id' => (int) $id, 'name_norm' => StationNames::normalize((string) $name)];
        }
        foreach (DB::table('station_sources')->whereIn('station_id', $ids)->whereNotNull('name_norm')->distinct()->get(['station_id', 'name_norm']) as $r) {
            $rows[(int) $r->station_id.'|'.$r->name_norm] = ['station_id' => (int) $r->station_id, 'name_norm' => (string) $r->name_norm];
        }
        foreach (array_chunk(array_values($rows), 1000) as $chunk) {
            DB::table('tmp_audit_names')->insert(array_map(fn (array $r): array => [
                'station_id' => $r['station_id'],
                'name_norm' => $r['name_norm'],
            ], $chunk));
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function write(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $now = now();
        DB::table('station_audits')->upsert(array_map(fn (array $r): array => [
            ...$r,
            'positions' => json_encode($r['positions'], JSON_UNESCAPED_UNICODE),
            'services' => json_encode($r['services'], JSON_UNESCAPED_UNICODE),
            'checks' => json_encode($r['checks'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows), ['station_id'], [
            'placement_grade', 'families', 'nearest_agreeing_m', 'farthest_precise_m', 'track_offset_m', 'positions',
            'opening_grade', 'closure_grade', 'services', 'checks', 'checks_count', 'region_code', 'rule_version', 'audited_at', 'updated_at',
        ]);
    }
}
