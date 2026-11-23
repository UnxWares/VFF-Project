<?php

namespace Tests\Feature;

use App\Models\Accident;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Station;
use App\Services\OnThisDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Ce jour-là » : faits datés au jour d'un jour du calendrier (OnThisDay), page /on-this-day, API REST
 * `GET /api/v1/on-this-day` et outil MCP `on_this_day`.
 */
class OnThisDayTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->line = Line::factory()->create(['slug' => 'rfn-000001', 'name' => 'Ligne de Morlaix à Roscoff', 'departements' => json_encode(['Finistère'])]);
    }

    /** @param  array<string, mixed>  $attrs */
    private function event(array $attrs): LineEvent
    {
        return LineEvent::factory()->create($attrs + [
            'line_id' => $this->line->id, 'description' => 'Fait daté.', 'confidence' => 'high', 'date_precision' => 'day',
            'source_url' => null, 'section_from' => null, 'section_to' => null,
        ]);
    }

    /** @param  array<string, mixed>  $attrs */
    private function stationEvent(Station $station, array $attrs): void
    {
        DB::table('station_events')->insert($attrs + [
            'station_id' => $station->id, 'type' => 'opening', 'date_precision' => 'day', 'source' => 'wikidata',
            'source_url' => 'https://www.wikidata.org/wiki/Q1', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return list<string> identifiants des faits d'un jour */
    private function ids(string $day, array $types = []): array
    {
        [$m, $d] = OnThisDay::parse($day);

        return array_column(app(OnThisDay::class)->day($m, $d, 'fr', $types)['facts'], 'id');
    }

    public function test_parse_accepts_real_calendar_days_including_the_29th_of_february(): void
    {
        $this->assertSame([2, 29], OnThisDay::parse('02-29'));
        $this->assertSame([11, 23], OnThisDay::parse('11-23'));
        $this->assertNull(OnThisDay::parse('02-30'));
        $this->assertNull(OnThisDay::parse('13-01'));
        $this->assertNull(OnThisDay::parse('1-1'));
        $this->assertCount(366, OnThisDay::allDays());
    }

    public function test_only_facts_known_to_the_day_with_a_publishable_source_are_served(): void
    {
        $day = $this->event(['type' => 'opening', 'event_date' => '1883-06-10', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/X']);
        // Date au mois ou à l'année : jamais présentée comme un jour (le 1er juin ou le 1er janvier).
        $this->event(['type' => 'closure_passenger', 'event_date' => '1930-06-10', 'date_precision' => 'month', 'source' => 'wikipedia']);
        $this->event(['type' => 'closure_freight', 'event_date' => '1931-06-10', 'date_precision' => 'year', 'source' => 'wikipedia']);
        // Source à usage non commercial : jamais servie.
        $this->event(['type' => 'electrification', 'event_date' => '1950-06-10', 'source' => 'archeoferroviaire']);
        $this->artisan('vff:compute-network-chronology')->assertSuccessful();

        $facts = app(OnThisDay::class)->day(6, 10, 'fr')['facts'];

        $this->assertCount(1, $facts);
        $this->assertSame('e'.$day->id, $facts[0]['id']);
        $this->assertSame('1883-06-10', $facts[0]['date']);
        $this->assertSame('10 juin 1883', $facts[0]['when']);
        $this->assertSame('opening', $facts[0]['kind']);
        $this->assertSame('Ligne de Morlaix à Roscoff', $facts[0]['title']);
        $this->assertSame('/lines/rfn-000001', $facts[0]['path']);
        $this->assertSame('https://fr.wikipedia.org/wiki/X', $facts[0]['sources'][0]['url']);
        $this->assertSame([], $this->ids('06-01'));
    }

    public function test_sources_that_disagree_on_the_day_keep_the_fact_out_of_every_day(): void
    {
        $this->event(['type' => 'opening', 'event_date' => '1890-03-04', 'source' => 'wikipedia']);
        $this->event(['type' => 'opening', 'event_date' => '1890-03-05', 'source' => 'wikidata']);
        $this->artisan('vff:compute-network-chronology')->assertSuccessful();

        $this->assertSame([], $this->ids('03-04'));
        $this->assertSame([], $this->ids('03-05'));
    }

    public function test_station_dates_must_be_the_ones_retained_by_the_sheet_and_never_inferred(): void
    {
        $kept = Station::factory()->create(['name' => 'Gare gardée', 'city' => 'Morlaix', 'opened_at' => '1865-04-01', 'opened_precision' => 'day']);
        $this->stationEvent($kept, ['event_date' => '1865-04-01']);
        // Ouverture que la fiche contredit : seule la date retenue est servie.
        $this->stationEvent($kept, ['event_date' => '1865-04-15', 'source' => 'osm', 'source_url' => 'https://www.openstreetmap.org/node/1']);
        // Date déduite d'une ligne : pas un fait de la gare.
        $inferred = Station::factory()->create(['opened_at' => '1870-05-02', 'opened_precision' => 'day']);
        $this->stationEvent($inferred, ['event_date' => '1870-05-02', 'source' => 'inferred:line_event', 'source_url' => null, 'is_inferred' => true]);
        // Fiche à l'année : un jour qu'une source donne ne remplace pas la date de la fiche.
        $yearOnly = Station::factory()->create(['opened_at' => '1880-01-01', 'opened_precision' => 'year']);
        $this->stationEvent($yearOnly, ['event_date' => '1880-07-07']);
        // Fermeture aux voyageurs : une seule date au jour, donc servie ; deux dates différentes : écartée.
        $closed = Station::factory()->create(['name' => 'Gare fermée', 'opened_at' => '1860-01-01', 'opened_precision' => 'year']);
        $this->stationEvent($closed, ['type' => 'closure_passenger', 'event_date' => '1938-10-02', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/G']);
        $twice = Station::factory()->create(['opened_at' => '1860-01-01', 'opened_precision' => 'year']);
        $this->stationEvent($twice, ['type' => 'closure_passenger', 'event_date' => '1938-10-02', 'source' => 'wikipedia']);
        $this->stationEvent($twice, ['type' => 'closure_passenger', 'event_date' => '1939-10-02', 'source' => 'wikidata']);
        $this->artisan('vff:compute-network-chronology')->assertSuccessful();

        $this->assertCount(1, $this->ids('04-01'));
        $this->assertSame([], $this->ids('04-15'));
        $this->assertSame([], $this->ids('05-02'));
        $this->assertSame([], $this->ids('07-07'));
        $fact = app(OnThisDay::class)->day(10, 2, 'fr')['facts'];
        $this->assertCount(1, $fact);
        $this->assertSame('station', $fact[0]['subject']);
        $this->assertSame('closure', $fact[0]['kind']);
        $this->assertSame('passenger', $fact[0]['traffic']);
        $this->assertSame('Gare fermée', $fact[0]['title']);
    }

    public function test_accidents_need_a_reviewed_sheet_a_day_date_and_a_publishable_source(): void
    {
        $ok = Accident::factory()->create(['slug' => '1911-11-23-x', 'occurred_on' => '1911-11-23', 'deaths' => 14, 'commune_name' => 'Montreuil-Bellay']);
        $toReview = Accident::factory()->toReview()->create(['occurred_on' => '1920-11-23']);
        $monthly = Accident::factory()->create(['occurred_on' => '1930-11-23', 'date_precision' => 'month']);
        // Sans aucune source : jamais servi.
        Accident::factory()->create(['occurred_on' => '1940-11-23']);
        foreach ([$ok, $toReview, $monthly] as $a) {
            DB::table('accident_sources')->insert(['accident_id' => $a->id, 'provider' => 'wikidata', 'role' => 'article', 'title' => 'W', 'licence' => 'CC0', 'url' => 'https://www.wikidata.org/wiki/Q1', 'retrieved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->artisan('vff:compute-network-chronology')->assertSuccessful();

        $facts = app(OnThisDay::class)->day(11, 23, 'fr')['facts'];

        $this->assertSame(['a'.$ok->id], array_column($facts, 'id'));
        $this->assertSame(14, $facts[0]['deaths']);
        $this->assertSame('Montreuil-Bellay', $facts[0]['detail']);
        $this->assertSame('/accidents/1911-11-23-x', $facts[0]['path']);
        $this->assertSame([], $this->ids('11-23', ['opening']));
    }

    public function test_facts_are_sorted_by_year_and_the_coverage_counts_the_days(): void
    {
        $this->event(['type' => 'closure_passenger', 'event_date' => '1938-10-02', 'source' => 'wikipedia']);
        $this->event(['type' => 'opening', 'event_date' => '1883-10-02', 'source' => 'wikipedia']);
        $this->event(['type' => 'electrification', 'event_date' => '1950-10-02', 'source' => 'wikipedia']);
        $this->artisan('vff:compute-network-chronology')->assertSuccessful();

        $service = app(OnThisDay::class);
        $this->assertSame([1883, 1938, 1950], array_column($service->day(10, 2, 'fr')['facts'], 'year'));
        $coverage = $service->coverage();
        $this->assertSame(366, $coverage['days']);
        $this->assertGreaterThanOrEqual(1, $coverage['days_with_facts']);
        $this->assertSame(366 - $coverage['days_with_facts'], $coverage['days_without_facts']);
        $this->assertContains('02-29', $coverage['empty_days']);
        // Les jalons nationaux du 1er janvier (SNCF, SNCF Réseau) sont les faits suivants du calendrier.
        $this->assertSame(['previous' => '10-02', 'next' => '01-01'], $service->neighbours('10-03'));
        // Un fait d'aujourd'hui ou du futur n'est jamais annoncé.
        $this->assertSame(0, $coverage['by_day'][now()->addDay()->format('m-d')] ?? 0);
    }

    public function test_the_page_shows_a_day_redirects_the_form_and_refuses_unknown_days(): void
    {
        $this->event(['type' => 'opening', 'event_date' => '1883-06-10', 'source' => 'wikipedia']);
        $this->artisan('vff:compute-network-chronology')->assertSuccessful();

        $this->get('/on-this-day/06-10')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Network/OnThisDay')
                ->where('day.key', '06-10')->where('day.previous', '06-09')->where('day.next', '06-11')
                ->where('facts.0.year', 1883)->where('facts.0.title', 'Ligne de Morlaix à Roscoff')
                ->where('accidents.shown', true)->etc());
        $this->get('/on-this-day')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('day.key', now()->format('m-d'))->where('day.is_today', true)->etc());
        $this->get('/on-this-day/06-10?accidents=0')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('accidents.shown', false)->etc());
        $this->get('/on-this-day?month=6&day=10')->assertRedirect('/on-this-day/06-10');
        $this->get('/on-this-day/02-29')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('day.next', '03-01')->where('facts', [])->etc());
        $this->get('/on-this-day/13-45')->assertNotFound();
        $this->get('/on-this-day/02-30')->assertNotFound();
    }

    public function test_the_api_serves_a_day_in_a_language_and_rejects_bad_input(): void
    {
        $this->event(['type' => 'opening', 'event_date' => '1883-06-10', 'source' => 'wikipedia']);
        $this->event(['type' => 'closure_passenger', 'event_date' => '1938-06-10', 'source' => 'wikipedia']);
        $this->artisan('vff:compute-network-chronology')->assertSuccessful();

        $this->getJson('/api/v1/on-this-day/06-10')->assertOk()
            ->assertJsonPath('data.day', '06-10')->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.facts.0.when', '10 juin 1883')->assertJsonPath('data.facts.1.traffic', 'passenger')
            ->assertJsonPath('meta.coverage.days', 366)
            ->assertJsonPath('links.previous', url('/api/v1/on-this-day/06-09'));
        $this->getJson('/api/v1/on-this-day/06-10?lang=en&types=closure')->assertOk()
            ->assertJsonPath('data.count', 1)->assertJsonPath('data.facts.0.when', '10 June 1938');
        $this->getJson('/api/v1/on-this-day')->assertOk()->assertJsonPath('data.day', now()->format('m-d'));
        $this->getJson('/api/v1/on-this-day/13-45')->assertNotFound();
        $this->getJson('/api/v1/on-this-day/06-10?types=nope')->assertUnprocessable();
        $this->getJson('/api/v1/on-this-day/06-10?lang=es')->assertUnprocessable();
    }

    public function test_today_is_never_cached_past_midnight(): void
    {
        $this->travelTo('2026-11-23 23:50:00');

        $this->assertStringContainsString('max-age=600', (string) $this->getJson('/api/v1/on-this-day')->assertOk()->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', (string) $this->getJson('/api/v1/on-this-day/11-23')->assertOk()->headers->get('Cache-Control'));
    }

    public function test_the_mcp_tool_answers_with_the_same_facts_and_validates_the_day(): void
    {
        $this->event(['type' => 'opening', 'event_date' => '1883-06-10', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/X']);
        $this->artisan('vff:compute-network-chronology')->assertSuccessful();
        $call = function (array $arguments): array {
            $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
                ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'on_this_day', 'arguments' => (object) $arguments]])
                ->assertOk()->json('result');

            return [json_decode($result['content'][0]['text'], true) ?? [], (bool) $result['isError']];
        };

        [$data, $error] = $call(['month' => 6, 'day' => 10, 'lang' => 'en']);
        $this->assertFalse($error);
        $this->assertSame(1, $data['count']);
        $this->assertSame('10 June 1883', $data['facts'][0]['when']);
        $this->assertSame('https://vff-project.org/on-this-day/06-10', $data['vff_url']);
        $this->assertSame(366, $data['coverage']['days']);
        [, $error] = $call(['month' => 2, 'day' => 30]);
        $this->assertTrue($error);
        [, $error] = $call(['month' => 2]);
        $this->assertTrue($error);
        [$today, $error] = $call([]);
        $this->assertFalse($error);
        $this->assertSame(now()->format('m-d'), $today['day']);
    }
}
