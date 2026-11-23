<?php

namespace App\Mcp\Support;

use App\Console\Commands\AttachStations;
use App\Services\LinePhases;
use Illuminate\Support\Facades\DB;

/**
 * Dates calculées d'après les événements de ligne (débuts et fins de desserte des gares, phases d'une ligne),
 * revues pour le serveur MCP : une date dont l'événement vient d'une source non commerciale
 * (NonCommercialSources) n'est jamais servie.
 *
 * Elle prend l'événement d'une source servie de même ligne, même nature, même date et même précision, à la
 * même règle de lieu que la page : il couvre la gare (AttachStations::EVENT_COVERS_STATION) ou une portion du
 * changement de phase (LinePhases : COVER_RATIO des points témoins de la portion à moins de COVER_TOL_M de sa
 * section), ou porte sur toute la ligne. Sinon la date devient nulle (desserte) ou non datée (phase :
 * « entre AAAA et AAAA »). Chaque remplacement est cherché en une requête groupée.
 *
 * Le site, l'API REST et GraphQL gardent ces dates avec la mention de leur source.
 */
final class ServedEventDates
{
    /** Événements qui ouvrent la desserte d'une gare (App\Services\StationServiceDates). */
    public const START_TYPES = ['opening', 'opening_passenger', 'opening_freight'];

    /** Événements qui la terminent (App\Services\StationServiceDates). */
    public const END_TYPES = ['closure_passenger', 'closure', 'declassification', 'dismantling'];

