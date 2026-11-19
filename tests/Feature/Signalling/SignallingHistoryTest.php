<?php

namespace Tests\Feature\Signalling;

use App\Models\Era;
use App\Models\Line;
use App\Services\Signalling\LineSignallingHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Historique de la signalisation : commande d'import (cache d'articles de fixture, jamais le réseau),
 * puis les surfaces (fiche web, API REST, GraphQL, MCP). Seuls les faits sûrs sont montrés.
 */
class SignallingHistoryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const ARTICLE = <<<'WIKI'
{{Infobox Ligne ferroviaire
| numéro = 974 000
}}
La '''ligne de Saint-Cloud à Saint-Nom-la-Bretèche''' est une ligne ferroviaire française.
== Histoire ==
En juin 1962, le block automatique lumineux est étendu de Garches à Saint-Nom-la-Bretèche.
Le KVB est installé en 1989.
Vers 1937, le block Banjo est remplacé par du block automatique lumineux entre Bordeaux et Lamothe.
La ligne est électrifiée depuis le 27 août 1950, équipée du BAPR.
WIKI;

    private string $cache;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create([
            'slug' => 'rfn-974000', 'code' => '974000', 'name' => 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche',
            'wikipedia_title' => 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche',
        ]);
        $this->cache = sys_get_temp_dir().'/vff-signalling-test-'.bin2hex(random_bytes(4));
        mkdir($this->cache);
        $this->article('Ligne de Saint-Cloud à Saint-Nom-la-Bretèche', self::ARTICLE, 240844633);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cache.'/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->cache);
        parent::tearDown();
    }

    private function article(string $title, string $content, int $revision): void
    {
        file_put_contents($this->cache.'/'.md5($title).'.json', json_encode([
            'requested' => $title, 'missing' => false, 'title' => $title, 'revid' => $revision, 'content' => $content, 'fetched_at' => '2026-11-19 10:00:00',
        ]));
    }

    private function import(array $options = []): void
    {
        $this->artisan('vff:import-signalling-history', ['--offline' => true, '--cache-dir' => $this->cache] + $options)->assertSuccessful();
    }

    public function test_the_import_stores_facts_with_the_source_revision_and_never_calls_the_network(): void
    {
        $this->import();

        $rows = DB::table('line_signalling_events')->orderBy('event_date')->get();
        // Par date : le « vers 1937 » est gardé mais peu fiable ; la date de l'électrification n'est pas donnée au BAPR.
        $this->assertSame(['BAL', 'BAL', 'KVB'], $rows->pluck('system')->all());
        $this->assertSame(['low', 'high', 'medium'], $rows->pluck('confidence')->all());
        $this->assertSame('speed_control', $rows->firstWhere('system', 'KVB')->role);
        $this->assertSame('1962-06-01', $rows->firstWhere('confidence', 'high')->event_date);
        $this->assertSame('month', $rows->firstWhere('confidence', 'high')->date_precision);
        $this->assertSame('section', $rows->firstWhere('confidence', 'high')->scope);
        $this->assertSame('wikipedia', $rows[0]->source);
        $this->assertSame(240844633, (int) $rows[0]->source_revision);
        $this->assertStringContainsString('oldid=240844633', $rows[0]->source_url);
        $this->assertStringContainsString('title=Ligne_de_Saint-Cloud', $rows[0]->source_url);
        Http::assertNothingSent();
    }

    public function test_the_import_is_replayable_and_drops_facts_that_left_the_article(): void
    {
        $this->import();
        $first = DB::table('line_signalling_events')->orderBy('id')->pluck('fingerprint', 'id')->all();
        $this->import();
        $this->assertSame($first, DB::table('line_signalling_events')->orderBy('id')->pluck('fingerprint', 'id')->all(), 'mêmes lignes, mêmes empreintes');

        $this->article('Ligne de Saint-Cloud à Saint-Nom-la-Bretèche', "== Histoire ==\nLe KVB est installé en 1989.", 240900000);
        $this->import();

        $rows = DB::table('line_signalling_events')->get();
        $this->assertSame(['KVB'], $rows->pluck('system')->all());
        $this->assertSame(240900000, (int) $rows[0]->source_revision, 'la révision lue est mise à jour');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->import(['--dry-run' => true]);

        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }

    public function test_an_article_that_announces_another_line_code_is_rejected(): void
    {
        $this->article('Ligne de Saint-Cloud à Saint-Nom-la-Bretèche', str_replace('974 000', '123 456', self::ARTICLE), 1);
        $this->import();

        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }

    public function test_an_article_of_several_lines_only_gives_low_reliability_facts(): void
    {
        $this->article('Ligne de Saint-Cloud à Saint-Nom-la-Bretèche', str_replace('974 000', '974 000 et 975 000', self::ARTICLE), 1);
        $this->import();

        $this->assertGreaterThan(0, DB::table('line_signalling_events')->count());
        $this->assertSame(0, DB::table('line_signalling_events')->where('confidence', '!=', 'low')->count());
    }

    public function test_a_title_shared_by_several_lines_without_a_code_is_left_alone(): void
    {
        $this->article('Ligne de Saint-Cloud à Saint-Nom-la-Bretèche', str_replace("| numéro = 974 000\n", '', self::ARTICLE), 1);
        Line::factory()->create(['slug' => 'ligne-voisine', 'code' => null, 'name' => 'Voisine', 'wikipedia_title' => 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche']);
        $this->import();

        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }

    public function test_only_sure_facts_are_shown_on_the_sheet_with_their_source(): void
    {
        $this->import();

        $facts = app(LineSignallingHistory::class)->facts($this->line);
        $this->assertSame(['BAL', 'KVB'], array_column($facts, 'system'), 'le fait peu fiable n\'est jamais exposé');
        $this->assertSame(['high', 'medium'], array_column($facts, 'reliability'));
        $this->assertArrayNotHasKey('excerpt', app(LineSignallingHistory::class)->forSheet($this->line)[0]['source']);

        $this->get('/lines/rfn-974000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lines/Show')
            ->has('signalling_history', 2)
            ->where('signalling_history.0.system', 'BAL')
            ->where('signalling_history.0.date', '1962-06-01')
            ->where('signalling_history.0.date_precision', 'month')
            ->where('signalling_history.0.scope', 'section')
            ->where('signalling_history.0.section.from', 'Garches')
            ->where('signalling_history.0.source.provider', 'wikipedia')
            ->missing('signalling_history.0.source.excerpt')
            ->where('signalling_history.1.role', 'speed_control'));
    }

    public function test_the_sheet_has_an_empty_history_when_nothing_is_known(): void
    {
        $this->get('/lines/rfn-974000')->assertOk()->assertInertia(fn (Assert $page) => $page->where('signalling_history', []));
    }

    public function test_the_rest_endpoint_gives_the_facts_with_provenance_and_the_cc_by_sa_attribution(): void
    {
        $this->import();

        $response = $this->getJson('/api/v1/lines/rfn-974000/signalling-history')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->assertJsonPath('line.slug', 'rfn-974000')
            ->assertJsonCount(2, 'history')
            ->assertJsonPath('history.0.system', 'BAL')
            ->assertJsonPath('history.0.role', 'block')
            ->assertJsonPath('history.0.reliability', 'high')
            ->assertJsonPath('history.0.section', ['from' => 'Garches', 'to' => 'Saint-Nom-la-Bretèche', 'pk_start_m' => null, 'pk_end_m' => null])
            ->assertJsonPath('history.0.source.revision', 240844633)
            ->assertJsonPath('history.1.role', 'speed_control')
            ->assertJsonPath('history.1.section', null)
            ->assertJsonPath('provenance.provider', 'wikipedia')
            ->assertJsonPath('provenance.license_status', 'verified');

        $this->assertStringContainsString('CC BY-SA 4.0', $response->json('attribution'));
        $this->assertLessThanOrEqual(200, mb_strlen($response->json('history.0.source.excerpt')));
        $this->getJson('/api/v1/lines/ligne-inconnue/signalling-history')->assertNotFound();
    }

    public function test_the_line_sheet_api_carries_the_history_next_to_the_current_state(): void
    {
        $this->import();

        $sheet = $this->getJson('/api/v1/lines/rfn-974000')->assertOk();
        $sheet->assertJsonCount(2, 'data.signalling.history')
            ->assertJsonPath('data.signalling.history.0.system', 'BAL')
            ->assertJsonPath('data.signalling.ranges', []);
        $this->assertStringEndsWith('/api/v1/lines/rfn-974000/signalling-history', $sheet->json('data.links.signalling_history'));
    }

    public function test_the_history_never_touches_the_current_state_table(): void
    {
        $before = DB::table('line_signalling')->count();
        $this->import();

        $this->assertSame($before, DB::table('line_signalling')->count());
    }

    public function test_graphql_gives_the_same_facts_with_provenance(): void
    {
        $this->import();

        $this->graphQL('{ line(slug: "rfn-974000") { signalling_history {
                system role date date_precision reliability scope
                section { from to pk_start_m pk_end_m }
                source { provider title url revision excerpt }
                attribution provenance { provider license_status }
            } } }')
            ->assertJsonMissingPath('errors')
            ->assertJsonCount(2, 'data.line.signalling_history')
            ->assertJsonPath('data.line.signalling_history.0.system', 'BAL')
            ->assertJsonPath('data.line.signalling_history.0.date', '1962-06-01')
            ->assertJsonPath('data.line.signalling_history.0.section.from', 'Garches')
            ->assertJsonPath('data.line.signalling_history.0.source.revision', 240844633)
            ->assertJsonPath('data.line.signalling_history.0.provenance', ['provider' => 'wikipedia', 'license_status' => 'verified'])
            ->assertJsonPath('data.line.signalling_history.1.section', null);
    }

    public function test_the_mcp_line_sheet_carries_the_history_and_credits_wikipedia(): void
    {
        $this->import();

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => 'get_line', 'arguments' => (object) ['line' => 'rfn-974000', 'include_events' => false]]])
            ->assertOk()->json('result');
        $data = json_decode($result['content'][0]['text'], true);

        $this->assertFalse($result['isError']);
        $history = $data['line']['signalling']['history'];
        $this->assertSame(['BAL', 'KVB'], array_column($history, 'system'));
        $this->assertSame('speed_control', $history[1]['role']);
        $this->assertSame(240844633, $history[0]['source']['revision']);
        // Aucun extrait de Wikipédia dans le MCP (décision Q22) : le permalien, pas la phrase.
        $this->assertArrayNotHasKey('excerpt', $history[0]['source']);
        $this->assertStringContainsString('Wikip', json_encode($data['attribution'], JSON_UNESCAPED_UNICODE));
    }
}
