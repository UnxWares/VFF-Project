<?php

namespace Tests\Feature\Search;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Position d'un PK sur une ligne : la recherche (« 570000 PK 120 »), la page /search et
 * `GET /api/v1/lines/{line}/at-pk` donnent les gares qui l'encadrent, la commune, le point de carte ou les bornes.
 */
class PkSearchTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $era = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd’hui']);
        $this->line = Line::factory()->create(['name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean', 'slug' => 'rfn-570000', 'code' => '570000', 'alt_names' => null]);
        Line::factory()->create(['name' => 'Ligne de Lyon à Marseille', 'slug' => 'rfn-830000', 'code' => '830000', 'alt_names' => null]);
        $stops = [['Gare Alpha', 'Alphaville', 0, 2.0], ['Gare Beta', 'Betaville', 118_000, 2.1], ['Gare Gamma', 'Gammaville', 128_000, 2.2]];
        foreach ($stops as $i => [$name, $city, $pk, $lng]) {
            $station = Station::factory()->create(['name' => $name, 'city' => $city, 'provenance' => [], 'geom' => Point::makeGeodetic(48.0, $lng)]);
            DB::table('line_stops')->insert([
                'line_id' => $this->line->id, 'era_id' => $era->id, 'station_id' => $station->id, 'position' => $i, 'pk_m' => $pk,
                'effective_status' => 'active', 'is_terminal' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_search_returns_the_position_between_two_stations(): void
    {
        $data = $this->getJson('/api/v1/search?q='.urlencode('570000 PK 120,5'))->assertOk()->json('pk_positions');

        $this->assertCount(1, $data);
        $p = $data[0];
        $this->assertSame('rfn-570000', $p['line']['slug']);
        $this->assertSame(120_500, $p['pk_m']);
        $this->assertSame('120+500', $p['pk_label']);
        $this->assertTrue($p['in_range']);
        $this->assertNull($p['at_station']);
        $this->assertSame('Gare Beta', $p['between']['from']['name']);
        $this->assertSame('Gare Gamma', $p['between']['to']['name']);
        $this->assertSame('Betaville', $p['commune']);
        $this->assertSame('stations', $p['position_method']);
        // 2,5 km sur 10 km entre Beta (2,1) et Gamma (2,2).
        $this->assertEqualsWithDelta(2.125, $p['lng'], 0.0001);
        $this->assertEqualsWithDelta(48.0, $p['lat'], 0.0001);
    }

    public function test_search_reads_the_line_by_name_and_other_notations(): void
    {
        foreach (['ligne 570000 120+500', 'Paris Bordeaux km 120,5', '570000 pk 120.5'] as $q) {
            $data = $this->getJson('/api/v1/search?q='.urlencode($q))->json('pk_positions');
            $this->assertCount(1, $data, $q);
            $this->assertSame(120_500, $data[0]['pk_m'], $q);
        }
    }

    public function test_a_pk_on_a_station_names_it(): void
    {
        $p = $this->getJson('/api/v1/search?q='.urlencode('570000 pk 118'))->json('pk_positions.0');

        $this->assertSame('Gare Beta', $p['at_station']['name']);
        $this->assertNull($p['between']);
        $this->assertSame('station', $p['position_method']);
    }

    public function test_a_pk_outside_the_line_gives_the_bounds(): void
    {
        $p = $this->getJson('/api/v1/search?q='.urlencode('570000 pk 700'))->json('pk_positions.0');

        $this->assertFalse($p['in_range']);
        $this->assertSame(['from_m' => 0, 'to_m' => 128_000, 'from_label' => '0+000', 'to_label' => '128+000'], $p['bounds']);
        $this->assertNull($p['lat']);
    }

    public function test_a_plain_query_has_no_position(): void
    {
        $this->assertSame([], $this->getJson('/api/v1/search?q=bordeaux')->json('pk_positions'));
        $this->assertSame([], $this->getJson('/api/v1/search?q='.urlencode('pk 120'))->json('pk_positions'));
    }

    public function test_at_pk_endpoint(): void
    {
        $this->getJson('/api/v1/lines/rfn-570000/at-pk?pk=120%2B500')->assertOk()
            ->assertJsonPath('pk_m', 120_500)
            ->assertJsonPath('between.from.slug', fn ($v) => is_string($v))
            ->assertJsonPath('bounds.to_label', '128+000');
        $this->getJson('/api/v1/lines/rfn-570000/at-pk?pk_m=120500')->assertOk()->assertJsonPath('pk_label', '120+500');
        $this->getJson('/api/v1/lines/rfn-570000/at-pk?pk=900')->assertNotFound()
            ->assertJsonPath('error', 'pk_out_of_range')->assertJsonPath('in_range', false);
        $this->getJson('/api/v1/lines/rfn-570000/at-pk')->assertStatus(422);
        $this->getJson('/api/v1/lines/rfn-570000/at-pk?pk=abc')->assertStatus(422);
        $this->getJson('/api/v1/lines/rfn-830000/at-pk?pk=1')->assertNotFound()->assertJsonPath('error', 'no_stops');
    }

    public function test_search_page_carries_the_position(): void
    {
        $this->get('/search?q='.urlencode('570000 PK 120'))->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Search/Index')
                ->where('positions.0.pk_m', 120_000)
                ->where('positions.0.between.from.name', 'Gare Beta'),
        );
        $this->get('/search?q=bordeaux')->assertInertia(fn (Assert $page) => $page->where('positions', []));
    }
}
