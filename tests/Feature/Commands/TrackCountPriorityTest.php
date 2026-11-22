<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\ImportSncfTrackRegime;
use App\Services\TrackCountPriority;
use App\Services\WikipediaLineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Règle commune de `lines.nb_voies_default` : la source datée du réseau actuel (trace `line_sources` « sncf-regime »
 * avec le champ) n'est remplacée par aucune autre commande ; dans l'autre sens, l'import SNCF ne remplace que sa
 * propre valeur ou un champ vide. Les imports de lignes-oubliees et de Wikidata sont testés dans leurs fichiers.
 */
class TrackCountPriorityTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
    }

    /** @param  list<string>  $fields */
    private function trace(int $lineId, array $fields = ['line_track_portions', 'nb_voies_default']): void
    {
        DB::table('line_sources')->insert(['line_id' => $lineId, 'source_type' => ImportSncfTrackRegime::SOURCE_TYPE, 'source_url' => 'https://example.org/s', 'fetched_at' => now(),
            'fields_provided' => json_encode($fields), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function rfnLineValue(int $id): int
    {
        return (int) DB::table('lines')->where('id', $id)->value('nb_voies_default');
    }

    public function test_only_a_trace_that_claims_the_field_locks_it(): void
    {
        $owned = $this->rfnLine('100000', 'A', ['nb_voies_default' => 2]);
        $portionsOnly = $this->rfnLine('200000', 'B', ['nb_voies_default' => 2]);
        $untraced = $this->rfnLine('300000', 'C', ['nb_voies_default' => 2]);
        $this->trace($owned->id);
        $this->trace($portionsOnly->id, ['line_track_portions']);
        DB::table('line_sources')->insert(['line_id' => $untraced->id, 'source_type' => 'wikipedia', 'source_url' => 'https://example.org/s', 'fetched_at' => now(), 'fields_provided' => json_encode(['nb_voies_default']),
            'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame([$owned->id => true], TrackCountPriority::lockedLineIds([$owned->id, $portionsOnly->id, $untraced->id]));
        $this->assertSame([], TrackCountPriority::lockedLineIds([]));
        $this->assertTrue(TrackCountPriority::isLocked($owned->id));
        $this->assertFalse(TrackCountPriority::isLocked($portionsOnly->id));
        $this->assertFalse(TrackCountPriority::isLocked($untraced->id), 'la trace d\'une autre source ne verrouille rien');
    }

    public function test_filter_drops_only_the_track_count_of_a_locked_line(): void
    {
        $locked = $this->rfnLine('100000', 'A', ['nb_voies_default' => 2]);
        $free = $this->rfnLine('200000', 'B', ['nb_voies_default' => 2]);
        $this->trace($locked->id);
        $update = ['nb_voies_default' => 1, 'max_speed_kmh_official' => 90];

        $this->assertSame(['max_speed_kmh_official' => 90], TrackCountPriority::filterUpdate($locked->id, $update));
        $this->assertSame($update, TrackCountPriority::filterUpdate($free->id, $update));
        $this->assertSame(['max_speed_kmh_official' => 90], TrackCountPriority::filterUpdate($locked->id, ['max_speed_kmh_official' => 90]));
    }

    /** @param  array<string, string>  $metadata */
    private function fakeParser(array $metadata): void
    {
        $parser = $this->createMock(WikipediaLineParser::class);
        $parser->method('fetchAndParse')->willReturn(['found' => true, 'title' => 'Ligne de test', 'url' => 'https://fr.wikipedia.org/wiki/Ligne_de_test',
            'metadata' => $metadata, 'events' => [], 'companies' => [], 'regions' => [], 'departements' => []]);
        $this->app->instance(WikipediaLineParser::class, $parser);
    }

    public function test_wikipedia_enrichment_does_not_overwrite_the_sncf_track_count(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de test', ['nb_voies_default' => 2]);
        $this->trace($line->id);
        $this->fakeParser(['nombre de voies act' => 'Voie unique (1)', 'vitesse' => '90 km/h']);

        $this->artisan('vff:enrich-line-wikipedia', ['--slug' => $line->slug, '--sleep' => 0])->assertSuccessful();

        $row = DB::table('lines')->where('id', $line->id)->first();
        $this->assertSame(2, (int) $row->nb_voies_default, 'la source datée du réseau actuel prime');
        $this->assertSame(90, (int) $row->max_speed_kmh_official, 'les autres champs sont bien enrichis');
        $this->assertNotContains('nb_voies_default', (array) json_decode((string) DB::table('line_sources')
            ->where('line_id', $line->id)->where('source_type', 'wikipedia')->value('fields_provided'), true), 'la trace Wikipédia ne revendique pas le champ');
    }

    public function test_wikipedia_enrichment_still_writes_the_track_count_without_a_sncf_trace(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de test', ['nb_voies_default' => 2]);
        $this->fakeParser(['nombre de voies act' => 'Voie unique (1)']);

        $this->artisan('vff:enrich-line-wikipedia', ['--slug' => $line->slug, '--sleep' => 0])->assertSuccessful();

        $this->assertSame(1, (int) DB::table('lines')->where('id', $line->id)->value('nb_voies_default'));
    }

    public function test_sncf_import_never_replaces_a_value_written_by_wikipedia_without_trace(): void
    {
        $line = $this->rfnLine('100000', 'A', ['nb_voies_default' => 1]);
        Http::fake([
            '*/exports/json*' => Http::response([['code_ligne' => '100000', 'lib_ligne' => 'A', 'exploitati' => 'Double voie', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '050+000']]),
            '*' => Http::response(['metas' => ['default' => ['license' => 'Open Database License (ODbL)', 'publisher' => 'SNCF Réseau', 'modified' => '2022-03-24T09:50:03+00:00']]]),
        ]);

        $this->artisan('vff:import-sncf-track-regime')->assertSuccessful();

        $this->assertSame(1, (int) DB::table('lines')->where('id', $line->id)->value('nb_voies_default'), 'valeur d\'une autre source : jamais remplacée');
        $this->assertFalse(TrackCountPriority::isLocked($line->id));
    }

    public function test_a_wikipedia_run_after_the_sncf_import_leaves_the_sncf_value_in_place(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de test');
        Http::fake([
            '*/exports/json*' => Http::response([['code_ligne' => '100000', 'lib_ligne' => 'A', 'exploitati' => 'Double voie', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '050+000']]),
            '*' => Http::response(['metas' => ['default' => ['license' => 'Open Database License (ODbL)', 'publisher' => 'SNCF Réseau', 'modified' => '2022-03-24T09:50:03+00:00']]]),
        ]);
        $this->artisan('vff:import-sncf-track-regime')->assertSuccessful();
        $this->assertSame(2, (int) DB::table('lines')->where('id', $line->id)->value('nb_voies_default'));
        $this->assertTrue(TrackCountPriority::isLocked($line->id));

        $this->fakeParser(['nombre de voies act' => 'Voie unique (1)']);
        $this->artisan('vff:enrich-line-wikipedia', ['--slug' => $line->slug, '--sleep' => 0])->assertSuccessful();

        $this->assertSame(2, (int) DB::table('lines')->where('id', $line->id)->value('nb_voies_default'));
        $this->artisan('vff:import-sncf-track-regime')->assertSuccessful();
        $this->assertSame(2, $this->rfnLineValue($line->id));
        $this->assertTrue(TrackCountPriority::isLocked($line->id), 'la trace survit au second import');
    }
}
