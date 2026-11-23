<?php

namespace Tests\Feature\Bibliography;

use App\Models\BibliographyEntry;
use App\Models\BibliographyLink;
use App\Models\Line;
use App\Services\Bibliography\BibliographyImporter;
use App\Services\StationArticleCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-bibliography : requêtes polies (5 secondes), rattachement prudent, remplacement sans doublon, --dry-run qui
 * n'écrit rien (même sans les tables). Réponses du catalogue de la BnF : un extrait réel (tests/Fixtures/Bibliography),
 * jamais le réseau.
 */
class ImportBibliographyCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        $this->line = Line::factory()->create([
            'slug' => 'rfn-722000', 'code' => '722000', 'name' => 'Ligne de Béziers à Neussargues',
            'alt_names' => ['names' => [['name' => 'ligne des Causses']]], 'wikipedia_title' => 'Ligne de Béziers à Neussargues',
        ]);
    }

    private function sru(): string
    {
        return (string) file_get_contents(__DIR__.'/../../Fixtures/Bibliography/bnf-causses.xml');
    }

    /** Article de Wikipédia de la ligne, déjà en cache du bac à sable (aucun téléchargement). */
    private function cacheArticle(): void
    {
        File::ensureDirectoryExists(StationArticleCache::directory());
        $wikitext = "La ligne des Causses.<ref>{{Ouvrage|auteur1=Guy Charmantier|titre=La ligne des Causses et de l'Aubrac : Béziers-Neussargues|année=2024|isbn=2-914603-92-4}}</ref>\n"
            ."== Bibliographie ==\n* Pierre Astier, ''Les Trains du toit de la France : la ligne des Causses'', Paris, Éditions du Cabri, 1988.\n"
            ."* Anna Martin, ''Histoire des chemins de fer en France'', Paris, Belin, 1996.\n";
        file_put_contents(StationArticleCache::directory().'/'.sha1('Ligne de Béziers à Neussargues').'.json',
            json_encode(['title' => 'Ligne de Béziers à Neussargues', 'revid' => 42, 'content' => $wikitext]));
    }

    public function test_the_bnf_source_stores_notices_with_the_alias_basis_and_waits_five_seconds_between_requests(): void
    {
        Http::fake(['catalogue.bnf.fr/*' => Http::response($this->sru())]);

        $this->artisan('vff:import-bibliography', ['--source' => ['bnf'], '--no-stations' => true, '--line' => ['rfn-722000']])->assertSuccessful();

        $titles = BibliographyEntry::query()->pluck('title')->all();
        $this->assertNotSame([], $titles);
        $this->assertTrue(BibliographyLink::query()->where('line_id', $this->line->id)->where('provider', 'bnf')->exists());
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://catalogue.bnf.fr/api/SRU')
            && $request->header('User-Agent') === ['VFF-Bot/1.0 (+https://vff-project.org)']);
        // Cinq secondes au moins entre deux requêtes au serveur : l'attente passe par Sleep (faux ici).
        Sleep::assertSlept(fn ($duration): bool => $duration->totalMilliseconds >= 4900, 1);
    }

    public function test_the_wikipedia_source_attaches_the_references_that_name_the_line_and_skips_general_books(): void
    {
        $this->cacheArticle();

        $this->artisan('vff:import-bibliography', ['--source' => ['wikipedia'], '--no-stations' => true, '--from-cache' => true, '--no-isbn-lookup' => true, '--line' => ['rfn-722000']])->assertSuccessful();

        $links = BibliographyLink::query()->with('entry')->orderBy('id')->get();
        $titles = $links->map(fn (BibliographyLink $l): string => $l->entry->title)->all();
        $this->assertContains("La ligne des Causses et de l'Aubrac : Béziers-Neussargues", $titles);
        $this->assertContains('Les Trains du toit de la France : la ligne des Causses', $titles);
        $this->assertNotContains('Histoire des chemins de fer en France', $titles, 'un ouvrage général n\'est jamais rattaché à une ligne');
        $bibliography = $links->firstWhere(fn (BibliographyLink $l): bool => $l->basis === 'article_bibliography');
        $this->assertNotNull($bibliography);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Ligne_de_B%C3%A9ziers_%C3%A0_Neussargues&oldid=42', $bibliography->origin_url);
        // Une seconde exécution remplace les liens de la source : ni doublon de lien, ni doublon de référence.
        $count = [BibliographyLink::query()->count(), BibliographyEntry::query()->count()];
        $this->artisan('vff:import-bibliography', ['--source' => ['wikipedia'], '--no-stations' => true, '--from-cache' => true, '--no-isbn-lookup' => true, '--line' => ['rfn-722000']])->assertSuccessful();
        $this->assertSame($count, [BibliographyLink::query()->count(), BibliographyEntry::query()->count()]);
    }

    public function test_a_wikipedia_reference_with_an_isbn_gets_the_notice_of_the_bnf_as_its_link(): void
    {
        $this->cacheArticle();
        Http::fake(['catalogue.bnf.fr/*' => Http::response($this->sru())]);

        $this->artisan('vff:import-bibliography', ['--source' => ['wikipedia'], '--no-stations' => true, '--from-cache' => true, '--line' => ['rfn-722000']])
            ->expectsOutputToContain('ISBN cherchés dans le catalogue de la BnF : 1 ; notices trouvées : 1')->assertSuccessful();

        $entry = BibliographyEntry::query()->where('dedup_key', 'isbn:9782914603928')->sole();
        $this->assertSame('bnf', $entry->provider);
        $this->assertStringStartsWith("L'épopée de la ligne des Causses", $entry->title);
        $this->assertMatchesRegularExpression('#^https://catalogue\.bnf\.fr/ark:/12148/cb#', (string) $entry->url);
        // Le rattachement garde sa source : l'article de Wikipédia et sa révision.
        $link = BibliographyLink::query()->where('entry_id', $entry->id)->sole();
        $this->assertSame('wikipedia', $link->provider);
        $this->assertStringContainsString('oldid=42', (string) $link->origin_url);
        Http::assertSent(fn ($request): bool => str_contains(urldecode($request->url()), 'bib.isbn any "9782914603928 2914603924"'));
    }

    public function test_the_dry_run_writes_nothing_and_writes_a_report(): void
    {
        $this->cacheArticle();
        $report = $this->sandbox.'/app/reports/bibliographie.csv';

        $this->artisan('vff:import-bibliography', ['--source' => ['wikipedia'], '--no-stations' => true, '--from-cache' => true, '--dry-run' => true, '--report' => $report])
            ->expectsOutputToContain('Essai (--dry-run) : rien n\'est écrit.')->assertSuccessful();

        $this->assertSame(0, BibliographyLink::query()->count());
        $this->assertSame(0, BibliographyEntry::query()->count());
        $csv = (string) file_get_contents($report);
        $this->assertStringContainsString('rfn-722000', $csv);
        $this->assertStringContainsString('article_bibliography', $csv);
    }

    public function test_without_the_tables_the_dry_run_works_and_the_write_is_refused(): void
    {
        $this->cacheArticle();
        Schema::drop('bibliography_links');
        Schema::drop('bibliography_entries');

        $this->artisan('vff:import-bibliography', ['--source' => ['wikipedia'], '--no-stations' => true, '--from-cache' => true, '--dry-run' => true])->assertSuccessful();
        $this->artisan('vff:import-bibliography', ['--source' => ['wikipedia'], '--no-stations' => true, '--from-cache' => true])
            ->expectsOutputToContain('Tables absentes')->assertFailed();
    }

    public function test_it_refuses_an_unknown_source_an_unknown_line_and_a_too_short_interval(): void
    {
        $this->artisan('vff:import-bibliography', ['--source' => ['gallica']])->assertExitCode(2);
        $this->artisan('vff:import-bibliography', ['--line' => ['rfn-999999'], '--source' => ['wikipedia']])->assertExitCode(2);
        $this->artisan('vff:import-bibliography', ['--interval' => 1000])->assertExitCode(2);
    }

    public function test_cql_queries_name_both_places_a_railway_word_and_books_only(): void
    {
        $cql = BibliographyImporter::cqlRoute('Béziers', 'Neussargues');

        $this->assertStringContainsString('bib.title all "Béziers" and bib.title all "Neussargues"', $cql);
        $this->assertStringContainsString('bib.recordtype any "mon" and bib.doctype any "a"', $cql);
        $this->assertStringNotContainsString('bib.digitized', $cql, 'tous les livres, pas seulement les documents numérisés');
        $this->assertStringContainsString('bib.title all "ligne"', BibliographyImporter::cqlAlias('ligne'));
    }
}
