<?php

namespace Tests\Feature\Validate;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\NetworkGraph\GraphDiagnostics;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertInertia(fn (Assert $page) => $page->where('layer', 'topology')->where('year', 1930));
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
        $this->assertSame(1, $t['zero_length_edges']);

        $lists = $this->report()['lists'];
        $this->assertSame([$this->n['N7']], array_column($lists['isolated_nodes']['items'], 'id'));
        // E5 (longueur nulle) d'abord ; puis l'arête gare à gare A - D, écrite ici sans tracé.
        $this->assertSame([['topology', $this->e['E5'], 'zero_length'], ['stations', null, 'no_geometry']], array_map(
            fn (array $i): array => [$i['layer'], $i['layer'] === 'topology' ? $i['id'] : null, $i['reason']],
            $lists['edges']['items'],
        ));
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
}
