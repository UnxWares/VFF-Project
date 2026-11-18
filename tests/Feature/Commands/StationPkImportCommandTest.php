<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\ImportStationSources;
use App\Models\Line;
use App\Services\SectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-station-sources --source=pk : gares nommées des listes par ligne
 * (lignes-oubliées, schéma de l'article de la ligne, schémas des articles en
 * cache) posées sur le tracé de leur ligne, avec leur précision et leur
 * méthode, dans station_sources (sources « lignes-oubliees » et
 * « wikipedia_schema »).
 *
 * Tracé de référence des tests du résolveur : Alphaville (2,0° E, PK 0),
 * Betaville (2,2° E, PK 15,197), Gammaville (2,4° E, PK 30,395) à 47° N.
 */
class StationPkImportCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        Http::fake(['*' => Http::response([])]);
        foreach (['poiMemo', 'communeMemo'] as $memo) {
            (new \ReflectionProperty(SectionResolver::class, $memo))->setValue(null, []);
        }

        $this->line = Line::factory()->create(['name' => 'Ligne de Alphaville à Gammaville', 'code' => '901000', 'network' => 'principal',
            'wikipedia_title' => 'Ligne de Alphaville à Gammaville']);
        DB::statement(
            "INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
             VALUES (?, 30395, '[\"test\"]', ST_GeomFromText('MULTILINESTRING((2.0 47.0, 2.1 47.0, 2.2 47.0, 2.3 47.0, 2.4 47.0))', 4326), now(), now())",
            [$this->line->id],
        );
        foreach ([['Alphaville', 2.0, 0], ['Betaville', 2.2, 15197], ['Gammaville', 2.4, 30395]] as [$name, $lon, $pk]) {
            DB::table('sncf_gares')->insert(['code_ligne' => '901000', 'libelle' => $name, 'commune' => $name, 'pk_m' => $pk,
                'lon' => $lon, 'lat' => 47.0, 'voyageurs' => true, 'fret' => true]);
        }
    }

    private function schemaRow(string $name, int $pk): void
    {
        DB::table('wikipedia_line_stations')->insert(['line_id' => $this->line->id, 'name' => $name, 'pk_m' => $pk, 'icon' => 'BHF',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Alphaville_%C3%A0_Gammaville&oldid=42',
            'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, array{lon: float, lat: float, props: array<string, mixed>}> */
    private function imported(string $source): array
    {
        return DB::table('station_sources')->where('source', $source)
            ->selectRaw('name, ST_X(geom) AS lon, ST_Y(geom) AS lat, properties')->get()
            ->mapWithKeys(fn ($r) => [$r->name => ['lon' => (float) $r->lon, 'lat' => (float) $r->lat, 'props' => json_decode($r->properties, true)]])->all();
    }

    public function test_stations_of_the_line_article_schema_are_placed_with_method_and_precision(): void
    {
        $this->schemaRow('Alphaville', 0);
        $this->schemaRow('Deltaville', 7599);
        $this->schemaRow('Betaville', 15197);

        $this->artisan('vff:import-station-sources', ['--source' => ['pk']])->assertSuccessful();

        $st = $this->imported(ImportStationSources::WIKIPEDIA_SCHEMA);
        $this->assertSame(['Alphaville', 'Betaville', 'Deltaville'], collect($st)->keys()->sort()->values()->all());
        $this->assertSame('station', $st['Alphaville']['props']['geocoding']);
        $d = $st['Deltaville'];
        $this->assertEqualsWithDelta(2.1, $d['lon'], 0.002);
        $this->assertSame('pk', $d['props']['geocoding']);
        $this->assertSame(480, $d['props']['precision_m']);
        $this->assertSame(7599, $d['props']['pk_m']);
        $this->assertSame($this->line->id, $d['props']['line_id']);
        $this->assertSame('Ligne de Alphaville à Gammaville', $d['props']['page']);
        $this->assertStringContainsString('oldid=42', $d['props']['source_url']);
    }

    public function test_network_article_schema_gives_only_the_stations_between_the_two_termini(): void
    {
        $content = "{{BS-début|Réseau de test}}\n{{BS|KBHFa|0,0|Omegaville}}\n{{BS|BHF|3,0|Alphaville}}\n{{BS|HST|10,599|Deltaville}}\n"
            ."{{BS|BHF|33,395|Gammaville}}\n{{BS|KBHFe|40,0|Psiville}}\n{{BS-fin}}";
        File::ensureDirectoryExists(storage_path('app/sources/secondary-networks'));
        File::put(storage_path('app/sources/secondary-networks/pages.json'),
            json_encode(['Réseau de test' => ['title' => 'Réseau de test', 'revid' => 7, 'content' => $content]], JSON_UNESCAPED_UNICODE));

        $this->artisan('vff:import-station-sources', ['--source' => ['pk']])->assertSuccessful();

        $st = $this->imported(ImportStationSources::WIKIPEDIA_SCHEMA);
        $this->assertSame(['Alphaville', 'Deltaville', 'Gammaville'], collect($st)->keys()->sort()->values()->all());
        $this->assertEqualsWithDelta(2.1, $st['Deltaville']['lon'], 0.002, 'PK du schéma recalé (origine décalée de 3 km)');
        $this->assertSame(7599, $st['Deltaville']['props']['pk_m']);
        $this->assertSame(10599, $st['Deltaville']['props']['pk_source_m']);
        $this->assertStringContainsString('oldid=7', $st['Deltaville']['props']['source_url']);
    }

    public function test_lignes_oubliees_stations_keep_their_source_pk_and_unplaceable_ones_are_skipped(): void
    {
        // Gares connues du groupe (calage) + une gare à placer par son PK + une gare introuvable sans PK.
        // « Bifurcation de Beaune » : un point de la ligne, pas une gare.
        foreach ([['Alphaville', 0], ['Betaville', 15197], ['Deltaville', 7599], ['Nullepart', null], ['Bifurcation de Beaune', 9000]] as $i => [$name, $pk]) {
            DB::table('source_stations')->insert(['source' => 'lignes-oubliees', 'source_ref' => "lo-{$i}", 'line_id' => $this->line->id,
                'name' => $name, 'pk_m' => $pk, 'properties' => json_encode(['page' => 'fiche', 'branch' => '']), 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->artisan('vff:import-station-sources', ['--source' => ['pk']])->assertSuccessful();

        $lo = $this->imported('lignes-oubliees');
        $this->assertSame(['Alphaville', 'Betaville', 'Deltaville'], collect($lo)->keys()->sort()->values()->all());
        $this->assertSame(7599, $lo['Deltaville']['props']['pk_m']);
        $this->assertSame('pk', $lo['Deltaville']['props']['geocoding']);
        $this->assertIsInt($lo['Deltaville']['props']['source_station_id']);
    }

    private function schemaWithAnchors(): void
    {
        $this->schemaRow('Alphaville', 0);
        $this->schemaRow('Deltaville', 7599);
        $this->schemaRow('Betaville', 15197);
    }

    public function test_a_lone_pk_that_no_known_station_confirms_is_not_placed(): void
    {
        $this->schemaRow('Deltaville', 7599);

        $this->artisan('vff:import-station-sources', ['--source' => ['pk']])
            ->expectsOutputToContain('0 / 1 gares placées')
            ->assertSuccessful();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->schemaWithAnchors();
        $before = $this->snapshot();

        $this->artisan('vff:import-station-sources', ['--source' => ['pk'], '--dry-run' => true])
            ->expectsOutputToContain('3 / 3 gares placées')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_pk_options_never_run_the_other_sources(): void
    {
        $this->schemaWithAnchors();

        // --dry-run seul : source pk seulement (les autres sources écriraient).
        $this->artisan('vff:import-station-sources', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('station_sources')->count());
        $this->artisan('vff:import-station-sources', ['--source' => ['sncf', 'pk'], '--dry-run' => true])->assertFailed();
        $this->artisan('vff:import-station-sources', ['--source' => ['osm'], '--line' => ['901000']])->assertFailed();
    }

    public function test_import_limited_to_other_lines_keeps_the_stations_of_this_line(): void
    {
        $this->schemaWithAnchors();
        $this->artisan('vff:import-station-sources', ['--source' => ['pk']])->assertSuccessful();
        $other = Line::factory()->create(['code' => '902000']);

        $this->artisan('vff:import-station-sources', ['--source' => ['pk'], '--line' => [$other->code]])->assertSuccessful();

        $this->assertArrayHasKey('Deltaville', $this->imported(ImportStationSources::WIKIPEDIA_SCHEMA));
    }

    public function test_schema_row_no_longer_given_is_removed_by_a_full_import(): void
    {
        $this->schemaWithAnchors();
        $this->artisan('vff:import-station-sources', ['--source' => ['pk']])->assertSuccessful();
        DB::table('wikipedia_line_stations')->delete();
        $this->travel(1)->minutes();

        $this->artisan('vff:import-station-sources', ['--source' => ['pk']])->assertSuccessful();

        $this->assertSame([], $this->imported(ImportStationSources::WIKIPEDIA_SCHEMA));
    }
}
