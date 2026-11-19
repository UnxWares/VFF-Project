<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use App\Services\Materials\SourceCache;
use App\Services\Materials\WikipediaMaterialSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\InMemorySourceCache;
use Tests\TestCase;

/**
 * Périodes de circulation (vff:import-material-periods) : import depuis le cache des articles et de
 * Wikidata, rattachement aux liens attestés, fiabilité, rejouabilité, --dry-run, puis surfaces (fiches
 * du site, REST, GraphQL, MCP) qui ne montrent que les périodes sûres et jamais la phrase lue.
 */
class MaterialPeriodsTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const LINE_TITLE = 'Ligne de Valence à Moirans';

    private InMemorySourceCache $cache;

    private Line $line;

    private Material $x72500;

    private Material $b82500;

    private Material $cc72000;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->line = Line::factory()->create(['slug' => 'rfn-908000', 'code' => '908000', 'name' => 'Ligne de Valence à Moirans', 'wikipedia_title' => self::LINE_TITLE]);
        $this->x72500 = Material::factory()->create(['slug' => 'x-72500', 'name' => 'X 72500', 'type' => 'autorail', 'wikipedia_fr' => 'X 72500', 'wikidata_id' => 'Q1', 'in_service_from' => 1997, 'in_service_to' => null]);
        $this->b82500 = Material::factory()->create(['slug' => 'b-82500', 'name' => 'B 82500', 'type' => 'bimode', 'wikipedia_fr' => 'B 82500', 'wikidata_id' => 'Q2', 'in_service_from' => 2007, 'in_service_to' => null]);
        $this->cc72000 = Material::factory()->create(['slug' => 'cc-72000', 'name' => 'CC 72000', 'type' => 'locomotive_diesel', 'wikipedia_fr' => 'CC 72000', 'wikidata_id' => 'Q3', 'in_service_from' => 1967, 'in_service_to' => null]);
        foreach ([$this->x72500, $this->b82500, $this->cc72000] as $m) {
            LineMaterial::create(['line_id' => $this->line->id, 'material_id' => $m->id, 'source' => 'wikipedia', 'confidence' => 'high',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1', 'evidence' => [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.', 'url' => 'https://fr.wikipedia.org/w/index.php?oldid=1']]]);
        }
        DB::table('wikidata_lines')->insert(['qid' => 'Q908000', 'category' => 'line', 'line_id' => $this->line->id, 'fetched_at' => now()]);

        $this->cache = new InMemorySourceCache;
        $this->app->instance(SourceCache::class, $this->cache);
        $this->articles("== Matériel roulant ==\n"
            ."* [[CC 72000]] du dépôt de Vénissieux (de 1971 à 1989).\n"
            ."* [[B 82500]] du dépôt de Lyon-Vaise, mis en service à partir du 6 septembre 2009, en remplacement des [[X 72500]].\n"
            ."* [[X 72500]] du dépôt de Vénissieux (dès 1990).\n");
        $this->cache->tables['wikipedia_materials'] = [];
        // Wikidata : fin de circulation des CC 72000 déclarée au jour près.
        $this->cache->tables['vehicle_usage_periods'] = ['rows' => [
            ['subject' => 'Q908000', 'material' => 'Q3', 'from' => null, 'from_precision' => null, 'to' => '2001-06-24', 'to_precision' => 11],
        ]];
    }

    private function articles(string $lineText): void
    {
        $this->cache->tables['wikipedia_lines'] = [self::LINE_TITLE => [
            'title' => self::LINE_TITLE, 'revid' => 230000000,
            'sections' => WikipediaMaterialSections::sections($lineText, WikipediaMaterialSections::LINE_SECTIONS),
        ]];
    }

    private function link(Material $m): LineMaterial
    {
        return LineMaterial::query()->where('line_id', $this->line->id)->where('material_id', $m->id)->firstOrFail();
    }

    public function test_import_reads_dated_facts_and_attaches_them_to_the_links(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();

        $cc = $this->link($this->cc72000)->periods;
        $this->assertSame([['1971-01-01', 'year', '1989-01-01', 'year', 'medium', 'line_article'], [null, null, '2001-06-24', 'day', 'high', 'wikidata']],
            $cc->map(fn (LineMaterialPeriod $p): array => [$p->start_date?->toDateString(), $p->start_precision, $p->end_date?->toDateString(), $p->end_precision, $p->confidence, $p->origin])->all());
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Ligne_de_Valence_%C3%A0_Moirans&oldid=230000000', $cc[0]->source_url);
        $this->assertSame(230000000, $cc[0]->source_revision);
        $this->assertSame('https://www.wikidata.org/wiki/Q908000#P3438', $cc[1]->source_url);

        $b = $this->link($this->b82500)->periods->first();
        $this->assertSame(['2009-09-06', 'day', null, 'high'], [$b->start_date->toDateString(), $b->start_precision, $b->end_date, $b->confidence]);
        // Remplacée le 6 septembre 2009 ; « dès 1990 » est antérieur à la mise en service de la série (1997) : jamais montré.
        $x = $this->link($this->x72500)->periods;
        $this->assertSame([['1990-01-01', 'low'], ['2009-09-06', 'high']], $x->map(fn ($p): array => [($p->start_date ?? $p->end_date)->toDateString(), $p->confidence])->all());
        $this->assertSame(['2009-09-06'], $this->link($this->x72500)->shownPeriods->map(fn ($p) => $p->end_date->toDateString())->all());
    }

    public function test_import_is_replayable_and_removes_what_the_sources_no_longer_say(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();
        $ids = LineMaterialPeriod::query()->orderBy('id')->pluck('id')->all();

        $this->artisan('vff:import-material-periods', ['--offline' => true])
            ->expectsOutputToContain('Périodes : 0 créée(s), 0 mise(s) à jour, 0 retirée(s).')
            ->assertSuccessful();
        $this->assertSame($ids, LineMaterialPeriod::query()->orderBy('id')->pluck('id')->all());

        $this->articles("== Matériel roulant ==\n* [[CC 72000]] du dépôt de Vénissieux (de 1971 à 1989).\n");
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();
        $this->assertSame(0, $this->link($this->b82500)->periods()->count());
        $this->assertSame(2, $this->link($this->cc72000)->periods()->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true, '--dry-run' => true])
            ->expectsOutputToContain('--dry-run')
            ->assertSuccessful();
        $this->assertSame(0, LineMaterialPeriod::query()->count());
    }

    public function test_a_fact_without_an_attested_link_is_left_out_and_periods_go_with_their_link(): void
    {
        $this->link($this->b82500)->delete();
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();
        $this->assertSame(0, LineMaterialPeriod::query()->whereHas('link', fn ($q) => $q->where('material_id', $this->b82500->id))->count());

        $this->link($this->cc72000)->delete();
        $this->assertSame(0, LineMaterialPeriod::query()->whereHas('link', fn ($q) => $q->where('material_id', $this->cc72000->id))->count());
    }

    public function test_line_sheet_shows_sure_periods_only(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();

        $this->get('/lines/rfn-908000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('materials.0.slug', 'b-82500')
            ->where('materials.0.periods', [[
                'start_date' => '2009-09-06', 'start_precision' => 'day', 'end_date' => null, 'end_precision' => null,
                'section_from' => null, 'section_to' => null, 'source' => 'wikipedia',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Valence_%C3%A0_Moirans&oldid=230000000',
            ]])
            ->where('materials.1.slug', 'cc-72000')
            ->has('materials.1.periods', 2)
            ->where('materials.2.slug', 'x-72500')
            ->has('materials.2.periods', 1));
    }

    public function test_material_sheet_lists_the_periods_of_each_line(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();

        $this->get('/materials/cc-72000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.slug', 'rfn-908000')
            ->where('lines.0.periods.0.start_date', '1971-01-01')
            ->where('lines.0.periods.1.end_date', '2001-06-24'));
    }

    public function test_rest_graphql_and_mcp_serve_the_periods_without_the_sentence(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();

        $rest = $this->getJson('/api/v1/lines/rfn-908000/materials')->assertOk();
        $x = collect($rest->json('data'))->firstWhere('material.slug', 'x-72500');
        $this->assertSame([[
            'start_date' => null, 'start_precision' => null, 'end_date' => '2009-09-06', 'end_precision' => 'day', 'section' => null,
            'confidence' => 'high', 'origin' => 'line_article',
            'source' => ['title' => self::LINE_TITLE, 'revision' => 230000000, 'url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Valence_%C3%A0_Moirans&oldid=230000000'],
            'provenance' => ['provider' => 'wikipedia', 'name' => 'Wikipédia (FR)', 'license' => 'CC BY-SA 4.0 pour les textes ; les faits (dates) ne sont pas protégés', 'license_status' => 'verified'],
        ]], $x['periods']);
        $this->assertStringNotContainsString('remplacement des', (string) json_encode($rest->json('data.*.periods')));

        $this->getJson('/api/v1/materials/cc-72000')->assertOk()
            ->assertJsonPath('data.lines.0.periods.*.origin', ['line_article', 'wikidata'])
            ->assertJsonPath('data.lines.0.periods.1.provenance.provider', 'wikidata');

        $links = collect($this->graphQL('{ line(slug: "rfn-908000") { material_links { material { slug } periods { start_date end_date confidence origin provenance { provider } } } } }')->assertGraphQLErrorFree()->json('data.line.material_links'));
        $this->assertSame(
            [['start_date' => '1971-01-01', 'end_date' => '1989-01-01', 'confidence' => 'medium', 'origin' => 'line_article', 'provenance' => ['provider' => 'wikipedia']],
                ['start_date' => null, 'end_date' => '2001-06-24', 'confidence' => 'high', 'origin' => 'wikidata', 'provenance' => ['provider' => 'wikidata']]],
            $links->firstWhere('material.slug', 'cc-72000')['periods'],
        );
        $this->graphQL('{ line(slug: "rfn-908000") { sheet { materials { slug periods { start_date end_date source source_url } } } } }')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.line.sheet.materials.0.periods.0.start_date', '2009-09-06');

        $mcp = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_materials', 'arguments' => ['line' => '908000']]])
            ->assertOk()->json('result');
        $this->assertFalse($mcp['isError']);
        $text = $mcp['content'][0]['text'];
        $data = json_decode($text, true);
        $this->assertSame(3, $data['total']);
        $this->assertSame(3, $data['dated']);
        $this->assertSame('high', collect($data['materials'])->firstWhere('material.slug', 'b-82500')['periods'][0]['confidence']);
        $this->assertStringNotContainsString('Vénissieux', $text, 'aucune phrase des articles dans la réponse MCP');
        $this->assertStringNotContainsString('quote', $text);
    }
}
