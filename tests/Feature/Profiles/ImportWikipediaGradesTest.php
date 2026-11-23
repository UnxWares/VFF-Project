<?php

namespace Tests\Feature\Profiles;

use App\Services\Profiles\SourcedGrades;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * vff:import-wikipedia-grades : articles lus dans le cache local (aucun appel réseau), infobox puis
 * texte contrôlé par la mesure du profil, fusion sans effacement, fichier écrit seulement hors passe
 * à blanc, import idempotent, base jamais modifiée.
 */
class ImportWikipediaGradesTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/vff-wiki-grades-'.getmypid().'-'.uniqid();
        mkdir($this->dir.'/articles', 0775, true);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            (new Filesystem)->deleteDirectory($this->dir);
            SourcedGrades::flush();
        }
    }

    private function article(string $title, string $content, int $revid = 1000): void
    {
        file_put_contents($this->dir.'/articles/'.md5($title).'.json', json_encode([
            'requested' => $title, 'missing' => false, 'title' => $title, 'revid' => $revid, 'content' => $content, 'fetched_at' => '2026-11-22 15:00:00',
        ], JSON_UNESCAPED_UNICODE));
    }

    private function line(string $slug, string $code, string $title, int $length, ?float $measured): void
    {
        $id = DB::table('lines')->insertGetId(['slug' => $slug, 'name' => $title, 'code' => $code, 'length_official_m' => $length, 'wikipedia_title' => $title]);
        DB::table('line_profiles')->insert([
            'line_id' => $id, 'resource' => 'test', 'step_m' => 100, 'length_m' => $length, 'trace_length_m' => $length,
            'structures' => '[]', 'altitude_sources' => '{}', 'gaps' => '[]', 'stations' => '[]',
            'min_altitude_m' => 100, 'max_altitude_m' => 200, 'ascent_m' => 100, 'descent_m' => 0,
            'max_grade_permil' => $measured, 'max_grade_withheld' => $measured === null ? 'terrain_only' : null, 'computed_at' => now(),
        ]);
    }

    private function fixtures(): void
    {
        $this->article('Ligne A', "{{Infobox Ligne ferroviaire\n | longueur = 20\n | pente = 15 ‰\n}}\nTexte.");
        $this->article('Ligne B', "{{Infobox Ligne ferroviaire\n | longueur = 90\n | numéro = 200 000\n | pente = 12\n}}\nTexte.");
        $this->article('Ligne C', "{{Infobox Ligne ferroviaire\n | longueur = 30\n}}\nC'est une ligne au profil moyen, les déclivités atteignaient 18 ‰.");
        $this->article('Ligne D', "{{Infobox Ligne ferroviaire\n | longueur = 30\n}}\nLes rampes maximales sont de 40 ‰.");
        $this->line('l-a', '100000', 'Ligne A', 20_500, null);
        $this->line('l-b', '200000', 'Ligne B', 40_000, 11.0);
        $this->line('l-c', '300000', 'Ligne C', 30_000, 17.2);
        $this->line('l-d', '400000', 'Ligne D', 30_000, 12.0);   // texte trop loin de la mesure sur la voie
        $this->line('l-e', '500000', 'Ligne absente du cache', 10_000, null);
    }

    public function test_dry_run_writes_nothing_and_reports_each_line(): void
    {
        $this->fixtures();
        $before = DB::table('line_profiles')->count();

        $this->artisan('vff:import-wikipedia-grades', ['--dry-run' => true, '--articles-dir' => $this->dir.'/articles', '--csv' => $this->dir.'/report.csv'])
            ->expectsOutputToContain('Passe à blanc')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->dir.'/out.php');
        $rows = array_map('str_getcsv', file($this->dir.'/report.csv', FILE_IGNORE_NEW_LINES) ?: []);
        array_shift($rows);
        $this->assertSame(['l-a' => 'infobox_length', 'l-b' => 'infobox_number', 'l-c' => 'text', 'l-d' => 'text_implausible', 'l-e' => 'article_missing'], array_column($rows, 2, 0));
        $this->assertSame($before, DB::table('line_profiles')->count());
    }

    public function test_the_file_is_written_merged_and_unchanged_on_a_second_run(): void
    {
        $this->fixtures();
        // Entrée relue à la main pour une ligne dont l'article ne dit plus rien : gardée.
        file_put_contents($this->dir.'/out.php', "<?php\n\nreturn ['l-z' => ['max_grade_permil' => 9, 'provider' => 'wikipedia', 'license' => 'CC BY-SA 4.0', 'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Z&oldid=1', 'source_title' => 'Z', 'quote' => 'texte : « 9 ‰ »']];\n");
        $args = ['--articles-dir' => $this->dir.'/articles', '--output' => $this->dir.'/out.php'];

        $this->artisan('vff:import-wikipedia-grades', $args)->assertSuccessful();
        $written = require $this->dir.'/out.php';

        $this->assertSame(['l-a', 'l-b', 'l-c', 'l-z'], array_keys($written));
        $this->assertSame(15, $written['l-a']['max_grade_permil']);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Ligne_A&oldid=1000', $written['l-a']['source_url']);
        $this->assertSame('pente = 12 ; numéro = 200 000', $written['l-b']['quote']);
        $this->assertSame('texte : « C\'est une ligne au profil moyen, les déclivités atteignaient 18 ‰. »', $written['l-c']['quote']);

        $this->artisan('vff:import-wikipedia-grades', $args)->expectsOutputToContain('inchangé')->assertSuccessful();
    }

    public function test_the_real_data_file_is_never_the_default_under_tests(): void
    {
        $this->artisan('vff:import-wikipedia-grades', ['--articles-dir' => $this->dir.'/articles'])
            ->expectsOutputToContain('--output obligatoire')
            ->assertFailed();
    }
}
