<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NetworkGraph\GraphDiagnostics;
use App\Services\NetworkGraph\GraphFeatureDetails;
use App\Services\NetworkGraph\GraphTiles;
use App\Support\MapTiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Graphe ferroviaire d'une ère dans l'espace d'administration (/validate/graph), réservé aux
 * administrateurs (UserPolicy::viewNetworkGraph) : carte des trois couches (tuiles MVT), attributs
 * d'un élément au clic, diagnostics (îles, nœuds isolés, arêtes anormales, jonctions écartées,
 * gares sans arête, lignes en plusieurs morceaux). Documentation : docs/GRAPHE-ADMIN.md.
 * Ces points d'accès ne font pas partie de l'API publique.
 */
class NetworkGraphAdminController extends Controller
{
    public function index(Request $request, GraphDiagnostics $diagnostics): Response
    {
        Gate::authorize('viewNetworkGraph', User::class);

        $eras = DB::table('network_graph_builds as b')
            ->join('eras as e', 'e.id', '=', 'b.era_id')
            ->orderBy('e.year')
            ->get(['e.id', 'e.year', 'e.is_current', 'b.built_at', 'b.nodes', 'b.edges', 'b.station_edges']);

        $requested = (int) $request->query('era', '0');
        $era = $eras->firstWhere('year', $requested)
            ?? $eras->firstWhere('is_current', true)
            ?? $eras->last();
        $layer = in_array($request->query('layer'), GraphTiles::LAYERS, true) ? (string) $request->query('layer') : 'topology';
        $report = $era !== null ? $diagnostics->report((int) $era->id) : null;

        return Inertia::render('Validate/Graph', [
            'eras' => $eras->map(fn (object $e): array => [
                'year' => (int) $e->year,
                'current' => (bool) $e->is_current,
                'built_at' => $this->iso($e->built_at),
                'nodes' => (int) $e->nodes,
                'edges' => (int) $e->edges,
                'station_edges' => (int) $e->station_edges,
            ])->values(),
            'year' => $era !== null ? (int) $era->year : null,
            'layer' => $layer,
            'layers' => GraphTiles::LAYERS,
            'checks' => GraphDiagnostics::CHECKS,
            'summary' => $report['summary'] ?? null,
            'thresholds' => self::thresholds(),
        ]);
    }

    /** Résumé des diagnostics d'une ère (JSON). */
    public function diagnostics(int $year, GraphDiagnostics $diagnostics): JsonResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        [$eraId] = $this->era($year);
        $report = $diagnostics->report($eraId);
        abort_if($report === null, 404);

        return response()->json([
            'era' => $year,
            'built_at' => $this->iso($report['built_at']),
            'summary' => $report['summary'],
            'checks' => GraphDiagnostics::CHECKS,
            'thresholds' => self::thresholds(),
        ]);
    }

    /** Une page d'une liste de diagnostics (JSON), le plus grave d'abord. */
    public function check(Request $request, int $year, string $check, GraphDiagnostics $diagnostics): JsonResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        [$eraId] = $this->era($year);
        $report = $diagnostics->report($eraId);
        abort_if($report === null, 404);
        $page = max(1, min(10_000, (int) $request->query('page', '1')));

        return response()->json(['era' => $year, ...GraphDiagnostics::page($report, $check, $page)]);
    }

    /** Tuile MVT d'une couche du graphe (calques `edges` et `nodes`). */
    public function tile(int $year, string $layer, int $z, int $x, int $y, GraphTiles $tiles): HttpResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        abort_unless(MapTiles::onGrid($z, $x, $y), 404);
        [$eraId, $builtAt] = $this->era($year);

        return response($tiles->tile($eraId, $builtAt, $layer, $z, $x, $y), 200, [
            'Content-Type' => 'application/x-protobuf',
            // Réservée aux administrateurs : jamais dans un cache partagé.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Attributs d'un élément du graphe (JSON). */
    public function feature(int $year, string $kind, int $id, GraphFeatureDetails $details): JsonResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        [$eraId] = $this->era($year);
        $feature = $details->find($eraId, $kind, $id);
        abort_if($feature === null, 404);

        return response()->json(['era' => $year, ...$feature]);
    }

    /**
     * Ère dont le graphe est construit : [identifiant, date de construction], sinon 404.
     *
     * @return array{0: int, 1: string}
     */
    private function era(int $year): array
    {
        $row = DB::table('network_graph_builds as b')
            ->join('eras as e', 'e.id', '=', 'b.era_id')
            ->where('e.year', $year)
            ->first(['e.id', 'b.built_at']);
        abort_if($row === null, 404);

        return [(int) $row->id, (string) $row->built_at];
    }

    private function iso(mixed $timestamp): ?string
    {
        return $timestamp !== null ? Carbon::parse((string) $timestamp)->toIso8601String() : null;
    }

    /** Seuils des diagnostics, rappelés dans l'interface et la documentation. @return array<string, float|int> */
    private static function thresholds(): array
    {
        return [
            'junction_tolerance_m' => GraphDiagnostics::JUNCTION_TOLERANCE_M,
            'length_tolerance_m' => GraphDiagnostics::LENGTH_TOLERANCE_M,
            'length_tolerance_ratio' => GraphDiagnostics::LENGTH_TOLERANCE_RATIO,
            'detour_ratio' => GraphDiagnostics::DETOUR_RATIO,
            'detour_min_m' => GraphDiagnostics::DETOUR_MIN_M,
            'list_cap' => GraphDiagnostics::LIST_CAP,
        ];
    }
}
