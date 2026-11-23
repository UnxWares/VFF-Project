<?php

namespace Tests\Feature\Mcp;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\Companies\CompanyFigures;
use App\Services\CoverageFigures;
use App\Services\Departments\DepartmentFigures;
use App\Services\Heritage\HeritageIndex;
use App\Services\Lines\LineSlugRedirects;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Parité du serveur MCP avec REST : `bibliography` (GET /lines|stations/{slug}/bibliography), `protected_heritage`
 * (GET /heritage), `companies` (GET /companies, /companies/{company}), `get_commune` (GET /communes/{insee}),
 * `department_history` (GET /departments/{code}/history, /stats/departments), `data_coverage` (GET /stats/coverage) et
 * `position_at_pk` (GET /lines/{slug}/at-pk) ; jamais un fait d'une source non commerciale.
 */
class VffMcpParityToolsTest extends TestCase
{
    use RefreshDatabase;

    private const ARCHEO_URL = 'http://archeoferroviaire.free.fr/page.html';

    /** @return array{0: array<string, mixed>, 1: bool} */
    private function tool(string $tool, array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');

        return [json_decode($result['content'][0]['text'], true) ?? ['text' => $result['content'][0]['text']], (bool) ($result['isError'] ?? false)];
    }

    /** @param list<array<string, mixed>> $arguments */
    private function assertErrors(string $tool, array $arguments): void
    {
        foreach ($arguments as $a) {
            [, $error] = $this->tool($tool, $a);
            $this->assertTrue($error, $tool.' '.json_encode($a));
        }
    }

