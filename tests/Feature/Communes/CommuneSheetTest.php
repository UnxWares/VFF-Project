<?php

namespace Tests\Feature\Communes;

use App\Models\Accident;
use App\Services\Communes\CommuneRail;
use App\Services\Communes\CommuneSheet;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Fiche « Le rail à <commune> » : gares, lignes (état, longueur, dates), chronologie fusionnée, communes voisines,
 * redirection vers l'adresse canonique, 404 sans gare ni ligne, API REST, recherche et plan du site.
 *
 * Deux communes en rectangles de part et d'autre de la longitude 2,30 : « Testville » (la gare de Paris-Saint-Lazare
 * du mini-réseau et le début de la ligne) et « Voisineville » (la suite de la ligne, sans gare).
 */
class CommuneSheetTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private function commune(string $insee, string $name, string $normalised, float $west, float $east, int $population): void
    {
        DB::table('communes')->insert([
            'code_insee' => $insee, 'type' => 'commune-actuelle', 'nom' => $name, 'nom_norm' => $normalised, 'departement' => '75',
            'population' => $population, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, 48.87), 4326)', ($west + $east) / 2)),
        ]);
        DB::table('commune_contours')->insert([
            'code_insee' => $insee, 'nom' => $name, 'departement' => '75', 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf("ST_Multi(ST_GeomFromText('POLYGON((%F 48.80, %F 48.80, %F 48.89, %F 48.89, %F 48.80))', 4326))", $west, $east, $east, $west, $west)),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
        $this->commune('99100', 'Testville', 'testville', 2.30, 2.35, 12000);
        $this->commune('99101', 'Voisineville', 'voisineville', 2.20, 2.30, 800);
        DB::table('commune_rail')->insert([
            ['code_insee' => '99100', 'stations_count' => 1, 'lines_count' => 1, 'built_at' => now()],
            ['code_insee' => '99101', 'stations_count' => 0, 'lines_count' => 1, 'built_at' => now()],
        ]);
    }

    public function test_the_page_lists_stations_lines_chronology_and_neighbours(): void
    {
        $this->portionEvent($this->line, 'opening', '1839-08-02', 48.8763, 2.31, 2.34);
        $this->portionEvent($this->line, 'closure', '1985-06-01', 48.8763, 2.31, 2.34);
        $this->portionEvent($this->line, 'electrification', '1928-03-01', 48.8763, 2.31, 2.34);
        // Une section hors de la commune ne la concerne pas.
        $this->portionEvent($this->line, 'opening', '1840-01-01', 48.8091, 2.1347, 2.1400);
        Accident::factory()->create(['slug' => '1950-01-01-testville', 'commune_insee' => '99100', 'commune_name' => 'Testville', 'occurred_on' => '1950-01-01']);
        Accident::factory()->create(['slug' => '1951-01-01-ailleurs', 'commune_insee' => '99101', 'commune_name' => 'Voisineville']);

        $this->get('/communes/99100-testville')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Communes/Show')
            ->where('commune.name', 'Testville')
            ->where('commune.slug', '99100-testville')
            ->where('commune.department_name', 'Paris')
            ->where('counts.stations', 1)
            ->where('counts.lines', 1)
            ->where('counts.lines_open', 1)
            ->where('stations.0.slug', 'paris-saint-lazare')
            ->where('stations.0.opened_at', '1837-08-26')
            ->where('lines.0.state', 'open')
            ->where('lines.0.km_year', 2030)
            ->where('lines.0.opened.date', '1839-08-02')
            // Ouverte en 1839 et « fermée » en 1985 sur la section, mais en service aujourd'hui : pas de fermeture sur la ligne.
            ->where('lines.0.closed', null)
            ->where('neighbors.0.slug', '99101-voisineville')
            ->where('map.stations.0.slug', 'paris-saint-lazare')
            ->has('map.lines')
            ->where('chronology', function (Collection $entries): bool {
                $entries = $entries->all();
                $kinds = array_column($entries, 'kind');
                $dates = array_column($entries, 'date');

                return in_array('station_opening', $kinds, true)
                    && in_array('line_electrification', $kinds, true)
                    && in_array('line_closure', $kinds, true)
                    && in_array('accident', $kinds, true)
                    && ! in_array('1840-01-01', $dates, true)
                    && ! in_array('1951-01-01', $dates, true)
                    && $dates === collect($dates)->sort()->values()->all();
            }));
    }

    public function test_facts_of_the_non_commercial_source_are_neither_shown_nor_served(): void
    {
        $this->portionEvent($this->line, 'opening', '1839-08-02', 48.8763, 2.31, 2.34);
        // Fermeture connue par la seule source à usage non commercial (année seule), par sa valeur ou par son lien.
        $this->portionEvent($this->line, 'closure', '1972-01-01', 48.8763, 2.31, 2.34);
        DB::table('line_events')->where('event_date', '1972-01-01')->update(['source' => 'archeoferroviaire', 'date_precision' => 'year']);
        $this->portionEvent($this->line, 'electrification', '1931-01-01', 48.8763, 2.31, 2.34);
        DB::table('line_events')->where('event_date', '1931-01-01')->update(['source' => 'manual', 'source_url' => 'http://archeoferroviaire.free.fr/x']);

        $dates = array_column($this->getJson('/api/v1/communes/99100')->assertOk()->json('data.chronology'), 'date');
        $this->assertContains('1839-08-02', $dates);
        $this->assertNotContains('1972-01-01', $dates);
        $this->assertNotContains('1931-01-01', $dates);
        $this->assertNull($this->getJson('/api/v1/communes/99100')->json('data.lines.0.electrified'));
    }

    public function test_a_commune_crossed_by_a_line_without_a_station_has_a_page(): void
    {
        $this->get('/communes/99101-voisineville')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('counts.stations', 0)
            ->where('counts.lines', 1)
            ->where('stations', [])
            ->where('neighbors.0.slug', '99100-testville'));
    }

    public function test_the_sheet_is_computed_once_per_commune_and_language(): void
    {
        $this->get('/communes/99100-testville')->assertOk();
        DB::enableQueryLog();
        $this->get('/communes/99100-testville')->assertOk();
        $this->getJson('/api/v1/communes/99100')->assertOk()->assertJsonPath('data.commune.name', 'Testville');
        $spatial = array_filter(DB::getQueryLog(), fn (array $q): bool => str_contains($q['query'], 'commune_contours'));
        $this->assertSame([], array_values($spatial), 'la deuxième lecture ne refait aucune requête géométrique');

        // Une autre langue a sa propre fiche (noms des accidents).
        $this->assertNotNull(app(CommuneSheet::class)->page('99100', 'en'));
        $this->assertTrue(Cache::has(CommuneSheet::CACHE_KEY.'99100:en'));
    }

    public function test_a_closed_line_shows_its_state_and_a_wrong_name_redirects_to_the_canonical_address(): void
    {
        DB::table('line_segments')->where('line_id', $this->line->id)->where('era_id', $this->currentEra->id)->update(['status' => 'abandoned']);
        $this->portionEvent($this->line, 'closure', '1985-06-01', 48.8763, 2.31, 2.34);

        $this->get('/communes/99100-testville')->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.state', 'closed')
            ->where('lines.0.track', 'in_place')
            ->where('lines.0.km_year', 1960)
            ->where('lines.0.closed.date', '1985-06-01')
            ->where('lines.0.closed.scope', 'all'));

        $this->get('/communes/99100')->assertRedirect('/communes/99100-testville')->assertStatus(301);
        $this->get('/communes/99100-un-ancien-nom')->assertRedirect('/communes/99100-testville')->assertStatus(301);
    }

    public function test_a_commune_without_a_station_or_a_line_and_unknown_codes_are_not_found(): void
    {
        $this->commune('99102', 'Videville', 'videville', 3.00, 3.05, 50);
        $this->get('/communes/99102-videville')->assertNotFound();
        $this->get('/communes/99999-nulle-part')->assertNotFound();
        $this->get('/communes/abc')->assertNotFound();
        $this->assertNull(app(CommuneSheet::class)->page('99102', 'fr'));
    }

    public function test_the_rest_endpoint_returns_the_sheet_without_the_map(): void
    {
        $this->getJson('/api/v1/communes/99100')->assertOk()
            ->assertJsonPath('data.commune.insee', '99100')
            ->assertJsonPath('data.counts.stations', 1)
            ->assertJsonPath('data.links.page', url('/communes/99100-testville'))
            ->assertJsonMissingPath('data.map');
        $this->getJson('/api/v1/communes/99999')->assertNotFound();
        $this->getJson('/api/v1/communes/abc')->assertNotFound();
    }

    public function test_search_finds_communes_with_rail_in_their_own_group(): void
    {
        $found = $this->getJson('/api/v1/search?q=testville')->assertOk()->json();
        $this->assertSame(['99100-testville'], array_column($found['communes'], 'slug'));
        $this->assertSame('Paris', $found['communes'][0]['department_name']);
        // Les autres catégories gardent leur forme : une commune ne s'y glisse pas.
        $this->assertSame([], $found['lines']);
        $this->assertSame([], $this->getJson('/api/v1/search?q=a')->json('communes'));

        $page = $this->get('/search?q=testville&type=commune')->assertOk();
        $page->assertInertia(fn (Assert $p) => $p
            ->where('counts.commune', 1)
            ->where('groups.0.type', 'commune')
            ->where('groups.0.items.0.slug', '99100-testville')
            ->where('groups.0.items.0.highlight.0.mark', true));
    }

    public function test_the_endpoint_and_the_search_communes_are_documented_in_the_specification(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $operation = $spec['paths']['/communes/{insee}']['get'];
        $this->assertLessThanOrEqual(60, mb_strlen($operation['summary']));
        $this->assertGreaterThan(100, mb_strlen($operation['description']));
        $this->assertNotEmpty($operation['responses']['200']['content']['application/json']['schema']['examples'] ?? null);
        foreach ([404, 429] as $code) {
            $this->assertArrayHasKey((string) $code, $operation['responses']);
        }
        $this->assertStringContainsString('communes', json_encode($spec['paths']['/search']['get']['responses']['200']) ?: '');
    }

    public function test_the_sitemap_lists_the_commune_family(): void
    {
        $xml = (string) (new SitemapBuilder)->family('https://vff.test', 'communes', 1);
        $this->assertStringContainsString('<loc>https://vff.test/communes/99100-testville</loc>', $xml);
        $this->assertStringContainsString('<loc>https://vff.test/communes/99101-voisineville</loc>', $xml);
        $this->assertStringNotContainsString('lastmod', $xml);
        $this->assertContains('communes', SitemapBuilder::FAMILIES);
    }

    public function test_slug_and_insee_helpers(): void
    {
        $this->assertSame('16015-angouleme', CommuneRail::slug('16015', 'Angoulême'));
        $this->assertSame('2A004-ajaccio', CommuneRail::slug('2A004', 'Ajaccio'));
        $this->assertSame('01001', CommuneRail::slug('01001', '...'));
        $this->assertSame('16015', CommuneRail::inseeOf('16015-angouleme'));
        $this->assertSame('2A004', CommuneRail::inseeOf('2A004'));
        $this->assertNull(CommuneRail::inseeOf('angouleme'));
        $this->assertNull(CommuneRail::inseeOf('1601-x'));
    }

    public function test_without_the_table_the_list_falls_back_to_communes_with_a_station(): void
    {
        DB::table('commune_rail')->delete();
        Cache::flush();

        $this->assertSame(['99100'], (new CommuneRail)->codes());
    }

    public function test_the_station_sheet_links_to_its_commune_and_department(): void
    {
        $this->origin->update(['department_code' => '75']);

        $this->getWithDeferredProps('/stations/'.$this->origin->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Stations/Show')
            ->where('department_name', 'Paris')
            ->where('commune', ['slug' => '99100-testville', 'name' => 'Testville']));
    }

    public function test_the_station_sheet_has_no_commune_link_without_a_commune_sheet(): void
    {
        DB::table('commune_rail')->where('code_insee', '99100')->delete();
        Cache::flush();

        $this->getWithDeferredProps('/stations/'.$this->origin->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('commune', null));
    }

    public function test_the_accident_sheet_links_to_the_commune_sheet_only_when_it_exists(): void
    {
        Accident::factory()->create(['slug' => '1950-01-01-testville', 'commune_insee' => '99100', 'commune_name' => 'Testville', 'occurred_on' => '1950-01-01']);
        Accident::factory()->create(['slug' => '1951-01-01-ailleurs', 'commune_insee' => '99999', 'commune_name' => 'Ailleurs', 'occurred_on' => '1951-01-01']);
        Accident::factory()->create(['slug' => '1952-01-01-sans-lieu', 'commune_insee' => null, 'commune_name' => null, 'occurred_on' => '1952-01-01']);

        $this->get('/accidents/1950-01-01-testville')->assertInertia(fn (Assert $page) => $page->where('commune_page', '99100-testville'));
        $this->get('/accidents/1951-01-01-ailleurs')->assertInertia(fn (Assert $page) => $page->where('commune_page', null));
        $this->get('/accidents/1952-01-01-sans-lieu')->assertInertia(fn (Assert $page) => $page->where('commune_page', null));
    }
}
