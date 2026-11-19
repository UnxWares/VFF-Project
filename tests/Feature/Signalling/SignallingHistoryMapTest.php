<?php

namespace Tests\Feature\Signalling;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\Signalling\SignallingSectionLocator;
use App\Services\Signalling\WikipediaArticleCache;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Historique de la signalisation, deuxième passe : portion placée par ses gares (PK calés), tracé sur les
 * formes SNCF Réseau, carte par ère (table et tuiles), API REST et GraphQL avec `era`, outil MCP
 * `signalling_history`, et règles de l'import relevées par la revue du soir (titres partagés, faits orphelins,
 * erreurs passagères de Wikipédia).
 */
class SignallingHistoryMapTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const ARTICLE = <<<'WIKI'
{{Infobox Ligne ferroviaire
| numéro = 974 000
}}
== Histoire ==
En juin 1962, le block automatique lumineux est étendu d'Alphaville à Betaville.
Le block manuel unifié (BMU) équipe la ligne en 1950.
Le KVB est installé en 1989.
WIKI;

    private string $cache;

    private Line $line;

    private Era $era1990;

    private Era $era1950;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        Era::factory()->current()->create(['year' => 2030]);
        $this->era1950 = Era::factory()->create(['year' => 1950, 'is_current' => false]);
        $this->era1990 = Era::factory()->create(['year' => 1990, 'is_current' => false]);
        $this->line = Line::factory()->create([
            'slug' => 'rfn-974000', 'code' => '974000', 'name' => 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche',
            'wikipedia_title' => 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche',
        ]);
        // Tracé nord-sud de 11 km environ : forme SNCF Réseau du PK 10+000 au PK 21+000, même tracé aux deux ères.
        DB::insert("INSERT INTO sncf_line_geometries (code_ligne, libelle, pk_debut_m, pk_fin_m, geom, created_at, updated_at)
            VALUES ('974000', 'Ligne de test', 10000, 21000, ST_GeomFromText('LINESTRING(2.0 48.0, 2.0 48.1)', 4326), now(), now())");
        foreach ([$this->era1950, $this->era1990] as $era) {
            DB::insert("INSERT INTO line_segments (line_id, era_id, geom, status, created_at, updated_at)
                VALUES (?, ?, ST_GeomFromText('LINESTRING(2.0 48.0, 2.0 48.1)', 4326), 'in_service', now(), now())", [$this->line->id, $era->id]);
        }
        $this->station('Alphaville', 48.02, 12_000, 'sncf');
        $this->station('Betaville', 48.08, 18_000, 'sncf');
        $this->cache = sys_get_temp_dir().'/vff-signalling-map-test-'.bin2hex(random_bytes(4));
        mkdir($this->cache);
        $this->article('Ligne de Saint-Cloud à Saint-Nom-la-Bretèche', self::ARTICLE, 240844633);
    }

    protected function tearDown(): void
    {
        // Le nettoyage des fichiers vient APRÈS parent::tearDown() : une erreur de fichier ne doit jamais empêcher
        // l'annulation de la transaction de RefreshDatabase (sinon la connexion reste « idle in transaction » et
        // le test suivant attend indéfiniment sur ses propres insertions).
        try {
            parent::tearDown();
        } finally {
            self::removeCache($this->cache);
        }
    }

    /** Dossier de cache du test : fichiers JSON, sous-dossiers et marqueurs `.nobackup`. */
    private static function removeCache(string $dir): void
    {
        foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $sub) {
            self::removeCache($sub);
        }
        foreach ([...(glob($dir.'/*.json') ?: []), $dir.'/.nobackup'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    private function station(string $name, float $lat, int $pk, string $source, ?Line $line = null): Station
    {
        $station = Station::factory()->create(['name' => $name, 'slug' => 'gare-'.strtolower($name).'-'.$pk]);
        DB::update('UPDATE stations SET geom = ST_SetSRID(ST_MakePoint(2.0, ?), 4326) WHERE id = ?', [$lat, $station->id]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => ($line ?? $this->line)->id, 'method' => 'sncf_pk',
            'pk_m' => $pk, 'pk_source' => $source, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
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

    // ── Portion placée ───────────────────────────────────────────────────

    public function test_a_named_section_is_placed_by_its_end_stations_and_drawn_on_the_sncf_shape(): void
    {
        $this->import();

        $bal = DB::table('line_signalling_events')->where('system', 'BAL')->first();
        $this->assertSame('stations', $bal->section_location);
        $this->assertSame([12_000, 18_000], [(int) $bal->located_pk_start_m, (int) $bal->located_pk_end_m]);
        $this->assertNull($bal->pk_start_m, 'les PK de l’article restent vides : rien n’est inventé');
        $length = (float) DB::scalar('SELECT ST_Length(section_geom::geography) FROM line_signalling_events WHERE id = ?', [$bal->id]);
        $this->assertEqualsWithDelta(6_000 * 11_119.5 / 11_000, $length, 150, 'portion de 6 km de PK sur un tracé de 11,1 km pour 11 km de PK');
    }

    public function test_ambiguous_or_uncalibrated_stations_leave_the_section_unlocated(): void
    {
        $locator = app(SignallingSectionLocator::class);
        $this->station('Gammaville', 48.05, 15_000, 'projected_raw');
        $this->station('Lyon-Perrache', 48.03, 13_000, 'sncf');
        $this->station('Lyon-Part-Dieu', 48.07, 17_000, 'sncf');

        $this->assertSame('stations', $locator->locate($this->line->id, 'section', 'Alphaville', 'Betaville', null, null)['location']);
        $this->assertSame('unlocated', $locator->locate($this->line->id, 'section', 'Alphaville', 'Gammaville', null, null)['location'], 'abscisse brute : pas un PK');
        $this->assertSame('unlocated', $locator->locate($this->line->id, 'section', 'Lyon', 'Betaville', null, null)['location'], 'homonymes éloignés');
        $this->assertSame('unlocated', $locator->locate($this->line->id, 'section', 'Alphaville', null, null, null)['location']);
        $this->assertSame(['location' => 'article_pk', 'pk_start_m' => 12_300, 'pk_end_m' => 20_100],
            array_intersect_key($locator->locate($this->line->id, 'section', null, null, 20_100, 12_300), array_flip(['location', 'pk_start_m', 'pk_end_m'])));
        $this->assertNull($locator->locate($this->line->id, 'whole_line', null, null, null, null)['location']);
        $this->assertSame('st nom la breteche foret de marly', SignallingSectionLocator::key('Gare de Saint-Nom-la-Bretêche - Forêt-de-Marly'));
    }

    // ── Carte par ère ────────────────────────────────────────────────────

    public function test_the_era_map_draws_the_facts_in_force_at_the_end_of_each_past_era(): void
    {
        $this->import();

        $rows = DB::table('line_signalling_history_map as h')->join('line_signalling_events as e', 'e.id', '=', 'h.event_id')
            ->join('eras', 'eras.id', '=', 'h.era_id')->orderBy('eras.year')->orderBy('h.sort')
            ->get(['eras.year', 'e.system', 'h.location', 'h.family']);
        $this->assertSame([[1950, 'BM', 'unknown', 'manual_block'], [1990, 'BM', 'unknown', 'manual_block'], [1990, 'BAL', 'section', 'automatic_block']],
            $rows->map(fn ($r) => [(int) $r->year, $r->system, $r->location, $r->family])->all(), 'le KVB n’est pas dessiné ; l’ère courante jamais');
        $this->assertSame(0, DB::table('line_signalling_history_map')->join('eras', 'eras.id', '=', 'line_signalling_history_map.era_id')->where('eras.is_current', true)->count());
    }

    public function test_a_closed_section_is_not_drawn_at_that_era(): void
    {
        DB::table('line_segments')->where('era_id', $this->era1990->id)->update(['status' => 'dismantled']);
        $this->import();

        $this->assertSame(0, DB::table('line_signalling_history_map')->where('era_id', $this->era1990->id)->count());
    }

    public function test_past_era_tiles_carry_the_history_and_the_current_era_keeps_sncf(): void
    {
        $this->import();

        $past = $this->get('/api/v1/eras/1990/signalling/tiles/6/32/22.mvt')->assertOk()->assertHeader('Content-Type', 'application/x-protobuf');
        $body = $past->getContent();
        $this->assertNotSame('', $body);
        foreach (['vff-signalling', 'history', 'section_from', 'Alphaville', 'oldid=240844633', 'automatic_block'] as $needle) {
            $this->assertStringContainsString($needle, $body);
        }
        $this->assertStringNotContainsString('Le block manuel', $body, 'aucune phrase de l’article dans les tuiles');
        $this->assertStringNotContainsString('history', $this->get('/api/v1/eras/2030/signalling/tiles/6/32/22.mvt')->assertOk()->getContent());
        $this->get('/api/v1/eras/1935/signalling/tiles/6/32/22.mvt')->assertNotFound();
    }

    public function test_dry_run_neither_writes_facts_nor_the_era_map(): void
    {
        $this->import(['--dry-run' => true]);

        $this->assertSame(0, DB::table('line_signalling_events')->count());
        $this->assertSame(0, DB::table('line_signalling_history_map')->count());
    }

    // ── API ──────────────────────────────────────────────────────────────

    public function test_rest_gives_the_located_section_and_the_facts_in_force_at_an_era(): void
    {
        $this->import();

        $this->getJson('/api/v1/lines/rfn-974000/signalling-history?era=1990')
            ->assertOk()
            ->assertJsonPath('era_year', 1990)
            ->assertJsonPath('history.2.system', 'KVB')
            ->assertJsonPath('in_force.0.system', 'BM')
            ->assertJsonPath('in_force.1.system', 'BAL')
            ->assertJsonPath('in_force.1.section.location', 'stations')
            ->assertJsonPath('in_force.1.section.pk_start_m', 12_000)
            ->assertJsonPath('in_force.1.section.from_station.name', 'Alphaville')
            ->assertJsonPath('in_force.1.section.to_station.pk_m', 18_000)
            ->assertJsonPath('in_force.2.system', 'KVB')
            ->assertJsonMissingPath('history.0.id');
        $this->getJson('/api/v1/lines/rfn-974000/signalling-history?era=1950')->assertJsonPath('in_force.0.system', 'BM')->assertJsonCount(1, 'in_force');
        $this->getJson('/api/v1/lines/rfn-974000/signalling-history')->assertJsonMissingPath('in_force');
        $this->getJson('/api/v1/lines/rfn-974000/signalling-history?era=1935')->assertNotFound();
        $this->getJson('/api/v1/lines/rfn-974000/signalling-history?era=abc')->assertStatus(422);
    }

    public function test_graphql_filters_by_era_and_the_sheet_carries_the_history(): void
    {
        $this->import();

        $data = $this->graphQL('{ line(slug: "rfn-974000") {
                all: signalling_history { system origin }
                in1950: signalling_history(era: 1950) { system section { location } }
                in1990: signalling_history(era: 1990) { system section { location pk_start_m from_station { slug name pk_m } } }
                unknown: signalling_history(era: 1935) { system }
                sheet { signalling { history { system date } } }
            } }')->assertJsonMissingPath('errors')->json('data.line');

        $this->assertSame(['BM', 'BAL', 'KVB'], array_column($data['all'], 'system'));
        $this->assertSame(['BM'], array_column($data['in1950'], 'system'));
        $this->assertSame(['BM', 'BAL', 'KVB'], array_column($data['in1990'], 'system'));
        $this->assertSame(12_000, $data['in1990'][1]['section']['pk_start_m']);
        $this->assertSame('Alphaville', $data['in1990'][1]['section']['from_station']['name']);
        $this->assertSame([], $data['unknown']);
        // (6) La fiche GraphQL a l'historique, comme GET /api/v1/lines/{line}.
        $rest = $this->getJson('/api/v1/lines/rfn-974000')->json('data.signalling.history');
        $this->assertSame(array_column($rest, 'system'), array_column($data['sheet']['signalling']['history'], 'system'));
    }

    public function test_graphql_loads_the_history_of_a_list_of_lines_in_one_query(): void
    {
        // (13) Le nombre de requêtes ne dépend pas du nombre de lignes.
        $this->import();
        $count = function (int $lines): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->graphQL("{ lines(first: {$lines}) { data { slug signalling_history { system } } } }")->assertJsonMissingPath('errors');
            $queries = count(array_filter(DB::getQueryLog(), fn (array $q) => str_contains($q['query'], 'line_signalling_events')));
            DB::disableQueryLog();

            return $queries;
        };
        Line::factory()->count(4)->create();

        $this->assertSame(1, $count(2));
        $this->assertSame(1, $count(5));
    }

    public function test_the_mcp_tool_gives_the_history_and_the_era_state_without_any_excerpt(): void
    {
        $this->import();

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => 'signalling_history', 'arguments' => (object) ['line' => '974000', 'year' => 1990]]])
            ->assertOk()->json('result');
        $data = json_decode($result['content'][0]['text'], true);

        $this->assertFalse($result['isError']);
        $this->assertSame(['BM', 'BAL', 'KVB'], array_column($data['history'], 'system'));
        $this->assertSame(['year' => 1990, 'is_current' => false], $data['era']);
        $this->assertSame(['BM', 'BAL', 'KVB'], array_column($data['in_force'], 'system'));
        $this->assertSame('stations', $data['history'][1]['section']['location']);
        // Décision Q22 : aucune phrase de Wikipédia dans le MCP, les permaliens suffisent.
        $this->assertStringNotContainsString('excerpt', $result['content'][0]['text']);
        $this->assertStringNotContainsString('est étendu', $result['content'][0]['text']);
        $this->assertStringContainsString('oldid=240844633', $data['history'][1]['source']['url']);
    }

    // ── Règles de l'import (revue du soir) ───────────────────────────────

    public function test_a_shared_title_without_a_code_is_left_alone_even_with_line_or_limit(): void
    {
        // (8) Le partage se compte sur toute la table, pas sur la sélection.
        $this->article('Ligne de Saint-Cloud à Saint-Nom-la-Bretèche', str_replace("| numéro = 974 000\n", '', self::ARTICLE), 1);
        Line::factory()->create(['slug' => 'autre', 'code' => null, 'wikipedia_title' => 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche']);

        $this->import(['--line' => ['rfn-974000']]);
        $this->import(['--limit' => 1]);

        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }

    public function test_facts_of_a_line_that_is_no_longer_read_are_removed_by_a_full_import(): void
    {
        // (9) Titre retiré, ou article qui annonce un autre code : les faits ne restent pas affichés.
        $this->import();
        $this->assertGreaterThan(0, DB::table('line_signalling_events')->count());

        $this->line->update(['wikipedia_title' => null]);
        Line::factory()->create(['slug' => 'rfn-111111', 'code' => '111111', 'wikipedia_title' => 'Autre article']);
        $this->article('Autre article', "{{Infobox Ligne ferroviaire\n| numéro = 111 111\n}}\n== Histoire ==\nRien.", 2);
        $this->import(['--line' => ['rfn-111111']]);
        $this->assertGreaterThan(0, DB::table('line_signalling_events')->count(), 'un import partiel ne retire rien d’autre');
        $this->import();
        $this->assertSame(0, DB::table('line_signalling_events')->count());
        $this->assertSame(0, DB::table('line_signalling_history_map')->count());

        $this->line->update(['wikipedia_title' => 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche']);
        $this->import();
        $this->assertGreaterThan(0, DB::table('line_signalling_events')->count());
        $this->article('Ligne de Saint-Cloud à Saint-Nom-la-Bretèche', str_replace('974 000', '999 999', self::ARTICLE), 3);
        $this->import();
        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }

    public function test_a_wikipedia_error_is_never_cached_as_a_missing_article(): void
    {
        // (10) Erreur en HTTP 200 (maintenance) : exception, aucun fichier écrit.
        Http::fake(['fr.wikipedia.org/*' => Http::response(['error' => ['code' => 'readonly', 'info' => 'maintenance']])]);
        $dir = $this->cache.'/erreur';
        $cache = new WikipediaArticleCache(app(WikimediaClient::class), $dir);

        try {
            $cache->fill(['Ligne de A à B']);
            $this->fail('une erreur de Wikipédia doit arrêter le remplissage');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('readonly', $e->getMessage());
        }
        $this->assertFalse($cache->has('Ligne de A à B'));
        $this->assertSame([], glob($dir.'/*.json') ?: []);
    }

    public function test_a_page_that_wikipedia_declares_missing_is_remembered(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response(['query' => ['pages' => [
            ['title' => 'Ligne absente', 'missing' => true],
            ['title' => 'Ligne présente', 'revisions' => [['revid' => 7, 'slots' => ['main' => ['content' => 'Texte']]]]],
        ]]])]);
        $dir = $this->cache.'/absente';
        $cache = new WikipediaArticleCache(app(WikimediaClient::class), $dir);

        $this->assertSame(2, $cache->fill(['Ligne absente', 'Ligne présente']));
        $this->assertTrue($cache->has('Ligne absente'));
        $this->assertNull($cache->get('Ligne absente'));
        $this->assertSame(7, $cache->get('Ligne présente')['revid']);
    }
}
