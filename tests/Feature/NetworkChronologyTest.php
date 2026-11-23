<?php

namespace Tests\Feature;

use App\Models\Accident;
use App\Models\Line;
use App\Models\LineEvent;
use App\Services\NetworkChronology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Chronologie du rail en France » : faits rassemblés et dédoublonnés (NetworkChronology), commande de calcul,
 * page /chronology et `GET /api/v1/chronology`.
 */
class NetworkChronologyTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->line = Line::factory()->create(['slug' => 'rfn-000001', 'name' => 'Ligne de Morlaix à Roscoff', 'departements' => json_encode(['Finistère']), 'length_official_m' => 28000]);
    }

    /** @param  array<string, mixed>  $attrs */
    private function event(array $attrs): LineEvent
    {
        return LineEvent::factory()->create($attrs + [
            'line_id' => $this->line->id, 'description' => 'Fait daté.', 'confidence' => 'high', 'date_precision' => 'day',
            'source_url' => null, 'section_from' => null, 'section_to' => null,
        ]);
    }

    /** @return list<array<string, mixed>> faits détaillés d'une année */
    private function facts(array $result, int $year): array
    {
        return array_values(array_filter($result['decades'][intdiv($year, 10) * 10] ?? [], fn (array $f): bool => $f['year'] === $year));
    }

    public function test_one_fact_per_line_kind_section_and_year_with_all_its_publishable_sources(): void
    {
        $this->event(['type' => 'opening', 'event_date' => '1883-06-10', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/X']);
        $this->event(['type' => 'opening_passenger', 'event_date' => '1883-06-10', 'source' => 'wikidata']);
        // Une estimation n'est pas un fait ; une année tirée du nom d'un tracé non plus.
        $this->event(['type' => 'closure', 'event_date' => '1950-01-01', 'date_precision' => 'year', 'source' => 'estimation-reseau']);
        $this->event(['type' => 'closure', 'event_date' => '1918-01-01', 'date_precision' => 'year', 'source' => 'archeoferroviaire']);

        $result = app(NetworkChronology::class)->compute();

        $facts = $this->facts($result, 1883);
        $this->assertCount(1, $facts);
        $this->assertSame(['day', '1883-06-10'], [$facts[0]['precision'], $facts[0]['date']]);
        $this->assertSame(['wikipedia', 'wikidata'], array_column($facts[0]['sources'], 'provider'));
        // Seul fait de son type pour la ligne, ligne entière : elle porte la longueur de la ligne.
        $this->assertSame(28.0, $facts[0]['km']);
        $this->assertSame(['53'], $facts[0]['regions']);
        $this->assertArrayNotHasKey(1950, $result['meta']['counts']);
        $this->assertArrayNotHasKey(1918, $result['meta']['counts']);
    }

    public function test_an_announced_fact_dated_after_today_is_not_in_the_chronology(): void
    {
        $this->travelTo('2026-11-23');
        $this->event(['type' => 'reopening', 'event_date' => '2026-12-13', 'source' => 'wikipedia']);
        $this->event(['type' => 'reopening', 'event_date' => '2026-06-01', 'source' => 'wikipedia', 'section_from' => 'A', 'section_to' => 'B']);

        $counts = app(NetworkChronology::class)->compute()['meta']['counts'][2026];

        $this->assertSame(1, $counts['reopening']['all'][0]);
    }

    public function test_disagreeing_days_fall_back_to_the_month_then_to_the_year(): void
    {
        $this->assertSame(['1883-06-01', 'month'], NetworkChronology::agreedDate([
            ['date' => '1883-06-10', 'precision' => 'day'], ['date' => '1883-06-12', 'precision' => 'day'],
        ]));
        $this->assertSame(['1883-01-01', 'year'], NetworkChronology::agreedDate([
            ['date' => '1883-06-10', 'precision' => 'day'], ['date' => '1883-09-12', 'precision' => 'day'],
        ]));
        $this->assertSame(['1883-06-10', 'day'], NetworkChronology::agreedDate([
            ['date' => '1883-06-10', 'precision' => 'day'], ['date' => '1883-01-01', 'precision' => 'year'],
        ]));
        // Confiance faible : pas de jour.
        $this->assertSame(['1883-06-01', 'month'], NetworkChronology::agreedDate([['date' => '1883-06-10', 'precision' => 'day', 'confidence' => 'low']]));
        // Un 1er janvier « au jour » d'OpenHistoricalMap est une année seule.
        $this->assertSame('year', NetworkChronology::precision('day', '1827-01-01', 'ohm'));
        $this->assertSame('day', NetworkChronology::precision('day', '1827-01-01', 'wikipedia'));
    }

    public function test_a_stretch_is_not_counted_twice_when_sections_overlap(): void
    {
        $this->assertSame(25.6, NetworkChronology::sectionsKm([
            ['Bapaume', 'Marcoing', 25.6], ['Bapaume', 'Havrincourt', 12.0], ['Havrincourt', 'Marcoing', 13.6],
        ]));
        $this->assertSame(10.0, NetworkChronology::sectionsKm([['A', 'B', 4.0], ['C', 'D', 6.0], ['près de E', null, null]]));
        $this->assertNull(NetworkChronology::sectionsKm([['A', 'B', null]]));
    }

    public function test_sections_opened_the_same_day_make_one_fact_and_the_whole_line_joins_them(): void
    {
        $this->event(['type' => 'opening', 'event_date' => '1981-09-27', 'source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/1', 'section_from' => 'Pasilly', 'section_to' => 'Tonnerre', 'section_length_m' => 20000]);
        $this->event(['type' => 'opening', 'event_date' => '1981-09-27', 'source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/2', 'section_from' => 'Tonnerre', 'section_to' => 'Saulieu', 'section_length_m' => 30000]);
        $this->event(['type' => 'opening', 'event_date' => '1981-01-01', 'date_precision' => 'year', 'source' => 'wikipedia']);

        $facts = $this->facts(app(NetworkChronology::class)->compute(), 1981);

        $this->assertCount(1, $facts);
        $this->assertCount(2, $facts[0]['sections']);
        $this->assertSame(50.0, $facts[0]['km']);
        $this->assertSame(['ohm', 'wikipedia'], array_column($facts[0]['sources'], 'provider'));
    }

    public function test_facts_resting_only_on_unpublishable_sources_are_counted_not_detailed(): void
    {
        $chronology = app(NetworkChronology::class);
        $this->assertTrue($chronology->isPublishable('lignes-oubliees'));
        $this->assertTrue($chronology->isPublishable('wikipedia'));
        $this->assertFalse($chronology->isPublishable('archeoferroviaire'));
        $this->assertFalse($chronology->isPublishable('retronews'));
        $this->assertFalse($chronology->isPublishable(null));

        $accident = Accident::factory()->create(['slug' => '1891-06-14-munchenstein', 'kind' => 'collision', 'deaths' => 12, 'occurred_on' => '1891-06-14', 'department_code' => '29']);
        DB::table('accident_sources')->insert(['accident_id' => $accident->id, 'provider' => 'delpher', 'role' => 'press', 'title' => 'Krant', 'licence' => 'inconnue', 'url' => 'https://www.delpher.nl/x', 'retrieved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $result = $chronology->compute();

        $this->assertSame([], $this->facts($result, 1891));
        $this->assertSame([0, 1, 0.0], $result['meta']['counts'][1891]['accident']['all']);
        $this->assertSame([0, 1, 0.0], $result['meta']['counts'][1891]['accident']['53']);
    }

    public function test_milestones_are_national_and_written_in_the_page_language(): void
    {
        $chronology = app(NetworkChronology::class);
        $result = $chronology->compute();
        $sncf = array_values(array_filter($this->facts($result, 1938), fn (array $f): bool => $f['kind'] === 'milestone'));

        $this->assertCount(1, $sncf);
        $this->assertSame([], $sncf[0]['regions']);
        $en = $chronology->present($sncf, [], null, 'en')[0];
        $this->assertSame('Birth of the SNCF', $en['title']);
        $this->assertSame('1 January 1938', $en['when']);
        $this->assertSame('French Wikipedia', $en['sources'][0]['name']);
        $this->assertSame([], $chronology->present($sncf, [], '53', 'fr'));
        $this->assertSame([], $chronology->present($sncf, ['closure'], null, 'fr'));
    }

    public function test_the_command_fills_the_cache_and_the_page_shows_one_decade(): void
    {
        $this->event(['type' => 'closure_passenger', 'event_date' => '1938-10-02', 'source' => 'wikipedia', 'section_from' => 'Morlaix', 'section_to' => 'Roscoff', 'section_length_m' => 28000]);

        $this->artisan('vff:compute-network-chronology')->assertSuccessful();
        $this->assertNotNull(Cache::get(NetworkChronology::CACHE_KEY.':meta'));

        $this->withHeader('Accept-Language', 'fr')->get('/chronology?decade=1930&types=closure,nope&region=53')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Network/Chronology')
                ->where('chronology.decade', 1930)
                ->where('chronology.filters.types', ['closure'])
                ->where('chronology.filters.region', '53')
                ->where('chronology.years.0.year', 1938)
                ->where('chronology.years.0.facts.0.traffic', 'passenger')
                ->where('chronology.years.0.facts.0.path', '/lines/rfn-000001')
                ->where('chronology.years.0.facts.0.sections.0.text', 'de Morlaix à Roscoff')
                ->where('chronology.years.0.facts.0.km', 28)
                ->etc());
        // Décennie inconnue : la première.
        $this->get('/chronology?decade=1935')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('chronology.decade', 1820)->etc());
    }

    public function test_the_api_serves_the_overview_and_a_decade_and_rejects_unknown_filters(): void
    {
        $this->event(['type' => 'reopening_passenger', 'event_date' => '2018-12-09', 'source' => 'lignes-oubliees', 'source_url' => 'https://www.lignes-oubliees.com/x']);

        $this->getJson('/api/v1/chronology?types=reopening')->assertOk()
            ->assertJsonPath('data.filters.types', ['reopening'])
            ->assertJsonPath('data.years.0.year', 2018)
            ->assertJsonPath('data.years.0.counts.reopening.shown', 1)
            ->assertJsonPath('links.decade', route('api.chronology.decade', 1820));
        $this->getJson('/api/v1/chronology/2010?lang=de&types=reopening')->assertOk()
            ->assertJsonPath('data.years.0.facts.0.when', '9. Dezember 2018')
            ->assertJsonPath('data.years.0.facts.0.sources.0.name', 'Lignes oubliées')
            ->assertJsonPath('links.next', route('api.chronology.decade', 2020));
        $this->getJson('/api/v1/chronology/1935')->assertNotFound();
        $this->getJson('/api/v1/chronology/1810')->assertNotFound();
        $this->getJson('/api/v1/chronology?types=opening,foo')->assertUnprocessable()->assertJsonValidationErrors('types');
        $this->getJson('/api/v1/chronology?region=99')->assertUnprocessable()->assertJsonValidationErrors('region');
    }
}
