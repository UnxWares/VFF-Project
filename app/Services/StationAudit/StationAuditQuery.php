<?php

namespace App\Services\StationAudit;

use App\Services\Api\Provenance;
use App\Support\FrenchDepartments;
use App\Support\SourceRights;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lecture de l'audit des gares (`station_audits`, `station_date_candidates`) pour la page
 * d'administration, l'export CSV, l'API REST, GraphQL et MCP.
 *
 * File de relecture : par défaut les gares en désaccord, triées par écart décroissant à la source
 * précise la plus éloignée. Filtres : note de placement (`grade`, `all` pour toutes), région INSEE
 * (`region`), contrôle en échec (`check`), note de l'ouverture et de la fermeture (`opening`,
 * `closure`), ligne (code à six chiffres ou slug, `line`), nom (`q`), tri (`sort`).
 */
class StationAuditQuery
{
    public const PER_PAGE = 25;

    public const CHECKS = [
        'closure_before_opening', 'opening_before_lines', 'open_after_lines', 'freight_closed_before_opening',
        'passenger_after_closure', 'wikidata_differs', 'far_from_track', 'retained_outvoted',
        'opening_before_map', 'era_passenger_mismatch', 'closed_without_date', 'freight_only_undated',
        'outlier_source', 'closure_sncf_referential',
    ];

    /** Tris : écart à la source précise la plus éloignée, écart à la voie, nom. */
    public const SORTS = ['spread', 'track', 'name'];

    private const CSV_COLUMNS = [
        'station_id', 'slug', 'name', 'department', 'region', 'status', 'position_source', 'placement_grade', 'families',
        'nearest_agreeing_m', 'farthest_precise_m', 'track_offset_m', 'opening_grade', 'closure_grade',
        'passenger_state', 'freight_state', 'checks', 'positions', 'audited_at',
    ];

    public function __construct(private readonly SourceRights $rights, private readonly Provenance $provenance) {}

    /**
     * Filtres lus dans la requête, bornés aux valeurs connues.
     *
     * @param  array<string, mixed>  $query
     * @return array{grade: string, region: ?string, check: ?string, opening: ?string, closure: ?string, line: ?string, q: ?string, sort: string}
     */
    public function filters(array $query): array
    {
        $pick = fn (string $key, array $allowed): ?string => is_string($query[$key] ?? null) && in_array($query[$key], $allowed, true) ? $query[$key] : null;
        $text = fn (string $key, int $max): ?string => is_string($query[$key] ?? null) && trim($query[$key]) !== '' ? mb_substr(trim($query[$key]), 0, $max) : null;

        return [
            'grade' => $pick('grade', [...PlacementGrade::values(), 'all']) ?? PlacementGrade::Disagree->value,
            'region' => $pick('region', FrenchDepartments::regionCodes()),
            'check' => $pick('check', self::CHECKS),
            'opening' => $pick('opening', DateGrade::values()),
            'closure' => $pick('closure', DateGrade::values()),
            'line' => $text('line', 120),
            'q' => $text('q', 120),
            'sort' => $pick('sort', self::SORTS) ?? 'spread',
        ];
    }

    /**
     * @param  array{grade: string, region: ?string, check: ?string, opening: ?string, closure: ?string, line: ?string, q: ?string, sort: string}  $filters
     * @return array{total: int, page: int, per_page: int, last_page: int, items: list<array<string, mixed>>}
     */
    public function page(array $filters, int $page): array
    {
        $base = $this->base($filters);
        $total = (clone $base)->count();
        $last = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $last);
        $items = $this->sorted($base, $filters['sort'])->forPage($page, self::PER_PAGE)->get()
            ->map(fn (object $r): array => $this->row($r))->all();

