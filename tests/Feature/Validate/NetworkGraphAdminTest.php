<?php

namespace Tests\Feature\Validate;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\NetworkGraph\DiagnosticsFilter;
use App\Services\NetworkGraph\GraphDiagnostics;
use App\Services\NetworkGraph\GraphDiagnosticsCsv;
use App\Services\NetworkGraph\GraphTiles;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Graphe du réseau dans l'espace d'administration (/validate/graph) : accès réservé aux
 * administrateurs, diagnostics justes sur un petit graphe écrit à la main, cache par construction.
 *
 *   N4 (ligne L2, finit à 7 m de N1)
 *    \
 *     N1 ── E1 (L1) ── N2 [gare A] ── E2 (L1) ── N3 [gare D] ── E5 (sans tracé, 0 m) ── N8
 *
 *   N5 ── E4 (L3) ── N6 [gare C, sans voisine]          île 1
 *   N9 ── E7 (L2) ── N10                                île 2 (L2 en deux morceaux)
 *   N7                                                  nœud isolé
 *   gare B : ouverte, sans nœud (loin de toute voie) ; arête gare à gare A - D
 */
class NetworkGraphAdminTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    private Era $era;

    /** @var array<string, int> */
    private array $n = [];

    /** @var array<string, int> */
    private array $e = [];

    /** @var array<string, Line> */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
        $this->era = Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);

        foreach (['L1', 'L2', 'L3'] as $code) {
            $this->lines[$code] = Line::factory()->create(['name' => "Ligne {$code}", 'code' => $code, 'is_lgv' => false]);
        }
        $a = $this->station('a', 2.01, 48.0, 'active');
        $b = $this->station('b', 5.0, 43.5, 'active');
        $c = $this->station('c', 3.01, 45.0, 'active');
        $d = $this->station('d', 2.02, 48.0, 'active');

        $this->n['N1'] = $this->node(2.00, 48.0, 'junction');
        $this->n['N2'] = $this->node(2.01, 48.0, 'station', $a->id);
        $this->n['N3'] = $this->node(2.02, 48.0, 'station', $d->id);
        $this->n['N4'] = $this->node(2.00, 48.01, 'end');
        $this->n['N5'] = $this->node(3.00, 45.0, 'end');
        $this->n['N6'] = $this->node(3.01, 45.0, 'station', $c->id);
        $this->n['N7'] = $this->node(4.00, 44.0, 'end');
        $this->n['N8'] = $this->node(2.02, 48.0, 'end');
        $this->n['N9'] = $this->node(2.05, 48.05, 'end');
        $this->n['N10'] = $this->node(2.06, 48.05, 'end');

        $this->e['E1'] = $this->edge('N1', 'N2', 'L1', [[2.00, 48.0], [2.01, 48.0]]);
        $this->e['E2'] = $this->edge('N2', 'N3', 'L1', [[2.01, 48.0], [2.02, 48.0]]);
        // La voie de L2 s'arrête à 0,0001° (environ 7,4 m) de la jonction N1 : jonction écartée.
        $this->e['E3'] = $this->edge('N4', 'N1', 'L2', [[2.00, 48.01], [2.0001, 48.0]]);
        $this->e['E4'] = $this->edge('N5', 'N6', 'L3', [[3.00, 45.0], [3.01, 45.0]]);
        $this->e['E5'] = $this->edge('N3', 'N8', 'L1', null);
        $this->e['E7'] = $this->edge('N9', 'N10', 'L2', [[2.05, 48.05], [2.06, 48.05]]);

        DB::table('station_edges')->insert([
            'era_id' => $this->era->id, 'from_station_id' => $a->id, 'to_station_id' => $d->id,
            'line_id' => $this->lines['L1']->id, 'length_m' => 744, 'skipped_count' => 0,
            'line_ids' => '{'.$this->lines['L1']->id.'}', 'line_lengths' => '{744}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->build('2026-11-20 08:00:00');
    }

    private function station(string $slug, float $lng, float $lat, string $status): Station
    {
        $station = Station::factory()->create([
            'slug' => $slug, 'name' => 'Gare '.strtoupper($slug), 'geom' => Point::makeGeodetic($lat, $lng),
        ]);
        DB::table('station_eras')->insert([
            'station_id' => $station->id, 'era_id' => $this->era->id, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $station;
    }

    private function node(float $lng, float $lat, string $kind, ?int $stationId = null): int
    {
        return (int) DB::scalar(
            'INSERT INTO network_nodes (era_id, station_id, kind, geom, created_at, updated_at)
             VALUES (?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now()) RETURNING id',
            [$this->era->id, $stationId, $kind, $lng, $lat],
        );
    }

    /** @param  list<array{0: float, 1: float}>|null  $coords */
    private function edge(string $from, string $to, string $line, ?array $coords): int
    {
        $wkt = $coords === null ? null : 'LINESTRING('.implode(', ', array_map(fn ($c) => "{$c[0]} {$c[1]}", $coords)).')';

        return (int) DB::scalar(
            "INSERT INTO network_edges (era_id, from_node_id, to_node_id, line_id, length_m, status, geom, created_at, updated_at)
             SELECT ?, ?, ?, ?, COALESCE(ROUND(ST_Length(g::geography)), 0)::int, 'in_service', g, now(), now()
             FROM (SELECT ST_GeomFromText(?, 4326) AS g) x RETURNING id",
            [$this->era->id, $this->n[$from], $this->n[$to], $this->lines[$line]->id, $wkt],
        );
    }

    private function build(string $builtAt): void
    {
        DB::table('network_graph_builds')->updateOrInsert(['era_id' => $this->era->id], [
            'built_at' => $builtAt, 'duration_ms' => 1, 'stations_source' => 'station_eras', 'segments' => 6,
            'nodes' => 10, 'edges' => 6, 'station_nodes' => 3, 'closed_station_nodes' => 0, 'station_edges' => 1,
            'heritage_station_edges' => 0, 'gap_joins' => 0, 'open_stations' => 4, 'unattached_stations' => 1,
            'isolated_stations' => 1, 'components' => 2, 'largest_component' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        $report = app(GraphDiagnostics::class)->report($this->era->id);
        $this->assertNotNull($report);

        return $report;
    }

    /**
     * Premier élément d'une liste du rapport dont `$key` vaut `$value`.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function firstWhere(array $items, string $key, mixed $value): ?array
    {
        foreach ($items as $item) {
            if (($item[$key] ?? null) === $value) {
                return $item;
            }
        }

        return null;
    }

    // ---- Accès ----------------------------------------------------------------------------------

    /** @return array<string, array{0: string}> */
    public static function nonAdminRoles(): array
    {
        return ['visiteur' => ['visitor'], 'contributeur' => ['contributor'], 'validateur' => ['validator']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_the_graph_and_its_diagnostics_are_refused_to_non_admins(string $role): void
    {
        $member = $this->member($role);
        foreach ([
            '/validate/graph',
            '/validate/graph/1930/diagnostics',
            '/validate/graph/1930/diagnostics/islands',
            '/validate/graph/1930/diagnostics/junctions.csv',
            '/validate/graph/1930/tiles/topology/8/129/88.mvt',
            "/validate/graph/1930/features/edge/{$this->e['E1']}",
        ] as $uri) {
            $this->actingAs($member)->get($uri)->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/validate/graph')->assertRedirect('/login');
        $this->getJson('/validate/graph/1930/diagnostics')->assertUnauthorized();
    }

    public function test_a_suspended_admin_is_refused(): void
    {
        $admin = $this->member('admin', ['suspended_at' => now()->subDay(), 'suspension_reason' => 'Motif de test suffisamment long.']);
        $this->actingAs($admin)->get('/validate/graph')->assertForbidden();
    }

    public function test_a_suspended_admin_is_refused_on_json_routes_and_tiles(): void
    {
        $admin = $this->member('admin', ['suspended_at' => now()->subDay(), 'suspension_reason' => 'Motif de test suffisamment long.']);
        foreach ([
            '/validate/graph/1930/diagnostics',
            '/validate/graph/1930/diagnostics/junctions?page=1',
            '/validate/graph/1930/diagnostics/edges.csv',
            '/validate/graph/1930/tiles/topology/8/129/88.mvt',
            "/validate/graph/1930/features/node/{$this->n['N1']}",
        ] as $uri) {
            $this->actingAs($admin)->getJson($uri)->assertForbidden();
        }
    }

    public function test_an_admin_sees_the_page_with_the_summary_of_the_chosen_era(): void
    {
        $this->actingAs($this->member('admin'))->get('/validate/graph?era=1930&layer=stations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Validate/Graph')
                ->where('year', 1930)
                ->where('layer', 'stations')
                ->where('eras.0.year', 1930)
                ->where('summary.topology.islands', 3)
                ->where('summary.stations.without_edge', 2));
    }

    public function test_an_unknown_layer_falls_back_to_the_topology(): void
    {
        $this->actingAs($this->member('admin'))->get('/validate/graph?layer=autre')
            ->assertInertia(fn (Assert $page) => $page->where('layer', 'topology')->where('year', 1930)->where('missing_era', null));
    }

    public function test_an_era_without_graph_in_the_address_is_announced(): void
    {
        // L'ère actuelle est montrée, mais la page sait que 1855 a été demandée et le dit.
        $this->actingAs($this->member('admin'))->get('/validate/graph?era=1855')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('year', 1930)->where('missing_era', 1855));
    }

    public function test_an_era_without_graph_answers_404(): void
    {
        Era::factory()->create(['year' => 1940]);
        $admin = $this->member('admin');
        $this->actingAs($admin)->getJson('/validate/graph/1940/diagnostics')->assertNotFound();
        $this->actingAs($admin)->get('/validate/graph/1940/tiles/topology/8/129/88.mvt')->assertNotFound();
    }

    // ---- Diagnostics ----------------------------------------------------------------------------

    public function test_topology_counts_islands_isolated_nodes_and_zero_length_edges(): void
    {
        $t = $this->report()['summary']['topology'];

        $this->assertSame(10, $t['nodes']);
        $this->assertSame(6, $t['edges']);
        // Réseau principal (N1, N2, N3, N4, N8), deux îles (N5-N6, N9-N10) et le nœud isolé N7.
        $this->assertSame(4, $t['components']);
        $this->assertSame(3, $t['islands']);
        $this->assertSame(5, $t['largest']['nodes']);
        $this->assertSame(1, $t['isolated_nodes']);
        // E5 n'a pas de tracé : elle est classée « sans tracé », pas « longueur nulle ».
        $this->assertSame(0, $t['zero_length_edges']);
        $this->assertSame(1, $t['no_geometry_edges']);

        $lists = $this->report()['lists'];
        $this->assertSame([$this->n['N7']], array_column($lists['isolated_nodes']['items'], 'id'));
        // E5 (sans tracé, nœuds au même point) et l'arête gare à gare A - D, écrite ici sans tracé.
        $edges = array_map(
            fn (array $i): array => [$i['layer'], $i['layer'] === 'topology' ? $i['id'] : null, $i['reason']],
            $lists['edges']['items'],
        );
        $this->assertEqualsCanonicalizing([['topology', $this->e['E5'], 'no_geometry'], ['stations', null, 'no_geometry']], $edges);
        $e5 = $this->firstWhere($lists['edges']['items'], 'id', $this->e['E5']);
        $this->assertNotNull($e5);
        $this->assertSame(0.0, $e5['measured_m'], 'distance entre les deux nœuds de E5');
        // Les îles sont listées de la plus longue à la plus courte, avec leur emprise et leurs lignes.
        $island = $lists['islands']['items'][0];
        $this->assertSame(2, $island['nodes']);
        $this->assertCount(4, $island['bbox']);
        $this->assertContains($island['lines'][0]['code'], ['L2', 'L3']);
    }

    public function test_a_junction_whose_tracks_do_not_touch_is_reported_with_its_gap(): void
    {
        $report = $this->report();
        $this->assertSame(1, $report['summary']['topology']['spread_junctions']);
        $this->assertSame(1, $report['summary']['topology']['spread_over_5m']);

        $junction = $report['lists']['junctions']['items'][0];
        $this->assertSame('spread', $junction['type']);
        $this->assertSame($this->n['N1'], $junction['id']);
        // 0,0001° de longitude à 48° N ≈ 7,45 m.
        $this->assertEqualsWithDelta(7.45, $junction['distance_m'], 0.1);
        $this->assertSame(2, $junction['ends']);
        $this->assertSame(2, $junction['lines']);
    }

    public function test_a_topology_edge_without_trace_between_distant_nodes_is_a_jump(): void
    {
        // N11 et N12 sont à 0,00067° de longitude l'un de l'autre à 48° N, environ 49,9 m.
        $this->n['N11'] = $this->node(2.10, 48.0, 'junction');
        $this->n['N12'] = $this->node(2.10067, 48.0, 'gap');
        $this->e['E8'] = $this->edge('N11', 'N12', 'L3', null);

        $report = app(GraphDiagnostics::class)->report($this->era->id, fresh: true);
        $this->assertNotNull($report);

        $edge = $this->firstWhere($report['lists']['edges']['items'], 'id', $this->e['E8']);
        $this->assertNotNull($edge);
        $this->assertSame('no_geometry', $edge['reason']);
        $this->assertSame(0, $edge['length_m']);
        $this->assertEqualsWithDelta(49.9, $edge['measured_m'], 0.3);

        $jump = $this->firstWhere($report['lists']['junctions']['items'], 'type', 'jump');
        $this->assertNotNull($jump, 'le saut figure dans les jonctions');
        $this->assertSame($this->e['E8'], $jump['id']);
        $this->assertEqualsWithDelta(49.9, $jump['distance_m'], 0.3);
        // Trié avec les autres jonctions par écart : 49,9 m passe avant les 7,45 m de N1.
        $this->assertSame('jump', $report['lists']['junctions']['items'][0]['type']);
        // E5 (sans tracé, nœuds au même point) n'est pas un saut.
        $this->assertSame(1, $report['summary']['topology']['jumps']);
        $this->assertEqualsWithDelta(49.9, $report['summary']['topology']['max_jump_m'], 0.3);
        // L'arête sans tracé relie quand même ses deux nœuds : une composante de plus, pas deux.
        $this->assertSame(5, $report['summary']['topology']['components']);

        $this->actingAs($this->member('admin'))->getJson("/validate/graph/1930/features/edge/{$this->e['E8']}")
            ->assertOk()
            ->assertJsonPath('measured_m', null)
            ->assertJsonPath('nodes_gap_m', fn (float $gap): bool => abs($gap - 49.9) < 0.3);
    }

    public function test_the_computation_gives_back_the_original_work_mem(): void
    {
        DB::statement("SET work_mem = '4MB'");
        app(GraphDiagnostics::class)->report($this->era->id, fresh: true);

        $this->assertSame('4MB', DB::scalar("SELECT current_setting('work_mem')"));
    }

    public function test_stations_without_edge_follow_station_dates_when_the_graph_was_built_from_them(): void
    {
        DB::table('network_graph_builds')->where('era_id', $this->era->id)->update(['stations_source' => 'stations']);
        Station::query()->update(['opened_at' => '1900-01-01', 'closed_at' => null]);
        // B ferme en 1925 : plus ouverte en 1930. E ouvre en 1935 : pas encore ouverte.
        Station::where('slug', 'b')->update(['closed_at' => '1925-06-01']);
        Station::factory()->create(['slug' => 'e', 'name' => 'Gare E', 'opened_at' => '1935-01-01', 'closed_at' => null,
            'geom' => Point::makeGeodetic(44.0, 1.0)]);
        Station::factory()->create(['slug' => 'f', 'name' => 'Gare F', 'opened_at' => '1928-01-01', 'closed_at' => '1931-03-01',
            'geom' => Point::makeGeodetic(44.0, 1.5)]);

        $without = app(GraphDiagnostics::class)->report($this->era->id, fresh: true)['lists']['stations_without_edge']['items'] ?? [];

        $this->assertSame(['f', 'c'], array_column($without, 'slug'));
        $this->assertSame(['unattached', 'no_neighbour'], array_column($without, 'reason'));
    }

    public function test_station_layer_finds_islands_and_stations_without_edge(): void
    {
        $report = $this->report();
        $s = $report['summary']['stations'];
        $this->assertSame(3, $s['stations']);
        $this->assertSame(1, $s['edges']);
        $this->assertSame(2, $s['components']);
        $this->assertSame(1, $s['islands']);
        $this->assertSame(2, $s['without_edge']);
        $this->assertSame(1, $s['unattached']);

        $without = $report['lists']['stations_without_edge']['items'];
        $this->assertSame(['b', 'c'], array_column($without, 'slug'));
        $this->assertSame(['unattached', 'no_neighbour'], array_column($without, 'reason'));
        $this->assertSame('c', $report['lists']['station_islands']['items'][0]['sample'][0]['slug']);
    }

    public function test_a_line_in_two_pieces_is_reported(): void
    {
        $report = $this->report();
        $this->assertSame(1, $report['summary']['lines']['fragmented']);
        $line = $report['lists']['fragmented_lines']['items'][0];
        $this->assertSame('L2', $line['line']['code']);
        $this->assertSame(2, $line['pieces']);
    }

    public function test_the_report_is_cached_until_the_graph_is_rebuilt(): void
    {
        $this->assertSame(1, $this->report()['summary']['topology']['isolated_nodes']);

        DB::table('network_nodes')->where('id', $this->n['N7'])->delete();
        $this->assertSame(1, $this->report()['summary']['topology']['isolated_nodes'], 'rapport gardé en cache');

        $this->build('2026-11-20 09:00:00');
        $this->assertSame(0, $this->report()['summary']['topology']['isolated_nodes'], 'nouvelle construction, nouveau calcul');
    }

    public function test_lists_are_served_by_pages(): void
    {
        $admin = $this->member('admin');
        $this->actingAs($admin)->getJson('/validate/graph/1930/diagnostics/islands?page=1')
            ->assertOk()
            ->assertJsonPath('check', 'islands')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('page', 1)
            ->assertJsonPath('last_page', 1)
            ->assertJsonCount(3, 'items');

        $page = GraphDiagnostics::page($this->report(), 'islands', 2, 2);
        $this->assertSame(2, $page['page']);
        $this->assertCount(1, $page['items']);
        $this->assertSame(1, GraphDiagnostics::page($this->report(), 'islands', 99, 25)['page']);

        $this->actingAs($admin)->getJson('/validate/graph/1930/diagnostics/inconnu')->assertNotFound();
    }

    // ---- Filtre des jonctions et export CSV ---------------------------------------------------

    /** Ajoute un saut sur L3 (49,9 m) et un raccord de lacune entre N8 (L1) et N9 (L2), puis recalcule. */
    private function withJumpAndConnector(): void
    {
        $this->n['N11'] = $this->node(2.10, 48.0, 'junction');
        $this->n['N12'] = $this->node(2.10067, 48.0, 'gap');
        $this->e['E8'] = $this->edge('N11', 'N12', 'L3', null);
        $this->e['C1'] = (int) DB::scalar(
            "INSERT INTO network_edges (era_id, from_node_id, to_node_id, line_id, length_m, status, is_connector, geom, created_at, updated_at)
             VALUES (?, ?, ?, NULL, 120, 'in_service', true, ST_GeomFromText('LINESTRING(2.02 48.0, 2.05 48.05)', 4326), now(), now()) RETURNING id",
            [$this->era->id, $this->n['N8'], $this->n['N9']],
        );
        $this->assertNotNull(app(GraphDiagnostics::class)->report($this->era->id, fresh: true));
    }

    public function test_each_junction_names_the_lines_it_concerns(): void
    {
        $this->withJumpAndConnector();
        $items = $this->report()['lists']['junctions']['items'];
        $ids = fn (string ...$codes): array => array_map(fn (string $c): int => $this->lines[$c]->id, $codes);

        $spread = $this->firstWhere($items, 'type', 'spread');
        $this->assertNotNull($spread);
        $this->assertEqualsCanonicalizing($ids('L1', 'L2'), $spread['line_ids']);
        // Le raccord n'a pas de ligne : ce sont celles des voies qui arrivent à ses deux bouts.
        $connector = $this->firstWhere($items, 'type', 'connector');
        $this->assertNotNull($connector);
        $this->assertEqualsCanonicalizing($ids('L1', 'L2'), $connector['line_ids']);
        $this->assertSame(2, $connector['lines']);
        $jump = $this->firstWhere($items, 'type', 'jump');
        $this->assertNotNull($jump);
        $this->assertSame($ids('L3'), $jump['line_ids']);
    }

    public function test_junctions_are_filtered_by_type_and_by_line(): void
    {
        $this->withJumpAndConnector();
        $admin = $this->member('admin');
        $url = '/validate/graph/1930/diagnostics/junctions';

        $this->actingAs($admin)->getJson("{$url}?type=jump")
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('filtered', 1)
            ->assertJsonPath('items.0.type', 'jump')
            ->assertJsonPath('items.0.line_refs.0.code', 'L3')
            ->assertJsonPath('filters.type', 'jump');

        $l2 = $this->lines['L2'];
        $response = $this->actingAs($admin)->getJson("{$url}?line={$l2->slug}")
            ->assertOk()
            ->assertJsonPath('filtered', 2)
            ->assertJsonPath('filters.line.code', 'L2')
            ->assertJsonPath('filters.unknown_line', null);
        $this->assertEqualsCanonicalizing(['spread', 'connector'], array_column($response->json('items'), 'type'));

        $this->actingAs($admin)->getJson("{$url}?type=spread&line={$l2->slug}")
            ->assertJsonPath('filtered', 1)
            ->assertJsonPath('items.0.id', $this->n['N1']);
        $this->actingAs($admin)->getJson("{$url}?type=jump&line={$l2->slug}")
            ->assertJsonPath('filtered', 0)
            ->assertJsonCount(0, 'items');

        // Ligne inconnue : rien de listé, et la réponse le dit ; type inconnu : 422.
        $this->actingAs($admin)->getJson("{$url}?line=999999")
            ->assertOk()
            ->assertJsonPath('filtered', 0)
            ->assertJsonPath('filters.unknown_line', '999999');
        $this->actingAs($admin)->getJson("{$url}?type=autre")->assertUnprocessable();
        // Les autres listes ne se filtrent pas.
        $this->actingAs($admin)->getJson('/validate/graph/1930/diagnostics/islands?line=999999')
            ->assertJsonPath('filtered', $this->report()['lists']['islands']['total']);
    }

    public function test_a_list_is_exported_in_csv_with_french_headers_and_the_era_in_the_file_name(): void
    {
        $this->withJumpAndConnector();
        $admin = $this->member('admin');

        $response = $this->actingAs($admin)->get('/validate/graph/1930/diagnostics/junctions.csv')->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('graphe-1930-jonctions.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}", $body);
        $rows = array_map(str_getcsv(...), array_filter(explode("\n", substr($body, 3))));
        $this->assertSame(GraphDiagnosticsCsv::headers('junctions'), $rows[0]);
        $this->assertSame('Type', $rows[0][0]);
        $this->assertCount(4, $rows, 'en-têtes + écartée, raccord, saut');
        // Le plus grand écart d'abord (raccord de 120 m), libellés en français, lignes nommées.
        $this->assertSame('Raccord de lacune', $rows[1][0]);
        $this->assertSame('120', $rows[1][2]);
        $this->assertStringContainsString('Ligne L1 (L1)', $rows[1][7]);
        $this->assertStringContainsString('Ligne L2 (L2)', $rows[1][7]);

        $l3 = $this->lines['L3'];
        $filtered = $this->actingAs($admin)->get("/validate/graph/1930/diagnostics/junctions.csv?type=jump&line={$l3->slug}")->assertOk();
        $this->assertStringContainsString("graphe-1930-jonctions-sauts-{$l3->slug}.csv", (string) $filtered->headers->get('Content-Disposition'));
        $this->assertCount(2, array_filter(explode("\n", $filtered->streamedContent())));

        foreach (GraphDiagnostics::CHECKS as $check) {
            $csv = $this->actingAs($admin)->get("/validate/graph/1930/diagnostics/{$check}.csv")->assertOk()->streamedContent();
            $this->assertSame(GraphDiagnosticsCsv::headers($check), str_getcsv(strtok(substr($csv, 3), "\n")), $check);
        }
        $this->actingAs($admin)->get('/validate/graph/1930/diagnostics/junctions.csv?line=999999')->assertNotFound();
        $this->actingAs($admin)->get('/validate/graph/1940/diagnostics/junctions.csv')->assertNotFound();
    }

    public function test_the_export_writes_the_whole_list_beyond_2000_items(): void
    {
        $items = [];
        for ($i = 1; $i <= 2500; $i++) {
            $items[] = ['type' => 'jump', 'id' => $i, 'distance_m' => 1.5, 'kind' => 'gap', 'station_id' => null, 'ends' => 2, 'lines' => 0, 'line_ids' => [], 'lng' => -1.25, 'lat' => 48.0];
        }
        $report = ['lists' => ['junctions' => ['total' => 2500, 'items' => $items]]];
        $handle = fopen('php://memory', 'w+b');
        $this->assertIsResource($handle);
        app(GraphDiagnosticsCsv::class)->write($handle, $report, 'junctions', new DiagnosticsFilter('junctions'));
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertCount(2501, $lines);
        $this->assertSame('"Saut sans tracé",2500,1.5,"Bout d’un raccord",,2,0,,-1.25,48', $lines[2500]);
        // Toute la liste est aussi servie par pages, sans plafond.
        $this->assertSame(100, GraphDiagnostics::page($report, 'junctions', 100)['last_page']);
    }

    public function test_a_csv_cell_never_starts_a_formula(): void
    {
        $this->assertSame("'=SOMME(A1)", GraphDiagnosticsCsv::cell('=SOMME(A1)'));
        $this->assertSame("'+33", GraphDiagnosticsCsv::cell('+33'));
        $this->assertSame("'@ligne", GraphDiagnosticsCsv::cell('@ligne'));
        $this->assertSame('-1.67', GraphDiagnosticsCsv::cell(-1.67));
        $this->assertSame('0', GraphDiagnosticsCsv::cell(0.0));
        $this->assertSame('', GraphDiagnosticsCsv::cell(null));
        $this->assertSame('Gare A', GraphDiagnosticsCsv::cell('Gare A'));
    }

    // ---- Carte et attributs --------------------------------------------------------------------

    public function test_tiles_are_protobuf_and_private(): void
    {
        $admin = $this->member('admin');
        // Tuile du zoom 8 qui contient Paris et le petit réseau (2,0° E, 48,0° N).
        $response = $this->actingAs($admin)->get('/validate/graph/1930/tiles/topology/8/129/88.mvt')->assertOk();
        $this->assertSame('application/x-protobuf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertNotSame('', $response->getContent());
        $this->assertStringContainsString('edges', (string) $response->getContent());

        foreach (['stations', 'lines'] as $layer) {
            $this->actingAs($admin)->get("/validate/graph/1930/tiles/{$layer}/8/129/88.mvt")->assertOk();
        }
        $this->actingAs($admin)->get('/validate/graph/1930/tiles/topology/20/1/1.mvt')->assertNotFound();
    }

    public function test_tiles_below_zoom_8_are_refused(): void
    {
        $admin = $this->member('admin');
        foreach (['0/0/0', '5/16/11', '7/64/44'] as $zxy) {
            $this->actingAs($admin)->get("/validate/graph/1930/tiles/topology/{$zxy}.mvt")->assertNotFound();
        }
        $this->assertSame(8, GraphTiles::MIN_ZOOM);
    }

    public function test_tiles_beyond_zoom_12_are_computed_without_server_cache(): void
    {
        $admin = $this->member('admin');
        // Tuile du zoom 13 qui contient N1 (2,0° E, 48,0° N).
        $first = $this->actingAs($admin)->get('/validate/graph/1930/tiles/topology/13/4141/2847.mvt')->assertOk()->getContent();
        $this->assertStringContainsString('edges', (string) $first);

        // Le graphe change sans nouvelle date de construction : seule une tuile recalculée le voit.
        DB::table('network_edges')->where('era_id', $this->era->id)->delete();
        $second = $this->actingAs($admin)->get('/validate/graph/1930/tiles/topology/13/4141/2847.mvt')->assertOk()->getContent();
        $this->assertStringNotContainsString('edges', (string) $second);

        // Au zoom 8, la tuile reste en cache.
        $this->actingAs($admin)->get('/validate/graph/1930/tiles/topology/8/129/88.mvt')->assertOk();
        $key = fn (int $z, int $x, int $y): string => sprintf('graph-tiles:%d:%d:20261120080000:topology:%d:%d:%d', GraphTiles::FORMAT, $this->era->id, $z, $x, $y);
        $this->assertTrue(Cache::has($key(8, 129, 88)));
        $this->assertFalse(Cache::has($key(13, 4141, 2847)));
    }

    public function test_an_element_shows_its_attributes(): void
    {
        $admin = $this->member('admin');
        $this->actingAs($admin)->getJson("/validate/graph/1930/features/edge/{$this->e['E1']}")
            ->assertOk()
            ->assertJsonPath('kind', 'edge')
            ->assertJsonPath('line.code', 'L1')
            ->assertJsonPath('from_node_id', $this->n['N1']);

        $this->actingAs($admin)->getJson("/validate/graph/1930/features/node/{$this->n['N1']}")
            ->assertOk()
            ->assertJsonPath('degree', 2)
            ->assertJsonPath('node_kind', 'junction');

        $this->actingAs($admin)->getJson('/validate/graph/1930/features/station/'.Station::where('slug', 'a')->value('id'))
            ->assertOk()
            ->assertJsonPath('neighbours.0.slug', 'd');

        $this->actingAs($admin)->getJson('/validate/graph/1930/features/edge/999999999')->assertNotFound();
    }

    public function test_an_identifier_too_large_for_an_integer_answers_404(): void
    {
        $this->actingAs($this->member('admin'))->getJson('/validate/graph/1930/features/edge/99999999999999999999')->assertNotFound();
    }

    public function test_an_element_of_another_era_is_not_found(): void
    {
        $era1970 = Era::factory()->create(['year' => 1970]);
        $node = (int) DB::scalar(
            "INSERT INTO network_nodes (era_id, kind, geom, created_at, updated_at)
             VALUES (?, 'end', ST_SetSRID(ST_MakePoint(2.5, 48.5), 4326), now(), now()) RETURNING id",
            [$era1970->id],
        );
        $edge = (int) DB::scalar(
            "INSERT INTO network_edges (era_id, from_node_id, to_node_id, length_m, status, created_at, updated_at)
             VALUES (?, ?, ?, 0, 'in_service', now(), now()) RETURNING id",
            [$era1970->id, $node, $node],
        );
        DB::table('network_graph_builds')->insert([
            'era_id' => $era1970->id, 'built_at' => '2026-11-20 08:00:00', 'duration_ms' => 1, 'stations_source' => 'station_eras',
            'segments' => 1, 'nodes' => 1, 'edges' => 1, 'station_nodes' => 0, 'closed_station_nodes' => 0, 'station_edges' => 0,
            'heritage_station_edges' => 0, 'gap_joins' => 0, 'open_stations' => 0, 'unattached_stations' => 0,
            'isolated_stations' => 0, 'components' => 1, 'largest_component' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = $this->member('admin');

        $this->actingAs($admin)->getJson("/validate/graph/1970/features/edge/{$edge}")->assertOk();
        $this->actingAs($admin)->getJson("/validate/graph/1930/features/edge/{$edge}")->assertNotFound();
        $this->actingAs($admin)->getJson("/validate/graph/1930/features/node/{$node}")->assertNotFound();
        $this->actingAs($admin)->getJson("/validate/graph/1970/features/edge/{$this->e['E1']}")->assertNotFound();
    }

    public function test_a_station_absent_from_the_era_has_no_sheet(): void
    {
        // Gare sans statut à l'ère 1930 ni nœud dans son graphe.
        $absent = Station::factory()->create(['slug' => 'z', 'name' => 'Gare Z', 'geom' => Point::makeGeodetic(45.5, 4.5)]);

        $this->actingAs($this->member('admin'))->getJson("/validate/graph/1930/features/station/{$absent->id}")->assertNotFound();
    }
}
