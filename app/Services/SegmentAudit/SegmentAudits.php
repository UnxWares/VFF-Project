<?php

namespace App\Services\SegmentAudit;

use App\Enums\AuditGrade;
use App\Models\Line;
use App\Services\LineDirectory;
use App\Support\FrenchDepartments;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lecture de l'audit des tronçons (table segment_audits) : liste des lignes les plus mal notées
 * (page /validate/audit/segments), fiche d'audit d'une ligne (page, API REST et GraphQL, serveur
 * MCP), export CSV.
 */
final class SegmentAudits
{
    /** Contrôles notés, dans l'ordre d'affichage. */
    public const CHECKS = ['classification', 'placement', 'accuracy', 'trace'];

    /** États de tronçon filtrables (line_segments.status). */
    public const STATUSES = ['in_service', 'single_track', 'service_track', 'construction', 'abandoned', 'dismantled', 'razed'];

    public const PER_PAGE = 25;

    /** Colonnes de l'export CSV des tronçons. */
    public const SEGMENT_CSV_COLUMNS = ['line_code', 'line_slug', 'era', 'segment_id', 'status', 'traffic_type', 'trace_source', 'length_m',
        'classification', 'placement', 'accuracy', 'trace', 'worst', 'score', 'classification_reasons', 'placement_reasons',
        'accuracy_reasons', 'trace_reasons', 'placement_median_m', 'placement_p95_m', 'audited_at'];

    /** Colonnes de l'export CSV des lignes. */
    public const LINE_CSV_COLUMNS = ['line_code', 'line_slug', 'line_name', 'era', 'segments', 'length_m',
        'classification', 'placement', 'accuracy', 'trace', 'worst', 'score', 'audited_at'];

