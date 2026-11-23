<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\Lines\LineSchematic;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Schéma de ligne : GET /api/v1/lines/{line}/schematic (App\Services\Lines\LineSchematic), sur une petite ligne
 * le long du parallèle 47° N :
 *
 *                            X (ligne 2, bifurcation en pleine voie)
 *                           /
 *   A ──────── B ──────── J ──────── C ──────── D
 *   PK 0       7+600      ≈ 11+400   15+200     22+800
 *                                     \
 *                                      Y (ligne 3, correspondance à la gare C)
 *
 * D est fermée aujourd'hui, ouverte en 1930 ; B est placée par projection (PK approché).
 */
class LineSchematicApiTest extends TestCase
{
    use RefreshDatabase;

    private Line $main;

    private Era $today;

    private Era $past;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->past = Era::factory()->create(['year' => 1930]);
        $this->today = Era::factory()->current()->create(['year' => 2030]);
        $this->main = Line::factory()->create(['slug' => 'rfn-990000', 'code' => '990000', 'name' => 'Ligne de A à D']);
        $branch = Line::factory()->create(['slug' => 'rfn-991000', 'code' => '991000', 'name' => 'Ligne de J à X']);
        $third = Line::factory()->create(['slug' => 'rfn-992000', 'code' => '992000', 'name' => 'Ligne de C à Y']);

