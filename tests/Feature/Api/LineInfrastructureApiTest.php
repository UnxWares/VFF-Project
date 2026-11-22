<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Services\LineFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Voies (régime d'exploitation) et radio sol-train d'une ligne : même contenu en REST
 * (`/lines/{line}/infrastructure`), GraphQL (`Line.infrastructure`) et MCP (`line_infrastructure`), mention
 * SNCF Réseau et licence ODbL ; fait « Nombre de voies » de la fiche tiré du régime.
 */
class LineInfrastructureApiTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-579000', 'code' => '579000', 'name' => 'Ligne de Beillant à Angoulême']);
        Line::factory()->create(['slug' => 'rfn-001000', 'code' => '001000', 'name' => 'Ligne sans données']);
        $this->track(-252, 855, 'Double voie', 2);
        $this->track(855, 64667, 'Voie unique', 1);
        $this->radio(-252, 64035, 'gsm-gfu', "Global System for Mobil- Groupe Fermé d'utilisateurs");
    }

    private function track(int $from, int $to, string $regime, ?int $tracks, ?Line $line = null): void
    {
        DB::table('line_track_portions')->insert(['line_id' => ($line ?? $this->line)->id, 'code_ligne' => ($line ?? $this->line)->code, 'rg_troncon' => 1,
            'pk_start_m' => $from, 'pk_end_m' => $to, 'regime' => $regime, 'tracks' => $tracks, 'source' => 'sncf', 'dataset_modified' => '2022-03-24']);
    }

    private function radio(int $from, int $to, string $system, string $label): void
    {
        DB::table('line_radio_portions')->insert(['line_id' => $this->line->id, 'code_ligne' => '579000', 'rg_troncon' => 1,
            'pk_start_m' => $from, 'pk_end_m' => $to, 'system' => $system, 'label' => $label, 'source' => 'sncf', 'dataset_modified' => '2022-03-24']);
    }

    public function test_rest_returns_portions_summaries_and_the_odbl_attribution(): void
    {
        $json = $this->getJson('/api/v1/lines/rfn-579000/infrastructure')->assertOk()->json();

        $this->assertSame(['slug' => 'rfn-579000', 'code' => '579000', 'name' => 'Ligne de Beillant à Angoulême'], $json['line']);
        $this->assertSame(1, $json['tracks']['summary']['tracks']);
        $this->assertTrue($json['tracks']['summary']['uniform']);
        $this->assertSame(63812, $json['tracks']['summary']['dominant_length_m']);
        $this->assertSame([['tracks' => 1, 'length_m' => 63812], ['tracks' => 2, 'length_m' => 1107]], $json['tracks']['summary']['length_m_by_tracks']);
        $this->assertSame(['pk_start_m' => -252, 'pk_end_m' => 855, 'pk_start_label' => '-0+252', 'pk_end_label' => '0+855', 'length_m' => 1107,
            'tracks' => 2, 'regime' => 'Double voie', 'banalised' => false], $json['tracks']['portions'][0]);
        $this->assertSame('2022-03-24', $json['tracks']['dataset_modified']);
        $this->assertSame([['system' => 'gsm-gfu', 'length_m' => 64287]], $json['radio']['systems']);
        $this->assertFalse($json['radio']['gsm_r']);
        $this->assertSame('sncf', $json['provenance']['provider']);
        $this->assertStringContainsString('ODbL', $json['provenance']['license']);
        $this->assertStringContainsString('SNCF Réseau', $json['attribution']);
    }

    public function test_a_line_outside_the_datasets_gets_empty_blocks(): void
    {
        $json = $this->getJson('/api/v1/lines/rfn-001000/infrastructure')->assertOk()->json();

        $this->assertNull($json['tracks']['summary']);
        $this->assertSame([], $json['tracks']['portions']);
        $this->assertSame([], $json['radio']['portions']);
        $this->assertSame(0, $json['radio']['gsm_r_length_m']);
        $this->getJson('/api/v1/lines/inconnue/infrastructure')->assertNotFound();
    }

    public function test_graphql_and_mcp_serve_the_same_content(): void
    {
        $rest = $this->getJson('/api/v1/lines/rfn-579000/infrastructure')->json();
        $gql = $this->postJson('/graphql', ['query' => '{ lines(first: 10) { data { slug infrastructure {
            tracks { summary { tracks uniform dominant_share dominant_length_m length_m_by_tracks { tracks length_m } banalised_length_m }
                     portions { pk_start_m pk_end_m pk_start_label pk_end_label length_m tracks regime banalised } dataset_modified }
            radio { systems { system length_m } gsm_r gsm_r_length_m portions { pk_start_m pk_end_m pk_start_label pk_end_label length_m system label } dataset_modified }
            attribution provenance { provider license } } } } }'])->assertOk()->assertJsonMissingPath('errors')->json('data.lines.data');
        $bySlug = array_column($gql, 'infrastructure', 'slug');

        $this->assertEquals($rest['tracks'], $bySlug['rfn-579000']['tracks']);
        $this->assertSame($rest['radio'], $bySlug['rfn-579000']['radio']);
        $this->assertSame($rest['attribution'], $bySlug['rfn-579000']['attribution']);
        $this->assertNull($bySlug['rfn-001000']['tracks']['summary']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_infrastructure', 'arguments' => ['line' => '579000']]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) ($result['isError'] ?? false));
        $mcp = json_decode($result['content'][0]['text'], true);
        $this->assertSame($rest['tracks'], $mcp['tracks']);
        $this->assertSame($rest['radio'], $mcp['radio']);
        $this->assertSame('sncf', $mcp['provenance']['provider']);
        $this->assertStringContainsString('SNCF Réseau', $mcp['provenance']['mention']);
    }

    public function test_the_sheet_fact_comes_from_the_regime_and_never_hides_another_source(): void
    {
        $fact = fn (Line $line) => collect(LineFacts::build($line, DB::table('lines')->where('id', $line->id)->first(), [], 2030, true))
            ->firstWhere('key', 'tracks');

        $uniform = $fact($this->line);
        $this->assertSame(['n' => 1, 'source' => 'sncf', 'as_of' => 'today', 'km' => null], array_intersect_key($uniform, array_flip(['n', 'source', 'as_of', 'km'])));
        $this->assertSame('aujourd’hui, d’après SNCF Réseau (régime d’exploitation)', $uniform['note']);

        $mixed = Line::factory()->create(['slug' => 'rfn-500000', 'code' => '500000', 'name' => 'Ligne mixte']);
        $this->track(0, 10000, 'Voie unique', 1, $mixed);
        $this->track(10000, 25000, 'Double voie', 2, $mixed);
        $this->assertSame(['n' => 2, 'km' => 15.0], array_intersect_key($fact($mixed), array_flip(['n', 'km'])));

        $other = Line::factory()->create(['slug' => 'rfn-600000', 'code' => '600000', 'name' => 'Ligne Wikidata', 'nb_voies_default' => 2]);
        $this->track(0, 10000, 'Voie unique', 1, $other);
        $this->assertSame(['n' => 2, 'source' => 'wikidata'], array_intersect_key($fact($other), array_flip(['n', 'source'])),
            'une autre source qui dit autre chose reste affichée');
    }
}
