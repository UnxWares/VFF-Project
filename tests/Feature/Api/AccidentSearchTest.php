<?php

namespace Tests\Feature\Api;

use App\Enums\AccidentDatePrecision;
use App\Models\Accident;
use App\Services\CatalogSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les accidents dans la recherche par nom : site (GET /api/v1/search), GraphQL (`search`) et serveur
 * MCP (`search`) partagent CatalogSearch. Nom dans les trois langues, commune, date (année, mois,
 * jour), pertinence, limites, et portée PublicAccidents (un accident masqué ne sort jamais).
 */
class AccidentSearchTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->accident('2013-07-12-bretigny-sur-orge', '2013-07-12', 'Brétigny-sur-Orge', 'Accident ferroviaire de Brétigny-sur-Orge', 'Brétigny-sur-Orge train crash', 'Eisenbahnunfall von Brétigny-sur-Orge', '91');
        $this->accident('1891-07-26-saint-mande', '1891-07-26', 'Saint-Mandé', 'Accident ferroviaire de Saint-Mandé', 'Saint-Mandé rail accident', 'Eisenbahnunfall von Saint-Mandé', '94');
        $this->accident('1922-villecomtal', '1922-01-01', 'Villecomtal-sur-Arros', 'Collision de Villecomtal', 'Villecomtal collision', 'Kollision bei Villecomtal', '32', AccidentDatePrecision::Year, withGeom: false);
        $this->accident('1950-03-01-brest', '1950-03-01', 'Brest', 'Déraillement de Brest', 'Brest derailment', 'Entgleisung in Brest', '29', AccidentDatePrecision::Month);
    }

    private function accident(string $slug, string $date, string $commune, string $fr, string $en, string $de, string $department, AccidentDatePrecision $precision = AccidentDatePrecision::Day, string $kind = 'derailment', bool $withGeom = true): Accident
    {
        $factory = Accident::factory()->on($date, $precision);
        if ($withGeom) {
            $factory = $factory->at(48.6, 2.3);
        }

        return $factory->create([
            'slug' => $slug, 'kind' => $kind, 'commune_name' => $commune, 'department_code' => $department,
            'name' => ['fr' => $fr, 'en' => $en, 'de' => $de],
            ...($withGeom ? [] : ['geom' => null, 'location_precision' => null]),
        ]);
    }

    /** @return list<string> */
    private function slugs(string $q, string $key = 'accidents'): array
    {
        return array_column($this->getJson('/api/v1/search?q='.rawurlencode($q))->assertOk()->json($key), 'slug');
    }

    public function test_the_response_has_an_accidents_list_with_the_documented_fields(): void
    {
        $first = $this->getJson('/api/v1/search?q=bretigny')->assertOk()->json('accidents.0');

        $this->assertSame([
            'type' => 'accident',
            'slug' => '2013-07-12-bretigny-sur-orge',
            'name' => 'Accident ferroviaire de Brétigny-sur-Orge',
            'names' => [
                'fr' => 'Accident ferroviaire de Brétigny-sur-Orge',
                'en' => 'Brétigny-sur-Orge train crash',
                'de' => 'Eisenbahnunfall von Brétigny-sur-Orge',
            ],
            'date' => '2013-07-12',
            'date_precision' => 'day',
            'kind' => 'derailment',
            'commune' => 'Brétigny-sur-Orge',
            'department_code' => '91',
            'lat' => 48.6,
            'lng' => 2.3,
            'approximate' => false,
        ], $first);
    }

    public function test_an_accident_without_a_position_has_null_coordinates(): void
    {
        $found = $this->getJson('/api/v1/search?q=villecomtal')->assertOk()->json('accidents.0');

        $this->assertSame('1922-villecomtal', $found['slug']);
        $this->assertNull($found['lat']);
        $this->assertNull($found['lng']);
        $this->assertSame('year', $found['date_precision']);
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function nameAndPlaceQueries(): array
    {
        return [
            'commune' => ['bretigny', ['2013-07-12-bretigny-sur-orge']],
            'commune sans accent ni casse' => ['SAINT-MANDE', ['1891-07-26-saint-mande']],
            'commune avec accent' => ['Brétigny', ['2013-07-12-bretigny-sur-orge']],
            'nom français' => ['déraillement', ['1950-03-01-brest']],
            'nom anglais' => ['train crash', ['2013-07-12-bretigny-sur-orge']],
            'nom allemand, ex æquo classés par nom français' => ['eisenbahnunfall', ['2013-07-12-bretigny-sur-orge', '1891-07-26-saint-mande']],
            'mot du nom' => ['orge', ['2013-07-12-bretigny-sur-orge']],
            'rien' => ['zzzzz', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('nameAndPlaceQueries')]
    public function test_accidents_are_found_by_name_in_any_language_or_by_commune(string $q, array $expected): void
    {
        $this->assertSame($expected, $this->slugs($q));
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function dateQueries(): array
    {
        return [
            'année' => ['1891', ['1891-07-26-saint-mande']],
            'année sans accident' => ['1900', []],
            'jour ISO' => ['2013-07-12', ['2013-07-12-bretigny-sur-orge']],
            'jour français' => ['12/07/2013', ['2013-07-12-bretigny-sur-orge']],
            'jour avec tirets et zéros omis' => ['12-7-2013', ['2013-07-12-bretigny-sur-orge']],
            'jour avec points' => ['26.07.1891', ['1891-07-26-saint-mande']],
            'mois français' => ['07/2013', ['2013-07-12-bretigny-sur-orge']],
            'mois ISO' => ['2013-07', ['2013-07-12-bretigny-sur-orge']],
            'mois d’un accident daté au mois' => ['03/1950', ['1950-03-01-brest']],
            'jour par défaut d’un accident daté au mois' => ['01/03/1950', []],
            'jour par défaut d’un accident daté à l’année' => ['01/01/1922', []],
            'année d’un accident daté à l’année' => ['1922', ['1922-villecomtal']],
            'mois d’un accident daté à l’année' => ['01/1922', []],
            'jour autre' => ['13/07/2013', []],
            'date impossible' => ['31/02/2013', []],
            'mois impossible' => ['2013-13', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('dateQueries')]
    public function test_accidents_are_found_by_date_according_to_its_precision(string $q, array $expected): void
    {
        $this->assertSame($expected, $this->slugs($q));
    }

    public function test_relevance_is_exact_then_prefix_then_word_then_the_rest(): void
    {
        $this->accident('2001-01-01-nancy', '2001-01-01', 'Nancy', 'Nancy', 'Nancy', 'Nancy', '54');
        $this->accident('2002-01-01-nancy', '2002-01-01', 'Laneuveville', 'Nancy-Est', 'Nancy East', 'Nancy-Ost', '54');
        $this->accident('2003-01-01-vandoeuvre', '2003-01-01', 'Vandœuvre', 'Collision près de Nancy', 'Collision near Nancy', 'Kollision bei Nancy', '54');
        $this->accident('2004-01-01-houdemont', '2004-01-01', 'Houdemont', 'Accident à Houdemont', 'Houdemont accident', 'Unfall in Houdemont', '54');
        $this->accident('2005-01-01-nancy-bis', '2005-01-01', 'Jarville', 'Accident de Nancy', 'Accident in Nancy', 'Unfall in Nancy', '54');

        $order = $this->slugs('nancy');

        $this->assertSame('2001-01-01-nancy', $order[0], 'nom exact d’abord');
        $this->assertSame('2002-01-01-nancy', $order[1], 'début du nom ensuite');
        $this->assertEqualsCanonicalizing(['2003-01-01-vandoeuvre', '2005-01-01-nancy-bis'], array_slice($order, 2));
        $this->assertNotContains('2004-01-01-houdemont', $order);
    }

    public function test_equal_relevance_follows_alphabetical_name_then_date(): void
    {
        $this->accident('2010-05-05-a', '2010-05-05', 'Gap', 'Accident de Gap', 'Gap', 'Gap', '05');
        $this->accident('2005-05-05-b', '2005-05-05', 'Gap', 'Accident de Gap', 'Gap', 'Gap', '05');
        $this->accident('2000-05-05-c', '2000-05-05', 'Gap', 'Accident à Gap', 'Gap', 'Gap', '05');

        $this->assertSame(['2000-05-05-c', '2005-05-05-b', '2010-05-05-a'], $this->slugs('gap'));
    }

    public function test_the_default_limit_is_five_like_materials(): void
    {
        foreach (range(1, 8) as $i) {
            $this->accident("200{$i}-02-02-toulouse", "200{$i}-02-02", 'Toulouse', 'Accident de Toulouse', 'Toulouse', 'Toulouse', '31');
        }

        $this->assertCount(5, $this->slugs('toulouse'));
        $this->assertCount(2, app(CatalogSearch::class)->search('toulouse', 8, 8, 5, 2)['accidents']);
        $this->assertSame([], app(CatalogSearch::class)->search('toulouse', 8, 8, 5, 0)['accidents']);
    }

    public function test_hidden_accidents_never_come_out_whatever_the_query(): void
    {
        $this->accident('2015-05-05-heurt-de-personne', '2015-05-05', 'Argenteuil', 'Personne heurtée à Argenteuil', 'Person struck at Argenteuil', 'Person erfasst in Argenteuil', '95', kind: 'person_struck');

        foreach (['argenteuil', 'personne heurtée', 'person struck', '2015', '2015-05', '05/2015', '05/05/2015', '2015-05-05-heurt'] as $q) {
            $this->assertSame([], $this->slugs($q), $q);
        }
        $this->assertSame([], app(CatalogSearch::class)->search('argenteuil')['accidents']);
        $this->assertSame(1, Accident::withoutGlobalScopes()->where('slug', '2015-05-05-heurt-de-personne')->count());
    }

    public function test_short_non_string_and_hostile_queries_are_harmless(): void
    {
        $this->assertSame([], $this->slugs('a'));
        $this->assertSame([], $this->slugs('%%'));
        $this->assertSame([], $this->slugs('__'));
        $this->assertSame([], $this->slugs("' OR 1=1 --"));
        $this->assertSame([], $this->slugs('\\'));
        $this->getJson('/api/v1/search?q[]=bretigny')->assertOk()->assertJsonPath('accidents', []);
        $this->assertSame(4, Accident::count(), 'la base est intacte');
    }

    public function test_a_wildcard_does_not_match_everything(): void
    {
        $this->assertSame([], $this->slugs('%1'));
        $this->assertSame([], $this->slugs('2_13'));
    }

    // ---- GraphQL ------------------------------------------------------------------------------

    public function test_graphql_search_returns_accidents_with_a_name_in_the_requested_language(): void
    {
        $data = $this->graphQL('{ search(query: "bretigny") { accidents { slug name nameEn: name(lang: "en") nameDe: name(lang: "de") date date_precision kind commune department_code lat lng } } }')
            ->assertGraphQLErrorFree()->json('data.search.accidents');

        $this->assertCount(1, $data);
        $this->assertSame('2013-07-12-bretigny-sur-orge', $data[0]['slug']);
        $this->assertSame('Accident ferroviaire de Brétigny-sur-Orge', $data[0]['name']);
        $this->assertSame('Brétigny-sur-Orge train crash', $data[0]['nameEn']);
        $this->assertSame('Eisenbahnunfall von Brétigny-sur-Orge', $data[0]['nameDe']);
        $this->assertSame('2013-07-12', $data[0]['date']);
        $this->assertSame('day', $data[0]['date_precision']);
        $this->assertSame('derailment', $data[0]['kind']);
        $this->assertSame('91', $data[0]['department_code']);
        $this->assertEqualsWithDelta(48.6, $data[0]['lat'], 0.0001);
    }

    public function test_graphql_search_is_the_rest_search_for_accidents_and_respects_the_limit(): void
    {
        foreach (range(1, 4) as $i) {
            $this->accident("201{$i}-04-04-lyon", "201{$i}-04-04", 'Lyon', 'Accident de Lyon', 'Lyon', 'Lyon', '69');
        }

        $rest = $this->slugs('lyon');
        $graphql = $this->graphQL('{ search(query: "lyon") { accidents { slug lat lng } } }')->assertGraphQLErrorFree()->json('data.search.accidents');
        $this->assertSame($rest, array_column($graphql, 'slug'));
        $this->assertCount(2, $this->graphQL('{ search(query: "lyon", limit: 2) { accidents { slug } } }')->json('data.search.accidents'));
        $this->graphQL('{ search(query: "villecomtal") { accidents { lat lng } } }')->assertJsonPath('data.search.accidents.0.lat', null);
        $this->graphQL('{ search(query: "x") { accidents { slug } } }')->assertJsonPath('data.search.accidents', []);
    }

    public function test_graphql_search_hides_hidden_accidents_and_finds_by_date(): void
    {
        $this->accident('2016-06-06-hidden', '2016-06-06', 'Dax', 'Personne heurtée à Dax', 'Person struck', 'Person erfasst', '40', kind: 'person_struck');

        $this->graphQL('{ search(query: "dax") { accidents { slug } } }')->assertJsonPath('data.search.accidents', []);
        $this->graphQL('{ search(query: "2016") { accidents { slug } } }')->assertJsonPath('data.search.accidents', []);
        $this->graphQL('{ search(query: "1891") { accidents { slug date } } }')
            ->assertJsonPath('data.search.accidents.0.slug', '1891-07-26-saint-mande')
            ->assertJsonPath('data.search.accidents.0.date', '1891-07-26');
    }

    // ---- MCP ----------------------------------------------------------------------------------

    /** @return array{0: array<string, mixed>, 1: bool} */
    private function tool(array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'search', 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');

        return [json_decode($result['content'][0]['text'], true) ?? ['raw' => $result['content'][0]['text']], (bool) $result['isError']];
    }

    public function test_mcp_search_returns_accidents_with_links_and_the_requested_language(): void
    {
        [$data, $error] = $this->tool(['query' => 'bretigny', 'lang' => 'en']);

        $this->assertFalse($error);
        $this->assertCount(1, $data['accidents']);
        $found = $data['accidents'][0];
        $this->assertSame('2013-07-12-bretigny-sur-orge', $found['slug']);
        $this->assertSame('Brétigny-sur-Orge train crash', $found['name']);
        $this->assertSame('Accident ferroviaire de Brétigny-sur-Orge', $found['names']['fr']);
        $this->assertSame('https://vff-project.org/accidents/2013-07-12-bretigny-sur-orge', $found['vff_url']);
        $this->assertArrayNotHasKey('type', $found);
        $this->assertSame('Accident ferroviaire de Brétigny-sur-Orge', $this->tool(['query' => 'bretigny'])[0]['accidents'][0]['name']);
        $this->assertSame('Eisenbahnunfall von Brétigny-sur-Orge', $this->tool(['query' => 'bretigny', 'lang' => 'de'])[0]['accidents'][0]['name']);
    }

    public function test_mcp_search_kinds_and_limit_apply_to_accidents(): void
    {
        [$data] = $this->tool(['query' => 'accident', 'kinds' => ['accidents'], 'limit' => 1]);
        $this->assertSame([], $data['lines']);
        $this->assertSame([], $data['stations']);
        $this->assertCount(1, $data['accidents']);

        [$data] = $this->tool(['query' => 'accident', 'kinds' => ['lines']]);
        $this->assertSame([], $data['accidents']);

        [, $error] = $this->tool(['query' => 'accident', 'kinds' => ['accidents', 'lines', 'stations', 'materials']]);
        $this->assertFalse($error);
        [, $error] = $this->tool(['query' => 'accident', 'kinds' => ['users']]);
        $this->assertTrue($error);
    }

    public function test_mcp_search_by_date_and_hidden_accidents(): void
    {
        $this->accident('2017-07-07-hidden', '2017-07-07', 'Sète', 'Personne heurtée à Sète', 'Person struck', 'Person erfasst', '34', kind: 'person_struck');

        $this->assertSame([], $this->tool(['query' => 'sète'])[0]['accidents']);
        $this->assertSame([], $this->tool(['query' => '07/07/2017'])[0]['accidents']);
        $this->assertSame(['1891-07-26-saint-mande'], array_column($this->tool(['query' => '26/07/1891'])[0]['accidents'], 'slug'));
    }

    public function test_mcp_search_schema_lists_the_accidents_kind(): void
    {
        /** @var list<array<string, mixed>> $list */
        $list = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk()->json('result.tools');
        $tools = array_column($list, null, 'name');

        $this->assertSame(['lines', 'stations', 'materials', 'accidents'], $tools['search']['inputSchema']['properties']['kinds']['items']['enum']);
        $this->assertStringContainsString('accident', $tools['search']['description']);
    }
}
