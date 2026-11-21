<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StationAudit\DateGrade;
use App\Services\StationAudit\PlacementGrade;
use App\Services\StationAudit\PlacementGrader;
use App\Services\StationAudit\StationAuditQuery;
use App\Support\FrenchDepartments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit des gares dans l'espace d'administration (/validate/audit/stations), réservé aux
 * administrateurs comme le graphe (UserPolicy::viewNetworkGraph) : file de relecture des gares
 * (désaccords d'abord), filtres, fiche d'une gare avec la carte des positions de chaque source et
 * les preuves, export CSV. Les données viennent de `vff:audit-stations` et
 * `vff:import-station-wikidata-dates` (docs/AUDIT-GARES.md). La page ne modifie rien.
 */
class StationAuditAdminController extends Controller
{
    public function index(Request $request, StationAuditQuery $audits): Response
    {
        Gate::authorize('viewNetworkGraph', User::class);
        $filters = $audits->filters($request->query());
        $page = $audits->page($filters, max(1, min(100_000, (int) $request->query('page', '1'))));

        return Inertia::render('Validate/AuditStations', [
            'summary' => $audits->summary(),
            'filters' => $filters,
            'stations' => $page,
            'options' => [
                'grades' => PlacementGrade::values(),
                'date_grades' => DateGrade::values(),
                'checks' => StationAuditQuery::CHECKS,
                'regions' => collect(FrenchDepartments::REGIONS)->map(fn (string $name, int|string $code): array => ['code' => (string) $code, 'name' => $name])->values(),
                'sorts' => StationAuditQuery::SORTS,
            ],
            'thresholds' => [
                'agree_m' => PlacementGrader::AGREE_M,
                'close_m' => PlacementGrader::CLOSE_M,
                'disagree_precise_m' => PlacementGrader::DISAGREE_PRECISE_M,
                'disagree_coarse_m' => PlacementGrader::DISAGREE_COARSE_M,
                'track_far_m' => PlacementGrader::TRACK_FAR_M,
            ],
        ]);
    }

    /** Fiche d'audit d'une gare (JSON) : positions, voies proches, dates, dates candidates. */
    public function show(int $station, StationAuditQuery $audits): JsonResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        $detail = $audits->detail($station);
        abort_if($detail === null, 404);

        return response()->json($detail)->header('Cache-Control', 'private, no-store');
    }

    /** Toute la file filtrée en CSV (mêmes filtres que la page), écrite au fil de la réponse. */
    public function export(Request $request, StationAuditQuery $audits): StreamedResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        $filters = $audits->filters($request->query());

        return response()->streamDownload(function () use ($audits, $filters): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            $audits->writeCsv($out, $filters);
            fclose($out);
        }, 'audit-gares-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            // Réservé aux administrateurs : jamais dans un cache partagé.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
