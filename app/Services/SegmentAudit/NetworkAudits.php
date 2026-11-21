<?php

namespace App\Services\SegmentAudit;

use App\Enums\AuditGrade;
use App\Services\LineDirectory;
use App\Services\StationAudit\DateGrade;
use App\Services\StationAudit\PlacementGrade;
use App\Services\StationAudit\StationAuditQuery;
use App\Support\FrenchDepartments;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Listes d'audits à l'échelle du réseau, les mêmes pour l'API REST, GraphQL et le serveur MCP :
 * les tronçons audités d'une ère (table segment_audits) et les gares auditées (station_audits).
 * Filtres, tri, `limit` (50 par défaut, 200 au plus), `offset` et `total` sont communs ; les règles de
 * validation ci-dessous servent aux trois interfaces.
 */
final class NetworkAudits
{
    public const DEFAULT_LIMIT = AuditPage::DEFAULT_LIMIT;

    public const MAX_LIMIT = AuditPage::MAX_LIMIT;

    /** Tris des tronçons : plus mal notés d'abord, plus longs d'abord, ordre des lignes. */
    public const SEGMENT_SORTS = ['worst', 'length', 'line'];

    public function __construct(private readonly StationAuditQuery $stations) {}

    /**
     * Règles de validation des filtres des tronçons (REST, MCP ; GraphQL les porte dans le schéma).
     *
     * @return array<string, list<mixed>>
     */
    public static function segmentRules(): array
    {
        return [
            'era' => ['nullable', 'integer', 'between:1830,2030'],
            'grade' => ['nullable', 'string', 'in:'.implode(',', AuditGrade::values())],
            'check' => ['nullable', 'string', 'in:'.implode(',', SegmentAudits::CHECKS)],
            'reason' => ['nullable', 'string', 'regex:/^[a-z0-9_]{1,60}$/'],
            'status' => ['nullable', 'string', 'in:'.implode(',', SegmentAudits::STATUSES)],
            'department' => ['nullable', 'string', 'in:'.implode(',', FrenchDepartments::codes())],
            'region' => ['nullable', 'string', 'in:'.implode(',', FrenchDepartments::regionCodes())],
            'line' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SEGMENT_SORTS)],
        ] + self::pageRules();
    }

    /**
     * Règles de validation des filtres des gares.
     *
     * @return array<string, list<mixed>>
     */
    public static function stationRules(): array
    {
        return [
            'grade' => ['nullable', 'string', 'in:'.implode(',', PlacementGrade::values())],
            'check' => ['nullable', 'string', 'in:'.implode(',', StationAuditQuery::CHECKS)],
            'opening' => ['nullable', 'string', 'in:'.implode(',', DateGrade::values())],
            'closure' => ['nullable', 'string', 'in:'.implode(',', DateGrade::values())],
            'department' => ['nullable', 'string', 'in:'.implode(',', FrenchDepartments::codes())],
            'region' => ['nullable', 'string', 'in:'.implode(',', FrenchDepartments::regionCodes())],
            'line' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', StationAuditQuery::SORTS)],
        ] + self::pageRules();
    }

    /** @return array<string, list<mixed>> */
    private static function pageRules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'between:1,'.self::MAX_LIMIT],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Une page des tronçons audités d'une ère. Ère sans audit : page vide.
     *
     * @param  array<string, mixed>  $filters  era, grade, check, reason, status, department, region, line, q, sort
     * @return array{era_year: int, total: int, limit: int, offset: int, items: list<array<string, mixed>>}
     */
    public function segments(array $filters, ?int $limit = null, ?int $offset = null): array
    {
        $year = (int) ($filters['era'] ?? 2030);
        [$limit, $offset] = self::bounds($limit, $offset);
        $eraId = app(SegmentAudits::class)->eraId($year);
        $page = ['era_year' => $year, 'total' => 0, 'limit' => $limit, 'offset' => $offset, 'items' => []];
        if ($eraId === null) {
            return $page;
        }
        $query = $this->segmentQuery($eraId, $filters);
        $page['total'] = (clone $query)->count();
        $rows = $query->offset($offset)->limit($limit)->get(['l.slug', 'l.code', 'l.name', 'a.segment_id', 'a.status', 'a.traffic_type',
            'a.trace_source', 'a.length_m', 'a.classification_grade', 'a.placement_grade', 'a.accuracy_grade', 'a.trace_grade',
            'a.worst_grade', 'a.score', 'a.evidence', 'a.audited_at']);
        $page['items'] = $rows->map(fn (object $r): array => $this->segmentRow($r))->values()->all();

        return $page;
    }

    /**
     * Une page des gares auditées. Une passe par gare, sans ère.
     *
     * @param  array<string, mixed>  $filters  grade, check, opening, closure, department, region, line, q, sort
     * @return array{total: int, limit: int, offset: int, items: list<array<string, mixed>>}
     */
    public function stations(array $filters, ?int $limit = null, ?int $offset = null): array
    {
        [$limit, $offset] = self::bounds($limit, $offset);
        $filters = [
            'grade' => $filters['grade'] ?? 'all',
            'region' => $filters['region'] ?? null,
            'department' => isset($filters['department']) ? strtoupper((string) $filters['department']) : null,
            'check' => $filters['check'] ?? null,
            'opening' => $filters['opening'] ?? null,
            'closure' => $filters['closure'] ?? null,
            'line' => isset($filters['line']) ? trim((string) $filters['line']) : null,
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : null,
            'sort' => $filters['sort'] ?? 'spread',
        ];
        $filters['line'] = $filters['line'] === '' ? null : $filters['line'];
        $filters['q'] = $filters['q'] === '' ? null : $filters['q'];

        return ['limit' => $limit, 'offset' => $offset] + $this->stations->listing($filters, $limit, $offset);
    }

    /** @return array{0: int, 1: int} */
    private static function bounds(?int $limit, ?int $offset): array
    {
        return [max(1, min(self::MAX_LIMIT, $limit ?? self::DEFAULT_LIMIT)), max(0, $offset ?? 0)];
    }

    /** @param  array<string, mixed>  $filters */
    private function segmentQuery(int $eraId, array $filters): Builder
    {
        $query = DB::table('segment_audits as a')->join('lines as l', 'l.id', '=', 'a.line_id')
            ->where('a.era_id', $eraId)->where('a.scope', 'segment');
        $check = in_array($filters['check'] ?? null, SegmentAudits::CHECKS, true) ? $filters['check'] : null;
        if (($grade = $filters['grade'] ?? null) !== null) {
            $query->where('a.'.($check !== null ? $check.'_grade' : 'worst_grade'), $grade);
        }
        if (($reason = $filters['reason'] ?? null) !== null) {
            // Le code de raison d'un seul contrôle, ou de l'un des quatre.
            $query->where(function (Builder $w) use ($reason, $check): void {
                foreach ($check !== null ? [$check] : SegmentAudits::CHECKS as $c) {
                    $w->orWhereRaw("a.evidence->'{$c}'->'reasons' @> ?::jsonb", [json_encode([$reason])]);
                }
            });
        }
        if (($status = $filters['status'] ?? null) !== null) {
            $query->where('a.status', $status);
        }
        if (($department = $filters['department'] ?? null) !== null) {
            $query->whereIn('a.line_id', LineDirectory::idsInDepartments([strtoupper((string) $department)]));
        }
        if (($region = $filters['region'] ?? null) !== null) {
            $query->whereIn('a.line_id', LineDirectory::idsInDepartments(FrenchDepartments::inRegion((string) $region)));
        }
        if (($line = trim((string) ($filters['line'] ?? ''))) !== '') {
            $query->where(fn (Builder $w) => $w->where('l.code', $line)->orWhere('l.slug', $line));
        }
        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->whereRaw('unaccent(lower(l.name)) LIKE unaccent(lower(?))', ['%'.addcslashes($q, '%_\\').'%']);
        }

        return match ($filters['sort'] ?? 'worst') {
            'length' => $query->orderByDesc('a.length_m')->orderByDesc('a.score')->orderBy('a.segment_id'),
            'line' => $query->orderBy('l.code')->orderBy('a.segment_id'),
            default => $query->orderByDesc('a.score')->orderByDesc('a.length_m')->orderBy('a.segment_id'),
        };
    }

    /** @return array<string, mixed> */
    private function segmentRow(object $r): array
    {
        $evidence = is_string($r->evidence) ? (array) json_decode($r->evidence, true) : [];
        $grades = $reasons = [];
        foreach (SegmentAudits::CHECKS as $check) {
            $grades[$check] = $r->{$check.'_grade'};
            $reasons[$check] = array_values((array) ($evidence[$check]['reasons'] ?? []));
        }

        return [
            'line_slug' => $r->slug,
            'line_code' => $r->code,
            'line_name' => $r->name,
            'segment_id' => (int) $r->segment_id,
            'status' => $r->status,
            'traffic_type' => $r->traffic_type,
            'trace_source' => $r->trace_source,
            'length_m' => (int) $r->length_m,
            'grades' => $grades,
            'worst_grade' => $r->worst_grade,
            'score' => (int) $r->score,
            'reasons' => $reasons,
            'audited_at' => $r->audited_at !== null ? Carbon::parse((string) $r->audited_at)->toIso8601String() : null,
        ];
    }
}