    /** Ères qui ont un audit, de la plus récente à la plus ancienne. */
    public function eras(): array
    {
        return DB::table('eras as e')
            ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('segment_audits as a')->whereColumn('a.era_id', 'e.id')->where('a.scope', 'line'))
            ->orderByDesc('e.year')->get(['e.year', 'e.is_current'])
            ->map(fn (object $e): array => ['year' => (int) $e->year, 'current' => (bool) $e->is_current])->values()->all();
    }

    public function eraId(int $year): ?int
    {
        $id = DB::table('eras')->where('year', $year)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Synthèses de lignes d'une ère, filtrées, la plus mal notée d'abord.
     *
     * @param  array{check?: ?string, grade?: ?string, status?: ?string, region?: ?string, q?: ?string}  $filters
     */
    public function lineQuery(int $eraId, array $filters): Builder
    {
        $query = DB::table('segment_audits as a')
            ->join('lines as l', 'l.id', '=', 'a.line_id')
            ->where('a.era_id', $eraId)->where('a.scope', 'line');
        $grade = $filters['grade'] ?? null;
        if ($grade !== null) {
            $column = in_array($filters['check'] ?? null, self::CHECKS, true) ? $filters['check'].'_grade' : 'worst_grade';
            $query->where('a.'.$column, $grade);
        }
        if (($status = $filters['status'] ?? null) !== null) {
            $query->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('segment_audits as s')
                ->whereColumn('s.line_id', 'a.line_id')->whereColumn('s.era_id', 'a.era_id')->where('s.scope', 'segment')->where('s.status', $status));
        }
        if (($region = $filters['region'] ?? null) !== null) {
            $query->whereIn('a.line_id', LineDirectory::idsInDepartments(FrenchDepartments::inRegion($region)));
        }
        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $like = '%'.addcslashes(mb_strtolower($q), '%_\\').'%';
            $query->where(fn (Builder $w) => $w->whereRaw('lower(l.name) LIKE ?', [$like])->orWhere('l.code', $q)->orWhere('l.slug', $q));
        }

        return $query->orderByDesc('a.score')->orderByDesc('a.length_m')->orderBy('l.id');
    }

    /**
     * Une page de la liste des lignes.
     *
     * @param  array{check?: ?string, grade?: ?string, status?: ?string, region?: ?string, q?: ?string}  $filters
     * @return array{total: int, page: int, per_page: int, last_page: int, items: list<array<string, mixed>>}
     */
    public function lines(int $eraId, array $filters, int $page = 1): array
    {
        $query = $this->lineQuery($eraId, $filters);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $lastPage));
        $rows = $query->forPage($page, self::PER_PAGE)
            ->get(['l.slug', 'l.code', 'l.name', 'a.length_m', 'a.score', 'a.worst_grade', 'a.classification_grade', 'a.placement_grade',
                'a.accuracy_grade', 'a.trace_grade', 'a.evidence', 'a.audited_at']);

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'last_page' => $lastPage,
            'items' => $rows->map(fn (object $r): array => $this->lineSummary($r))->values()->all(),
        ];
    }

    /**
     * Nombre de lignes et de tronçons par note pour chaque contrôle (ère entière).
     *
     * @return array{lines: int, segments: int, length_m: int, by_check: array<string, array<string, array{segments: int, length_m: int}>>, audited_at: ?string}
     */
    public function summary(int $eraId): array
    {
        $byCheck = [];
        foreach (self::CHECKS as $check) {
            $byCheck[$check] = array_fill_keys(AuditGrade::values(), ['segments' => 0, 'length_m' => 0]);
            foreach (DB::table('segment_audits')->where('era_id', $eraId)->where('scope', 'segment')
                ->groupBy($check.'_grade')->selectRaw($check.'_grade AS g, COUNT(*) AS n, COALESCE(SUM(length_m), 0) AS m')->get() as $r) {
                $byCheck[$check][$r->g] = ['segments' => (int) $r->n, 'length_m' => (int) $r->m];
            }
        }
        $totals = DB::table('segment_audits')->where('era_id', $eraId)
            ->selectRaw("COUNT(*) FILTER (WHERE scope = 'line') AS lines, COUNT(*) FILTER (WHERE scope = 'segment') AS segments,
                         COALESCE(SUM(length_m) FILTER (WHERE scope = 'segment'), 0) AS m, MAX(audited_at) AS at")->first();

        return [
            'lines' => (int) ($totals->lines ?? 0),
            'segments' => (int) ($totals->segments ?? 0),
            'length_m' => (int) ($totals->m ?? 0),
            'by_check' => $byCheck,
            'audited_at' => self::iso($totals->at ?? null),
        ];
    }

    /**
     * Fiche d'audit d'une ligne à une ère, ou null si elle n'est pas auditée.
     *
     * @return array<string, mixed>|null
     */
    public function forLine(Line $line, int $year, bool $withGeometry = false): ?array
    {
        $eraId = $this->eraId($year);
        if ($eraId === null) {
            return null;
        }
        $summary = DB::table('segment_audits')->where('line_id', $line->id)->where('era_id', $eraId)->where('scope', 'line')->first();
        if ($summary === null) {
            return null;
        }
        $columns = ['segment_id', 'status', 'traffic_type', 'trace_source', 'length_m', 'classification_grade', 'placement_grade',
            'accuracy_grade', 'trace_grade', 'worst_grade', 'score', 'evidence'];
        $query = DB::table('segment_audits')->where('line_id', $line->id)->where('era_id', $eraId)->where('scope', 'segment')
            ->orderByDesc('score')->orderByDesc('length_m')->orderBy('segment_id');
        $rows = $withGeometry
            ? $query->select($columns)->selectRaw('ST_AsGeoJSON(geom, 6) AS geometry')->get()
            : $query->get($columns);
        $evidence = self::decode($summary->evidence);

        return [
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'era_year' => $year,
            'audited_at' => self::iso($summary->audited_at),
            'rule_version' => $summary->rule_version,
            'grades' => self::grades($summary),
            'worst_grade' => $summary->worst_grade,
            'score' => (int) $summary->score,
            'length_m' => (int) $summary->length_m,
            'shares' => $evidence['shares'] ?? [],
            'line_checks' => $evidence['accuracy'] ?? [],
            'segments' => $rows->map(fn (object $r): array => [
                'segment_id' => (int) $r->segment_id,
                'status' => $r->status,
                'traffic_type' => $r->traffic_type,
                'trace_source' => $r->trace_source,
                'length_m' => (int) $r->length_m,
                'grades' => self::grades($r),
                'worst_grade' => $r->worst_grade,
                'score' => (int) $r->score,
                'evidence' => self::decode($r->evidence),
            ] + ($withGeometry ? ['geometry' => $r->geometry !== null ? json_decode($r->geometry, true) : null] : []))->values()->all(),
        ];
    }

    /**
     * Écrit l'export CSV (séparateur virgule, UTF-8 avec BOM pour les tableurs) : les lignes filtrées,
     * ou les tronçons d'une ligne si $line est donnée.
     *
     * @param  resource  $out
     * @param  array{check?: ?string, grade?: ?string, status?: ?string, region?: ?string, q?: ?string}  $filters
     */
    public function writeCsv($out, int $eraId, int $year, array $filters, ?Line $line = null): void
    {
        fwrite($out, "\u{FEFF}");
        if ($line !== null) {
            fputcsv($out, self::SEGMENT_CSV_COLUMNS, escape: '');
            DB::table('segment_audits')->where('line_id', $line->id)->where('era_id', $eraId)->where('scope', 'segment')
                ->select(['segment_id', 'status', 'traffic_type', 'trace_source', 'length_m', 'classification_grade', 'placement_grade',
                    'accuracy_grade', 'trace_grade', 'worst_grade', 'score', 'evidence', 'audited_at'])
                ->lazyById(500, 'segment_id')
                ->each(function (object $r) use ($out, $line, $year): void {
                    $e = self::decode($r->evidence);
                    fputcsv($out, [$line->code, $line->slug, $year, $r->segment_id, $r->status, $r->traffic_type, $r->trace_source, $r->length_m,
                        $r->classification_grade, $r->placement_grade, $r->accuracy_grade, $r->trace_grade, $r->worst_grade, $r->score,
                        implode(' ', $e['classification']['reasons'] ?? []), implode(' ', $e['placement']['reasons'] ?? []),
                        implode(' ', $e['accuracy']['reasons'] ?? []), implode(' ', $e['trace']['reasons'] ?? []),
                        $e['placement']['median_m'] ?? '', $e['placement']['p95_m'] ?? '', self::iso($r->audited_at)], escape: '');
                });

            return;
        }
        fputcsv($out, self::LINE_CSV_COLUMNS, escape: '');
        foreach ($this->lineQuery($eraId, $filters)->cursor() as $r) {
            $e = self::decode($r->evidence);
            fputcsv($out, [$r->code, $r->slug, $r->name, $year, $e['segments'] ?? 0, $r->length_m, $r->classification_grade, $r->placement_grade,
                $r->accuracy_grade, $r->trace_grade, $r->worst_grade, $r->score, self::iso($r->audited_at)], escape: '');
        }
    }

    /** @return array<string, mixed> */
    private function lineSummary(object $r): array
    {
        $e = self::decode($r->evidence);

        return [
            'slug' => $r->slug,
            'code' => $r->code,
            'name' => $r->name,
            'length_m' => (int) $r->length_m,
            'segments' => (int) ($e['segments'] ?? 0),
            'score' => (int) $r->score,
            'worst_grade' => $r->worst_grade,
            'grades' => self::grades($r),
            'audited_at' => self::iso($r->audited_at),
        ];
    }

    /** @return array<string, string> */
    private static function grades(object $r): array
    {
        $out = [];
        foreach (self::CHECKS as $check) {
            $out[$check] = $r->{$check.'_grade'};
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function decode(mixed $json): array
    {
        return is_string($json) ? (array) json_decode($json, true) : [];
    }

    private static function iso(mixed $at): ?string
    {
        return $at !== null ? Carbon::parse((string) $at)->toIso8601String() : null;
    }
}
