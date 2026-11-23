<?php

namespace Tests\Feature\Speed;

use App\Models\Era;
use App\Models\Line;
use App\Services\Speed\LineSpeeds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Vitesses maximales par PK (vff:import-line-speeds) : import du jeu SNCF Réseau depuis un cache local et
 * des changements datés depuis le cache des articles, sans réseau ; simulation sans écriture ; lecture
 * commune à la fiche ligne, l'API REST, GraphQL et le serveur MCP.
 */
class LineSpeedsTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const ARTICLE = <<<'WIKI'
{{Infobox Ligne ferroviaire
| numéro = 536 000
}}
La '''ligne de Sainte-Pazanne à Pornic''' est une ligne ferroviaire française.
== Histoire ==
Le 4 novembre 2013, la vitesse limite est abaissée de 80 à 60 km/h sur toute la ligne en raison du mauvais état de la voie.
La vitesse de la ligne est relevée à 140 km/h le 15 décembre 2015.
La vitesse sera portée à 160 km/h en 2030.
En 1992, un TGV aborde la courbe à 120 km/h et déraille.
== Projets ==
Un relèvement de la vitesse à 150 km/h en 2019 a été évoqué.
WIKI;

    private string $dir;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create([
            'slug' => 'rfn-536000', 'code' => '536000', 'name' => 'Ligne de Sainte-Pazanne à Pornic',
            'wikipedia_title' => 'Ligne de Sainte-Pazanne à Pornic',
        ]);
        $this->dir = sys_get_temp_dir().'/vff-speeds-test-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        mkdir($this->dir.'/articles');
        file_put_contents($this->dir.'/vitesse-maximale-nominale-sur-ligne.json', json_encode(['fetched_at' => '2026-11-21 02:28:43', 'records' => [
            ['code_ligne' => '536000', 'v_max' => '140', 'pkd' => '034+650', 'pkf' => '050+000', 'rg_troncon' => 1, 'idgaia' => 'a'],
            ['code_ligne' => '536000', 'v_max' => '140', 'pkd' => '050+000', 'pkf' => '058+000', 'rg_troncon' => 1, 'idgaia' => 'b'],
            ['code_ligne' => '536000', 'v_max' => '60', 'pkd' => '058+000', 'pkf' => '061+190', 'rg_troncon' => 1, 'idgaia' => 'c'],
            ['code_ligne' => '999999', 'v_max' => '100', 'pkd' => '000+000', 'pkf' => '010+000', 'rg_troncon' => 1, 'idgaia' => 'd'],
            ['code_ligne' => '536000', 'v_max' => null, 'pkd' => '061+190', 'pkf' => '062+000', 'rg_troncon' => 1, 'idgaia' => 'e'],
        ]]));
        $title = 'Ligne de Sainte-Pazanne à Pornic';
        file_put_contents($this->dir.'/articles/'.md5($title).'.json', json_encode([
            'requested' => $title, 'missing' => false, 'title' => $title, 'revid' => 230000000, 'content' => self::ARTICLE, 'fetched_at' => '2026-11-19 10:00:00',
        ]));
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            foreach ([...(glob($this->dir.'/articles/*.json') ?: []), ...(glob($this->dir.'/*.json') ?: [])] as $file) {
                unlink($file);
            }
            foreach ([$this->dir.'/articles', $this->dir] as $d) {
                if (is_dir($d)) {
                    rmdir($d);
                }
            }
        }
    }

    private function import(array $options = []): void
    {
        $this->artisan('vff:import-line-speeds', ['--offline' => true, '--cache-dir' => $this->dir, '--articles-dir' => $this->dir.'/articles'] + $options)
            ->assertSuccessful();
    }

    public function test_the_dry_run_writes_nothing(): void
    {
        $this->import(['--dry-run' => true]);

        $this->assertSame(0, DB::table('line_speed_ranges')->count());
        $this->assertSame(0, DB::table('line_speed_events')->count());
    }

    public function test_the_import_stores_sncf_ranges_by_pk_and_dated_changes_without_network(): void
    {
        $this->import();

        // Ligne inconnue et vitesse absente écartées.
        $this->assertSame(3, DB::table('line_speed_ranges')->where('line_id', $this->line->id)->count());
        $this->assertSame(3, DB::table('line_speed_ranges')->count());
        $events = DB::table('line_speed_events')->orderBy('event_date')->get();
        // Futur, accident et section « Projets » écartés.
        $this->assertSame([60, 140], $events->pluck('speed_kmh')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(['lower', 'raise'], $events->pluck('kind')->all());
        $this->assertSame(80, (int) $events[0]->previous_speed_kmh);
        $this->assertSame('whole_line', $events[0]->scope);
        $this->assertSame(['high', 'high'], $events->pluck('confidence')->all());
        $this->assertStringContainsString('oldid=230000000', $events[1]->source_url);

        // Rejouable : pas de doublon.
        $this->import();
        $this->assertSame(2, DB::table('line_speed_events')->count());
        $this->assertSame(3, DB::table('line_speed_ranges')->count());
    }

    public function test_rest_graphql_and_sheet_read_the_same_merged_ranges(): void
    {
        $this->import();

        $json = $this->getJson('/api/v1/lines/rfn-536000/speeds')->assertOk()
            ->assertJsonPath('era_year', 2030)
            ->assertJsonPath('summary.max_speed_kmh', 140)
            ->assertJsonPath('summary.min_speed_kmh', 60)
            ->assertJsonPath('provenance.ranges.license', 'ODbL-1.0')
            ->json();
        // Deux tranches voisines à 140 km/h réunies.
        $this->assertSame([[34650, 58000, 140], [58000, 61190, 60]],
            array_map(fn (array $r) => [$r['pk_start_m'], $r['pk_end_m'], $r['max_speed_kmh']], $json['ranges']));
        $this->assertSame('34+650', $json['ranges'][0]['pk_start_label']);
        $this->assertSame(['lower', 'raise'], array_column($json['history'], 'kind'));
        $this->assertNotNull($json['history'][0]['source']['excerpt']);

        $this->getJson('/api/v1/lines/rfn-536000/speeds?era=1850')->assertNotFound();

        $gql = $this->graphQL('{ line(slug: "rfn-536000") { speeds { era_year ranges { pk_start_m max_speed_kmh } summary { max_speed_kmh by_speed { max_speed_kmh length_m } } history { kind speed_kmh previous_speed_kmh date_precision source { revision } } } } }')
            ->assertJsonMissingPath('errors')->json('data.line.speeds');
        $this->assertSame(array_column($json['ranges'], 'max_speed_kmh'), array_column($gql['ranges'], 'max_speed_kmh'));
        $this->assertSame([['max_speed_kmh' => 140, 'length_m' => 23350], ['max_speed_kmh' => 60, 'length_m' => 3190]], $gql['summary']['by_speed']);
        $this->assertSame(230000000, $gql['history'][1]['source']['revision']);

        $this->getWithDeferredProps('/lines/rfn-536000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('speeds.summary.max_speed_kmh', 140)
            ->has('speeds.ranges', 2)
            ->has('speeds.history', 2));
    }

    public function test_the_mcp_tool_gives_ranges_and_history_without_excerpts(): void
    {
        $this->import();

        $res = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_speeds', 'arguments' => ['line' => '536000']]])
            ->assertOk();
        $text = (string) $res->json('result.content.0.text');
        $payload = json_decode($text, true);
        $this->assertIsArray($payload, $text);
        $this->assertSame(140, $payload['summary']['max_speed_kmh']);
        $this->assertCount(2, $payload['ranges']);
        $this->assertCount(2, $payload['history']);
        $this->assertArrayNotHasKey('excerpt', $payload['history'][0]['source']);
        $this->assertStringNotContainsString('mauvais état', $text);
    }

    public function test_a_line_without_speed_has_empty_lists(): void
    {
        $other = Line::factory()->create(['slug' => 'sans-vitesse', 'code' => null]);

        $this->getJson('/api/v1/lines/'.$other->slug.'/speeds')->assertOk()
            ->assertJsonPath('ranges', [])->assertJsonPath('history', [])
            ->assertJsonPath('summary.max_speed_kmh', null);
    }

    /** Deux itinéraires SNCF d'une ligne (rg_troncon 1 et 2) qui se recouvrent : la longueur couverte n'est comptée qu'une fois. */
    public function test_overlapping_routes_are_covered_once(): void
    {
        $summary = LineSpeeds::summary([
            ['pk_start_m' => 57109, 'length_m' => 5668, 'max_speed_kmh' => 115],
            ['pk_start_m' => 60215, 'length_m' => 2685, 'max_speed_kmh' => 90],
            ['pk_start_m' => 62777, 'length_m' => 8736, 'max_speed_kmh' => 105],
            ['pk_start_m' => 80000, 'length_m' => 1000, 'max_speed_kmh' => 105],
        ]);

        $this->assertSame(71513 - 57109 + 1000, $summary['covered_m']);
        $this->assertSame([115, 90], [$summary['max_speed_kmh'], $summary['min_speed_kmh']]);
    }
}
