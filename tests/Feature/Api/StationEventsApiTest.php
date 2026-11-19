<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * GET /api/v1/stations/{station}/events : chronologie d'une gare avec la source de chaque événement.
 */
class StationEventsApiTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();

        $this->event($this->origin->id, 'opening', '1837-08-26', 'day', 'wikidata', false, 'Q1#P1619', 'https://www.wikidata.org/wiki/Q1');
        $this->event($this->origin->id, 'closure_passenger', '1970-01-01', 'year', 'inferred:line_event', true, null, null, 'Déduit de la fermeture de la ligne.');
        $this->event($this->origin->id, 'demolition', '1985-03-01', 'month', 'wikipedia', false);
        // Autre gare : ne doit jamais apparaître.
        $this->event($this->destination->id, 'opening', '1839-08-02', 'day', 'wikidata', false);
    }

    private function event(int $stationId, string $type, string $date, string $precision, string $source, bool $inferred, ?string $ref = null, ?string $url = null, ?string $note = null): void
    {
        DB::table('station_events')->insert([
            'station_id' => $stationId, 'type' => $type, 'event_date' => $date, 'date_precision' => $precision,
            'source' => $source, 'source_ref' => $ref, 'source_url' => $url, 'is_inferred' => $inferred, 'note' => $note,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_lists_the_events_of_one_station_with_their_provenance(): void
    {
        $this->getJson("/api/v1/stations/{$this->origin->slug}/events")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.type', 'opening')
            ->assertJsonPath('data.0.date', '1837-08-26')
            ->assertJsonPath('data.0.source', 'wikidata')
            ->assertJsonPath('data.0.source_ref', 'Q1#P1619')
            ->assertJsonPath('data.0.source_url', 'https://www.wikidata.org/wiki/Q1')
            ->assertJsonPath('data.0.is_inferred', false)
            ->assertJsonPath('data.0.provenance.provider', 'wikidata')
            ->assertJsonPath('data.0.provenance.license', 'CC0 1.0')
            ->assertJsonPath('data.1.is_inferred', true)
            ->assertJsonPath('data.1.note', 'Déduit de la fermeture de la ligne.')
            ->assertJsonPath('data.1.provenance.provider', 'vff')
            ->assertJsonPath('data.2.date_precision', 'month')
            ->assertJsonPath('links.next', null);
    }

    public function test_filters_by_type_source_period_and_inference(): void
    {
        $url = "/api/v1/stations/{$this->origin->slug}/events";

        $this->getJson("{$url}?type=demolition")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("{$url}?source=wikipedia")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("{$url}?inferred=true")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'closure_passenger');
        $this->getJson("{$url}?inferred=false")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("{$url}?inferred=0")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("{$url}?from=1900&to=1979")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("{$url}?order=desc")->assertOk()->assertJsonPath('data.0.type', 'demolition');
    }

    public function test_paginates_by_cursor(): void
    {
        $first = $this->getJson("/api/v1/stations/{$this->origin->slug}/events?per_page=2")->assertOk()->assertJsonCount(2, 'data');
        $this->assertNotNull($first->json('links.next'));

        $this->getJson($first->json('links.next'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'demolition')->assertJsonPath('links.next', null);
    }

    public function test_unknown_station_is_a_404_and_bad_filters_a_422(): void
    {
        $this->getJson('/api/v1/stations/inconnue/events')->assertNotFound();
        $url = "/api/v1/stations/{$this->origin->slug}/events";
        $this->getJson("{$url}?inferred=peut-etre")->assertUnprocessable()->assertJsonValidationErrors('inferred');
        $this->getJson("{$url}?from=2020-13-01")->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson("{$url}?per_page=500")->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_a_station_without_events_has_an_empty_page(): void
    {
        DB::table('station_events')->where('station_id', $this->destination->id)->delete();

        $this->getJson("/api/v1/stations/{$this->destination->slug}/events")->assertOk()->assertJsonCount(0, 'data');
    }
}
