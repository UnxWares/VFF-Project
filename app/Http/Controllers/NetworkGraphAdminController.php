<?php

namespace App\Http\Controllers;

use App\Mcp\Support\Lookup;
use App\Models\User;
use App\Services\NetworkGraph\DiagnosticsFilter;
use App\Services\NetworkGraph\GraphDiagnostics;
use App\Services\NetworkGraph\GraphDiagnosticsCsv;
use App\Services\NetworkGraph\GraphFeatureDetails;
use App\Services\NetworkGraph\GraphTiles;
use App\Support\MapTiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Graphe ferroviaire d'une ère dans l'espace d'administration (/validate/graph), réservé aux
 * administrateurs (UserPolicy::viewNetworkGraph) : carte des trois couches (tuiles MVT), attributs
 * d'un élément au clic, diagnostics (îles, nœuds isolés, arêtes anormales, jonctions écartées,
 * gares sans arête, lignes en plusieurs morceaux), filtre des jonctions par type et par ligne,
 * export CSV de chaque liste. Documentation : docs/GRAPHE-ADMIN.md.
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
        $era = $eras->firstWhere('year', $requested);
        // Ère demandée sans graphe : l'ère actuelle est montrée, et la page le dit (`missing_era`).
        $missing = $requested > 0 && $era === null ? $requested : null;
        $era ??= $eras->firstWhere('is_current', true) ?? $eras->last();
        $layer = in_array($request->query('layer'), GraphTiles::LAYERS, true) ? (string) $request->query('layer') : 'topology';
        $report = $era !== null ? $diagnostics->head((int) $era->id) : null;

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
            'missing_era' => $missing,
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
        $report = $diagnostics->head($eraId);
        abort_if($report === null, 404);

        return response()->json([
            'era' => $year,
            'built_at' => $this->iso($report['built_at']),
            'summary' => $report['summary'],
            'checks' => GraphDiagnostics::CHECKS,
            'thresholds' => self::thresholds(),
        ]);
    }

    /**
     * Une page d'une liste de diagnostics (JSON), le plus grave d'abord. Jonctions : filtres `type`
     * et `line` (slug ou numéro à 6 chiffres) ; chaque élément reçoit `line_refs` (ses lignes).
     */
    public function check(Request $request, int $year, string $check, GraphDiagnostics $diagnostics): JsonResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        [$eraId] = $this->era($year);
        // Seule la liste demandée est lue dans le cache, pas tout le rapport.
        $report = $this->listReport($diagnostics, $eraId, $check);
        $page = max(1, min(100_000, (int) $request->query('page', '1')));
        [$filter, $line, $unknownLine] = $this->filter($request, $check);

        $result = $unknownLine !== null
            ? ['check' => $check, 'total' => $report['lists'][$check]['total'] ?? 0, 'filtered' => 0, 'page' => 1,
                'per_page' => GraphDiagnostics::PER_PAGE, 'last_page' => 1, 'items' => []]
            : GraphDiagnostics::page($report, $check, $page, filter: $filter);
        if ($check === 'junctions') {
            $refs = $diagnostics->lineRefs(array_merge([], ...array_map(fn (array $i): array => (array) ($i['line_ids'] ?? []), $result['items'])));
            $result['items'] = array_map(fn (array $i): array => [
                ...$i,
                'line_refs' => array_values(array_filter(array_map(fn (int $id): ?array => $refs[$id] ?? null, (array) ($i['line_ids'] ?? [])))),
            ], $result['items']);
        }

        return response()->json([
            'era' => $year,
            ...$result,
            'filters' => ['type' => $filter->type, 'line' => $line, 'unknown_line' => $unknownLine],
        ]);
    }

    /**
     * Toute une liste de diagnostics en CSV, écrite au fil de la réponse (mêmes filtres que la
     * liste JSON ; format décrit dans GraphDiagnosticsCsv).
     */
    public function export(Request $request, int $year, string $check, GraphDiagnostics $diagnostics, GraphDiagnosticsCsv $csv): StreamedResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        [$eraId] = $this->era($year);
        $report = $this->listReport($diagnostics, $eraId, $check);
        [$filter, $line, $unknownLine] = $this->filter($request, $check);
        abort_if($unknownLine !== null, 404);

        return response()->streamDownload(function () use ($csv, $report, $check, $filter): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            $csv->write($out, $report, $check, $filter);
            fclose($out);
        }, GraphDiagnosticsCsv::fileName($year, $check, $filter, $line['slug'] ?? null), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            // Réservé aux administrateurs : jamais dans un cache partagé.
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Rapport réduit à la liste demandée (forme attendue par GraphDiagnostics::page et GraphDiagnosticsCsv).
     * 404 si le graphe de l'ère n'est pas construit.
     *
     * @return array{lists: array<string, array{total: int, items: list<array<string, mixed>>}>}
     */
    private function listReport(GraphDiagnostics $diagnostics, int $eraId, string $check): array
    {
        $list = $diagnostics->list($eraId, $check);
        abort_if($list === null, 404);

        return ['lists' => [$check => $list]];
    }

    /**
     * Filtre demandé (`type`, `line`) : [filtre, ligne trouvée, texte de la ligne introuvable].
     *
     * @return array{0: DiagnosticsFilter, 1: array{id: int, slug: string, name: string, code: string|null}|null, 2: string|null}
     */
    private function filter(Request $request, string $check): array
    {
        $valid = $request->validate([
            'type' => ['nullable', 'string', Rule::in(GraphDiagnostics::JUNCTION_TYPES)],
            'line' => ['nullable', 'string', 'max:100'],
        ]);
        $type = $valid['type'] ?? null;
        $query = trim((string) ($valid['line'] ?? ''));
        $found = $query !== '' && $check === 'junctions' ? Lookup::line($query) : null;
        $line = $found !== null
            ? ['id' => (int) $found->id, 'slug' => (string) $found->slug, 'name' => (string) $found->name, 'code' => $found->code]
            : null;
        $unknown = $query !== '' && $check === 'junctions' && $found === null ? $query : null;

        return [new DiagnosticsFilter($check, is_string($type) ? $type : null, $line['id'] ?? null), $line, $unknown];
    }

    /** Tuile MVT d'une couche du graphe (calques `edges` et `nodes`). */
    public function tile(int $year, string $layer, int $z, int $x, int $y, GraphTiles $tiles): HttpResponse
    {
        Gate::authorize('viewNetworkGraph', User::class);
        // Jamais toute la France en une réponse : pas de tuile sous GraphTiles::MIN_ZOOM.
        abort_unless($z >= GraphTiles::MIN_ZOOM && MapTiles::onGrid($z, $x, $y), 404);
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
        ];
    }
}