    public function test_bibliography_of_a_line_and_of_a_station_with_paging_licences_and_an_old_slug(): void
    {
        $this->app->instance(LineSlugRedirects::class, new LineSlugRedirects(['ancien-causses' => 'rfn-722000']));
        $line = Line::factory()->create(['slug' => 'rfn-722000', 'code' => '722000', 'name' => 'Ligne de Béziers à Neussargues']);
        $station = Station::factory()->create(['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins']);
        $entry = fn (string $key, string $title, ?int $year, string $provider): int => DB::table('bibliography_entries')->insertGetId([
            'dedup_key' => $key, 'kind' => 'book', 'title' => $title, 'authors' => json_encode(['Jean Dupont']), 'year' => $year,
            'provider' => $provider, 'url' => 'https://catalogue.bnf.fr/ark:/12148/cb'.$key, 'fetched_at' => '2026-11-23 10:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $link = fn (int $entry, ?int $lineId, ?int $stationId, string $provider, string $basis): bool => DB::table('bibliography_links')->insert([
            'entry_id' => $entry, 'line_id' => $lineId, 'station_id' => $stationId, 'provider' => $provider, 'basis' => $basis,
            'reasons' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $link($entry('a', "L'épopée de la ligne des Causses", 2025, 'bnf'), $line->id, null, 'bnf', 'title_alias');
        $link($entry('b', 'Le train des Causses', 1988, 'wikipedia'), $line->id, null, 'wikipedia', 'article_bibliography');
        $link($entry('c', 'Étude sans date', null, 'wikidata'), $line->id, null, 'wikidata', 'wikidata');
        $link($entry('d', 'La gare de Limoges-Bénédictins', 1999, 'wikipedia'), null, $station->id, 'wikipedia', 'article_bibliography');

        [$all, $error] = $this->tool('bibliography', ['line' => 'rfn-722000']);
        $this->assertFalse($error);
        $this->assertSame(3, $all['total']);
        $this->assertSame([2025, 1988, null], array_column($all['items'], 'year'), 'les plus récents d\'abord, sans date en dernier');
        $this->assertSame('title_alias', $all['items'][0]['match']['basis']);
        $this->assertSame('2026-11-23', $all['items'][0]['fetched_on']);
        $this->assertSame('https://vff-project.org/lines/rfn-722000', $all['vff_url']);
        $providers = array_column($all['attribution']['sources'], 'provider');
        $this->assertContains('gallica', $providers, 'notices de la BnF');
        $this->assertContains('wikipedia', $providers);

        [$page] = $this->tool('bibliography', ['line' => 'ancien-causses', 'limit' => 1, 'offset' => 1]);
        $this->assertSame(['Le train des Causses'], array_column($page['items'], 'title'));
        $this->assertTrue($page['has_more']);
        $this->assertSame(['kind' => 'line', 'requested' => 'ancien-causses', 'current' => 'rfn-722000', 'reason' => 'renamed'],
            array_diff_key($page['redirected'][0], ['note' => true]));

        [$gare] = $this->tool('bibliography', ['station' => 'limoges-benedictins']);
        $this->assertSame(['La gare de Limoges-Bénédictins'], array_column($gare['items'], 'title'));
        $this->assertSame('limoges-benedictins', $gare['station']['slug']);

        $this->assertErrors('bibliography', [[], ['line' => 'rfn-722000', 'station' => 'limoges-benedictins'], ['line' => 'nulle-part'],
            ['station' => 'nulle-part'], ['line' => 'rfn-722000', 'limit' => 61], ['x' => 1]]);
    }

    /** @return array<string, mixed> */
    private static function heritageItem(string $id, string $object, string $name, string $protection, ?string $department, ?string $architect, ?int $year): array
    {
        return [
            'id' => $id, 'object' => $object, 'name' => $name, 'title' => null, 'protection' => $protection, 'protection_date' => null, 'scope' => null,
            'architects' => $architect === null ? [] : [$architect], 'built' => ['from' => $year, 'to' => null, 'period' => null, 'year' => $year],
            'era' => HeritageIndex::era($year), 'style' => null, 'commune' => 'X', 'department' => $department, 'region' => 'Nouvelle-Aquitaine',
            'reference' => ['id' => 'PA1', 'url' => 'https://www.pop.culture.gouv.fr/notice/merimee/PA1'], 'image' => null,
            'station' => null, 'line' => null, 'lat' => 45.0, 'lng' => 1.0,
        ];
    }

    public function test_protected_heritage_filters_sorts_pages_and_cites_merimee(): void
    {
        Cache::put(HeritageIndex::CACHE_KEY, [
            'items' => [
                self::heritageItem('st1', 'building', 'Limoges-Bénédictins', 'registered', '87', 'Roger Gonthier', 1925),
                self::heritageItem('sx1', 'viaduct', 'Viaduc de Garabit', 'classified', '15', 'Gustave Eiffel', 1884),
                self::heritageItem('st2', 'building', 'Tours', 'registered', '37', 'Victor Laloux', 1898),
            ],
            'facets' => ['protections' => [], 'objects' => [], 'eras' => [], 'styles' => [], 'regions' => [],
                'architects' => [['name' => 'Gustave Eiffel', 'count' => 1], ['name' => 'Roger Gonthier', 'count' => 1], ['name' => 'Victor Laloux', 'count' => 1]]],
            'attribution' => 'Base Mérimée (POP), Licence Ouverte 2.0.',
        ], 600);

        [$all, $error] = $this->tool('protected_heritage', []);
        $this->assertFalse($error);
        $this->assertSame(3, $all['total']);
        $this->assertSame('https://vff-project.org/heritage', $all['vff_url']);
        $this->assertNotEmpty($all['heritage_attribution']);
        $providers = array_column($all['attribution']['sources'], 'provider');
        $this->assertContains('pop', $providers);
        $this->assertContains('ign', $providers, 'ouvrage d\'art : BD TOPO');

        [$built] = $this->tool('protected_heritage', ['sort' => 'built', 'limit' => 2]);
        $this->assertSame(['sx1', 'st2'], array_column($built['items'], 'id'));
        $this->assertTrue($built['has_more']);

        [$registered] = $this->tool('protected_heritage', ['protection' => 'registered', 'department' => '87']);
        $this->assertSame(['st1'], array_column($registered['items'], 'id'));
        [$laloux] = $this->tool('protected_heritage', ['architect' => 'Victor Laloux']);
        $this->assertSame(['st2'], array_column($laloux['items'], 'id'));

        $this->assertErrors('protected_heritage', [['protection' => 'listed'], ['object' => 'gare'], ['department' => 'Haute-Vienne'], ['limit' => 51], ['x' => 1]]);
    }

    public function test_companies_list_and_sheet_page_their_lines_and_never_serve_the_map(): void
    {
        $this->assertErrors('companies', [[]]);   // chiffres pas encore calculés

        $company = ['slug' => 'nord', 'kind' => 'grande-compagnie', 'name' => 'Compagnie des chemins de fer du Nord', 'short' => 'Nord', 'wikidata' => 'Q541879',
            'founded' => ['date' => '1845-09-20', 'precision' => 'day'], 'ended' => ['date' => '1937-12-31', 'precision' => 'day'], 'seat' => 'Paris', 'summary' => ['fr' => 'Compagnie du nord.', 'en' => 'Northern company.']];
        Cache::put(CompanyFigures::INDEX_KEY, ['computed_at' => '2026-11-23T05:00:00+01:00', 'current_year' => 2030,
            'companies' => [$company + ['lines' => 2, 'lines_counted' => 2, 'km_peak' => 3046, 'peak_year' => 1920, 'km_today' => 0, 'lines_open_today' => 0]],
            'coverage' => [['year' => 1930, 'share' => 0.575]], 'quality' => []], 600);
        $line = fn (string $slug): array => ['slug' => $slug, 'name' => 'Ligne '.$slug, 'code' => null, 'is_lgv' => false, 'from_year' => 1845, 'to_year' => 1937,
            'to_basis' => 'event', 'from_date' => '1845-01-01', 'from_precision' => 'year', 'km' => 10.0, 'open_today' => false, 'event_id' => 1];
        Cache::put(CompanyFigures::SHEET_KEY.'nord', ['company' => $company, 'summary' => [], 'computed_at' => '2026-11-23T05:00:00+01:00', 'current_year' => 2030,
            'events' => [['date' => '1845-09-20', 'precision' => 'day', 'text' => ['fr' => 'Création.', 'en' => 'Founded.']]],
            'sources' => [['label' => 'Wikipédia', 'url' => 'https://fr.wikipedia.org/wiki/Compagnie_des_chemins_de_fer_du_Nord']],
            'predecessors' => [], 'successors' => [], 'decades' => [], 'peak' => null, 'first_year' => 1850,
            'lines' => [$line('a'), $line('b'), $line('c')], 'map' => ['paths' => [[[2.0, 48.0], [3.0, 50.0]]]], 'map_info' => ['year' => 1920, 'lines' => 3]], 600);

        [$list, $error] = $this->tool('companies', ['lang' => 'en']);
        $this->assertFalse($error);
        $this->assertSame(['nord'], array_column($list['companies'], 'slug'));
        $this->assertSame('Northern company.', $list['companies'][0]['summary']);
        $this->assertSame('https://vff-project.org/companies', $list['vff_url']);
        $this->assertArrayNotHasKey('quality', $list);

        [$sheet] = $this->tool('companies', ['company' => 'nord', 'limit' => 2, 'lang' => 'en']);
        $this->assertSame(['a', 'b'], array_column($sheet['lines'], 'slug'));
        $this->assertSame(3, $sheet['lines_total']);
        $this->assertTrue($sheet['lines_has_more']);
        $this->assertSame('Founded.', $sheet['events'][0]['text']);
        $this->assertArrayNotHasKey('map', $sheet, 'aucun tracé');
        $this->assertArrayNotHasKey('map_info', $sheet);
        $this->assertSame('https://vff-project.org/companies/nord', $sheet['vff_url']);
        $this->assertContains('wikipedia', array_column($sheet['attribution']['sources'], 'provider'));

        [$unknown, $error] = $this->tool('companies', ['company' => 'zzz']);
        $this->assertTrue($error);
        $this->assertSame(['nord'], $unknown['available']);
        $this->assertErrors('companies', [['company' => 'Nord!'], ['company' => 'nord', 'limit' => 101], ['x' => 1]]);
    }

    public function test_commune_by_code_or_name_hides_station_events_of_a_non_commercial_source(): void
    {
        $point = DB::raw('ST_SetSRID(ST_MakePoint(0.15, 45.65), 4326)');
        DB::table('communes')->insert([
            ['geom' => $point, 'code_insee' => '16015', 'nom' => 'Angoulême', 'nom_norm' => 'angouleme', 'type' => 'commune-actuelle', 'departement' => '16', 'population' => 41908],
            ['geom' => $point, 'code_insee' => '81270', 'nom' => 'Saint-Sulpice', 'nom_norm' => 'saint sulpice', 'type' => 'commune-actuelle', 'departement' => '81', 'population' => 9000],
            ['geom' => $point, 'code_insee' => '53250', 'nom' => 'Saint-Sulpice', 'nom_norm' => 'saint sulpice', 'type' => 'commune-actuelle', 'departement' => '53', 'population' => 200],
        ]);
        // Communes qui ont du rail (CommuneRail::codes, gardé en cache ; commune_rail vide dans la base de test).
        Cache::put('communes:rail:stations', ['16015', '53250', '81270'], 600);
        $station = Station::factory()->create(['slug' => 'angouleme', 'name' => 'Angoulême']);
        $event = fn (string $type, string $date, string $source, ?string $url): array => ['station_id' => $station->id, 'type' => $type, 'event_date' => $date,
            'date_precision' => 'day', 'source' => $source, 'source_url' => $url, 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()];
        DB::table('station_events')->insert([$event('reopening', '1950-01-01', 'wikipedia', self::ARCHEO_URL), $event('demolition', '1972-06-01', 'osm', null)]);
        $ref = ['slug' => 'angouleme', 'name' => 'Angoulême'];
        $chronology = [
            ['kind' => 'station_opening', 'date' => '1852-09-01', 'precision' => 'day', 'after' => null, 'station' => $ref],
            ['kind' => 'station_reopening', 'date' => '1950-01-01', 'precision' => 'day', 'after' => null, 'station' => $ref],
            ['kind' => 'station_demolition', 'date' => '1972-06-01', 'precision' => 'day', 'after' => null, 'station' => $ref],
        ];
        $page = fn (string $code, string $name, string $dep): array => [
            'commune' => ['insee' => $code, 'slug' => $code.'-'.str($name)->slug(), 'name' => $name, 'department_code' => $dep, 'department_name' => 'X', 'population' => 1, 'former_names' => []],
            'current_era' => 2030, 'counts' => ['stations' => 1, 'stations_open' => 1, 'lines' => 0, 'lines_open' => 0],
            'stations' => [], 'lines' => [], 'chronology' => $chronology, 'neighbors' => [], 'map' => ['stations' => [], 'lines' => [['geometry' => 'x']]],
        ];
        foreach ([['16015', 'Angoulême', '16'], ['81270', 'Saint-Sulpice', '81'], ['53250', 'Saint-Sulpice', '53']] as [$code, $name, $dep]) {
            Cache::put('communes:sheet:v1:'.$code.':fr', $page($code, $name, $dep), 600);
        }

        [$sheet, $error] = $this->tool('get_commune', ['commune' => '16015']);
        $this->assertFalse($error);
        $this->assertSame('Angoulême', $sheet['commune']['name']);
        $this->assertSame(['station_opening', 'station_demolition'], array_column($sheet['chronology'], 'kind'), 'la réouverture d\'une source non commerciale n\'est pas servie');
        $this->assertSame(2, $sheet['chronology_total']);
        $this->assertArrayNotHasKey('map', $sheet, 'aucun tracé');
        $this->assertSame('https://vff-project.org/communes/16015-angouleme', $sheet['vff_url']);
        $this->assertStringNotContainsString('archeoferroviaire', (string) json_encode($sheet));

        [$byName] = $this->tool('get_commune', ['commune' => 'angouleme', 'limit' => 1]);
        $this->assertSame('16015', $byName['commune']['insee']);
        $this->assertSame(['station_opening'], array_column($byName['chronology'], 'kind'));
        $this->assertTrue($byName['chronology_has_more']);

        [$ambiguous, $error] = $this->tool('get_commune', ['commune' => 'Saint-Sulpice']);
        $this->assertTrue($error);
        $this->assertSame('commune_ambiguous', $ambiguous['error']);
        $this->assertEqualsCanonicalizing(['81270', '53250'], array_column($ambiguous['candidates'], 'insee'));
        [$tarn] = $this->tool('get_commune', ['commune' => 'Saint-Sulpice', 'department' => '81']);
        $this->assertSame('81270', $tarn['commune']['insee']);

        $this->assertErrors('get_commune', [[], ['commune' => '99999'], ['commune' => 'Nullepart'], ['commune' => '16015', 'department' => '17'], ['commune' => '16015', 'limit' => 401]]);
    }

    public function test_department_history_serves_only_dates_of_a_served_source(): void
    {
        $this->assertErrors('department_history', [['department' => '16']]);   // chiffres pas encore calculés

        $a = Line::factory()->create(['slug' => 'ligne-a', 'name' => 'Ligne A']);
        $b = Line::factory()->create(['slug' => 'ligne-b', 'name' => 'Ligne B']);
        $event = fn (Line $l, string $type, string $date, string $precision, string $source, ?string $url = null): array => ['line_id' => $l->id, 'type' => $type,
            'event_date' => $date, 'date_precision' => $precision, 'source' => $source, 'source_url' => $url, 'description' => 'x', 'created_at' => now(), 'updated_at' => now()];
        DB::table('line_events')->insert([
            $event($a, 'opening', '1872-05-12', 'day', 'wikipedia', 'https://fr.wikipedia.org/wiki/A'),
            // B : ouverture au jour par la source non commerciale seulement, et l'année par Wikipédia ; fermeture non commerciale seule.
            $event($b, 'opening', '1880-06-01', 'day', 'archeoferroviaire', self::ARCHEO_URL),
            $event($b, 'opening', '1880-01-01', 'year', 'wikipedia', 'https://fr.wikipedia.org/wiki/B'),
            $event($b, 'closure', '1938-05-15', 'day', 'archeoferroviaire', self::ARCHEO_URL),
            $event($b, 'reopening', '1950-03-01', 'day', 'archeoferroviaire', self::ARCHEO_URL),
        ]);
        Cache::put(DepartmentFigures::INDEX_KEY, ['computed_at' => '2026-11-23T03:00:00+01:00', 'reference_year' => 1930, 'current_year' => 2030,
            'departments' => [['code' => '16', 'slug' => '16-charente', 'name' => 'Charente', 'region' => 'Nouvelle-Aquitaine', 'km_reference' => 800, 'km_today' => 300]]], 600);
        $row = fn (string $slug, ?array $opened, ?array $closed): array => ['slug' => $slug, 'name' => $slug, 'code' => null, 'is_lgv' => false, 'state' => 'closed', 'track' => 'dismantled',
            'km' => 12.0, 'km_year' => 1930, 'first_year' => 1880, 'last_year' => 1930, 'opened' => $opened, 'closed' => $closed, 'electrified' => null];
        Cache::put(DepartmentFigures::SHEET_KEY.'16', [
            'summary' => [], 'department' => ['code' => '16', 'slug' => '16-charente', 'name' => 'Charente', 'region' => 'Nouvelle-Aquitaine'],
            'computed_at' => '2026-11-23T03:00:00+01:00', 'reference_year' => 1930, 'current_year' => 2030, 'decades' => [], 'closed_share' => 0.6,
            'milestones' => ['first_opening' => ['date' => '1872-05-12', 'precision' => 'day', 'line' => ['slug' => 'ligne-a', 'name' => 'Ligne A']], 'peak' => null, 'closure_waves' => [],
                'reopenings' => [['date' => '1950-03-01', 'precision' => 'day', 'line' => ['slug' => 'ligne-b', 'name' => 'Ligne B']]]],
            'lines' => [
                $row('ligne-a', ['date' => '1872-05-12', 'precision' => 'day'], null),
                $row('ligne-b', ['date' => '1880-06-01', 'precision' => 'day'], ['date' => '1938-05-15', 'precision' => 'day', 'scope' => 'all']),
            ],
            'communes' => [], 'accidents' => ['count' => 0, 'deaths' => 0, 'list' => [], 'limit' => 10], 'map' => ['contour' => null, 'stations' => [], 'lines' => []],
        ], 600);

        [$index, $error] = $this->tool('department_history', []);
        $this->assertFalse($error);
        $this->assertSame(['16'], array_column($index['departments'], 'code'));

        [$sheet, $error] = $this->tool('department_history', ['department' => 'charente']);
        $this->assertFalse($error);
        $lines = array_column($sheet['lines'], null, 'slug');
        $this->assertSame(['date' => '1872-05-12', 'precision' => 'day'], $lines['ligne-a']['opened'], 'date d\'une source servie gardée');
        $this->assertSame(['date' => '1880-01-01', 'precision' => 'year'], $lines['ligne-b']['opened'], 'jour non commercial remplacé par l\'année servie');
        $this->assertNull($lines['ligne-b']['closed'], 'fermeture de la seule source non commerciale retirée');
        $this->assertSame([], $sheet['milestones']['reopenings']);
        $this->assertSame('1872-05-12', $sheet['milestones']['first_opening']['date']);
        $this->assertArrayNotHasKey('map', $sheet);
        $this->assertSame('https://vff-project.org/departments/16-charente', $sheet['vff_url']);
        $this->assertStringNotContainsString('1938-05-15', (string) json_encode($sheet));

        [$page] = $this->tool('department_history', ['department' => '16', 'limit' => 1, 'offset' => 1]);
        $this->assertSame(['ligne-b'], array_column($page['lines'], 'slug'));
        $this->assertSame(2, $page['lines_total']);

        $this->assertErrors('department_history', [['department' => '96'], ['department' => 'Atlantide'], ['department' => '16', 'limit' => 201], ['x' => 1]]);
    }

    public function test_data_coverage_serves_the_cached_figures_whole_or_by_set(): void
    {
        Cache::put(CoverageFigures::CACHE_KEY, [
            'computed_at' => '2026-11-23T05:00:00+01:00',
            'lines' => ['metrics' => [['key' => 'opening_dated', 'unit' => 'lines', 'available' => true, 'total' => 10, 'done' => 6, 'share' => 0.6, 'open' => 5]]],
            'stations' => ['metrics' => []], 'accidents' => ['metrics' => [], 'by_decade' => []], 'materials' => ['metrics' => [], 'by_decade' => []],
            'eras' => [['year' => 1930, 'current' => false, 'lines' => 700]],
        ], 600);

        [$all, $error] = $this->tool('data_coverage', []);
        $this->assertFalse($error);
        $this->assertSame(0.6, $all['lines']['metrics'][0]['share']);
        $this->assertSame(1930, $all['eras'][0]['year']);
        $this->assertSame('https://vff-project.org/network/coverage', $all['vff_url']);

        [$lines] = $this->tool('data_coverage', ['set' => 'lines']);
        $this->assertArrayHasKey('lines', $lines);
        $this->assertArrayNotHasKey('eras', $lines);
        $this->assertSame('2026-11-23T05:00:00+01:00', $lines['computed_at']);

        $this->assertErrors('data_coverage', [['set' => 'signalling'], ['x' => 1]]);
    }

    public function test_position_at_pk_gives_the_stations_on_either_side_and_the_bounds(): void
    {
        $era = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd’hui']);
        $line = Line::factory()->create(['name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean', 'slug' => 'rfn-570000', 'code' => '570000']);
        foreach ([['Gare Alpha', 'Alphaville', 0, 2.0], ['Gare Beta', 'Betaville', 118_000, 2.1], ['Gare Gamma', 'Gammaville', 128_000, 2.2]] as $i => [$name, $city, $pk, $lng]) {
            $station = Station::factory()->create(['name' => $name, 'city' => $city, 'provenance' => [], 'geom' => Point::makeGeodetic(48.0, $lng)]);
            DB::table('line_stops')->insert(['line_id' => $line->id, 'era_id' => $era->id, 'station_id' => $station->id, 'position' => $i, 'pk_m' => $pk,
                'effective_status' => 'active', 'is_terminal' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        Line::factory()->create(['slug' => 'sans-gare', 'name' => 'Ligne sans gare']);

        [$at, $error] = $this->tool('position_at_pk', ['line' => '570000', 'pk' => '120+500']);
        $this->assertFalse($error);
        $this->assertSame(120_500, $at['pk_m']);
        $this->assertSame('Gare Beta', $at['between']['from']['name']);
        $this->assertSame('Gare Gamma', $at['between']['to']['name']);
        $this->assertSame('Betaville', $at['commune']);
        $this->assertSame('stations', $at['position_method']);
        $this->assertSame('https://vff-project.org/lines/rfn-570000', $at['vff_url']);

        [$onStation] = $this->tool('position_at_pk', ['line' => 'rfn-570000', 'pk_m' => 118_000]);
        $this->assertSame('Gare Beta', $onStation['at_station']['name']);

        [$out, $error] = $this->tool('position_at_pk', ['line' => 'rfn-570000', 'pk' => '700']);
        $this->assertTrue($error);
        $this->assertSame('pk_out_of_range', $out['error']);
        $this->assertSame('128+000', $out['bounds']['to_label']);
        [$none] = $this->tool('position_at_pk', ['line' => 'sans-gare', 'pk' => '1']);
        $this->assertSame('no_stops', $none['error']);

        $this->assertErrors('position_at_pk', [['line' => 'rfn-570000'], ['line' => 'rfn-570000', 'pk' => 'abc'], ['line' => 'rfn-570000', 'pk' => '1', 'pk_m' => 1000],
            ['line' => 'nulle-part', 'pk' => '1'], ['line' => 'rfn-570000', 'pk_m' => -1]]);
    }
}