    /** Événement sans lieu : toute la ligne (même règle qu'AttachStations et LinePhases). */
    private const WHOLE_LINE = "(c.section_pk_start_m IS NULL AND c.section_geom IS NULL
        AND (c.section_resolution = 'whole_line' OR (c.section_from IS NULL AND c.section_to IS NULL)))";

    /** @var array<int, ?object> événements déjà lus : la ligne s'il est écarté, null s'il est servi */
    private array $seen = [];

    public function __construct(private readonly NonCommercialSources $nonCommercial) {}

    /**
     * Bornes de desserte (StationServiceDates::bounds) servies au MCP, en une lecture des événements et une
     * requête de remplacement pour toutes les bornes écartées.
     *
     * @param  array<array-key, array{bound: ?array{date: string, precision: string, event_id: ?int}, role: 'start'|'end', station_id: int}>  $requests
     * @return array<array-key, ?array{date: string, precision: string, event_id: ?int}>
     */
    public function bounds(array $requests): array
    {
        $excluded = $this->excluded(array_values(array_filter(array_map(fn (array $r): ?int => is_int($r['bound']['event_id'] ?? null)
            ? $r['bound']['event_id'] : null, $requests))));
        $out = [];
        $wanted = [];
        foreach ($requests as $key => $r) {
            $event = is_int($r['bound']['event_id'] ?? null) ? ($excluded[$r['bound']['event_id']] ?? null) : null;
            $out[$key] = $r['bound'];
            if ($event !== null) {
                $wanted[] = ['k' => (string) $key, 'line_id' => (int) $event->line_id, 'station_id' => $r['station_id'],
                    'types' => '{'.implode(',', $r['role'] === 'start' ? self::START_TYPES : self::END_TYPES).'}',
                    'd' => substr((string) $r['bound']['date'], 0, 10), 'p' => (string) $r['bound']['precision']];
            }
        }
        if ($wanted === []) {
            return $out;
        }
        $covers = str_replace('le.', 'c.', AttachStations::EVENT_COVERS_STATION);
        $found = [];
        foreach (DB::select(<<<SQL
            SELECT DISTINCT ON (x.k) x.k, c.id
            FROM json_to_recordset(?::json) AS x(k text, line_id bigint, station_id bigint, types text, d date, p text)
            JOIN line_events c ON c.line_id = x.line_id AND c.type = ANY(x.types::text[]) AND c.event_date = x.d
                AND c.date_precision = x.p
            JOIN station_lines sl ON sl.line_id = x.line_id AND sl.station_id = x.station_id
            JOIN stations s ON s.id = x.station_id
            WHERE {$this->kept()} AND {$covers}
            ORDER BY x.k, c.id
            SQL, [json_encode($wanted, JSON_THROW_ON_ERROR)]) as $row) {
            $found[(string) $row->k] = (int) $row->id;
        }
        foreach ($out as $key => $bound) {
            if (in_array((string) $key, array_column($wanted, 'k'), true)) {
                $out[$key] = isset($found[(string) $key]) && $bound !== null ? array_replace($bound, ['event_id' => $found[(string) $key]]) : null;
            }
        }

        return $out;
    }

    /**
     * Phases de LinePhases::forLine servies au MCP : chaque changement daté par un événement écarté prend un
     * événement servi équivalent qui couvre l'une de ses portions (ou toute la ligne), ou perd son événement ;
     * une phase dont plus aucun changement n'est daté devient non datée (précision range, bornes from_year et
     * to_year de sa fenêtre).
     *
     * @param  list<array<string, mixed>>  $phases
     * @param  list<array{geometry: mixed}>  $portions  portions de forLine(), désignées par leur index dans les changements
     * @return list<array<string, mixed>>
     */
    public function phases(array $phases, array $portions): array
    {
        $ids = [];
        foreach ($phases as $ph) {
            foreach ($ph['changes'] as $c) {
                if (is_int($c['event_id'] ?? null)) {
                    $ids[] = $c['event_id'];
                }
            }
        }
        $excluded = $this->excluded($ids);
        if ($excluded === []) {
            return $phases;
        }

        $wanted = [];
        foreach ($phases as $i => $ph) {
            foreach ($ph['changes'] as $j => $c) {
                $event = is_int($c['event_id'] ?? null) ? ($excluded[$c['event_id']] ?? null) : null;
                if ($event === null) {
                    continue;
                }
                foreach ($c['portions'] ?? [] as $p) {
                    $geometry = $portions[$p]['geometry'] ?? null;
                    $wanted[] = ['k' => "{$i}:{$j}", 'line_id' => (int) $event->line_id, 'type' => (string) $event->type,
                        'd' => substr((string) $event->event_date, 0, 10), 'p' => (string) $event->date_precision,
                        'g' => $geometry === null ? null : (is_string($geometry) ? $geometry : json_encode($geometry, JSON_THROW_ON_ERROR))];
                }
                if (($c['portions'] ?? []) === []) {
                    $wanted[] = ['k' => "{$i}:{$j}", 'line_id' => (int) $event->line_id, 'type' => (string) $event->type,
                        'd' => substr((string) $event->event_date, 0, 10), 'p' => (string) $event->date_precision, 'g' => null];
                }
            }
        }
        $found = [];
        $tol = LinePhases::COVER_TOL_M;
        $needed = (int) ceil(5 * LinePhases::COVER_RATIO);
        foreach (DB::select(<<<SQL
            SELECT DISTINCT ON (x.k) x.k, c.id
            FROM json_to_recordset(?::json) AS x(k text, line_id bigint, type text, d date, p text, g text)
            JOIN line_events c ON c.line_id = x.line_id AND c.type = x.type AND c.event_date = x.d AND c.date_precision = x.p
            CROSS JOIN LATERAL (SELECT CASE WHEN x.g IS NULL THEN NULL ELSE ST_LineMerge(ST_SetSRID(ST_GeomFromGeoJSON(x.g), 4326)) END AS geom) pg
            WHERE {$this->kept()}
              AND (
                ({$this->wholeLine()})
                OR (c.section_geom IS NOT NULL AND GeometryType(pg.geom) = 'LINESTRING' AND (
                    SELECT COUNT(*) FROM unnest(ARRAY[0.1, 0.3, 0.5, 0.7, 0.9]) AS f(f)
                    WHERE ST_DWithin(ST_Transform(c.section_geom, 2154), ST_Transform(ST_LineInterpolatePoint(pg.geom, f.f), 2154), {$tol})
                ) >= {$needed})
              )
            ORDER BY x.k, ({$this->wholeLine()}), c.id
            SQL, [json_encode($wanted, JSON_THROW_ON_ERROR)]) as $row) {
            $found[(string) $row->k] = (int) $row->id;
        }

        foreach ($phases as $i => &$ph) {
            $dated = false;
            foreach ($ph['changes'] as $j => &$c) {
                if (is_int($c['event_id'] ?? null) && isset($excluded[$c['event_id']])) {
                    $c['event_id'] = $found["{$i}:{$j}"] ?? null;
                }
                $dated = $dated || $c['event_id'] !== null;
            }
            unset($c);
            if (! $dated && $ph['precision'] !== 'range') {
                $ph['date'] = null;
                $ph['precision'] = 'range';
            }
        }
        unset($ph);

        return $phases;
    }

    /** Condition « événement servi » sur l'alias c. */
    private function kept(): string
    {
        return $this->nonCommercial->sqlKept('line_events', 'c.source', 'c.source_url');
    }

    private function wholeLine(): string
    {
        return self::WHOLE_LINE;
    }

    /**
     * Événements de cette liste écartés (source non commerciale), par identifiant ; une lecture par
     * identifiant nouveau.
     *
     * @param  list<int>  $ids
     * @return array<int, object>
     */
    private function excluded(array $ids): array
    {
        $missing = array_values(array_diff(array_unique($ids), array_keys($this->seen)));
        if ($missing !== []) {
            $this->seen += array_fill_keys($missing, null);
            foreach (DB::table('line_events')->whereIn('id', $missing)
                ->get(['id', 'line_id', 'type', 'event_date', 'date_precision', 'source', 'source_url']) as $e) {
                if ($this->nonCommercial->excludes('line_events', $e->source, $e->source_url)) {
                    $this->seen[(int) $e->id] = $e;
                }
            }
        }

        return array_filter(array_intersect_key($this->seen, array_flip($ids)));
    }
}
