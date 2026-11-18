<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\ImportLineDescriptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Commandes de la fiche ligne : noms historiques (vff:import-line-names), texte « À propos »
 * tiré de Wikipédia (vff:import-line-descriptions) et cache de la signalisation SNCF
 * (vff:import-sncf-signalling --from-cache), qui rendent ces informations durables.
 */
class LineSheetCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    private function writeJson(string $name, array $content): string
    {
        $path = $this->sandbox.'/'.$name;
        file_put_contents($path, json_encode($content, JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function writePhp(string $name, array $content): string
    {
        $path = $this->sandbox.'/'.$name;
        file_put_contents($path, '<?php return '.var_export($content, true).';');

        return $path;
    }

    // ─── vff:import-line-names ───────────────────────────────────────────

    private function namesFixtures(): array
    {
        $entities = $this->writeJson('entities.json', [
            'Q585219' => [
                'labels' => ['fr' => ['value' => 'LGV Sud-Est'], 'en' => ['value' => 'LGV Sud-Est']],
                'aliases' => ['fr' => [['value' => 'Ligne nouvelle 1'], ['value' => 'LGV Sud-Est']]],
                'sitelinks' => ['frwiki' => ['title' => 'LGV Sud-Est']],
                'claims' => ['P1448' => [
                    ['mainsnak' => ['datavalue' => ['value' => ['text' => 'Ligne à grande vitesse Paris — Lyon', 'language' => 'fr']]],
                        'qualifiers' => ['P580' => [['datavalue' => ['value' => ['time' => '+1981-09-27T00:00:00Z']]]],
                            'P582' => [['datavalue' => ['value' => ['time' => '+1996-01-01T00:00:00Z']]]]]],
                    ['mainsnak' => ['datavalue' => ['value' => ['text' => 'Paris–Lyon high-speed line', 'language' => 'en']]]],
                ]],
            ],
            'Q1' => ['labels' => [], 'aliases' => [], 'claims' => []],
        ]);
        $data = $this->writePhp('noms.php', [
            '752000' => [
                'names' => [['name' => 'LN1', 'from' => 1981, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Sud-Est']],
                'sections' => [[
                    'name' => 'LGV Sud-Est', 'alt_names' => ['LN1', 'LGV Paris - Lyon'], 'pk_start_m' => 0, 'pk_end_m' => 409000,
                    'opening_year_from' => 1981, 'opening_year_to' => 1983, 'description' => 'Première LGV.', 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Sud-Est',
                ]],
            ],
        ]);

        return ['--entities' => $entities, '--data' => $data];
    }

    public function test_line_names_come_with_their_sources_and_keep_other_metadata(): void
    {
        $lgv = $this->rfnLine('752000', 'Ligne de Combs-la-Ville à St-Louis (LGV)', [
            'alt_names' => ['wikidata' => 'Q585219', 'name_source' => 'sncf', 'merged' => [['name' => 'Ancienne fiche']]],
        ]);
        $other = $this->rfnLine('100000', 'Ligne de Alpha à Bravo', ['alt_names' => ['wikidata' => 'Q1']]);

        $this->artisan('vff:import-line-names', $this->namesFixtures())->assertSuccessful();

        $alt = json_decode(DB::table('lines')->where('id', $lgv->id)->value('alt_names'), true);
        $this->assertSame('Q585219', $alt['wikidata'], 'les autres clés sont gardées');
        $this->assertSame('sncf', $alt['name_source']);
        $this->assertSame([['name' => 'Ancienne fiche']], $alt['merged']);
        $byName = array_column($alt['names'], null, 'name');
        // Nom officiel daté (P1448, français seulement, tiret long remplacé), libellé, alias, nom curé.
        $official = $byName['Ligne à grande vitesse Paris - Lyon'];
        $this->assertSame(['wikidata', 1981, 1996], [$official['source'], $official['from'], $official['to']]);
        $this->assertSame('https://www.wikidata.org/wiki/Q585219#P1448', $byName['Ligne à grande vitesse Paris - Lyon']['source_url']);
        $this->assertArrayHasKey('LGV Sud-Est', $byName);
        $this->assertArrayHasKey('Ligne nouvelle 1', $byName);
        $this->assertSame(['wikipedia', 1981], [$byName['LN1']['source'], $byName['LN1']['from']]);
        $this->assertArrayNotHasKey('Paris–Lyon high-speed line', $byName);
        $this->assertCount(4, $alt['names'], 'sans doublon (LGV Sud-Est en libellé, titre et alias)');

        $section = DB::table('line_sections')->where('line_id', $lgv->id)->first();
        $this->assertSame(['LGV Sud-Est', 0, 409000, 1981, 1983], [$section->name, $section->pk_start_m, $section->pk_end_m, $section->opening_year_from, $section->opening_year_to]);
        $this->assertSame(['LN1', 'LGV Paris - Lyon'], json_decode($section->alt_names, true));

        // Item sans nom : rien n'est ajouté.
        $this->assertArrayNotHasKey('names', json_decode(DB::table('lines')->where('id', $other->id)->value('alt_names'), true));

        // Idempotent.
        $snapshot = $this->snapshot(['lines', 'line_sections'], except: ['id']);
        $this->artisan('vff:import-line-names', $this->namesFixtures())->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['lines', 'line_sections'], except: ['id']));
    }

    public function test_line_names_dry_run_writes_nothing(): void
    {
        $this->rfnLine('752000', 'LGV', ['alt_names' => ['wikidata' => 'Q585219']]);
        $before = $this->snapshot(['lines', 'line_sections']);
        $this->artisan('vff:import-line-names', $this->namesFixtures() + ['--dry-run' => true])
            ->expectsOutputToContain('[simulation]')
            ->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['lines', 'line_sections']));
    }

    public function test_line_names_work_without_wikidata_cache(): void
    {
        $line = $this->rfnLine('752000', 'LGV');
        $args = $this->namesFixtures();
        $this->artisan('vff:import-line-names', ['--entities' => $this->sandbox.'/absent.json', '--data' => $args['--data']])
            ->expectsOutputToContain('Cache Wikidata absent')
            ->assertSuccessful();
        $alt = json_decode(DB::table('lines')->where('id', $line->id)->value('alt_names'), true);
        $this->assertSame(['LN1'], array_column($alt['names'], 'name'));
    }

    // ─── vff:import-line-descriptions ────────────────────────────────────

    private function fakeWikipedia(int $status = 200): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response($status === 200 ? ['query' => [
            'normalized' => [['from' => 'LGV_Sud-Est', 'to' => 'LGV Sud-Est']],
            'redirects' => [['from' => 'Ligne de Briouze à Couterne (ancien)', 'to' => 'Ligne de Briouze à Couterne']],
            'pages' => [
                ['title' => 'LGV Sud-Est', 'extract' => "La LGV Sud-Est — dite aussi LN1 — est la première ligne à grande vitesse française.\nElle relie Paris à Lyon ( ).",
                    'revisions' => [['revid' => 1234, 'timestamp' => '2026-10-01T10:00:00Z']]],
                ['title' => 'Ligne de Briouze à Couterne', 'extract' => 'La ligne de Briouze à Couterne est une ancienne ligne ferroviaire normande, fermée par étapes.',
                    'revisions' => [['revid' => 99, 'timestamp' => '2026-09-01T10:00:00Z']]],
                ['title' => 'Saint-Louis', 'extract' => 'Saint-Louis peut désigner plusieurs lieux.', 'pageprops' => ['disambiguation' => '']],
                ['title' => 'Article absent', 'missing' => true],
            ],
        ]] : 'erreur', $status)]);
    }

    public function test_descriptions_come_from_wikipedia_with_attribution_and_never_overwrite_handwritten_text(): void
    {
        $this->fakeWikipedia();
        $lgv = $this->rfnLine('752000', 'LGV', ['wikipedia_title' => 'LGV_Sud-Est', 'description' => null]);
        $closed = $this->rfnLine('434000', 'Ligne de Briouze à La Ferté-Macé', ['wikipedia_title' => 'Ligne de Briouze à Couterne (ancien)', 'description' => null]);
        $manual = $this->rfnLine('100000', 'Ligne déclarée', ['wikipedia_title' => 'LGV_Sud-Est', 'description' => 'Texte rédigé à la main.']);
        $homonymy = $this->rfnLine('200000', 'Ligne X', ['wikipedia_title' => 'Saint-Louis', 'description' => null]);
        $missing = $this->rfnLine('300000', 'Ligne Y', ['wikipedia_title' => 'Article absent', 'description' => null]);
        $cache = $this->sandbox.'/intros';

        $this->artisan('vff:import-line-descriptions', ['--cache-dir' => $cache])
            ->expectsOutputToContain('2 description(s) écrite(s), 1 rédigée(s) à la main conservée(s), 2 sans introduction exploitable.')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->hasHeader('User-Agent', 'VFF-Bot/1.0 (+https://vff-project.org)')
            && $r['exintro'] == 1 && $r['explaintext'] == 1 && $r['redirects'] == 1);
        $this->assertSame("La LGV Sud-Est - dite aussi LN1 - est la première ligne à grande vitesse française.\n\nElle relie Paris à Lyon.",
            DB::table('lines')->where('id', $lgv->id)->value('description'));
        $this->assertStringStartsWith('La ligne de Briouze à Couterne', DB::table('lines')->where('id', $closed->id)->value('description'));
        $this->assertSame('Texte rédigé à la main.', DB::table('lines')->where('id', $manual->id)->value('description'));
        $this->assertNull(DB::table('lines')->where('id', $homonymy->id)->value('description'));
        $this->assertNull(DB::table('lines')->where('id', $missing->id)->value('description'));

        $src = DB::table('line_sources')->where('line_id', $lgv->id)->where('source_type', 'wikipedia-intro')->first();
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=LGV_Sud-Est&oldid=1234', $src->source_url);
        $this->assertSame(['fields' => ['description'], 'title' => 'LGV Sud-Est', 'revision_id' => 1234, 'license' => 'CC BY-SA 4.0'], json_decode($src->fields_provided, true));
        $this->assertSame(0, DB::table('line_sources')->where('line_id', $manual->id)->count());

        // Hors ligne : le cache suffit, aucune requête ; une introduction déjà écrite par la
        // commande est rafraîchie, le texte manuel reste intouché.
        Http::preventStrayRequests();
        Http::fake();
        DB::table('lines')->where('id', $lgv->id)->update(['description' => 'ancienne introduction']);
        $this->artisan('vff:import-line-descriptions', ['--cache-dir' => $cache, '--from-cache' => true])->assertSuccessful();
        Http::assertNothingSent();
        $this->assertStringStartsWith('La LGV Sud-Est', DB::table('lines')->where('id', $lgv->id)->value('description'));
    }

    public function test_descriptions_keep_the_cache_when_wikipedia_is_down(): void
    {
        $this->fakeWikipedia(503);
        $this->rfnLine('752000', 'LGV', ['wikipedia_title' => 'LGV_Sud-Est', 'description' => null]);
        $this->artisan('vff:import-line-descriptions', ['--cache-dir' => $this->sandbox.'/intros'])
            ->expectsOutputToContain('Wikipédia injoignable')
            ->assertSuccessful();
        $this->assertSame(0, DB::table('line_sources')->count());
    }

    public function test_clean_cuts_at_a_sentence_and_follows_the_style_guide(): void
    {
        $long = str_repeat('Une phrase assez longue pour le test. ', 80);
        $out = ImportLineDescriptions::clean($long, 300);
        $this->assertLessThanOrEqual(300, mb_strlen($out));
        $this->assertStringEndsWith('.', $out);
        $this->assertSame('', ImportLineDescriptions::clean('Trop court.'));
        $this->assertStringNotContainsString('—', ImportLineDescriptions::clean('La ligne — longue de 20 km — relie A à B, deux villes de France.'));
    }

    // ─── vff:import-sncf-signalling --from-cache ─────────────────────────

    public function test_signalling_is_cached_and_rebuilt_offline(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $cant = $this->jsonFixture('Sncf/mode-de-cantonnement-des-lignes.records.json');
        $kvb = $this->jsonFixture('Sncf/lignes-equipees-de-kvb.json');
        $up = true;
        Http::fake(function (Request $r) use (&$up, $cant, $kvb) {
            if (! $up) {
                return Http::response('panne', 503);
            }

            return Http::response(str_contains($r->url(), 'mode-de-cantonnement') ? $cant : $kvb);
        });
        $cache = $this->sandbox.'/sncf-signalling';

        $this->artisan('vff:import-sncf-signalling', ['--from-cache' => true, '--cache-dir' => $cache])->assertSuccessful();
        $this->assertFileExists("{$cache}/mode-de-cantonnement-des-lignes.json");
        $this->assertFileExists("{$cache}/lignes-equipees-de-kvb.json");
        $before = $this->snapshot(['line_signalling'], except: ['id']);
        $this->assertGreaterThan(0, DB::table('line_signalling')->where('line_id', $line->id)->count());

        // La table est vidée (base recréée) et SNCF est en panne : le cache reconstitue tout.
        DB::table('line_signalling')->delete();
        $up = false;
        $this->artisan('vff:import-sncf-signalling', ['--from-cache' => true, '--cache-dir' => $cache])
            ->expectsOutputToContain('cache du')
            ->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_signalling'], except: ['id']));
    }
}
