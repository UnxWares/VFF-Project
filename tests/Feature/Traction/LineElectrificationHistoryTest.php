<?php

namespace Tests\Feature\Traction;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Histoire de l'électrification : même lecture pour la fiche, l'API REST, GraphQL et le serveur MCP.
 */
class LineElectrificationHistoryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create([
            'slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest', 'electrified' => true, 'voltage_v' => 25000,
        ]);
        foreach ([[430, 100000, 1500, 0.0], [100000, 213203, 1500, 0.0], [213203, 622408, 25000, 50.0]] as [$a, $b, $v, $hz]) {
            DB::table('sncf_line_electrification')->insert([
                'code_ligne' => '420000', 'rg_troncon' => 1, 'pkd_m' => $a, 'pkf_m' => $b, 'elect' => $v.' volts', 'voltage_v' => $v,
                'frequency_hz' => $hz, 'third_rail' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Ligne sans PK : ne doit pas dupliquer les tranches.
        DB::table('sncf_line_electrification')->insert([
            'code_ligne' => '420000', 'rg_troncon' => 1, 'pkd_m' => null, 'pkf_m' => null, 'elect' => '1500 volts', 'voltage_v' => 1500,
            'frequency_hz' => 0, 'third_rail' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->event('1937-01-01', 'year', 'wikipedia', 'La section entre Paris-Montparnasse et Le Mans a été électrifiée en 1500 volts continu, en 1937', 'Paris-Montparnasse', 'Le Mans');
        $this->event('1987-04-23', 'day', 'wikipedia', "D'orientation est-ouest, elle est électrifiée depuis le 23 avril 1987 entre Rennes et Saint-Brieuc", 'Rennes', 'Saint-Brieuc');
        $this->event('1999-01-01', 'year', 'wikipedia', 'Les travaux de ré-électrification sont engagés en 1999', null, null);
        $this->event('1950-01-01', 'year', 'lignes-oubliees', 'La ligne a été électrifiée en 1950', null, null);
        $this->event('1958-01-01', 'year', 'wikidata', 'Wikidata Q1 · P930 = électrification ferroviaire 25 kV 50 Hz', null, null);
    }

    private function event(string $date, string $precision, string $source, string $quote, ?string $from, ?string $to): void
    {
        DB::table('line_events')->insert([
            'line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => $date, 'date_precision' => $precision,
            'description' => 'Électrification (Wikipédia, texte)', 'source' => $source, 'source_quote' => $quote,
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Montparnasse_%C3%A0_Brest&oldid=239547071',
            'confidence' => 'medium', 'section_from' => $from, 'section_to' => $to, 'section_resolution' => $from ? 'places' : 'whole_line',
            'fingerprint' => sha1($date.$quote), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_rest_endpoint_gives_merged_ranges_and_only_sure_dated_facts(): void
    {
        $json = $this->getJson('/api/v1/lines/rfn-420000/electrification-history')->assertOk()
            ->assertJsonPath('line.code', '420000')
            ->assertJsonCount(2, 'ranges')
            ->assertJsonPath('ranges.0.voltage_v', 1500)
            ->assertJsonPath('ranges.0.system', 'dc')
            ->assertJsonPath('ranges.0.pk_end_m', 213203)
            ->assertJsonPath('ranges.1.system', 'ac')
            ->assertJsonPath('summary.line_electrified', true)
            ->assertJsonPath('summary.by_voltage.0.voltage_v', 25000)
            ->assertJsonPath('provenance.ranges.license', 'ODbL-1.0');

        // Travaux écartés, source de droits incertains (lignes-oubliees) non servie, Wikidata écarté dès qu'un article détaille.
        $this->assertSame(['1937-01-01', '1987-04-23'], array_column($json->json('history'), 'date'));
        $this->assertSame('section', $json->json('history.0.scope'));
        $this->assertSame(1500, $json->json('history.0.voltage_v'));
        $this->assertSame('high', $json->json('history.1.reliability'));
        $this->assertStringContainsString('1937', (string) $json->json('history.0.source.excerpt'));
    }

    public function test_a_line_without_data_has_empty_lists(): void
    {
        $other = Line::factory()->create(['slug' => 'sans-elec', 'code' => null]);

        $this->getJson('/api/v1/lines/'.$other->slug.'/electrification-history')->assertOk()
            ->assertJsonPath('ranges', [])->assertJsonPath('history', [])
            ->assertJsonPath('summary.electrified_m', 0);
    }

    public function test_wikidata_is_the_fallback_when_no_article_gives_the_detail(): void
    {
        DB::table('line_events')->where('source', 'wikipedia')->delete();

        $this->getJson('/api/v1/lines/rfn-420000/electrification-history')->assertOk()
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.date', '1958-01-01')
            ->assertJsonPath('history.0.voltage_v', 25000)
            ->assertJsonPath('history.0.source.provider', 'wikidata');
    }

    public function test_graphql_and_the_sheet_read_the_same_data(): void
    {
        $gql = $this->graphQL('{ line(slug: "rfn-420000") { electrification_history { ranges { pk_start_m voltage_v system } summary { electrified_m } history { kind date reliability section { from to } source { provider } } provenance { ranges { license } } } } }')
            ->assertJsonPath('data.line.electrification_history.ranges.1.system', 'ac')
            ->assertJsonPath('data.line.electrification_history.history.0.section.to', 'Le Mans')
            ->assertJsonPath('data.line.electrification_history.provenance.ranges.license', 'ODbL-1.0');
        $this->assertCount(2, $gql->json('data.line.electrification_history.history'));

        $this->getWithDeferredProps('/lines/rfn-420000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('electrification_history.ranges', 2)
            ->has('electrification_history.history', 2));
    }

    public function test_the_mcp_tool_gives_ranges_and_history_without_excerpts(): void
    {
        $res = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_electrification_history', 'arguments' => ['line' => '420000']]])
            ->assertOk();
        $text = (string) $res->json('result.content.0.text');
        $payload = json_decode($text, true);
        $this->assertIsArray($payload, $text);
        $this->assertCount(2, $payload['ranges']);
        $this->assertCount(2, $payload['history']);
        $this->assertArrayNotHasKey('excerpt', $payload['history'][0]['source']);
        $this->assertStringNotContainsString('Raoul', $text);
        $this->assertStringNotContainsString('orientation', mb_strtolower($text));
    }
}
