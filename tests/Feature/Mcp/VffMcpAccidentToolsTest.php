<?php

namespace Tests\Feature\Mcp;

use App\Enums\AccidentDatePrecision;
use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\AccidentSource;
use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Outils MCP des accidents (accidents, get_accident) et de la comparaison d'ères (compare_eras) par
 * le transport HTTP réel : mêmes filtres et mêmes données que l'API REST (AccidentQuery,
 * AccidentResource, AccidentDetailResource, EraComparison), langue, curseurs, attribution des sources.
 */
class VffMcpAccidentToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $line = Line::factory()->create(['slug' => 'rfn-570000', 'code' => '570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean']);
        $station = Station::factory()->create(['slug' => 'bretigny', 'name' => 'Brétigny', 'geom' => Point::makeGeodetic(48.607, 2.302)]);

        $bretigny = Accident::factory()->on('2013-07-12')->at(48.6072, 2.3022)->toReview('toll_mismatch', 'blessés : Wikidata 192, Wikipédia 70')->create([
            'slug' => '2013-07-12-bretigny-sur-orge', 'wikidata_id' => 'Q13835935', 'kind' => 'derailment',
            'name' => ['fr' => 'Accident ferroviaire de Brétigny-sur-Orge', 'en' => 'Brétigny-sur-Orge train crash', 'de' => 'Eisenbahnunfall von Brétigny-sur-Orge'],
            'commune_name' => 'Brétigny-sur-Orge', 'commune_insee' => '91103', 'department_code' => '91',
            'deaths' => 7, 'injuries' => 192, 'toll_source' => 'bea-tt', 'nearest_station_id' => $station->id, 'nearest_station_distance_m' => 120,
            'summary' => ['fr' => 'Le 12 juillet 2013, un train déraille.', 'en' => 'On 12 July 2013, a train derailed.', 'de' => 'Am 12. Juli 2013 entgleiste ein Zug.'],
        ]);
        AccidentLine::query()->forceCreate([
            'accident_id' => $bretigny->id, 'line_id' => $line->id, 'is_primary' => true, 'era_year' => 2010,
            'distance_m' => 8, 'pk_m' => 31336, 'section_from_station_id' => $station->id, 'method' => 'proximity+wikidata',
            'confidence' => 'high', 'score' => 0.99,
        ]);
        AccidentSource::query()->forceCreate([
            'accident_id' => $bretigny->id, 'provider' => 'bea-tt', 'external_id' => 'bretigny-f', 'role' => 'report',
            'title' => 'Déraillement d’un train Intercités à Brétigny-sur-Orge (91)', 'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport.pdf',
            'licence' => 'Licence Ouverte 2.0 (Etalab)', 'facts' => ['deaths' => 7], 'retrieved_at' => now(),
        ]);
        AccidentSource::query()->forceCreate([
            'accident_id' => $bretigny->id, 'provider' => 'wikidata', 'external_id' => 'Q13835935', 'role' => 'data',
            'title' => 'Accident ferroviaire de Brétigny-sur-Orge', 'url' => 'https://www.wikidata.org/wiki/Q13835935',
            'licence' => 'CC0 1.0', 'revision' => 2555864454, 'facts' => ['deaths' => 7, 'injuries' => 192], 'retrieved_at' => now(),
        ]);
        Accident::factory()->on('1891-07-26')->create(['slug' => '1891-07-26-saint-mande', 'kind' => 'collision', 'deaths' => 43, 'injuries' => null,
            'department_code' => '94', 'commune_name' => 'Saint-Mandé', 'name' => ['fr' => 'Accident ferroviaire de Saint-Mandé', 'en' => 'Saint-Mandé rail accident', 'de' => 'Eisenbahnunfall von Saint-Mandé']]);
        Accident::factory()->on('1922-01-01', AccidentDatePrecision::Year)->create(['slug' => '1922-villecomtal', 'kind' => 'collision', 'deaths' => null, 'injuries' => null,
            'department_code' => '32', 'commune_name' => 'Villecomtal-sur-Arros', 'geom' => null, 'location_precision' => null]);

        // Réseau fictif pour compare_eras : une ligne maintenue, une fermée, une disparue.
        $e1930 = Era::factory()->create(['year' => 1930]);
        $e1980 = Era::factory()->create(['year' => 1980]);
        Era::factory()->current()->create(['year' => 2030]);
        foreach ([['kept', 'in_service', 'in_service', 10_000], ['closed', 'in_service', 'abandoned', 4_000], ['gone', 'in_service', null, 3_000]] as $i => [$name, $before, $after, $meters]) {
            $l = Line::factory()->create(['slug' => "cmp-{$name}"]);
            foreach ([[$e1930, $before], [$e1980, $after]] as [$era, $status]) {
                if ($status !== null) {
                    DB::table('line_segments')->insert([
                        'line_id' => $l->id, 'era_id' => $era->id, 'status' => $status, 'traffic_type' => 'mixed', 'length_meters' => $meters,
                        'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.0 %F, 2.6 %F)', 4326)", 48.45 + $i * 0.05, 48.45 + $i * 0.05)),
                        'source' => 'osm', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function tool(string $tool, array $arguments = []): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');
        $text = $result['content'][0]['text'];

        return [json_decode($text, true) ?? ['raw' => $text], (bool) $result['isError']];
    }

    public function test_accidents_are_listed_chronologically_with_rest_fields(): void
    {
        [$data, $error] = $this->tool('accidents');

        $this->assertFalse($error);
        $this->assertSame(['1891-07-26-saint-mande', '1922-villecomtal', '2013-07-12-bretigny-sur-orge'], array_column($data['accidents'], 'slug'));
        $this->assertSame(3, $data['count']);
        $this->assertFalse($data['has_more']);
        $this->assertSame('https://vff-project.org/accidents', $data['vff_url']);

        $bretigny = $data['accidents'][2];
        $this->assertSame('Brétigny-sur-Orge train crash', $this->tool('accidents', ['q' => 'bretigny', 'lang' => 'en'])[0]['accidents'][0]['name']);
        $this->assertSame('Accident ferroviaire de Brétigny-sur-Orge', $bretigny['name'], 'nom dans la langue demandée (fr par défaut)');
        $this->assertSame('Le 12 juillet 2013, un train déraille.', $bretigny['summary']);
        $this->assertSame('Déraillement', $bretigny['kind_label']);
        $this->assertSame('12 juillet 2013', $bretigny['when']);
        $this->assertSame('day', $bretigny['date_precision']);
        $this->assertSame('rfn-570000', $bretigny['line']['slug']);
        $this->assertSame('Essonne', $bretigny['place']['department']);
        $this->assertSame(7, $bretigny['deaths']);
        $this->assertSame('bea-tt', $bretigny['toll_source']);
        $this->assertSame('https://vff-project.org/accidents/2013-07-12-bretigny-sur-orge', $bretigny['url']);
    }

    public function test_unknown_toll_stays_null_and_year_precision_is_kept(): void
    {
        [$data] = $this->tool('accidents', ['q' => 'villecomtal']);

        $this->assertNull($data['accidents'][0]['deaths']);
        $this->assertSame('year', $data['accidents'][0]['date_precision']);
        $this->assertSame('1922', $data['accidents'][0]['when']);
        $this->assertNull($data['accidents'][0]['place']['lat']);
    }

    public function test_filters_are_shared_with_the_rest_api(): void
    {
        $slugs = fn (array $args): array => array_column($this->tool('accidents', $args)[0]['accidents'], 'slug');

        $this->assertSame(['2013-07-12-bretigny-sur-orge'], $slugs(['line' => 'rfn-570000']));
        $this->assertSame(['1891-07-26-saint-mande'], $slugs(['to' => 1900]));
        $this->assertSame(['2013-07-12-bretigny-sur-orge'], $slugs(['from' => 2000, 'to' => 2020]));
        $this->assertSame(['2013-07-12-bretigny-sur-orge'], $slugs(['department' => '91']));
        $this->assertSame(['1891-07-26-saint-mande', '1922-villecomtal'], $slugs(['kind' => 'collision']));
        $this->assertSame(['2013-07-12-bretigny-sur-orge'], $slugs(['review' => 'to_review']));
        $this->assertSame(['1891-07-26-saint-mande'], $slugs(['min_deaths' => 10, 'to' => 1900]));
        $this->assertSame(['1891-07-26-saint-mande', '2013-07-12-bretigny-sur-orge', '1922-villecomtal'], $slugs(['sort' => '-deaths']));
        $this->assertEqualsCanonicalizing(['1891-07-26-saint-mande', '1922-villecomtal'], $slugs(['has_line' => false]));
    }

    public function test_pagination_follows_the_cursor_for_every_sort(): void
    {
        foreach (['date', '-date', 'deaths', '-deaths'] as $sort) {
            $all = array_column($this->tool('accidents', ['sort' => $sort])[0]['accidents'], 'slug');
            $seen = [];
            $cursor = null;
            do {
                [$data] = $this->tool('accidents', ['sort' => $sort, 'limit' => 1] + ($cursor !== null ? ['cursor' => $cursor] : []));
                $seen = [...$seen, ...array_column($data['accidents'], 'slug')];
                $cursor = $data['next_cursor'];
            } while ($data['has_more']);

            $this->assertSame($all, $seen, "tri {$sort} : aucun accident perdu ni répété");
            $this->assertNull($cursor);
        }
        $this->assertSame(['2013-07-12-bretigny-sur-orge', '1922-villecomtal', '1891-07-26-saint-mande'], array_column($this->tool('accidents', ['sort' => '-date'])[0]['accidents'], 'slug'));
    }

    public function test_to_review_records_carry_a_caution_and_the_providers_read(): void
    {
        [$data] = $this->tool('accidents', ['q' => 'bretigny']);
        $this->assertStringContainsString('à vérifier', $data['review_note']);
        $this->assertSame('to_review', $data['accidents'][0]['review_status']);
        $providers = array_column($data['attribution']['sources'], 'provider');
        $this->assertContains('wikidata', $providers);
        $this->assertContains('bea-tt', $providers);
        $this->assertNotContains('wikipedia', $providers, 'Wikipédia seulement si une source Wikipédia a été lue');
        $mentions = implode(' ', array_column($data['attribution']['sources'], 'mention'));
        $this->assertStringContainsString('Licence Ouverte 2.0', $mentions);
        $this->assertStringContainsString('CC0', $mentions);

        [$data] = $this->tool('accidents', ['q' => 'saint-mande']);
        $this->assertArrayNotHasKey('review_note', $data);
    }

    public function test_accident_list_validation_is_strict(): void
    {
        foreach ([['kind' => 'explosion-nucleaire'], ['sort' => 'name'], ['from' => 1500], ['to' => 1900, 'from' => 1950], ['line' => 'inconnue'],
            ['limit' => 51], ['cursor' => 'illisible'], ['inconnu' => 1], ['department' => 'abc']] as $arguments) {
            [$data, $error] = $this->tool('accidents', $arguments);
            $this->assertTrue($error, json_encode($arguments, JSON_UNESCAPED_UNICODE).' refusé');
        }
    }

    public function test_accident_sheet_has_lines_sources_and_review_notes_in_the_requested_language(): void
    {
        [$data, $error] = $this->tool('get_accident', ['accident' => '2013-07-12-bretigny-sur-orge', 'lang' => 'en']);

        $this->assertFalse($error);
        $accident = $data['accident'];
        $this->assertSame('Brétigny-sur-Orge train crash', $accident['name']);
        $this->assertSame('On 12 July 2013, a train derailed.', $accident['summary']);
        $this->assertSame('Derailment', $accident['kind_label']);
        $this->assertSame('rfn-570000', $accident['lines'][0]['line']['slug']);
        $this->assertSame('31+336', $accident['lines'][0]['pk_label']);
        $this->assertSame(['slug' => 'bretigny', 'name' => 'Brétigny', 'distance_m' => 120], $accident['nearest_station']);
        $this->assertSame(['bea-tt', 'wikidata'], array_column($accident['sources'], 'provider'));
        $this->assertSame('Licence Ouverte 2.0 (Etalab)', $accident['sources'][0]['license']);
        $this->assertSame('to_review', $accident['review']['status']);
        $this->assertSame('toll_mismatch', $accident['review']['notes'][0]['code']);
        $this->assertNotSame('', $accident['review']['notes'][0]['label']);
        $this->assertStringContainsString('to be checked', $data['review_note']);
        $this->assertSame('https://vff-project.org/accidents/2013-07-12-bretigny-sur-orge', $data['vff_url']);
        $this->assertSame(['bea-tt', 'wikidata'], array_slice(array_column($data['attribution']['sources'], 'provider'), 3));
    }

    public function test_accident_sheet_unknown_slug_is_a_business_error(): void
    {
        [$data, $error] = $this->tool('get_accident', ['accident' => '1900-nulle-part']);

        $this->assertTrue($error);
        $this->assertSame('accident_not_found', $data['error']);
        $this->assertStringContainsString('accidents', $data['message']);
    }

    public function test_accident_sheet_of_a_merged_duplicate_answers_with_the_kept_record(): void
    {
        $kept = Accident::query()->where('slug', '2013-07-12-bretigny-sur-orge')->sole();
        Accident::factory()->on('2013-07-13')->create(['slug' => '2013-07-13-doublon', 'alias_of_id' => $kept->id]);

        [$data, $error] = $this->tool('get_accident', ['accident' => '2013-07-13-doublon']);

        $this->assertFalse($error);
        $this->assertSame('2013-07-12-bretigny-sur-orge', $data['accident']['slug']);
    }

    public function test_compare_eras_returns_the_rest_summary(): void
    {
        [$data, $error] = $this->tool('compare_eras', ['from' => 1930, 'to' => 1980]);

        $this->assertFalse($error);
        $this->assertSame(['from' => 1930, 'to' => 1980], ['from' => $data['from'], 'to' => $data['to']]);
        $this->assertSame([
            'opened' => ['km' => 0, 'lines' => 0],
            'closed' => ['km' => 4, 'lines' => 1],
            'removed' => ['km' => 3, 'lines' => 1],
            'kept' => ['km' => 10, 'lines' => 1],
        ], $data['changes']);
        $this->assertSame(['from' => 17, 'to' => 10], $data['open_km']);
        $this->assertSame(['opened', 'closed', 'removed', 'kept'], array_keys($data['labels']));
        $this->assertSame('https://vff-project.org/maps?era=1930&compare=1980', $data['links']['map']);
        $this->assertSame('https://vff-project.org/api/v1/eras/1930/compare/1980/tiles/{z}/{x}/{y}.mvt', $data['links']['tiles']);
        $this->assertContains('osm', array_column($data['attribution']['sources'], 'provider'));
    }

    public function test_compare_eras_orders_the_years_and_snaps_them_to_eras(): void
    {
        [$data] = $this->tool('compare_eras', ['from' => 1987, 'to' => 1937, 'lang' => 'en']);

        $this->assertSame([1930, 1980], [$data['from'], $data['to']]);
        $this->assertSame([1987, 1937], $data['requested_years'], 'années dans l\'ordre demandé');
        $this->assertStringContainsString('Opened or reopened', $data['labels']['opened']);
        $this->assertSame($this->tool('compare_eras', ['from' => 1930, 'to' => 1980])[0]['changes'], $data['changes']);
    }

    public function test_compare_eras_reads_the_providers_from_the_cached_summary(): void
    {
        $first = $this->tool('compare_eras', ['from' => 1930, 'to' => 1980])[0];

        DB::flushQueryLog();
        DB::enableQueryLog();
        $again = $this->tool('compare_eras', ['from' => 1930, 'to' => 1980])[0];
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertSame($first['attribution'], $again['attribution']);
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'line_segments'))));
    }

    public function test_compare_eras_errors(): void
    {
        [$data, $error] = $this->tool('compare_eras', ['from' => 1932, 'to' => 1938]);
        $this->assertTrue($error);
        $this->assertSame('same_era', $data['error']);

        [$data, $error] = $this->tool('compare_eras', ['from' => 1801, 'to' => 1980]);
        $this->assertTrue($error);
        $this->assertSame('era_not_found', $data['error']);
        $this->assertSame([1930, 1980, 2030], $data['available_eras']);

        $this->assertTrue($this->tool('compare_eras', ['from' => 1930])[1], 'to est obligatoire');
        $this->assertTrue($this->tool('compare_eras', ['from' => 1930, 'to' => 3000])[1]);
    }
}
