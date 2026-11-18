<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-heritage-lines : exploitations associatives (trains touristiques,
 * vélorails) observées sur les tracés IGN / OSM, datées par les sources
 * (fiche lignes-oubliees « État actuel »), écrites en événements
 * heritage_start / heritage_end sur la PORTION exploitée. Wikipédia et
 * Wikidata hors ligne (--from-cache, client Wikimedia neutralisé).
 */
class HeritageLinesCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Line $cevennes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::fake();
        $this->era(2030, current: true);
        $this->app->instance(WikimediaClient::class, new class extends WikimediaClient
        {
            public function fetchPages(array $titles, bool $leadOnly = false): array
            {
                return [];
            }

            public function api(array $params, string $endpoint = self::FRWIKI_API): array
            {
                return [];
            }
        });

        $this->cevennes = $this->lineWithTrace('999100', 'Ligne de Anduze à Saint-Jean-du-Gard', 'LINESTRING(3.98 44.05, 3.88 44.10)');
        $this->bdtopo('b1', 'LINESTRING(3.98 44.05, 3.88 44.10)', ['cpx_toponyme' => 'Train à Vapeur des Cévennes', 'etat_de_l_objet' => 'En service']);
        // Fiche lignes-oubliees rattachée à la ligne (événement importé de cette fiche).
        DB::table('line_events')->insert(['line_id' => $this->cevennes->id, 'type' => 'opening', 'event_date' => '1909-01-01', 'source' => 'lignes-oubliees',
            'description' => 'Ouverture', 'source_url' => 'https://www.lignes-oubliees.com/index.php?page=anduze', 'created_at' => now(), 'updated_at' => now()]);
        File::ensureDirectoryExists(storage_path('app/sources/lignes-oubliees/fiches'));
        File::put(storage_path('app/sources/lignes-oubliees/fiches/anduze.html'),
            '<dl><dt>État actuel de la ligne</dt><dd><span>Anduze - Saint-Jean-du-Gard :</span> train touristique (depuis le 3 juin 1982)</dd></dl><p>Mise à jour : 12/03/2024</p>');
    }

    private function lineWithTrace(string $code, string $name, string $wkt): Line
    {
        $line = $this->rfnLine($code, $name);
        DB::statement("INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), '[\"test\"]', ST_Multi(ST_GeomFromText(?, 4326)), now(), now())",
            [$line->id, $wkt, $wkt]);

        return $line;
    }

    /** @param  array<string, string>  $properties */
    private function bdtopo(string $ref, string $wkt, array $properties): void
    {
        DB::statement("INSERT INTO source_traces (source, source_ref, layer, properties, seen_at, geom)
            VALUES ('bdtopo', ?, 'Voie ferrée principale', ?::jsonb, now(), ST_Multi(ST_GeomFromText(?, 4326)))", [$ref, json_encode($properties), $wkt]);
    }

    /** @return list<array<string, mixed>> */
    private function heritageEvents(?Line $line = null): array
    {
        return DB::table('line_events')->where('source', 'heritage')->where('line_id', ($line ?? $this->cevennes)->id)->orderBy('event_date')
            ->get(['type', 'event_date', 'date_precision', 'confidence', 'heritage_kind', 'section_resolution', 'section_from', 'section_to', 'description', 'source_url'])
            ->map(fn ($e) => (array) $e)->all();
    }

    public function test_observed_tourist_train_is_dated_by_the_lignes_oubliees_fiche(): void
    {
        $this->artisan('vff:import-heritage-lines', ['--from-cache' => true])->assertSuccessful();

        $this->assertSame([[
            'type' => 'heritage_start', 'event_date' => '1982-06-03', 'date_precision' => 'day', 'confidence' => 'high',
            'heritage_kind' => 'tourist_train', 'section_resolution' => 'trace', 'section_from' => 'Anduze', 'section_to' => 'Saint-Jean-du-Gard',
            'description' => 'Exploitation associative : Train à Vapeur des Cévennes, train touristique entre Anduze et Saint-Jean-du-Gard, depuis le 3 juin 1982 (d\'après lignes-oubliees). Sources : IGN BD TOPO, lignes-oubliees.',
            'source_url' => 'https://www.lignes-oubliees.com/index.php?page=anduze#etat-actuel',
        ]], $this->heritageEvents());
        $this->assertStringNotContainsString('—', $this->heritageEvents()[0]['description']);
        $this->assertTrue(DB::table('line_events')->where('source', 'heritage')->whereNotNull('section_geom')->exists(), 'portion posée sur le tracé');
        $this->assertFileExists(storage_path('app/reports/heritage-exploitations.csv'));
        $this->assertFileExists(storage_path('app/reports/heritage-import.json'));
        Http::assertNothingSent();
    }

    public function test_velorail_on_a_closed_line_is_attested_with_low_confidence(): void
    {
        $larzac = $this->lineWithTrace('999200', 'Ligne de Tournemire à Le Vigan', 'LINESTRING(3.00 43.97, 3.12 43.99)');
        $this->bdtopo('b2', 'LINESTRING(3.00 43.97, 3.12 43.99)', ['cpx_toponyme' => 'Vélorail du Larzac', 'usage' => 'Vélo-rail', 'etat_de_l_objet' => 'En service']);

        $this->artisan('vff:import-heritage-lines', ['--from-cache' => true])->assertSuccessful();

        $events = $this->heritageEvents($larzac);
        $this->assertCount(1, $events);
        $this->assertSame(['heritage_start', 'velorail', 'low', 'trace'],
            [$events[0]['type'], $events[0]['heritage_kind'], $events[0]['confidence'], $events[0]['section_resolution']],
            'début inconnu : exploitation « attestée » à la date de la source, confiance basse');
        $this->assertLessThanOrEqual(now()->toDateString(), $events[0]['event_date']);
    }

    public function test_import_is_replayable_and_only_touches_its_own_events(): void
    {
        $this->artisan('vff:import-heritage-lines', ['--from-cache' => true])->assertSuccessful();
        $before = $this->snapshot(['line_events'], ['id']);

        $this->artisan('vff:import-heritage-lines', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_events'], ['id']));

        // L'exploitation n'est plus observée ni décrite : son événement disparaît, pas celui de lignes-oubliees.
        DB::table('source_traces')->delete();
        File::delete(storage_path('app/sources/lignes-oubliees/fiches/anduze.html'));
        $this->artisan('vff:import-heritage-lines', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame([], $this->heritageEvents());
        $this->assertTrue(DB::table('line_events')->where('source', 'lignes-oubliees')->exists());
    }

    public function test_a_second_run_in_the_same_process_sees_new_reference_traces(): void
    {
        // Premier passage sans la ligne du Larzac ; elle reçoit ensuite son tracé de référence.
        $this->artisan('vff:import-heritage-lines', ['--from-cache' => true])->assertSuccessful();
        $larzac = $this->lineWithTrace('999200', 'Ligne de Tournemire à Le Vigan', 'LINESTRING(3.00 43.97, 3.12 43.99)');
        $this->bdtopo('b2', 'LINESTRING(3.00 43.97, 3.12 43.99)', ['cpx_toponyme' => 'Vélorail du Larzac', 'usage' => 'Vélo-rail', 'etat_de_l_objet' => 'En service']);

        $this->artisan('vff:import-heritage-lines', ['--from-cache' => true])->assertSuccessful();

        $this->assertCount(1, $this->heritageEvents($larzac), 'avant : liste des tracés de référence figée par le premier passage');
        $this->assertCount(1, $this->heritageEvents());
    }

    public function test_dry_run_and_unknown_line(): void
    {
        $before = $this->snapshot(['line_events']);
        $this->artisan('vff:import-heritage-lines', ['--from-cache' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_events']));

        $this->artisan('vff:import-heritage-lines', ['--from-cache' => true, '--line' => ['123456']])
            ->expectsOutputToContain('Aucune ligne ne correspond à --line.')
            ->assertFailed();
    }
}
