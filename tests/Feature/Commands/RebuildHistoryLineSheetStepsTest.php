<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\RebuildHistory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:rebuild-history rend durables les informations de la fiche ligne qui avaient
 * disparu (base recréée le 15/10/2026, étapes hors pipeline) : signalisation SNCF par PK,
 * noms historiques et sections nommées, texte « À propos » tiré de Wikipédia. Les trois
 * étapes tournent hors ligne quand les caches existent.
 */
class RebuildHistoryLineSheetStepsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    /** @return list<array{0: string, 1: array<string, mixed>}> */
    private static function derivations(): array
    {
        return (new \ReflectionClassConstant(RebuildHistory::class, 'DERIVATIONS'))->getValue();
    }

    public function test_line_sheet_steps_are_in_the_pipeline_after_the_lines_are_final(): void
    {
        $steps = self::derivations();
        $names = array_column($steps, 0);
        $at = fn (string $n) => array_search($n, $names, true);

        foreach (['vff:import-sncf-signalling', 'vff:import-line-names', 'vff:import-line-descriptions'] as $step) {
            $this->assertNotFalse($at($step), "{$step} manque au pipeline");
            // Après les fusions de lignes et le classement (codes et lignes définitifs).
            $this->assertGreaterThan($at('vff:merge-split-lines'), $at($step));
            $this->assertGreaterThan($at('vff:classify-lines'), $at($step));
        }
        $args = array_column($steps, 1, 0);
        $this->assertSame(['--from-cache' => true], $args['vff:import-sncf-signalling'], 'hors ligne : cache SNCF');
        $this->assertSame(['--from-cache' => true], $args['vff:import-line-descriptions'], 'hors ligne : cache Wikipédia');

        // Chaque étape existe et accepte ses options.
        $commands = $this->app->make(Kernel::class)->all();
        foreach ($steps as [$name, $options]) {
            $this->assertArrayHasKey($name, $commands, $name);
            foreach (array_keys($options) as $opt) {
                $this->assertTrue($commands[$name]->getDefinition()->hasOption(ltrim($opt, '-')), "{$name} {$opt}");
            }
        }

        // Sans cache ni réseau, la signalisation sort en échec sans casser la chaîne.
        $nonBlocking = (new \ReflectionClassConstant(RebuildHistory::class, 'NON_BLOCKING'))->getValue();
        $this->assertContains('vff:import-sncf-signalling', $nonBlocking);
    }

    public function test_after_a_database_rebuild_the_three_steps_restore_the_line_sheet_offline(): void
    {
        $this->sandboxStorage();
        $this->era(2030, current: true);
        $line = $this->rfnLine('752000', 'Ligne de Combs-la-Ville à St-Louis (LGV)', [
            'alt_names' => ['wikidata' => 'Q585219'], 'wikipedia_title' => 'LGV Sud-Est', 'description' => null,
        ]);
        DB::table('sncf_line_catlig')->insert(['code_ligne' => '752000', 'catlig' => 'Ligne à grande vitesse']);

        // Caches locaux (ceux que les imports en ligne ont déposés).
        $sources = $this->sandbox.'/app/sources';
        @mkdir("{$sources}/sncf-signalling", 0775, true);
        @mkdir("{$sources}/wikipedia-intros", 0775, true);
        @mkdir("{$sources}/wikidata", 0775, true);
        file_put_contents("{$sources}/sncf-signalling/mode-de-cantonnement-des-lignes.json", json_encode(['records' => [
            ['libelle' => 'Transmission voie-machine 300', 'code_ligne' => '752000', 'pkd' => '000+000', 'pkf' => '409+000'],
        ]]));
        file_put_contents("{$sources}/sncf-signalling/lignes-equipees-de-kvb.json", json_encode(['records' => [
            ['code_ligne' => '752000', 'pkd' => '000+000', 'pkf' => '010+000'],
        ]]));
        file_put_contents("{$sources}/wikipedia-intros/".md5('LGV Sud-Est').'.json', json_encode([
            'requested' => 'LGV Sud-Est', 'title' => 'LGV Sud-Est', 'revid' => 239633865, 'missing' => false, 'disambiguation' => false,
            'extract' => 'La LGV Sud-Est, ou ligne nouvelle 1 (LN1), est une ligne à grande vitesse française longue de 409 km.',
            'fetched_at' => '2026-10-19 23:45:00',
        ]));
        file_put_contents("{$sources}/wikidata/entities.json", json_encode(['Q585219' => [
            'labels' => ['fr' => ['value' => 'LGV Sud-Est']], 'aliases' => [], 'sitelinks' => ['frwiki' => ['title' => 'LGV Sud-Est']], 'claims' => [],
        ]]));

        Http::preventStrayRequests();
        Http::fake();
        $args = array_column(self::derivations(), 1, 0);
        foreach (['vff:import-sncf-signalling', 'vff:import-line-names', 'vff:import-line-descriptions'] as $step) {
            // En test, le cache n'est lu que dans un dossier explicite (jamais le vrai cache).
            $extra = $step === 'vff:import-sncf-signalling' ? ['--cache-dir' => "{$sources}/sncf-signalling"]
                : ($step === 'vff:import-line-descriptions' ? ['--cache-dir' => "{$sources}/wikipedia-intros"] : []);
            $this->artisan($step, $args[$step] + $extra)->assertSuccessful();
        }
        Http::assertNothingSent();

        // Signalisation : cabine TVM sur la LGV, jamais de KVB.
        $sig = DB::table('line_signalling')->where('line_id', $line->id)->first();
        $this->assertSame(['TVM-300', ['TVM-300'], false], [$sig->signalling, json_decode($sig->train_protection, true), (bool) $sig->kvb_equipped]);
        // Noms : LGV Sud-Est, LGV Paris - Lyon, LN1 (données curées) ; sections nommées.
        $names = array_column(json_decode(DB::table('lines')->where('id', $line->id)->value('alt_names'), true)['names'], 'name');
        $this->assertContains('LGV Paris - Lyon', $names);
        $this->assertContains('LN1', $names);
        $this->assertSame(['LGV Sud-Est', 'LGV Rhône-Alpes', 'LGV Méditerranée'],
            DB::table('line_sections')->where('line_id', $line->id)->orderBy('pk_start_m')->pluck('name')->all());
        // À propos : introduction attribuée.
        $this->assertStringStartsWith('La LGV Sud-Est', DB::table('lines')->where('id', $line->id)->value('description'));
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=LGV_Sud-Est&oldid=239633865',
            DB::table('line_sources')->where('line_id', $line->id)->where('source_type', 'wikipedia-intro')->value('source_url'));
    }
}