        return ['total' => $total, 'page' => $page, 'per_page' => self::PER_PAGE, 'last_page' => $last, 'items' => $items];
    }

    /**
     * Bilan de la dernière passe : gares par note, par note de date, par contrôle en échec.
     *
     * @return array{audited: int, audited_at: ?string, placement: array<string, int>, opening: array<string, int>, closure: array<string, int>, checks: array<string, int>, candidates: array<string, int>}
     */
    public function summary(): array
    {
        $count = fn (string $col): array => DB::table('station_audits')->groupBy($col)->selectRaw("{$col} AS k, count(*) AS n")
            ->pluck('n', 'k')->map(fn ($n): int => (int) $n)->all();
        $checks = DB::table('station_audits')->crossJoin(DB::raw('jsonb_array_elements(checks) AS c'))
            ->groupByRaw("c->>'code'")->selectRaw("c->>'code' AS k, count(*) AS n")->pluck('n', 'k')->map(fn ($n): int => (int) $n)->all();
        $last = DB::table('station_audits')->max('audited_at');

        return [
            'audited' => (int) DB::table('station_audits')->count(),
            'audited_at' => $last !== null ? Carbon::parse((string) $last)->toIso8601String() : null,
            'placement' => array_merge(array_fill_keys(PlacementGrade::values(), 0), $count('placement_grade')),
            'opening' => array_merge(array_fill_keys(DateGrade::values(), 0), $count('opening_grade')),
            'closure' => array_merge(array_fill_keys(DateGrade::values(), 0), $count('closure_grade')),
            'checks' => array_merge(array_fill_keys(self::CHECKS, 0), $checks),
            'candidates' => DB::table('station_date_candidates')->groupBy('comparison')->selectRaw('comparison AS k, count(*) AS n')
                ->pluck('n', 'k')->map(fn ($n): int => (int) $n)->all(),
        ];
    }

    /**
     * Fiche d'audit complète d'une gare (page d'administration) : positions avec coordonnées,
     * tracés de ses lignes à moins de 2 km, dates publiées, dates candidates.
     *
     * @return array<string, mixed>|null
     */
    public function detail(int $stationId): ?array
    {
        $row = $this->base(['grade' => 'all', 'region' => null, 'check' => null, 'opening' => null, 'closure' => null, 'line' => null, 'q' => null, 'sort' => 'name'])
            ->where('a.station_id', $stationId)->first();
        if ($row === null) {
            return null;
        }
        $tracks = DB::select(<<<'SQL'
            SELECT l.code, l.name, ST_AsGeoJSON(ST_Intersection(g.geom, ST_Buffer(s.geom::geography, 2000)::geometry), 6) AS geojson
            FROM stations s JOIN station_lines sl ON sl.station_id = s.id
            JOIN lines l ON l.id = sl.line_id JOIN line_reference_geometries g ON g.line_id = l.id
            WHERE s.id = ? AND ST_DWithin(g.geom::geography, s.geom::geography, 2000)
            ORDER BY l.code
            SQL, [$stationId]);

        return [
            ...$this->row($row),
            'lon' => (float) $row->lon,
            'lat' => (float) $row->lat,
            'opened_at' => $row->opened_at, 'opened_precision' => $row->opened_precision, 'opened_source' => $row->opened_source,
            'closed_at' => $row->closed_at, 'closed_precision' => $row->closed_precision, 'closed_source' => $row->closed_source,
            'positions' => array_map(fn (array $p): array => [
                ...$p, 'provenance' => $this->provenance->for('station_audits', $p['source'] ?? null),
            ], $this->decode($row->positions)),
            'services' => $this->decode($row->services),
            'checks' => $this->decode($row->checks),
            'lines' => $this->lines($stationId),
            'tracks' => $this->tracks($tracks),
            'candidates' => $this->candidates($stationId),
        ];
    }

    /**
     * Audit d'une gare pour l'API publique (REST, GraphQL, MCP) : coordonnées d'une position
     * seulement si sa source est redistribuable (licence ouverte prouvée), sinon l'écart seul.
     *
     * @return array<string, mixed>|null
     */
    public function forStation(int $stationId): ?array
    {
        $row = DB::table('station_audits')->where('station_id', $stationId)->first();
        if ($row === null) {
            return null;
        }
        $positions = array_map(function (array $p): array {
            $provenance = $this->provenance->for('station_audits', $p['source'] ?? null);
            $open = $this->rights->allowsRedistribution($provenance['provider']);

            return [
                'source' => $p['source'] ?? null,
                'family' => $p['family'] ?? null,
                'ref' => $p['ref'] ?? null,
                'lon' => $open ? ($p['lon'] ?? null) : null,
                'lat' => $open ? ($p['lat'] ?? null) : null,
                'distance_m' => $p['distance_m'] ?? null,
                'track_m' => $p['track_m'] ?? null,
                'precision_m' => $p['precision_m'] ?? null,
                'precise' => (bool) ($p['precise'] ?? false),
                'retained' => (bool) ($p['retained'] ?? false),
                'provenance' => $provenance,
            ];
        }, $this->decode($row->positions));

        return [
            'placement' => [
                'grade' => $row->placement_grade,
                'families' => (int) $row->families,
                'nearest_agreeing_m' => $row->nearest_agreeing_m !== null ? (int) $row->nearest_agreeing_m : null,
                'farthest_precise_m' => $row->farthest_precise_m !== null ? (int) $row->farthest_precise_m : null,
                'track_offset_m' => $row->track_offset_m !== null ? (int) $row->track_offset_m : null,
                'positions' => $positions,
            ],
            'dates' => [
                'opening_grade' => $row->opening_grade,
                'closure_grade' => $row->closure_grade,
                'services' => $this->decode($row->services),
            ],
            'checks' => $this->decode($row->checks),
            'date_candidates' => $this->candidates($stationId),
            'rule_version' => $row->rule_version,
            'audited_at' => Carbon::parse((string) $row->audited_at)->toIso8601String(),
        ];
    }

    /**
     * Dates candidates d'une gare, comparées à VFF.
     *
     * @return list<array<string, mixed>>
     */
    public function candidates(int $stationId): array
    {
        return DB::table('station_date_candidates')->where('station_id', $stationId)->orderBy('kind')->orderBy('date')->get()
            ->map(fn (object $c): array => [
                'kind' => $c->kind,
                'date' => (string) $c->date,
                'precision' => $c->precision,
                'rank' => $c->rank,
                'qualifiers' => (object) $this->decode($c->qualifiers),
                'references' => $this->decode($c->references),
                'vff_date' => $c->vff_date !== null ? (string) $c->vff_date : null,
                'vff_precision' => $c->vff_precision,
                'vff_source' => $c->vff_source,
                'comparison' => $c->comparison,
                'delta_days' => $c->delta_days !== null ? (int) $c->delta_days : null,
                'would_replace' => SourceRank::wouldReplace((string) $c->comparison, (string) $c->source, $c->vff_source),
                'source' => $c->source,
                'source_ref' => $c->source_ref,
                'source_url' => $c->source_url,
                'provenance' => $this->provenance->for('station_date_candidates', $c->source, $c->source_url),
            ])->all();
    }

    /**
     * @param  resource  $out
     * @param  array{grade: string, region: ?string, check: ?string, opening: ?string, closure: ?string, line: ?string, q: ?string, sort: string}  $filters
     */
    public function writeCsv($out, array $filters): void
    {
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::CSV_COLUMNS, escape: '');
        $this->sorted($this->base($filters), $filters['sort'])->chunk(1000, function ($rows) use ($out): void {
            foreach ($rows as $r) {
                $services = $this->decode($r->services);
                fputcsv($out, [
                    $r->station_id, $r->slug, $r->name, $r->department_code, $r->region_code, $r->status, $r->position_source,
                    $r->placement_grade, $r->families, $r->nearest_agreeing_m, $r->farthest_precise_m, $r->track_offset_m,
                    $r->opening_grade, $r->closure_grade, $services['passenger']['state'] ?? '', $services['freight']['state'] ?? '',
                    implode(' ', array_column($this->decode($r->checks), 'code')),
                    implode(' ', array_map(fn (array $p): string => ($p['source'] ?? '?').':'.($p['distance_m'] ?? '?'), $this->decode($r->positions))),
                    $r->audited_at,
                ], escape: '');
            }
        });
    }

    /** @param array{grade: string, region: ?string, check: ?string, opening: ?string, closure: ?string, line: ?string, q: ?string, sort: string} $filters */
    private function base(array $filters): Builder
    {
        $q = DB::table('station_audits as a')->join('stations as s', 's.id', '=', 'a.station_id')
            ->select('a.*', 's.slug', 's.name', 's.status', 's.department_code', 's.position_source',
                's.opened_precision', 's.closed_precision')
            ->selectRaw("s.opened_at::text AS opened_at, s.closed_at::text AS closed_at, s.provenance->>'opened_at' AS opened_source, s.provenance->>'closed_at' AS closed_source, ST_X(s.geom) AS lon, ST_Y(s.geom) AS lat");
        if ($filters['grade'] !== 'all') {
            $q->where('a.placement_grade', $filters['grade']);
        }
        foreach (['region' => 'a.region_code', 'opening' => 'a.opening_grade', 'closure' => 'a.closure_grade'] as $key => $col) {
            if ($filters[$key] !== null) {
                $q->where($col, $filters[$key]);
            }
        }
        if ($filters['check'] !== null) {
            $q->whereRaw('a.checks @> ?::jsonb', [json_encode([['code' => $filters['check']]])]);
        }
        if ($filters['q'] !== null) {
            $q->whereRaw('unaccent(lower(s.name)) LIKE unaccent(lower(?))', ['%'.addcslashes($filters['q'], '%_\\').'%']);
        }
        if ($filters['line'] !== null) {
            $line = $filters['line'];
            $q->whereExists(fn ($e) => $e->selectRaw('1')->from('station_lines as sl')->join('lines as l', 'l.id', '=', 'sl.line_id')
                ->whereColumn('sl.station_id', 's.id')->where(fn ($w) => $w->where('l.code', $line)->orWhere('l.slug', $line)));
        }

        return $q;
    }

    private function sorted(Builder $q, string $sort): Builder
    {
        return match ($sort) {
            'track' => $q->orderByRaw('a.track_offset_m DESC NULLS LAST')->orderBy('s.name')->orderBy('s.id'),
            'name' => $q->orderBy('s.name')->orderBy('s.id'),
            default => $q->orderByRaw('COALESCE(a.farthest_precise_m, a.nearest_agreeing_m) DESC NULLS LAST')->orderBy('s.name')->orderBy('s.id'),
        };
    }

    /** @return array<string, mixed> ligne de la file (page et export) */
    private function row(object $r): array
    {
        $services = $this->decode($r->services);

        return [
            'station_id' => (int) $r->station_id,
            'slug' => $r->slug,
            'name' => $r->name,
            'department' => $r->department_code,
            'region' => $r->region_code !== null ? (FrenchDepartments::REGIONS[$r->region_code] ?? null) : null,
            'status' => $r->status,
            'position_source' => $r->position_source,
            'placement_grade' => $r->placement_grade,
            'families' => (int) $r->families,
            'nearest_agreeing_m' => $r->nearest_agreeing_m !== null ? (int) $r->nearest_agreeing_m : null,
            'farthest_precise_m' => $r->farthest_precise_m !== null ? (int) $r->farthest_precise_m : null,
            'track_offset_m' => $r->track_offset_m !== null ? (int) $r->track_offset_m : null,
            'opening_grade' => $r->opening_grade,
            'closure_grade' => $r->closure_grade,
            'passenger' => $services['passenger']['state'] ?? null,
            'freight' => $services['freight']['state'] ?? null,
            'checks' => array_column($this->decode($r->checks), 'code'),
            // Familles comptées : une position recopiée d'une autre source n'en nomme pas une.
            'sources' => array_values(array_unique(array_map(fn (array $p): string => (string) ($p['family'] ?? ''),
                array_filter($this->decode($r->positions), fn (array $p): bool => ! ($p['derived'] ?? false))))),
        ];
    }

    /**
     * Tracés des lignes autour de la gare (GeoJSON), tracés vides écartés.
     *
     * @param  list<object>  $rows
     * @return list<array{code: string, name: string, geometry: array<string, mixed>}>
     */
    private function tracks(array $rows): array
    {
        $out = [];
        foreach ($rows as $t) {
            $geo = json_decode((string) $t->geojson, true);
            if (is_array($geo)) {
                $out[] = ['code' => (string) $t->code, 'name' => (string) $t->name, 'geometry' => $geo];
            }
        }

        return $out;
    }

    /** @return list<array{code: string, name: string, slug: string, pk_m: ?int, method: string}> */
    private function lines(int $stationId): array
    {
        return DB::table('station_lines as sl')->join('lines as l', 'l.id', '=', 'sl.line_id')->where('sl.station_id', $stationId)
            ->orderBy('l.code')->get(['l.code', 'l.name', 'l.slug', 'sl.pk_m', 'sl.method'])
            ->map(fn (object $l): array => ['code' => (string) $l->code, 'name' => (string) $l->name, 'slug' => (string) $l->slug,
                'pk_m' => $l->pk_m !== null ? (int) $l->pk_m : null, 'method' => (string) $l->method])->all();
    }

    /** @return array<mixed> */
    private function decode(mixed $json): array
    {
        $v = is_string($json) ? json_decode($json, true) : $json;

        return is_array($v) ? $v : [];
    }
}
