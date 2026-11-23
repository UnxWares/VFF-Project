<?php

namespace Tests\Feature\OpenData;

use App\Models\Accident;
use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\OpenData\OpenDataCatalogue;
use App\Services\OpenData\OpenDataDictionary;
use App\Services\OpenData\OpenDataRights;
use App\Services\OpenData\OpenDataSuiteExporter;
use App\Support\SourceRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jeux ouverts de la deuxième vague (signalisation, matériel préservé, projets, infrastructure SNCF, sources
 * d'accidents, redirections, profils en long) : seules les sources autorisées entrent, aucun texte rédigé, aucune
 * donnée personnelle, provenance et licence par ligne, dictionnaire des champs en trois langues.
 */
class ExportOpenDataSuiteTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_OPEN = 'archeoferroviaire';

    private string $out;

    private Era $era;

    private Line $line;

    private Line $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->out = sys_get_temp_dir().'/vff-open-data-test-suite-'.getmypid().'-'.bin2hex(random_bytes(4));
        mkdir($this->out, 0775, true);
        $this->era = Era::factory()->create(['year' => 1930]);
        $current = Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-999000', 'code' => '999000', 'alt_names' => null]);
        $this->other = Line::factory()->create(['slug' => 'ligne-autre', 'code' => null, 'alt_names' => null]);

        // Signalisation : deux faits d'un article, un fait d'un document à lien inconnu, un fait d'une source non libre, un fait douteux.
        $this->signal(1, 'BAL', 'block', '1926-01-01', 'high', 'wikipedia', 'https://fr.wikipedia.org/w/index.php?oldid=5', 'Article visible');
        $this->signal(2, 'TVM-300', 'cab', '1985-06-01', 'medium', 'wikipedia', 'https://fr.wikipedia.org/w/index.php?oldid=6', 'Article visible');
        $this->signal(3, 'BM', 'block', '1950-01-01', 'high', self::NOT_OPEN, 'https://exemple.test/x', 'Titre secret');
        $this->signal(4, 'ETCS_L1', 'cab', '2020-03-01', 'high', 'manual', 'https://www.sncf-reseau.com/document', 'Document nominatif');
        $this->signal(5, 'CT', 'block', '1960-01-01', 'low', 'wikipedia', 'https://fr.wikipedia.org/w/index.php?oldid=7', 'Douteux');

        // Infrastructure SNCF.
        DB::table('line_track_portions')->insert(['line_id' => $this->line->id, 'code_ligne' => '999000', 'rg_troncon' => 1, 'pk_start_m' => 0,
            'pk_end_m' => 5000, 'regime' => 'Double voie', 'tracks' => 2, 'source' => 'sncf', 'dataset_modified' => '2022-03-24', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_radio_portions')->insert([
            ['line_id' => $this->line->id, 'code_ligne' => '999000', 'rg_troncon' => 1, 'pk_start_m' => 0, 'pk_end_m' => 5000, 'system' => 'gsm-r', 'label' => 'GSM-R', 'source' => 'sncf', 'created_at' => now(), 'updated_at' => now()],
            ['line_id' => $this->line->id, 'code_ligne' => '999000', 'rg_troncon' => 1, 'pk_start_m' => 0, 'pk_end_m' => 5000, 'system' => 'rst', 'label' => 'RST', 'source' => self::NOT_OPEN, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $station = Station::factory()->create(['slug' => 'gare-alpha', 'name' => 'Gare Alpha']);
        DB::table('station_platforms')->insert(['station_id' => $station->id, 'sncf_station_name' => 'Alpha', 'code_ligne' => '999000', 'code_voie' => '999000-1-V1',
            'rg_troncon' => 1, 'label' => 'Quai 1', 'length_m' => 200, 'pk_start_m' => 10, 'lat' => 48.5, 'lon' => 2.0, 'match_method' => 'uic', 'source' => 'sncf',
            'dataset_modified' => '2020-05-26', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_signalling')->insert(['line_id' => $this->line->id, 'era_id' => $current->id, 'pk_start_m' => 0, 'pk_end_m' => 5000, 'signalling' => 'BAL',
            'signalling_systems' => json_encode(['BAL']), 'source_label' => 'Block automatique lumineux', 'note' => 'note rédigée', 'created_at' => now(), 'updated_at' => now()]);

        // Redirections de gares.
        DB::table('station_redirects')->insert([
            ['old_slug' => 'osm-1-alpha', 'station_id' => $station->id, 'created_at' => now(), 'updated_at' => now()],
            ['old_slug' => 'archeo-alpha', 'station_id' => $station->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Accidents relus : références libres et références de presse.
        $accident = Accident::factory()->create(['slug' => '1910-01-01-alpha', 'occurred_on' => '1910-01-01', 'context_checked_on' => '2026-11-01', 'alias_of_id' => null]);
        foreach ([['wikipedia', 'encyclopedia', 'https://fr.wikipedia.org/w/index.php?oldid=2', 'Nom de victime'], ['presse', 'press', 'https://journal.test/a', 'Titre nommant une victime'], ['bea-tt', 'official', 'https://bea-tt.developpement-durable.gouv.fr/r', 'Rapport']] as $i => [$provider, $kind, $url, $title]) {
            DB::table('accident_references')->insert(['accident_id' => $accident->id, 'key' => "k{$i}", 'position' => $i + 1, 'provider' => $provider, 'kind' => $kind,
                'reliability' => $kind, 'title' => $title, 'publisher' => 'Éditeur', 'author' => 'Auteur Personne', 'url' => $url, 'access' => 'open', 'published' => '1910', 'consulted_on' => '2026-11-01',
                'created_at' => now(), 'updated_at' => now()]);
        }

        $this->profile($this->line, 'bdtopo', 'rge_alti');
        $this->profile($this->other, self::NOT_OPEN, 'rge_alti');

        $this->app->bind(OpenDataSuiteExporter::class, fn ($app, array $p): OpenDataSuiteExporter => new OpenDataSuiteExporter(
            $p['rights'], $p['today'],
            ['venues' => ['v1' => ['name' => 'Musée', 'type' => 'museum', 'commune' => 'Alpha', 'tourist_railway' => null], 'v2' => ['name' => 'Autre', 'type' => 'site', 'commune' => null, 'tourist_railway' => null]],
                'units' => ['bb-9999' => [
                    ['number' => 'BB 9999', 'venue' => 'v1', 'where' => null, 'state' => 'exposed', 'mh' => ['kind' => 'classified', 'date' => '1988-02-10', 'year' => 1988, 'ref' => 'PM1'], 'src' => [['wikipedia', 'BB 9999', 123], ['palissy', 'PM1']]],
                    ['number' => 'X 1', 'venue' => 'v2', 'where' => null, 'state' => null, 'mh' => null, 'src' => [[self::NOT_OPEN, 'x']]],
                ]], 'others' => []],
            [['id' => 'p1', 'name' => 'Projet un', 'kind' => 'reopening', 'regions' => ['Bretagne'], 'line_slugs' => ['rfn-999000'], 'summary' => 'Résumé rédigé', 'checked' => '2026-11-23',
                'stages' => [
                    ['kind' => 'dup', 'date' => '2020-05-01', 'done' => true, 'note' => 'note', 'source' => ['label' => 'JO', 'url' => 'https://www.legifrance.gouv.fr/jorf/id/1', 'published' => '2020-05-02']],
                    ['kind' => 'works', 'date' => '2024', 'done' => false, 'note' => 'note', 'source' => ['label' => 'Presse régionale', 'url' => 'https://www.quotidien-regional.test/p', 'published' => '2024-01-01']],
                    ['kind' => 'funding', 'date' => '2025-03', 'done' => true, 'note' => 'note', 'source' => ['label' => 'Banque des Territoires', 'url' => 'https://www.banquedesterritoires.fr/f', 'published' => '2025-03-02']],
                    ['kind' => 'study', 'date' => '1990', 'done' => true, 'note' => 'note', 'source' => ['label' => 'Titre secret', 'url' => 'http://archeoferroviaire.free.fr/v31/x']],
                    ['kind' => 'debate', 'date' => '2001', 'done' => true, 'note' => 'note', 'source' => ['label' => 'Revue', 'url' => 'https://www.persee.fr/doc/x']],
                    ['kind' => 'service', 'date' => '2010', 'done' => true, 'note' => 'note', 'source' => ['label' => 'Sans lien']],
                ]]],
            ['archeo-ancien' => 'rfn-999000', 'ancienne-ligne' => 'rfn-999000'],
            ['rfn-999000' => ['max_grade_permil' => 12, 'provider' => 'wikipedia', 'license' => 'CC BY-SA 4.0', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=9', 'source_title' => 'Ligne', 'quote' => 'pente = 12']],
            ['rfn-999000' => ['max_grade_permil' => 10.5, 'provider' => 'sncf', 'license' => 'ODbL 1.0', 'source_url' => 'https://data.sncf.com/x', 'source_title' => 'Jeu', 'quote' => 'q', 'covered_share' => 0.5]],
        ));
    }

    protected function tearDown(): void
    {
        $this->assertStringStartsWith(sys_get_temp_dir().'/vff-open-data-test-suite-', $this->out);
        foreach (glob($this->out.'/{*,.[!.]*}/*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach (glob($this->out.'/{*,.[!.]*}', GLOB_BRACE) ?: [] as $entry) {
            if (is_file($entry)) {
                unlink($entry);
            } elseif (is_dir($entry)) {
                rmdir($entry);
            }
        }
        rmdir($this->out);
        parent::tearDown();
    }

    public function test_signalling_keeps_open_facts_without_text_and_states_per_era(): void
    {
        $this->export('signalling');
        $events = $this->csv('signalisation/signalisation-evenements.csv');
        $this->assertSame(['1', '2', '4'], array_column($events, 'event_id'));
        $this->assertSame(['wikipedia', 'wikipedia', 'vff'], array_column($events, 'source'));
        $this->assertSame(['Article visible', 'Article visible', ''], array_column($events, 'source_title'), 'le titre d\'un document n\'est jamais repris');
        $this->assertSame('https://www.sncf-reseau.com/document', $events[2]['source_url']);
        foreach (['source_excerpt', 'documents', 'correction'] as $column) {
            $this->assertArrayNotHasKey($column, $events[0]);
        }

        $states = $this->csv('signalisation/signalisation-par-epoque.csv');
        $byEra = [];
        foreach ($states as $s) {
            $byEra[(int) $s['era']][] = $s['system'];
        }
        $this->assertSame(['BAL'], $byEra[1930]);
        $this->assertEqualsCanonicalizing(['BAL', 'TVM-300', 'ETCS_L1'], $byEra[2030]);
    }

    public function test_sncf_infrastructure_keeps_tracks_radio_platforms_and_block(): void
    {
        $this->export('infrastructure');
        $this->assertSame(['Double voie'], array_column($this->csv('infrastructure-sncf/voies.csv'), 'regime'));
        $this->assertSame(['gsm-r'], array_column($this->csv('infrastructure-sncf/radio.csv'), 'system'), 'une source non libre est retirée');
        $platforms = $this->csv('infrastructure-sncf/quais.csv');
        $this->assertSame(['gare-alpha', 'sncf', 'ODbL 1.0'], [$platforms[0]['station_slug'], $platforms[0]['source'], $platforms[0]['licence']]);
        $block = $this->csv('infrastructure-sncf/cantonnement.csv');
        $this->assertSame(['BAL', 'sncf'], [$block[0]['signalling'], $block[0]['source']]);
        $this->assertArrayNotHasKey('note', $block[0]);
    }

    public function test_preserved_stock_projects_and_redirects(): void
    {
        $this->export('preserved', 'projects', 'redirects');
        $units = $this->csv('materiel-preserve/materiel-preserve-exemplaires.csv');
        $this->assertSame(['BB 9999'], array_column($units, 'number'));
        $this->assertSame(['palissy|wikipedia', 'PM1'], [$units[0]['sources'], $units[0]['protection_ref']]);
        $this->assertSame(['v1'], array_column($this->csv('materiel-preserve/materiel-preserve-lieux.csv'), 'venue_slug'));

        $this->assertSame(['p1'], array_column($this->csv('projets/projets.csv'), 'project_id'));
        $this->assertArrayNotHasKey('summary', $this->csv('projets/projets.csv')[0]);
        // Décision du 23/11 : une étape est rédigée par VFF ; un lien de presse, de ministère ou de collectivité n'est
        // qu'une citation. Restent retirées les étapes sans lien et celles d'une source non commerciale ou non citable.
        $stages = array_column($this->csv('projets/projets-etapes.csv'), null, 'stage');
        $this->assertSame(['dup', 'works', 'funding'], array_keys($stages));
        $this->assertSame(['jorf', 'Licence Ouverte', 'source'], [$stages['dup']['source'], $stages['dup']['licence'], $stages['dup']['link_role']]);
        foreach (['works' => 'https://www.quotidien-regional.test/p', 'funding' => 'https://www.banquedesterritoires.fr/f'] as $stage => $url) {
            $this->assertSame(['vff', 'ODbL 1.0', 'citation', $url], [$stages[$stage]['source'], $stages[$stage]['licence'], $stages[$stage]['link_role'], $stages[$stage]['source_url']]);
        }
        $this->assertSame(['2024', 'year', 'false'], [$stages['works']['stage_date'], $stages['works']['date_precision'], $stages['works']['done']]);
        $text = (string) file_get_contents($this->out.'/projets/projets-etapes.csv');
        foreach (['archeoferroviaire', 'persee', 'Presse régionale', 'Banque des Territoires', 'note'] as $needle) {
            $this->assertStringNotContainsString($needle, $text);
        }
        $withheld = array_column(OpenDataCatalogue::read($this->out), null, 'id')['projets']['withheld']['projects.stages']['removed'];
        $this->assertSame(3, array_sum($withheld), 'sans lien, non commercial (archeoferroviaire) et usage privé (Persée)');

        $this->assertSame(['osm-1-alpha'], array_column($this->csv('redirections/redirections-gares.csv'), 'old_slug'));
        $this->assertSame(['ancienne-ligne'], array_column($this->csv('redirections/redirections-lignes.csv'), 'old_slug'));
        $catalogue = array_column(OpenDataCatalogue::read($this->out), null, 'id');
        $this->assertSame(1, $catalogue['redirections']['withheld']['redirects.lines']['removed'][OpenDataRights::NOT_OPEN]);
    }

    public function test_proofread_accidents_list_source_families_without_title_or_author(): void
    {
        $this->export('accidents');
        $rows = $this->csv('accidents/accidents-sources.csv');
        $this->assertEqualsCanonicalizing(['wikipedia', 'bea-tt'], array_column($rows, 'source'), 'la presse tierce est retirée');
        foreach (['title', 'author', 'publisher', 'note', 'facts'] as $column) {
            $this->assertArrayNotHasKey($column, $rows[0]);
        }
        $this->assertStringNotContainsString('victime', (string) file_get_contents($this->out.'/accidents/accidents-sources.csv'));
    }

    public function test_profiles_publish_only_measures_on_an_open_trace(): void
    {
        $this->export('profiles');
        $lines = array_column($this->csv('profils/profils-lignes.csv'), null, 'line_slug');
        $this->assertSame(['published', 'ign'], [$lines['rfn-999000']['status'], $lines['rfn-999000']['source']]);
        $this->assertSame(['withheld', '', ''], [$lines['ligne-autre']['status'], $lines['ligne-autre']['max_altitude_m'], $lines['ligne-autre']['source']]);
        $points = $this->csv('profils/profils-points.csv');
        $this->assertSame(['rfn-999000'], array_values(array_unique(array_column($points, 'line_slug'))));
        $this->assertSame(['ign', 'ign'], [$points[0]['altitude_source'], $points[0]['trace_source']]);
        $grades = $this->csv('profils/declivites-sourcees.csv');
        $this->assertSame(['wikipedia', 'sncf'], array_column($grades, 'source'));
        $this->assertArrayNotHasKey('quote', $grades[0]);
    }

    /** Garde-fou : aucune source non libre ne sort, en valeur de provenance comme en texte, dans aucun fichier de la vague. */
    public function test_no_unauthorised_source_enters_any_file_of_the_suite(): void
    {
        $this->export();
        $rights = new OpenDataRights(new SourceRights);
        $checked = 0;
        foreach (glob($this->out.'/*/*.csv') ?: [] as $file) {
            $relative = substr($file, strlen($this->out) + 1);
            if (str_starts_with($relative, 'reseau-')) {
                continue;
            }
            foreach ($this->csv($relative) as $record) {
                foreach ($record as $column => $value) {
                    if ($value !== '' && ($column === 'sources' || str_ends_with((string) $column, '_source') || $column === 'source')) {
                        foreach (explode('|', $value) as $provider) {
                            $this->assertTrue($rights->allows($provider), "{$relative} : {$column} = {$provider} n'est pas autorisé");
                            $checked++;
                        }
                    }
                }
            }
        }
        $this->assertGreaterThan(20, $checked);
        foreach (glob($this->out.'/*/*') ?: [] as $file) {
            $text = (string) file_get_contents($file);
            foreach ([self::NOT_OPEN, 'archeo-', 'journal.test', 'Titre secret', 'Document nominatif', 'Auteur Personne'] as $needle) {
                $this->assertStringNotContainsString($needle, $text, basename($file));
            }
            $this->assertStringNotContainsString("\u{2014}", $text, basename($file));
        }
    }

    public function test_every_new_file_has_a_dictionary_in_three_languages(): void
    {
        $this->export();
        $dictionary = OpenDataDictionary::all();
        foreach (['signalisation' => 'signalisation-evenements.csv', 'infrastructure-sncf' => 'voies.csv', 'profils' => 'profils-points.csv'] as $dir => $file) {
            $rows = $this->csv("{$dir}/dictionnaire.csv");
            $this->assertNotSame([], $rows);
            foreach ($rows as $row) {
                $this->assertNotSame('', $row['description_fr']);
                $this->assertNotSame('', $row['description_en']);
                $this->assertNotSame('', $row['description_de']);
                $this->assertNotSame('', $row['provenance']);
                $this->assertNotSame('', $row['licence']);
            }
            $this->assertContains($file, array_column($rows, 'file'));
        }
        // Chaque colonne écrite est décrite : les en-têtes des fichiers sont couverts par le dictionnaire.
        $described = [];
        foreach ($dictionary as $file => $entries) {
            foreach ($entries as $entry) {
                foreach (array_map('trim', explode(',', $entry[0])) as $field) {
                    $described[$file][$field] = true;
                }
            }
        }
        $headers = [
            'signalisation-evenements.csv' => OpenDataSuiteExporter::SIGNALLING_EVENT_FIELDS, 'signalisation-par-epoque.csv' => OpenDataSuiteExporter::SIGNALLING_ERA_FIELDS,
            'materiel-preserve-exemplaires.csv' => OpenDataSuiteExporter::PRESERVED_UNIT_FIELDS, 'materiel-preserve-lieux.csv' => OpenDataSuiteExporter::PRESERVED_VENUE_FIELDS,
            'projets.csv' => OpenDataSuiteExporter::PROJECT_FIELDS, 'projets-etapes.csv' => OpenDataSuiteExporter::PROJECT_STAGE_FIELDS,
            'voies.csv' => OpenDataSuiteExporter::TRACK_FIELDS, 'radio.csv' => OpenDataSuiteExporter::RADIO_FIELDS, 'quais.csv' => OpenDataSuiteExporter::PLATFORM_FIELDS,
            'cantonnement.csv' => OpenDataSuiteExporter::BLOCK_FIELDS, 'accidents-sources.csv' => OpenDataSuiteExporter::ACCIDENT_SOURCE_FIELDS,
            'redirections-gares.csv' => OpenDataSuiteExporter::STATION_REDIRECT_FIELDS, 'redirections-lignes.csv' => OpenDataSuiteExporter::LINE_REDIRECT_FIELDS,
            'profils-lignes.csv' => OpenDataSuiteExporter::PROFILE_FIELDS, 'profils-points.csv' => OpenDataSuiteExporter::PROFILE_POINT_FIELDS,
            'declivites-sourcees.csv' => OpenDataSuiteExporter::GRADE_FIELDS,
        ];
        foreach ($headers as $file => $fields) {
            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $described[$file] ?? [], "{$file} : le champ {$field} n'est pas décrit");
            }
        }
        $this->assertContains('signalling', OpenDataCatalogue::KINDS);
    }

    private function export(string ...$only): void
    {
        $args = ['--era' => ['1930'], '--output' => $this->out, '--date' => '2026-11-23'];
        $args['--only'] = $only !== [] ? $only : ['signalling', 'preserved', 'projects', 'infrastructure', 'profiles', 'redirects', 'accidents'];
        $this->artisan('vff:export-open-data', $args)->assertExitCode(0);
    }

    private function signal(int $id, string $system, string $role, string $date, string $confidence, string $source, string $url, string $title): void
    {
        DB::table('line_signalling_events')->insert(['id' => $id, 'line_id' => $this->line->id, 'system' => $system, 'role' => $role, 'event_date' => $date,
            'date_precision' => 'day', 'scope' => 'whole_line', 'confidence' => $confidence, 'origin' => 'text', 'source' => $source, 'source_title' => $title,
            'source_revision' => 5, 'source_url' => $url, 'source_excerpt' => 'Extrait rédigé', 'fingerprint' => sha1((string) $id), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function profile(Line $line, string $trace, string $altitude): void
    {
        $id = DB::table('line_profiles')->insertGetId(['line_id' => $line->id, 'era_id' => $this->era->id, 'source' => 'bdtopo-rge-alti', 'resource' => 'ign_rge_alti_wld',
            'step_m' => 100, 'length_m' => 200, 'trace_length_m' => 200, 'structures' => '[]', 'altitude_sources' => '{}', 'gaps' => '[]', 'stations' => '[]',
            'min_altitude_m' => 10, 'max_altitude_m' => 30, 'ascent_m' => 20, 'descent_m' => 0, 'covered_m' => 200, 'off_path_m' => 0, 'pk_anchors' => 0, 'branches' => '[]',
            'computed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        foreach ([0, 1, 2] as $seq) {
            DB::table('line_profile_points')->insert(['line_profile_id' => $id, 'line_id' => $line->id, 'seq' => $seq, 'distance_m' => $seq * 100, 'pk_m' => $seq * 100,
                'altitude_m' => 10 + $seq * 10, 'source' => $altitude, 'trace_source' => $trace, 'interpolated' => false]);
        }
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
}
