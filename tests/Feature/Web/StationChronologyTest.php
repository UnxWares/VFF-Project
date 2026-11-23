<?php

namespace Tests\Feature\Web;

use App\Models\Accident;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Chronologie de la fiche gare : la même liste sur la page (/stations/{slug}), dans l'API REST
 * (`chronology` de GET /api/v1/stations/{station}) et en GraphQL (`StationSheet.chronology`), avec les
 * vues aériennes de l'IGN (`StationSheet.aerial_views`, `links.aerial`).
 */
class StationChronologyTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->origin->update(['opened_at' => '1837-08-26', 'opened_precision' => 'day', 'provenance' => ['opened_at' => 'wikidata']]);
        DB::table('station_events')->insert([
            ['station_id' => $this->origin->id, 'type' => 'opening', 'event_date' => '1837-08-26', 'date_precision' => 'day',
                'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q1', 'is_inferred' => false, 'note' => null,
                'created_at' => now(), 'updated_at' => now()],
            ['station_id' => $this->origin->id, 'type' => 'opening', 'event_date' => '1837-01-01', 'date_precision' => 'year',
                'source' => 'ohm', 'source_url' => null, 'is_inferred' => false, 'note' => null,
                'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('station_lines')->insert([
            'station_id' => $this->origin->id, 'line_id' => $this->line->id, 'method' => 'line_stops', 'pk_m' => 0,
            'pk_source' => 'sncf', 'first_year' => 1837, 'last_year' => null, 'years_source' => 'line_events',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Un accident à la gare, un autre trop loin (8 km), une fiche doublon (alias) jamais montrée.
        $near = Accident::factory()->create(['slug' => '1891-07-12-paris', 'occurred_on' => '1891-07-12', 'date_precision' => 'day',
            'nearest_station_id' => $this->origin->id, 'nearest_station_distance_m' => 40, 'location_precision' => 'station',
            'geom' => Point::makeGeodetic(48.8763, 2.3253)]);
        Accident::factory()->create(['slug' => '1900-01-01-loin', 'occurred_on' => '1900-01-01',
            'nearest_station_id' => $this->origin->id, 'nearest_station_distance_m' => 8000]);
        Accident::factory()->create(['slug' => '1891-07-12-paris-2', 'occurred_on' => '1891-07-12',
            'nearest_station_id' => $this->origin->id, 'nearest_station_distance_m' => 40, 'alias_of_id' => $near->id]);
    }

    public function test_the_page_shows_one_dated_list_with_sources_lines_and_nearby_accidents(): void
    {
        $this->get('/stations/paris-saint-lazare')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stations/Show')
                ->has('chronology', 2)
                ->where('chronology.0.kind', 'event')
                ->where('chronology.0.type', 'opening')
                ->where('chronology.0.date', '1837-08-26')
                ->where('chronology.0.precision', 'day')
                ->where('chronology.0.source', 'wikidata')
                ->where('chronology.0.retained', true)
                ->where('chronology.0.also.0.source', 'ohm')
                ->where('chronology.0.also.0.precision', 'year')
                ->where('chronology.0.lines.0.slug', $this->line->slug)
                ->where('chronology.0.lines.0.role', 'start')
                ->where('chronology.1.kind', 'accident')
                ->where('chronology.1.accident.slug', '1891-07-12-paris')
                ->where('chronology.1.accident.distance_m', 40)
                ->has('aerial_views', 3));
    }

    public function test_rest_and_graphql_serve_the_same_chronology_and_the_aerial_views(): void
    {
        $rest = $this->getJson('/api/v1/stations/paris-saint-lazare')->assertOk();
        $rest->assertJsonPath('data.chronology.0.type', 'opening')
            ->assertJsonPath('data.chronology.0.lines.0.links.web', url('/lines/'.$this->line->slug))
            ->assertJsonPath('data.chronology.1.source_url', url('/accidents/1891-07-12-paris'))
            ->assertJsonPath('data.chronology.1.accident.links.api', route('api.accidents.show', '1891-07-12-paris'))
            ->assertJsonPath('data.chronology.1.links.event', null)
            ->assertJsonPath('data.links.aerial', route('api.stations.aerial', 'paris-saint-lazare'))
            ->assertJsonCount(2, 'data.chronology');

        $graphql = $this->postJson('/graphql', ['query' => '{ station(slug: "paris-saint-lazare") { sheet {
            chronology { kind type date precision after estimated retained source source_url also lines event_id accident name links }
            aerial_views { period from to url license attribution }
        } } }'])->assertOk();
        $graphql->assertJsonMissingPath('errors');
        $sheet = $graphql->json('data.station.sheet');
        $this->assertSame(array_column($rest->json('data.chronology'), 'date'), array_column($sheet['chronology'], 'date'));
        $this->assertSame('ohm', $sheet['chronology'][0]['also'][0]['source']);
        $this->assertSame(['1950-1965', '1965-1980', '1980-1995'], array_column($sheet['aerial_views'], 'period'));
        $this->assertSame('Licence Ouverte 2.0', $sheet['aerial_views'][0]['license']);
    }
}
