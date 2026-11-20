<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\Schematic\Mercator;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Vue ferroviaire : GET /api/v1/eras/{year}/schematic.geojson (SchematicMap), sur un petit réseau
 * dont le graphe est construit par vff:build-network-graph, près de Bourges (loin des loupes) :
 *
 *                  C
 *                  |  ligne 2 (archeoferroviaire : tracé retenu par l'API)
 *   A ── S ────── J ────── B
 *        ligne 1 (OpenStreetMap)
 */
class SchematicMapApiTest extends TestCase
{
    use RefreshDatabase;

    /** Sommet intérieur du tracé retenu : il ne doit jamais sortir. */
    private const WITHHELD_VERTEX = [2.4123, 47.1871];

    private Era $era;

    private Line $main;

    private Line $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);
        $this->main = Line::factory()->create(['slug' => 'rfn-996000', 'code' => '996000', 'name' => 'Ligne de A à B',
            'is_lgv' => false, 'network' => 'principal', 'type' => 'passenger']);
        $this->branch = Line::factory()->create(['slug' => 'rfn-995000', 'code' => '995000', 'name' => 'Ligne de J à C',
            'is_lgv' => false, 'network' => 'principal', 'type' => 'passenger']);

        $this->segment($this->main, 'osm', [[2.20, 47.08], [2.30, 47.08], [2.40, 47.08]], 0);
        $this->segment($this->main, 'osm', [[2.40, 47.08], [2.50, 47.08], [2.60, 47.08]], 15_000);
        $this->segment($this->branch, 'archeoferroviaire', [[2.40, 47.08], self::WITHHELD_VERTEX, [2.40, 47.30]], 0);

        $stations = [];
        foreach (['a' => [2.20, 47.08], 's' => [2.30, 47.08], 'j' => [2.40, 47.08], 'b' => [2.60, 47.08], 'c' => [2.40, 47.30]] as $slug => [$lng, $lat]) {
            $stations[$slug] = Station::factory()->create(['slug' => $slug, 'name' => 'Gare '.strtoupper($slug),
                'city' => 'Ville '.strtoupper($slug), 'department_code' => '18', 'type' => 'gare',
                'geom' => Point::makeGeodetic($lat, $lng), 'status' => 'active']);
            DB::table('station_eras')->insert(['station_id' => $stations[$slug]->id, 'era_id' => $this->era->id,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([[$this->main, ['a', 's', 'j', 'b']], [$this->branch, ['j', 'c']]] as [$line, $slugs]) {
            foreach ($slugs as $i => $slug) {
                DB::table('line_stops')->insert(['line_id' => $line->id, 'era_id' => $this->era->id,
                    'station_id' => $stations[$slug]->id, 'position' => $i, 'pk_m' => $i * 7_500,
                    'effective_status' => 'active', 'is_terminal' => $i === 0 || $i === count($slugs) - 1,
                    'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $this->artisan('vff:build-network-graph', ['--era' => '1930'])->assertSuccessful();
    }

    /** @param  list<array{0: float, 1: float}>  $lonLat */
    private function segment(Line $line, string $source, array $lonLat, int $pkStart): void
    {
        LineSegment::factory()->create([
            'line_id' => $line->id, 'era_id' => $this->era->id, 'source' => $source,
            'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false, 'gauge_mm' => 1435,
            'pk_start_m' => $pkStart, 'pk_end_m' => $pkStart + 15_000, 'length_meters' => 15_000,
            'geom' => LineString::make(array_map(fn (array $c): Point => Point::makeGeodetic($c[1], $c[0]), $lonLat)),
        ]);
    }

    /** @return array<string, mixed> */
    private function plan(): array
    {
        return $this->getJson('/api/v1/eras/1930/schematic.geojson')->assertOk()->json();
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, array<string, mixed>> gares par slug
     */
    private static function stations(array $plan): array
    {
        $out = [];
        foreach ($plan['features'] as $f) {
            if ($f['properties']['kind'] === 'station') {
                $out[$f['properties']['slug']] = $f['properties'] + ['coordinates' => $f['geometry']['coordinates']];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<array<string, mixed>>
     */
    private static function axes(array $plan): array
    {
        return array_values(array_filter($plan['features'], fn (array $f): bool => $f['properties']['kind'] === 'axis'));
    }

    public function test_the_plan_of_an_era_has_axes_stations_and_the_lines_they_belong_to(): void
    {
        $plan = $this->plan();

        $this->assertSame('FeatureCollection', $plan['type']);
        $this->assertSame(1930, $plan['era']);
        $this->assertSame(6000, $plan['grid_m']);
        $this->assertNotEmpty($plan['graph_built_at']);
        $this->assertEqualsCanonicalizing(['rfn-996000', 'rfn-995000'], array_column($plan['lines'], 'slug'));
        foreach (self::axes($plan) as $axis) {
            $this->assertArrayHasKey((string) $axis['properties']['line_id'], $plan['lines']);
            $this->assertSame('mixed', $axis['properties']['category']);
            $this->assertSame('LineString', $axis['geometry']['type']);
        }
        $stations = self::stations($plan);
        $this->assertEqualsCanonicalizing(['a', 's', 'j', 'b', 'c'], array_keys($stations));
        $this->assertSame('stop', $stations['s']['role']);
        $this->assertSame('hub', $stations['j']['role']);
        $this->assertSame('end', $stations['a']['role']);
        $this->assertSame('Ville A', $stations['a']['city']);
        $this->assertSame(5, $plan['summary']['stations']);
        $this->assertSame(2, $plan['summary']['lines']);
        $this->assertGreaterThan(50, $plan['summary']['km']['mixed']);
    }

    public function test_each_station_gets_the_sides_where_its_name_covers_the_fewest_axes(): void
    {
        $stations = self::stations($this->plan());

        foreach ($stations as $slug => $station) {
            $this->assertCount(4, $station['label_anchors'], $slug);
            $this->assertSame([], array_diff($station['label_anchors'], ['left', 'right', 'top', 'bottom', 'top-left', 'top-right', 'bottom-left', 'bottom-right']));
        }
        // Gare intermédiaire d'un axe ouest-est : son nom va dessous ou dessus, pas sur l'axe.
        $this->assertContains($stations['s']['label_anchors'][0], ['top', 'bottom']);
        // Toutes les gares sont à moins de 80 km sur le plan : une seule ville au niveau 0, nommée
        // d'après sa commune ; les gares sans ville nommée gardent leur nom.
        $topCities = array_filter($stations, fn (array $s): bool => $s['tier'] === 0);
        $this->assertCount(1, $topCities);
        $top = reset($topCities);
        $this->assertSame($top['city'], $top['city_label']);
        foreach ($stations as $station) {
            if ($station['tier'] >= 2) {
                $this->assertNull($station['city_label']);
            }
        }
    }

    public function test_a_city_is_named_on_the_node_of_its_principal_station(): void
    {
        // Deux gares de la commune « Ville J » : celle qui porte le nom de la ville seul la représente.
        Station::where('slug', 'j')->update(['name' => 'Ville J']);
        Station::where('slug', 'c')->update(['name' => 'Ville J-Nord', 'city' => 'Ville J']);

        $stations = self::stations($this->plan());

        $this->assertSame('Ville J', $stations['j']['city_label']);
        $this->assertSame(0, $stations['j']['tier']);
        $this->assertNull($stations['c']['city_label']);
        $this->assertGreaterThanOrEqual(2, $stations['c']['tier']);
    }

    public function test_axes_are_horizontal_vertical_or_at_45_degrees_in_web_mercator(): void
    {
        foreach (self::axes($this->plan()) as $axis) {
            $points = array_map(fn (array $c): array => Mercator::project($c[0], $c[1]), $axis['geometry']['coordinates']);
            for ($k = 1; $k < count($points); $k++) {
                $dx = abs($points[$k][0] - $points[$k - 1][0]);
                $dy = abs($points[$k][1] - $points[$k - 1][1]);
                // Coordonnées arrondies à 5 décimales : 1,1 m au plus.
                $this->assertTrue($dx < 3 || $dy < 3 || abs($dx - $dy) < 3, "segment non octilinéaire : {$dx} × {$dy}");
            }
        }
    }

    public function test_the_plan_never_copies_a_track_and_ignores_the_rights_switch(): void
    {
        $body = (string) $this->get('/api/v1/eras/1930/schematic.geojson')->getContent();
        $plan = json_decode($body, true);

        $this->assertStringNotContainsString((string) self::WITHHELD_VERTEX[0], $body);
        $this->assertStringNotContainsString((string) self::WITHHELD_VERTEX[1], $body);
        // La ligne au tracé retenu est sur le plan, redessinée : ses nœuds sont des points de la grille.
        $branch = array_values(array_filter(self::axes($plan), fn (array $a): bool => $plan['lines'][(string) $a['properties']['line_id']]['slug'] === 'rfn-995000'));
        $this->assertNotEmpty($branch);
        foreach ($branch as $axis) {
            foreach ([$axis['geometry']['coordinates'][0], end($axis['geometry']['coordinates'])] as [$lng, $lat]) {
                [$x, $y] = Mercator::project($lng, $lat);
                $this->assertEqualsWithDelta(round($x / 6000), $x / 6000, 0.001);
                $this->assertEqualsWithDelta(round($y / 6000), $y / 6000, 0.001);
            }
        }

        config(['api.withhold_unlicensed_geometry' => false]);
        $this->assertSame($body, (string) $this->get('/api/v1/eras/1930/schematic.geojson')->getContent());
    }

    public function test_the_plan_is_kept_until_the_graph_of_the_era_is_rebuilt(): void
    {
        $first = $this->plan();
        DB::table('network_edges')->where('era_id', $this->era->id)->delete();

        $this->assertSame($first, $this->plan(), 'plan servi depuis le cache');

        DB::table('network_graph_builds')->where('era_id', $this->era->id)->update(['built_at' => now()->addMinute()]);
        $this->assertSame([], self::axes($this->plan()), 'nouvelle construction du graphe : plan recalculé');
    }

    public function test_an_unknown_era_or_an_era_without_graph_answers_404(): void
    {
        Era::factory()->create(['year' => 1940, 'label' => 'Années 1940']);

        $this->getJson('/api/v1/eras/1945/schematic.geojson')->assertNotFound();
        $this->getJson('/api/v1/eras/1940/schematic.geojson')->assertNotFound();
    }

    public function test_the_view_can_be_switched_off_by_configuration(): void
    {
        $this->get('/maps')->assertInertia(fn (Assert $page) => $page->component('Maps')->where('schematic', true));

        config(['maps.schematic' => false]);

        $this->getJson('/api/v1/eras/1930/schematic.geojson')->assertNotFound();
        $this->get('/maps')->assertInertia(fn (Assert $page) => $page->component('Maps')->where('schematic', false));
    }

    public function test_the_command_prepares_the_plans_and_writes_one_to_a_file(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'plan-');
        try {
            $this->artisan('vff:build-schematic-maps', ['--era' => '1930', '--output' => $file])->assertSuccessful();
            $written = json_decode((string) file_get_contents($file), true);
            $this->assertSame(1930, $written['era']);

            // Plan déjà en cache : la requête ne relit pas le graphe.
            $graphReads = 0;
            DB::listen(function (QueryExecuted $query) use (&$graphReads): void {
                $graphReads += (int) str_contains($query->sql, 'network_edges');
            });
            $this->assertSame($written['features'], $this->plan()['features']);
            $this->assertSame(0, $graphReads);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }

        Era::factory()->create(['year' => 1940, 'label' => 'Années 1940']);
        $this->artisan('vff:build-schematic-maps', ['--era' => '1855'])->assertExitCode(2);
        $this->artisan('vff:build-schematic-maps', ['--era' => '1930,1940', '--output' => 'plan.json'])->assertExitCode(2);
    }
}
