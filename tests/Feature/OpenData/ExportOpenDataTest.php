<?php

namespace Tests\Feature\OpenData;

use App\Models\Accident;
use App\Models\Era;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\OpenData\OpenDataCatalogue;
use App\Services\OpenData\OpenDataRights;
use App\Services\OpenData\OpenDataSuiteExporter;
use App\Support\SourceRights;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:export-open-data : contenu des jeux, règle des droits (rien d'une source non autorisée ne sort),
 * remplacements par une donnée libre, comptes de ce qui est retiré, idempotence, catalogue et page.
 *
 * Réseau fictif de 1930 : une ligne du RFN en trois sections (OSM libre ; source non ouverte doublée d'un
 * tracé OSM à 3 m ; source non ouverte sans tracé libre), une ligne au nom non libre qui a un article
 * Wikipédia, deux gares dont une au nom et à la position non libres.
 */
class ExportOpenDataTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_OPEN = 'archeoferroviaire';

    private string $out;

    private Era $era;

    private Line $rfn;

    private Line $named;

    protected function setUp(): void
    {
        parent::setUp();
        $this->out = sys_get_temp_dir().'/vff-open-data-test-'.getmypid().'-'.bin2hex(random_bytes(4));
        mkdir($this->out, 0775, true);

        $this->era = Era::factory()->create(['year' => 1930]);
        Era::factory()->current()->create(['year' => 2030]);

        $this->rfn = Line::factory()->create(['slug' => 'rfn-999000', 'code' => '999000', 'name' => 'Ligne de Alpha à Bravo',
            'network' => 'principal', 'alt_names' => null, 'wikipedia_title' => null]);
        $this->named = Line::factory()->create(['slug' => 'ligne-de-charlie-a-delta', 'code' => null, 'name' => 'Ligne de Charlie à Delta',
            'network' => 'secondaire', 'alt_names' => ['name_source' => self::NOT_OPEN], 'wikipedia_title' => 'Ligne de Charlie à Delta (Wikipédia)']);

        // Section libre (OSM), section non ouverte doublée d'un tracé OSM, section non ouverte sans tracé libre.
        $this->segment($this->rfn, 'osm', 'LINESTRING(2.0 48.5, 2.01 48.5)', 0, 'in_service');
        $this->segment($this->rfn, self::NOT_OPEN, 'LINESTRING(2.01 48.5, 2.02 48.5)', 734, 'in_service', ['gauge_source' => 'archeo', 'gauge_mm' => 1000]);
        $this->segment($this->rfn, self::NOT_OPEN, 'LINESTRING(2.5 48.9, 2.51 48.9)', 1468, 'abandoned');
        DB::table('source_traces')->insert(['source' => 'osm', 'source_ref' => 'way/1', 'seen_at' => now(),
            'geom' => DB::raw("ST_GeomFromText('LINESTRING(2.009 48.50003, 2.021 48.50003)', 4326)")]);
        // Un tracé OpenHistoricalMap au même endroit ne sert jamais de remplacement.
        DB::table('source_traces')->insert(['source' => 'ohm', 'source_ref' => 'way/2', 'seen_at' => now(),
            'geom' => DB::raw("ST_GeomFromText('LINESTRING(2.4999 48.9, 2.5101 48.9)', 4326)")]);
        $this->segment($this->named, 'bdtopo', 'LINESTRING(3.0 47.0, 3.01 47.0)', 0, 'in_service');

        $free = $this->station('Alpha', ['name' => 'sncf', 'geom' => 'sncf', 'opened_at' => 'wikidata'], 48.5, 2.0);
        $closed = $this->station('Nom non libre', ['name' => 'lignes-oubliees', 'geom' => 'lignes-oubliees', 'opened_at' => 'lignes-oubliees'], 48.5, 2.02);
        DB::table('station_sources')->insert(['station_id' => $closed->id, 'source' => 'osm', 'source_ref' => 'node/9', 'name' => 'Bravo',
            'seen_at' => now(), 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.0201, 48.5001), 4326)')]);
        foreach ([$free, $closed] as $i => $s) {
            DB::table('line_stops')->insert(['line_id' => $this->rfn->id, 'era_id' => $this->era->id, 'station_id' => $s->id,
                'position' => $i, 'pk_m' => $i * 1468, 'effective_status' => 'active']);
        }

        $this->event($this->rfn, 'opening', '1875-03-01', 'wikipedia', 'https://fr.wikipedia.org/w/index.php?oldid=1');
        $this->event($this->rfn, 'closure', '1938-05-15', 'lignes-oubliees', 'https://www.lignes-oubliees.com/x');
        $this->event($this->rfn, 'closure', '1939-01-01', self::NOT_OPEN, null);
        $this->event($this->rfn, 'electrification', '1926-01-01', 'manual', 'https://www.legifrance.gouv.fr/jorf/id/1');

        $accident = Accident::factory()->create(['slug' => '1910-01-01-alpha', 'occurred_on' => '1910-01-01',
            'provenance' => ['date' => 'wikipedia', 'location' => 'commune', 'deaths' => 'context']]);
        DB::table('accident_sources')->insert([
            ['accident_id' => $accident->id, 'provider' => 'wikipedia', 'role' => 'primary', 'title' => 'Article', 'url' => 'https://fr.wikipedia.org/w/index.php?oldid=2', 'licence' => 'CC BY-SA 4.0', 'retrieved_at' => now()],
            ['accident_id' => $accident->id, 'provider' => 'delpher', 'role' => 'secondary', 'title' => 'Journal', 'url' => 'https://www.delpher.nl/x', 'licence' => '?', 'retrieved_at' => now()],
        ]);
        // Fichiers versionnés vides : le test ne dépend pas des données réelles du dépôt.
        $this->app->bind(OpenDataSuiteExporter::class, fn ($app, array $p): OpenDataSuiteExporter => new OpenDataSuiteExporter(
            $p['rights'], $p['today'], ['venues' => [], 'units' => [], 'others' => []], [], [], [], []));
        Material::factory()->create(['slug' => 'bb-9999', 'name' => 'BB 9999', 'source' => 'wikidata', 'wikidata_id' => 'Q1',
            'provenance' => ['name' => ['source' => 'wikidata'], 'builder' => ['source' => 'wikidata']]]);
    }

    protected function tearDown(): void
    {
        // Dossier temporaire propre au test (préfixe vérifié) : fichiers un à un, puis dossiers vides.
        // Motif « .[!.]* » et non « .* » : « .* » prend aussi « .. », et « ../* » viderait le dossier
        // temporaire du système (/tmp), ce qui bloquait la suite en CI.
        $this->assertStringStartsWith(sys_get_temp_dir().'/vff-open-data-test-', $this->out);
        foreach (glob($this->out.'/{*,.[!.]*}/*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach (glob($this->out.'/{*,.[!.]*}', GLOB_BRACE) ?: [] as $entry) {
            if (is_file($entry)) {
                unlink($entry);
            } elseif (is_dir($entry) && ! in_array(basename($entry), ['.', '..'], true)) {
                rmdir($entry);
            }
        }
        rmdir($this->out);
        parent::tearDown();
    }

    public function test_sections_keep_open_geometry_replace_or_withhold_the_rest(): void
    {
        $this->export();
        $rows = $this->csv('reseau-1930/sections.csv');
        $bySource = array_column(array_filter($rows, fn (array $r): bool => $r['line_slug'] === 'rfn-999000'), null, 'pk_start_m');

        $this->assertSame(['original', 'osm'], [$bySource['0']['geometry_status'], $bySource['0']['geometry_source']]);
        $this->assertSame(['replaced', 'osm'], [$bySource['734']['geometry_status'], $bySource['734']['geometry_source']]);
        $this->assertSame(['withheld', ''], [$bySource['1468']['geometry_status'], $bySource['1468']['geometry_source']],
            'un tracé OpenHistoricalMap ne remplace jamais une géométrie');
        $this->assertSame(['', ''], [$bySource['734']['gauge_mm'], $bySource['734']['gauge_source']], 'écartement d\'une source non ouverte retiré');

        $geo = $this->geojsonFile('reseau-1930/sections.geojson');
        $this->assertSame('FeatureCollection', $geo['type']);
        $geometries = array_column(array_column($geo['features'], 'properties'), 'geometry_status');
        $this->assertCount(4, $geometries);
        foreach ($geo['features'] as $f) {
            $this->assertSame($f['properties']['geometry_status'] === 'withheld', $f['geometry'] === null);
        }
        // Le tracé de remplacement est celui d'OSM, découpé à la section (latitude du tracé OSM).
        $replaced = array_values(array_filter($geo['features'], fn ($f) => $f['properties']['geometry_status'] === 'replaced'))[0];
        $this->assertEqualsWithDelta(48.50003, $replaced['geometry']['coordinates'][0][1], 1e-6);
    }

    public function test_lines_resolve_open_names_and_dates(): void
    {
        $this->export();
        $lines = array_column($this->csv('reseau-1930/lignes.csv'), null, 'slug');

        $this->assertSame(['Ligne de Alpha à Bravo', 'sncf'], [$lines['rfn-999000']['name'], $lines['rfn-999000']['name_source']]);
        $this->assertSame(['Ligne de Charlie à Delta (Wikipédia)', 'wikipedia'],
            [$lines['ligne-de-charlie-a-delta']['name'], $lines['ligne-de-charlie-a-delta']['name_source']]);
        $this->assertSame(['1875-03-01', 'wikipedia'], [$lines['rfn-999000']['first_opening_on'], $lines['rfn-999000']['first_opening_source']]);
        $this->assertSame('', $lines['rfn-999000']['last_closure_on'], 'les fermetures de sources non libres ne comptent pas');
        $this->assertSame(['3', '2', '1'], [$lines['rfn-999000']['sections'], $lines['rfn-999000']['geometry_sections'], $lines['rfn-999000']['geometry_withheld_sections']]);
        $this->assertSame(['in_service', 'abandoned|in_service'], [$lines['rfn-999000']['main_status'], $lines['rfn-999000']['statuses']]);

        $feature = array_values(array_filter($this->geojsonFile('reseau-1930/lignes.geojson')['features'], fn ($f) => $f['properties']['slug'] === 'rfn-999000'))[0];
        $this->assertSame('MultiLineString', $feature['geometry']['type']);
        $this->assertCount(2, $feature['geometry']['coordinates']);
    }

    public function test_stations_replace_a_name_and_a_position_from_an_open_source(): void
    {
        $this->export();
        $stations = array_column($this->csv('reseau-1930/gares.csv'), null, 'name');

        $this->assertSame(['sncf', 'original', 'sncf', '1875-03-01'], [$stations['Gare Alpha']['name_source'],
            $stations['Gare Alpha']['geometry_status'], $stations['Gare Alpha']['geometry_source'], $stations['Gare Alpha']['opened_on']]);
        $this->assertArrayHasKey('Bravo', $stations, 'nom remplacé par celui d\'OSM');
        $this->assertSame(['osm', 'replaced', 'osm', '48.5001', '2.0201', ''], [$stations['Bravo']['name_source'], $stations['Bravo']['geometry_status'],
            $stations['Bravo']['geometry_source'], $stations['Bravo']['lat'], $stations['Bravo']['lon'], $stations['Bravo']['opened_on']]);
        $this->assertArrayNotHasKey('Nom non libre', $stations);
    }

    public function test_events_accidents_and_materials_keep_only_open_sources_and_no_text(): void
    {
        $this->export();
        $events = $this->csv('evenements/evenements.csv');
        $this->assertSame(['opening', 'electrification'], array_column($events, 'type'));
        $this->assertSame(['wikipedia', 'jorf'], array_column($events, 'source'));
        $this->assertNotContains('description', array_keys($events[0]));

        $accidents = $this->csv('accidents/accidents.csv');
        $this->assertCount(1, $accidents);
        $this->assertSame(['wikipedia', 'communes', 'vff', 'wikipedia'], [$accidents[0]['date_source'], $accidents[0]['location_source'],
            $accidents[0]['toll_source'], $accidents[0]['sources']]);
        $this->assertStringNotContainsString('delpher', $accidents[0]['source_urls']);
        $this->assertArrayNotHasKey('summary', $accidents[0]);

        $materials = $this->csv('materiel/materiel.csv');
        $this->assertSame(['BB 9999', 'wikidata'], [$materials[0]['name'], $materials[0]['sources']]);
    }

    /**
     * Garde-fou : toute valeur de provenance d'un fichier publié (colonnes *_source, sources, et propriétés
     * GeoJSON) doit désigner un fournisseur autorisé par le registre des droits. Ce test échoue dès qu'une
     * source non autorisée entre dans un export.
     */
    public function test_no_unauthorised_source_enters_any_published_file(): void
    {
        $this->export();
        $rights = new OpenDataRights(new SourceRights);
        $checked = 0;
        foreach (glob($this->out.'/*/*.{csv,geojson}', GLOB_BRACE) ?: [] as $file) {
            $records = str_ends_with($file, '.csv') ? $this->csv(substr($file, strlen($this->out) + 1))
                : array_column($this->geojsonFile(substr($file, strlen($this->out) + 1))['features'], 'properties');
            foreach ($records as $record) {
                foreach ($record as $column => $value) {
                    if (($column === 'sources' || str_ends_with((string) $column, '_source')) && $value !== '' && $value !== null) {
                        foreach (explode('|', (string) $value) as $provider) {
                            $this->assertTrue($rights->allows($provider), "{$file} : {$column} = {$provider} n'est pas autorisé");
                            $checked++;
                        }
                    }
                }
            }
        }
        $this->assertGreaterThan(20, $checked);

        // Aucun fichier publié ne nomme une source écartée.
        foreach (glob($this->out.'/*/*') ?: [] as $file) {
            $text = (string) file_get_contents($file);
            foreach ([self::NOT_OPEN, 'lignes-oubliees', 'lignes-oubliees.com', 'delpher'] as $needle) {
                $this->assertStringNotContainsString($needle, $text, basename($file));
            }
        }
        $this->assertStringNotContainsString(self::NOT_OPEN, (string) file_get_contents($this->out.'/'.OpenDataCatalogue::FILE));
    }

    /** Ligne créée d'après un tracé non autorisé (sans code) : ni la ligne, ni ses sections, ni leurs états n'entrent. */
    public function test_a_line_created_from_an_unauthorised_trace_never_enters(): void
    {
        $created = Line::factory()->create(['slug' => 'ligne-d-echo-a-foxtrot', 'code' => null, 'name' => 'Ligne d\'Echo à Foxtrot',
            'network' => 'secondaire', 'alt_names' => ['source' => self::NOT_OPEN], 'wikipedia_title' => null]);
        $this->segment($created, self::NOT_OPEN, 'LINESTRING(4.0 46.0, 4.01 46.0)', 0, 'dismantled');

        $this->export();

        $this->assertNotContains('ligne-d-echo-a-foxtrot', array_column($this->csv('reseau-1930/lignes.csv'), 'slug'));
        $this->assertNotContains('ligne-d-echo-a-foxtrot', array_column($this->csv('reseau-1930/sections.csv'), 'line_slug'));
        $this->assertContains('rfn-999000', array_column($this->csv('reseau-1930/lignes.csv'), 'slug'));
    }

    /** Un slug à préfixe de source nommerait la source dans les fichiers : l'export attend la neutralisation. */
    public function test_the_export_waits_for_neutral_line_slugs(): void
    {
        Line::factory()->create(['slug' => 'archeo-ligne-de-golf-a-hotel', 'code' => null, 'alt_names' => null]);

        $this->artisan('vff:export-open-data', ['--era' => ['1930'], '--output' => $this->out, '--date' => '2026-11-23'])
            ->expectsOutputToContain('vff:neutralize-line-slugs')
            ->assertExitCode(1);
    }

    public function test_readme_licence_and_catalogue_count_what_was_withheld(): void
    {
        $this->export();
        $readme = (string) file_get_contents($this->out.'/reseau-1930/LISEZMOI.txt');
        $this->assertStringContainsString('géométries de sections : 1 retiré(s)', $readme);
        $this->assertStringContainsString('géométries de sections : 1 remplacé(s)', $readme);
        $this->assertStringContainsString('noms de gares : 1 remplacé(s)', $readme);
        $this->assertStringContainsString('section geometries: 1 replaced', str_replace(' : ', ': ', $readme));
        $this->assertStringNotContainsString("\u{2014}", $readme);
        $this->assertStringNotContainsString('--', $readme);

        $licence = (string) file_get_contents($this->out.'/reseau-1930/LICENCE.txt');
        $this->assertStringContainsString('Open Database License (ODbL) 1.0', $licence);
        $this->assertStringContainsString('© contributeurs OpenStreetMap', $licence);
        $this->assertStringContainsString('### Preamble', $licence, 'texte officiel de l\'ODbL joint');

        $catalogue = OpenDataCatalogue::read($this->out);
        $this->assertSame(['evenements', 'accidents', 'materiel', 'signalisation', 'materiel-preserve', 'projets', 'infrastructure-sncf', 'profils', 'redirections', 'reseau-1930'], array_column($catalogue, 'id'));
        $network = $catalogue[9];
        $this->assertSame(['ODbL-1.0', true, '2026-11-23'], [$network['licence'], $network['commercial_reuse'], $network['generated_on']]);
        $this->assertSame(1, $network['withheld']['sections.geometry']['removed'][OpenDataRights::NOT_OPEN]);
        $this->assertFileExists($this->out.'/reseau-1930.zip');
        $this->assertSame(hash_file('sha256', $this->out.'/reseau-1930.zip'), $network['archive']['sha256']);
        $this->assertContains('LISEZMOI.txt', array_column($network['files'], 'name'));
    }

    public function test_the_export_is_idempotent(): void
    {
        $this->export();
        $first = $this->hashes();
        $this->export();
        $this->assertSame($first, $this->hashes());
    }

    public function test_a_sample_never_goes_to_the_public_directory_and_bad_options_are_refused(): void
    {
        $this->artisan('vff:export-open-data', ['--lines' => 'rfn-999000', '--output' => OpenDataCatalogue::publicRoot()])->assertExitCode(2);
        $this->artisan('vff:export-open-data', ['--lines' => 'inconnue', '--output' => $this->out])->assertExitCode(2);
        $this->artisan('vff:export-open-data', ['--only' => ['trains'], '--output' => $this->out])->assertExitCode(2);
        $this->artisan('vff:export-open-data', ['--era' => ['1931'], '--output' => $this->out])->assertExitCode(2);
    }

    public function test_the_sample_only_covers_the_chosen_lines(): void
    {
        $this->artisan('vff:export-open-data', ['--era' => ['1930'], '--lines' => 'ligne-de-charlie-a-delta', '--output' => $this->out,
            '--date' => '2026-11-23'])->assertExitCode(0);
        $this->assertSame(['ligne-de-charlie-a-delta'], array_column($this->csv('reseau-1930/lignes.csv'), 'slug'));
        $this->assertSame([], $this->csv('evenements/evenements.csv'));
    }

    public function test_the_open_data_page_lists_the_catalogue(): void
    {
        $this->export();
        config(['open_data.root' => $this->out]);

        $this->get('/open-data')->assertOk()->assertInertia(fn ($page) => $page->component('OpenData')
            ->has('datasets', 10)
            ->where('datasets.9.id', 'reseau-1930')
            ->where('datasets.9.archive.url', '/downloads/open-data/reseau-1930.zip')
            ->where('datasets.9.files.0.url', '/downloads/open-data/reseau-1930/LICENCE.txt')
            ->where('licence.code', 'ODbL-1.0')
            ->where('withheld', fn ($w): bool => $w['sections.geometry'] === ['removed' => 1, 'replaced' => 1])
            ->has('credits'));
    }

    public function test_the_page_shows_an_empty_state_without_catalogue(): void
    {
        config(['open_data.root' => $this->out]);
        $this->get('/open-data')->assertOk()->assertInertia(fn ($page) => $page->component('OpenData')->has('datasets', 0));
    }

    private function export(): void
    {
        $this->artisan('vff:export-open-data', ['--era' => ['1930'], '--output' => $this->out, '--date' => '2026-11-23'])->assertExitCode(0);
    }

    /** @return array<string, string> */
    private function hashes(): array
    {
        $out = [];
        foreach (glob($this->out.'/{*,*/*}', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                $out[substr($file, strlen($this->out))] = (string) hash_file('sha256', $file);
            }
        }
        ksort($out);

        return $out;
    }

    /** @return list<array<string, string>> */
    private function csv(string $relative): array
    {
        $handle = fopen($this->out.'/'.$relative, 'r');
        $this->assertIsResource($handle);
        $header = fgetcsv($handle, null, ',', '"', '');
        $rows = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $rows[] = array_combine((array) $header, $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<string, mixed> */
    private function geojsonFile(string $relative): array
    {
        return json_decode((string) file_get_contents($this->out.'/'.$relative), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param  array<string, mixed>  $extra */
    private function segment(Line $line, string $source, string $wkt, int $pk, string $status, array $extra = []): void
    {
        DB::table('line_segments')->insert($extra + [
            'line_id' => $line->id, 'era_id' => $this->era->id, 'status' => $status, 'traffic_type' => 'mixed', 'source' => $source,
            'pk_start_m' => $pk, 'pk_end_m' => $pk + 734, 'length_meters' => 734, 'gauge_mm' => 1435, 'gauge_source' => 'osm',
            'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  array<string, string>  $provenance */
    private function station(string $name, array $provenance, float $lat, float $lng): Station
    {
        $station = Station::factory()->create(['name' => "Gare {$name}", 'slug' => 'gare-'.strtolower(str_replace(' ', '-', $name)),
            'geom' => Point::makeGeodetic($lat, $lng), 'opened_at' => '1875-03-01', 'opened_precision' => 'day',
            'provenance' => $provenance, 'position_source' => $provenance['geom']]);
        if ($name !== 'Alpha') {
            $station->update(['name' => $name]);
        }
        DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $this->era->id, 'status' => 'active',
            'uncertain' => false, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    private function event(Line $line, string $type, string $date, string $source, ?string $url): void
    {
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'day',
            'description' => 'Texte rédigé', 'source' => $source, 'source_url' => $url, 'created_at' => now(), 'updated_at' => now()]);
    }
}