        $stations = [];
        foreach ([
            'a' => [2.00, 0, 'sncf_pk', 'gare', ['active', 'active']],
            'b' => [2.10, 7600, 'segment', 'gare', ['active', 'active']],
            'c' => [2.20, 15200, 'sncf_pk', 'halte', ['active', 'active']],
            'd' => [2.30, 22800, 'source_pk', 'gare', ['active', 'declassified']],
        ] as $slug => [$lng, $pk, $method, $type, $statuses]) {
            $stations[$slug] = Station::factory()->create(['slug' => "gare-{$slug}", 'name' => 'Gare '.strtoupper($slug),
                'type' => $type, 'geom' => Point::makeGeodetic(47.0, $lng)]);
            DB::table('station_lines')->insert(['station_id' => $stations[$slug]->id, 'line_id' => $this->main->id, 'method' => $method,
                'pk_m' => $pk, 'first_year' => 1900, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([$this->past, $this->today] as $i => $era) {
                DB::table('station_eras')->insert(['station_id' => $stations[$slug]->id, 'era_id' => $era->id,
                    'status' => $statuses[$i], 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        foreach ([$this->past, $this->today] as $era) {
            $node = fn (float $lng, float $lat, string $kind, ?Station $s = null): int => (int) DB::table('network_nodes')->insertGetId([
                'era_id' => $era->id, 'station_id' => $s?->id, 'kind' => $kind,
                'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $n = [
                'a' => $node(2.00, 47.0, 'station', $stations['a']), 'b' => $node(2.10, 47.0, 'station', $stations['b']),
                'j' => $node(2.15, 47.0, 'junction'), 'c' => $node(2.20, 47.0, 'station', $stations['c']),
                'd' => $node(2.30, 47.0, 'station', $stations['d']), 'x' => $node(2.15, 47.1, 'end'), 'y' => $node(2.20, 46.9, 'end'),
            ];
            foreach ([['a', 'b', $this->main], ['b', 'j', $this->main], ['j', 'c', $this->main], ['c', 'd', $this->main],
                ['j', 'x', $branch], ['c', 'y', $third]] as [$from, $to, $line]) {
                DB::table('network_edges')->insert(['era_id' => $era->id, 'from_node_id' => $n[$from], 'to_node_id' => $n[$to],
                    'line_id' => $line->id, 'length_m' => 1000, 'status' => 'in_service', 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        DB::table('sncf_line_geometries')->insert(['code_ligne' => '990000', 'libelle' => 'Ligne de A à D', 'pk_debut_m' => 0,
            'pk_fin_m' => 22800, 'rg_troncon' => 1, 'geom' => DB::raw('ST_GeomFromText(\'LINESTRING(2.0 47.0, 2.3 47.0)\', 4326)')]);
        DB::table('line_track_portions')->insert(['line_id' => $this->main->id, 'code_ligne' => '990000', 'rg_troncon' => 1,
            'pk_start_m' => 0, 'pk_end_m' => 22800, 'regime' => 'Double voie', 'tracks' => 2, 'source' => 'sncf']);
        foreach ([[0, 10000, 160], [10000, 22800, 100]] as [$from, $to, $v]) {
            DB::table('line_speed_ranges')->insert(['line_id' => $this->main->id, 'era_id' => $this->today->id,
                'pk_start_m' => $from, 'pk_end_m' => $to, 'max_speed_kmh' => $v]);
        }
        DB::table('line_signalling')->insert(['line_id' => $this->main->id, 'era_id' => $this->today->id, 'pk_start_m' => 0,
            'pk_end_m' => 22800, 'signalling' => 'BAL', 'signalling_systems' => '["BAL"]', 'train_protection' => '["KVB"]', 'kvb_equipped' => true]);
        DB::table('sncf_line_electrification')->insert(['code_ligne' => '990000', 'pkd_m' => 0, 'pkf_m' => 22800, 'elect' => 'Électrifiée',
            'voltage_v' => 25000, 'frequency_hz' => 50, 'third_rail' => false]);
        DB::table('sncf_level_crossings')->insert(['id_if' => 'PN1', 'libelle' => 'PN 1', 'mnemo' => 'CLASSE 17', 'code_ligne' => '990000',
            'rg_troncon' => 1, 'pk_m' => 5000, 'lat' => 47.0, 'lon' => 2.066, 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.066, 47.0), 4326)')]);
    }

    public function test_today_the_line_is_laid_out_by_pk_with_its_stations_junctions_and_bands(): void
    {
        $json = $this->getJson('/api/v1/lines/rfn-990000/schematic')->assertOk()
            ->assertHeader('Cache-Control', 'max-age=600, public')->json();

        $this->assertSame(['slug' => 'rfn-990000', 'code' => '990000', 'name' => 'Ligne de A à D'], $json['line']);
        $this->assertSame(2030, $json['era_year']);
        $this->assertTrue($json['is_current_era']);
        $this->assertSame([['year' => 1930, 'is_current' => false, 'stations' => 4, 'open' => 4],
            ['year' => 2030, 'is_current' => true, 'stations' => 4, 'open' => 3]], $json['eras']);
        $this->assertSame(['pk_start_m' => 0, 'pk_end_m' => 22800, 'pk_start_label' => '0+000', 'pk_end_label' => '22+800'], $json['extent']);

        $stations = array_column($json['stations'], null, 'slug');
        $this->assertSame(['gare-a', 'gare-b', 'gare-c', 'gare-d'], array_keys($stations));
        $this->assertSame(['type' => 'halt', 'pk_approx' => false, 'status' => 'active', 'open' => true],
            array_intersect_key($stations['gare-c'], array_flip(['type', 'status', 'open', 'pk_approx'])));
        $this->assertTrue($stations['gare-b']['pk_approx'], 'PK projeté sur le tracé : approché');
        $this->assertSame(['status' => 'declassified', 'open' => false], array_intersect_key($stations['gare-d'], array_flip(['status', 'open'])));
        $this->assertSame([['slug' => 'rfn-992000', 'code' => '992000', 'name' => 'Ligne de C à Y']], $stations['gare-c']['connections']);
        $this->assertSame([], $stations['gare-a']['connections']);
        $this->assertArrayNotHasKey('lng', $stations['gare-a'], 'pas de coordonnées dans le schéma');

        $this->assertCount(1, $json['junctions']);
        $junction = $json['junctions'][0];
        $this->assertEqualsWithDelta(11400, $junction['pk_m'], 50, 'PK lu sur la forme SNCF');
        $this->assertTrue($junction['pk_approx']);
        $this->assertSame([['slug' => 'rfn-991000', 'code' => '991000', 'name' => 'Ligne de J à X']], $junction['lines']);

        $bands = $json['bands'];
        $this->assertSame([['pk_start_m' => 0, 'pk_end_m' => 22800, 'tracks' => 2, 'regime' => 'Double voie', 'banalised' => false]], $bands['tracks']['ranges']);
        $this->assertSame('today', $bands['tracks']['as_of']);
        $this->assertSame([['pk_start_m' => 0, 'pk_end_m' => 22800, 'voltage_v' => 25000, 'system' => 'ac']], $bands['electrification']['ranges']);
        $this->assertSame([['pk_start_m' => 0, 'pk_end_m' => 22800, 'signalling' => 'BAL', 'family' => 'automatic_block',
            'systems' => ['BAL'], 'train_protection' => ['KVB']]], $bands['signalling']['ranges']);
        $this->assertSame([160, 100], array_column($bands['speed']['ranges'], 'max_speed_kmh'));
        $this->assertSame([['pk_m' => 5000, 'pk_label' => '5+000', 'name' => 'PN 1', 'group' => 'automatic']], $json['level_crossings']['items']);
        $this->assertTrue($json['level_crossings']['available']);
        $this->assertStringContainsString('ODbL', $json['provenance']['speed']);
    }

    public function test_a_past_era_keeps_stations_and_junctions_but_no_band_of_todays_datasets(): void
    {
        $json = $this->getJson('/api/v1/lines/rfn-990000/schematic?era=1930')->assertOk()->json();

        $this->assertSame(1930, $json['era_year']);
        $this->assertFalse($json['is_current_era']);
        $this->assertSame([true, true, true, true], array_column($json['stations'], 'open'), 'D était ouverte en 1930');
        $this->assertCount(1, $json['junctions']);
        foreach (['tracks', 'electrification', 'signalling', 'speed'] as $band) {
            $this->assertFalse($json['bands'][$band]['available'], $band);
            $this->assertSame([], $json['bands'][$band]['ranges'], $band);
        }
        $this->assertFalse($json['level_crossings']['available']);
        $this->assertSame([], $json['level_crossings']['items']);
    }

    public function test_an_unknown_line_or_era_answers_404_and_a_bad_era_422(): void
    {
        $this->getJson('/api/v1/lines/inconnue/schematic')->assertNotFound();
        $this->getJson('/api/v1/lines/rfn-990000/schematic?era=1835')->assertNotFound();
        $this->getJson('/api/v1/lines/rfn-990000/schematic?era=abc')->assertUnprocessable();
        // Un entier qui ne tient pas dans la colonne `year` : refusé (422), pas une erreur de la base (500).
        $this->getJson('/api/v1/lines/rfn-990000/schematic?era=99999999999')->assertUnprocessable();
    }

    public function test_the_schematic_is_served_from_the_cache_on_the_second_call(): void
    {
        $first = $this->getJson('/api/v1/lines/rfn-990000/schematic')->assertOk()->json();

        $graphReads = 0;
        DB::listen(function (QueryExecuted $query) use (&$graphReads): void {
            $graphReads += (int) str_contains($query->sql, 'network_edges');
        });
        $this->assertSame($first, $this->getJson('/api/v1/lines/rfn-990000/schematic')->assertOk()->json());
        $this->assertSame(0, $graphReads);
    }

    public function test_a_point_away_from_the_sncf_shape_is_placed_between_the_two_stations_it_separates(): void
    {
        $stations = [
            ['pk_m' => 0, 'lng' => 2.00, 'lat' => 47.0],
            ['pk_m' => 10000, 'lng' => 2.10, 'lat' => 47.0],
            ['pk_m' => 20000, 'lng' => 2.20, 'lat' => 47.0],
        ];

        $this->assertEqualsWithDelta(15000, LineSchematic::interpolate($stations, 2.15, 47.0), 10);
        $this->assertEqualsWithDelta(2500, LineSchematic::interpolate($stations, 2.025, 47.0), 10);
        $this->assertNull(LineSchematic::interpolate($stations, 2.15, 47.5), 'à 55 km de la ligne : pas placé');
        $this->assertNull(LineSchematic::interpolate([$stations[0]], 2.0, 47.0));
    }
}
