<?php

namespace Tests\Feature\Signalling;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * Faits relus dans les articles (compléments de `vff:import-signalling-history`) : la phrase du fichier est
 * cherchée dans l'article lu, le fait est rangé comme un fait de Wikipédia (révision lue), une phrase absente
 * est une erreur, une ligne absente de la base n'en est pas une.
 */
class SignallingSupplementsTest extends TestCase
{
    use RefreshDatabase;

    private const ARTICLE = <<<'WIKI'
{{Infobox Ligne ferroviaire
| numéro = 974 000
}}
La '''ligne de Saint-Cloud à Saint-Nom-la-Bretèche''' est une ligne ferroviaire française.
== Histoire ==
En juin 1962, le block automatique lumineux est étendu de Garches à Saint-Nom-la-Bretèche.
La signalisation de type block automatique lumineux (BAL) date de 1975 sur toute la ligne, hors de la [[ligne de Paris à Lyon]].
WIKI;

    private const STATION = <<<'WIKI'
La gare de Garches est une gare de la ligne.
En 1970, le BAPR est mis en service entre Garches et Vaucresson, en remplacement du block manuel.
WIKI;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        Era::factory()->current()->create(['year' => 2030]);
        Line::factory()->create(['slug' => 'rfn-974000', 'code' => '974000', 'name' => 'Ligne de Saint-Cloud', 'wikipedia_title' => 'Ligne de Saint-Cloud']);
        $this->dir = sys_get_temp_dir().'/vff-signalling-supp-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->article('Ligne de Saint-Cloud', self::ARTICLE, 240844633);
        $this->article('Gare de Garches', self::STATION, 222390282);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            foreach (glob($this->dir.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_file($this->dir.'/.nobackup')) {
                unlink($this->dir.'/.nobackup');
            }
            if (is_dir($this->dir)) {
                rmdir($this->dir);
            }
        }
    }

    private function article(string $title, string $content, int $revision): void
    {
        file_put_contents($this->dir.'/'.md5($title).'.json', json_encode([
            'requested' => $title, 'missing' => false, 'title' => $title, 'revid' => $revision, 'content' => $content, 'fetched_at' => '2026-11-19 10:00:00',
        ]));
    }

    /** @param  array<string, mixed>  $over */
    private function supplement(array $over = []): array
    {
        return $over + [
            'line' => '974000', 'system' => 'BAL', 'date' => '1975', 'scope' => 'whole_line',
            'quote' => 'La signalisation de type block automatique lumineux (BAL) date de 1975 sur toute la ligne',
        ];
    }

    /** @param  list<array<string, mixed>>  $facts */
    private function import(array $facts, array $options = []): PendingCommand
    {
        $path = $this->dir.'/supplements.php';
        file_put_contents($path, '<?php return '.var_export($facts, true).';');

        return $this->artisan('vff:import-signalling-history', ['--offline' => true, '--cache-dir' => $this->dir, '--supplements' => $path] + $options);
    }

    public function test_the_extractor_alone_misses_the_sentence_the_supplement_gives(): void
    {
        $this->import([])->assertSuccessful();

        $this->assertSame(['1962-06-01'], DB::table('line_signalling_events')->where('system', 'BAL')->pluck('event_date')->all());
    }

    public function test_a_supplement_is_stored_like_a_wikipedia_fact_with_the_revision_read(): void
    {
        $this->import([$this->supplement()])->assertSuccessful();

        $row = DB::table('line_signalling_events')->where('system', 'BAL')->where('event_date', '1975-01-01')->sole();
        $this->assertSame(['wikipedia', 'text', 'medium', 'year', 'whole_line'], [$row->source, $row->origin, $row->confidence, $row->date_precision, $row->scope]);
        $this->assertSame(240844633, (int) $row->source_revision);
        $this->assertStringContainsString('oldid=240844633', $row->source_url);
        $this->assertSame('La signalisation de type block automatique lumineux (BAL) date de 1975 sur toute la ligne', $row->source_excerpt);
        $this->assertSame('block', $row->role);
        Http::assertNothingSent();
    }

    public function test_a_supplement_can_quote_another_article_and_keeps_its_revision_and_portion(): void
    {
        $this->import([$this->supplement([
            'article' => 'Gare de Garches', 'system' => 'BAPR', 'date' => '1970', 'scope' => 'section', 'from' => 'Garches', 'to' => 'Vaucresson',
            'quote' => 'En 1970, le BAPR est mis en service entre Garches et Vaucresson, en remplacement du block manuel.',
        ])])->assertSuccessful();

        $row = DB::table('line_signalling_events')->where('system', 'BAPR')->sole();
        $this->assertSame('Gare de Garches', $row->source_title);
        $this->assertSame(222390282, (int) $row->source_revision);
        $this->assertStringContainsString('title=Gare_de_Garches', $row->source_url);
        $this->assertSame(['Garches', 'Vaucresson'], [$row->section_from, $row->section_to]);
    }

    public function test_a_phrase_that_left_the_article_is_an_error_and_nothing_is_stored_for_it(): void
    {
        $this->import([$this->supplement(['quote' => 'La signalisation date de 1976 sur toute la ligne'])])
            ->expectsOutputToContain('Complément sans phrase retrouvée : 974000 BAL 1975-01-01')->assertFailed();

        $this->assertSame(0, DB::table('line_signalling_events')->where('event_date', '1975-01-01')->count());
    }

    public function test_an_article_that_is_not_in_the_cache_is_an_error_too(): void
    {
        $this->import([$this->supplement(['article' => 'Gare absente'])])->expectsOutputToContain('Complément sans phrase retrouvée')->assertFailed();
    }

    public function test_an_invalid_file_writes_nothing(): void
    {
        $this->import([$this->supplement(['system' => 'CBTC'])])->expectsOutputToContain('1 complément(s) refusé(s)')->assertFailed();

        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }

    public function test_a_line_that_is_not_in_the_base_is_not_an_error(): void
    {
        $this->import([$this->supplement(['line' => '752000'])])->assertSuccessful();
    }

    public function test_a_supplement_that_leaves_the_file_leaves_the_table_on_the_next_import(): void
    {
        $this->import([$this->supplement()])->assertSuccessful();
        $this->assertSame(1, DB::table('line_signalling_events')->where('event_date', '1975-01-01')->count());

        $this->import([])->assertSuccessful();
        $this->assertSame(0, DB::table('line_signalling_events')->where('event_date', '1975-01-01')->count());
    }

    public function test_a_supplement_replayed_does_not_duplicate_the_fact(): void
    {
        $this->import([$this->supplement()])->assertSuccessful();
        $this->import([$this->supplement()])->assertSuccessful();

        $this->assertSame(1, DB::table('line_signalling_events')->where('event_date', '1975-01-01')->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->import([$this->supplement()], ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }
}
