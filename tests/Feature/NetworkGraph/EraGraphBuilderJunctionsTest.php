<?php

namespace Tests\Feature\NetworkGraph;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\NetworkGraph\EraGraphBuilder;
use App\Services\NetworkGraph\GraphDiagnostics;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jonctions du graphe (EraGraphBuilder) : les arêtes se rejoignent exactement sur leur nœud, et les
 * arêtes de longueur nulle sans tracé disparaissent (nœuds confondus fusionnés). Mini-réseau, une ère :
 *
 *   A ────────── ╮ 6,7 m          ligne AB : deux tracés dont les bouts ne se touchent pas
 *                B                (regroupés par le constructeur au barycentre)
 *
 *   C ───────┬────── C'           ligne CD : branchée à 8,9 m au nord de l'intérieur de CC'
 *            D                    (jonction en T)
 *
 *   G1 ────── 100 m ────── G2      ligne G : deux tronçons séparés par une lacune (raccords et
 *                                  coupures confondues aux bouts, autrefois arêtes de longueur nulle)
 */
class EraGraphBuilderJunctionsTest extends TestCase
{
    use RefreshDatabase;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);

        $this->line('Ligne de A à A2', [[[48.0, 2.0], [48.0, 2.1]]]);
        $this->line('Ligne de B à B2', [[[48.00005, 2.10005], [48.1, 2.10005]]]);
        $this->line('Ligne de C à C2', [[[48.0, 2.2], [48.0, 2.4]]]);
        $this->line('Ligne de D à D2', [[[48.00008, 2.3], [48.1, 2.3]]]);
        $this->line('Ligne de G1 à G2', [[[48.0, 2.7], [48.0, 2.8]], [[48.0, 2.8014], [48.0, 2.9]]]);

        $this->station('a', 48.0, 2.0);
        $this->station('b', 48.1, 2.10005);
        $this->station('d', 48.1, 2.3);
        $this->station('c', 48.0, 2.2);
        $this->station('g1', 48.0, 2.7);
        $this->station('g2', 48.0, 2.9);
    }

    /** @param  list<list<array{0: float, 1: float}>>  $parts  tronçons [lat, lng] de la ligne */
    private function line(string $name, array $parts): void
    {
        $line = Line::factory()->create(['name' => $name, 'is_lgv' => false, 'network' => 'principal']);
        foreach ($parts as $coords) {
            LineSegment::factory()->create([
                'line_id' => $line->id,
                'era_id' => $this->era->id,
                'geom' => LineString::make(array_map(fn ($c) => Point::makeGeodetic($c[0], $c[1]), $coords)),
                'status' => 'in_service',
                'traffic_type' => 'mixed',
                'is_heritage' => false,
                'gauge_mm' => 1435,
                'source' => 'osm',
            ]);
        }
    }

    private function station(string $slug, float $lat, float $lng): void
    {
        $station = Station::factory()->create([
            'slug' => $slug, 'name' => 'Gare '.strtoupper($slug), 'geom' => Point::makeGeodetic($lat, $lng), 'status' => 'active',
        ]);
        DB::table('station_eras')->insert([
            'station_id' => $station->id, 'era_id' => $this->era->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function build(): array
    {
        return (new EraGraphBuilder)->build($this->era->id, 1930);
    }

    /** Plus grand écart (m) entre les extrémités d'arêtes qui arrivent au même nœud. */
    private function maxSpread(): float
    {
        return (float) DB::scalar(<<<'SQL'
            WITH ends AS (
                SELECT from_node_id AS node_id, ST_StartPoint(geom) AS p FROM network_edges WHERE era_id = ? AND geom IS NOT NULL
                UNION ALL
                SELECT to_node_id, ST_EndPoint(geom) FROM network_edges WHERE era_id = ? AND geom IS NOT NULL
            )
            SELECT COALESCE(max(d), 0) FROM (
                SELECT ST_MaxDistance(ST_Transform(ST_Collect(p), 2154), ST_Transform(ST_Collect(p), 2154)) AS d
                FROM ends GROUP BY node_id HAVING count(*) > 1
            ) x
            SQL, [$this->era->id, $this->era->id]);
    }

    public function test_edge_ends_meet_exactly_on_their_node(): void
    {
        $this->build();

        $this->assertLessThan(GraphDiagnostics::JUNCTION_TOLERANCE_M, $this->maxSpread());
        // Chaque extrémité d'arête est au point de son nœud (gares exceptées : le nœud est la gare).
        $off = DB::scalar(<<<'SQL'
            SELECT max(greatest(
                CASE WHEN a.station_id IS NULL THEN ST_Distance(ST_Transform(ST_StartPoint(e.geom), 2154), ST_Transform(a.geom, 2154)) ELSE 0 END,
                CASE WHEN b.station_id IS NULL THEN ST_Distance(ST_Transform(ST_EndPoint(e.geom), 2154), ST_Transform(b.geom, 2154)) ELSE 0 END))
            FROM network_edges e JOIN network_nodes a ON a.id = e.from_node_id JOIN network_nodes b ON b.id = e.to_node_id
            WHERE e.era_id = ? AND e.geom IS NOT NULL
            SQL, [$this->era->id]);
        $this->assertLessThan(0.01, (float) $off);

        // La longueur reste celle de la voie d'origine (le recollage ne sert qu'au dessin).
        $line = DB::table('lines')->where('name', 'Ligne de A à A2')->value('id');
        $this->assertEqualsWithDelta(
            (float) DB::scalar('SELECT sum(ST_Length(geom::geography)) FROM line_segments WHERE line_id = ?', [$line]),
            (float) DB::table('network_edges')->where('era_id', $this->era->id)->where('line_id', $line)->sum('length_m'),
            1.0,
        );
    }

    public function test_a_branch_is_joined_on_the_through_line_which_stays_straight(): void
    {
        $this->build();

        $through = DB::table('lines')->where('name', 'Ligne de C à C2')->value('id');
        $branch = DB::table('lines')->where('name', 'Ligne de D à D2')->value('id');
        $original = 'ST_Transform(ST_SetSRID(ST_MakeLine(ST_MakePoint(2.2, 48.0), ST_MakePoint(2.4, 48.0)), 4326), 2154)';

        // La ligne traversante n'a pas bougé : tous ses points restent sur le tracé d'origine.
        $deviation = DB::scalar("SELECT max(ST_Distance(ST_Transform((dp).geom, 2154), {$original}))
            FROM (SELECT ST_DumpPoints(geom) AS dp FROM network_edges WHERE era_id = ? AND line_id = ?) x", [$this->era->id, $through]);
        $this->assertLessThan(0.01, (float) $deviation);

        // La branche commence sur la ligne traversante (8,9 m plus au sud que son tracé), à son nœud de jonction.
        $start = DB::selectOne("SELECT ST_Distance(ST_Transform(ST_StartPoint(e.geom), 2154), {$original}) AS on_line, n.kind,
                (SELECT count(*) FROM network_edges x WHERE x.era_id = e.era_id AND ? IN (x.from_node_id, x.to_node_id)) AS degree
            FROM network_edges e JOIN network_nodes n ON n.id = e.from_node_id
            WHERE e.era_id = ? AND e.line_id = ? ORDER BY ST_Y(ST_StartPoint(e.geom)) LIMIT 1", [0, $this->era->id, $branch]);
        $this->assertLessThan(0.01, (float) $start->on_line);
        $this->assertSame('junction', $start->kind);
    }

    public function test_zero_length_edges_without_geometry_are_merged(): void
    {
        $stats = $this->build();

        $this->assertSame(0, DB::table('network_edges')->where('era_id', $this->era->id)->whereNull('geom')->count());
        $this->assertSame(0, DB::table('network_edges')->where('era_id', $this->era->id)->where('length_m', '<=', 0)->count());
        $this->assertGreaterThanOrEqual(1, $stats['merged_zero_length']);
        // Aucun nœud sans arête, aucune boucle.
        $this->assertSame(0, (int) DB::scalar('SELECT count(*) FROM network_nodes n WHERE n.era_id = ? AND NOT EXISTS (
            SELECT 1 FROM network_edges e WHERE e.era_id = n.era_id AND n.id IN (e.from_node_id, e.to_node_id))', [$this->era->id]));
        $this->assertSame(0, DB::table('network_edges')->where('era_id', $this->era->id)->whereColumn('from_node_id', 'to_node_id')->count());
    }

    public function test_station_links_and_lengths_are_kept(): void
    {
        $this->build();
        $edges = DB::table('station_edges as e')
            ->join('stations as f', 'f.id', '=', 'e.from_station_id')
            ->join('stations as t', 't.id', '=', 'e.to_station_id')
            ->where('e.era_id', $this->era->id)
            ->get(['f.slug as from', 't.slug as to', 'e.length_m', 'e.gap_joins', DB::raw('ST_NumGeometries(e.geom) AS parts')])
            ->keyBy(fn ($e) => implode('-', [min($e->from, $e->to), max($e->from, $e->to)]));

        // A - B : 7,45 km + 11,1 km, d'un seul tenant (les deux tracés se rejoignent).
        $this->assertEqualsWithDelta(18_570, $edges['a-b']->length_m, 30);
        $this->assertSame(1, (int) $edges['a-b']->parts);
        // G1 - G2 : la lacune de 104 m reste franchie par un raccord compté.
        $this->assertEqualsWithDelta(14_900, $edges['g1-g2']->length_m, 30);
        $this->assertSame(1, (int) $edges['g1-g2']->gap_joins);
        $this->assertTrue($edges->has('c-d'));
    }

    public function test_rebuilding_gives_the_same_graph(): void
    {
        $first = $this->build();
        $second = $this->build();

        foreach (['nodes', 'edges', 'station_edges', 'gap_joins', 'merged_zero_length'] as $key) {
            $this->assertSame($first[$key], $second[$key], $key);
        }
    }
}
