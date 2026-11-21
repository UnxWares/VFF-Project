<?php

namespace App\Http\Controllers;

use App\Enums\AuditGrade;
use App\Mcp\Support\Lookup;
use App\Models\Line;
use App\Models\User;
use App\Services\SegmentAudit\AuditGrader;
use App\Services\SegmentAudit\SegmentAudits;
use App\Support\FrenchDepartments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit des tronçons dans l'espace d'administration (/validate/audit/segments), mêmes droits que le
 * graphe du réseau (UserPolicy::viewNetworkGraph) : lignes les plus mal notées d'une ère, filtrées
 * par note, état, région et nom ; pour une ligne, carte des tronçons colorés par note et preuves ;
 * export CSV. Les notes sont calculées par `vff:audit-segments` (docs/AUDIT-TRONCONS.md) et
 * publiées en lecture par l'API (GET /api/v1/lines/{line}/audit, champ GraphQL `Line.audit`).
 */
class SegmentAuditAdminController extends Controller
{
    public function index(Request $request, SegmentAudits $audits): Response
    {
        Gate::authorize('viewNetworkGraph', User::class);
        [$filters, $year, $eraId] = $this->filters($request, $audits);
        $line = $this->selectedLine($request);

        return Inertia::render('Validate/AuditSegments', [
            'eras' => $audits->eras(),
            'year' => $year,
            'filters' => $filters,
            'checks' => SegmentAudits::CHECKS,
            'grades' => AuditGrade::values(),
            'statuses' => SegmentAudits::STATUSES,
            'regions' => self::regions(),
            'summary' => $eraId !== null ? $audits->summary($eraId) : null,
            'lines' => $eraId !== null ? $audits->lines($eraId, $filters, max(1, (int) $request->query('page', '1'))) : null,
            'selected' => $line !== null && $year !== null ? $audits->forLine($line, $year, withGeometry: true) : null,
            'selected_slug' => $line?->slug,
            'thresholds' => self::thresholds(),
            'rule_version' => AuditGrader::RULE_VERSION,
        ]);
    }

    /** Export CSV : les lignes de la liste filtrée, ou les tronçons d'une ligne (`line`). */
    public function export(Request $request, SegmentAudits $audits): StreamedResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        [$filters, $year, $eraId] = $this->filters($request, $audits);
        abort_if($eraId === null || $year === null, 404);
        $line = $this->selectedLine($request);
        $name = $line !== null ? "audit-troncons-{$line->slug}-{$year}.csv" : "audit-troncons-lignes-{$year}.csv";

        return response()->streamDownload(function () use ($audits, $eraId, $year, $filters, $line): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            $audits->writeCsv($out, $eraId, $year, $filters, $line);
            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            // Réservé aux administrateurs : jamais dans un cache partagé.
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Filtres validés, ère retenue (demandée, sinon la plus récente auditée) et son identifiant.
     *
     * @return array{0: array{check: ?string, grade: ?string, status: ?string, region: ?string, q: ?string}, 1: ?int, 2: ?int}
     */
    private function filters(Request $request, SegmentAudits $audits): array
    {
        $valid = $request->validate([
            'era' => ['nullable', 'integer', 'between:1800,2100'],
            'check' => ['nullable', 'string', Rule::in(SegmentAudits::CHECKS)],
            'grade' => ['nullable', 'string', Rule::in(AuditGrade::values())],
            'status' => ['nullable', 'string', Rule::in(SegmentAudits::STATUSES)],
            'region' => ['nullable', 'string', Rule::in(array_keys(FrenchDepartments::REGIONS))],
            'q' => ['nullable', 'string', 'max:100'],
            'line' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        $eras = $audits->eras();
        $years = array_column($eras, 'year');
        $requested = isset($valid['era']) ? (int) $valid['era'] : null;
        $year = $requested !== null && in_array($requested, $years, true)
            ? $requested
            : (array_values(array_filter($eras, fn (array $e): bool => $e['current']))[0]['year'] ?? ($years[0] ?? null));

        return [[
            'check' => $valid['check'] ?? null,
            'grade' => $valid['grade'] ?? null,
            'status' => $valid['status'] ?? null,
            'region' => $valid['region'] ?? null,
            'q' => isset($valid['q']) && trim($valid['q']) !== '' ? trim($valid['q']) : null,
        ], $year, $year !== null ? $audits->eraId($year) : null];
    }

    private function selectedLine(Request $request): ?Line
    {
        $query = trim((string) $request->query('line', ''));
        if ($query === '') {
            return null;
        }

        return Lookup::line($query);
    }

    /** Régions de métropole et d'outre-mer, par ordre alphabétique. @return list<array{code: string, name: string}> */
    private static function regions(): array
    {
        $out = [];
        foreach (FrenchDepartments::REGIONS as $code => $name) {
            $out[] = ['code' => (string) $code, 'name' => $name];
        }
        $collator = class_exists(\Collator::class) ? new \Collator('fr_FR') : null;
        usort($out, fn (array $a, array $b): int => $collator?->compare($a['name'], $b['name']) ?? strcmp($a['name'], $b['name']));

        return $out;
    }

    /** Seuils rappelés dans l'interface. @return array<string, float|int> */
    private static function thresholds(): array
    {
        return [
            'target_m' => AuditGrader::TARGET_M,
            'tolerance_m' => AuditGrader::TOLERANCE_M,
            'reference_radius_m' => AuditGrader::REFERENCE_RADIUS_M,
            'junction_poor_m' => AuditGrader::JUNCTION_POOR_M,
            'station_far_m' => AuditGrader::STATION_FAR_M,
            'near_date_years' => AuditGrader::NEAR_DATE_YEARS,
            'line_share' => AuditGrader::LINE_SHARE,
        ];
    }
}
