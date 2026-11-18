<?php

namespace Tests\Feature;

use App\Console\Commands\ImportWikipediaChronology;
use App\Models\Line;
use App\Services\WikimediaClient;
use App\Services\WikipediaLineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:import-wikipedia-chronology : une portion citée dans une référence
 * temporelle (« un mois avant la mise sous tension du tronçon d'Hazebrouck à
 * Calais-Ville ») appartient à l'événement de référence, pas à la ligne de
 * l'article (cas Boulogne-Ville - Calais-Maritime, 2026-10-19).
 */
class WikipediaReferenceSectionTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = 'Ligne de Boulogne-Ville à Calais-Maritime';

    private const WIKITEXT = <<<'WIKI'
        La '''ligne de Boulogne-Ville à Calais-Maritime''' est une ligne ferroviaire française.

        == Histoire ==
        === Origines ===
        La section de Calais à Marquise est ouverte uniquement au trafic des marchandises le 22 avril 1866.

        === Impact du TGV Nord : modernisation et électrification de la ligne ===
        Un nouveau poste d'aiguillage à relais à commande informatique (PRCI) est créé à Fréthun ; il remplace les anciens postes mécaniques de Calais-Ville. Il est mis en service le 16 mai 1993, un mois avant la mise sous tension du tronçon d'Hazebrouck à Calais-Ville et du tronçon de Lille à Fréthun de la LGV Nord.

        La ligne est électrifiée le 2 mai 1994, peu après l'électrification du tronçon de Lille à Fréthun.
        WIKI;

    public function test_section_of_a_time_reference_is_not_attached_to_the_line(): void
    {
        $parsed = app(WikipediaLineParser::class)->parseChronology(self::WIKITEXT, self::TITLE);
        // Le parseur seul rattache bien la portion de la référence à l'événement principal…
        $this->assertNotEmpty(array_filter($parsed, fn ($e) => $e['section_from'] === 'Lille'));
        // … sauf la mise en service du poste d'aiguillage (sous un titre « électrification ») : écartée
        // dès le parseur, ce n'est pas une ouverture de la ligne (tests/Unit/WikipediaOpeningRulesTest).
        $this->assertSame([], array_values(array_filter($parsed, fn ($e) => $e['date'] === '1993-05-16')));

        $stats = [];
        $events = app(ImportWikipediaChronology::class)->withoutReferenceSections($parsed, $stats);

        // … l'import les retire : plus aucune portion Hazebrouck - Calais ni Lille - Fréthun.
        $this->assertSame([], array_values(array_filter($events, fn ($e) => in_array($e['section_from'], ['Hazebrouck', 'Lille'], true))));
        // « Il est mis en service le 16 mai 1993 » (un poste d'aiguillage) : sans portion ni sujet, écarté.
        $this->assertSame([], array_values(array_filter($events, fn ($e) => $e['date'] === '1993-05-16')));
        // « La ligne est électrifiée le 2 mai 1994, peu après … » : gardé, portion inconnue (pas d'office toute la ligne).
        $electrification = array_values(array_filter($events, fn ($e) => $e['date'] === '1994-05-02'));
        $this->assertCount(1, $electrification);
        $this->assertNull($electrification[0]['section_from']);
        $this->assertTrue($electrification[0]['portion_unknown']);
        $this->assertStringNotContainsString('Lille', $electrification[0]['description']);
        $this->assertSame(1, $stats['reference_section']);
        // Portion de la proposition principale : inchangée.
        $freight = array_values(array_filter($events, fn ($e) => $e['type'] === 'opening_freight'));
        $this->assertSame(['Calais', 'Marquise'], [$freight[0]['section_from'], $freight[0]['section_to']]);
    }

    public function test_import_drops_the_misattributed_event_and_is_replayable(): void
    {
        $line = Line::factory()->create(['name' => self::TITLE, 'code' => '314000', 'wikipedia_title' => self::TITLE, 'network' => 'principal']);
        // Événement d'un import précédent (avant la règle) : doit disparaître à la relance.
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'opening', 'event_date' => '1993-05-16',
            'date_precision' => 'day', 'description' => 'Ouverture de Hazebrouck à Calais-Ville (Wikipédia, texte)', 'source' => 'wikipedia',
            'section_from' => 'Hazebrouck', 'section_to' => 'Calais-Ville', 'section_resolution' => 'unresolved',
            'fingerprint' => 'wikipedia:ancien', 'created_at' => now(), 'updated_at' => now()]);

        $wiki = $this->createStub(WikimediaClient::class);
        $wiki->method('wikidataLineCodes')->willReturn([]);
        $wiki->method('fetchPages')->willReturn([self::TITLE => ['title' => self::TITLE, 'revid' => 1, 'content' => self::WIKITEXT]]);
        $this->app->instance(WikimediaClient::class, $wiki);

        $this->artisan('vff:import-wikipedia-chronology', ['--line' => '314000'])->assertSuccessful();

        $events = DB::table('line_events')->where('line_id', $line->id)->where('source', 'wikipedia')->get();
        $this->assertSame(0, $events->where('section_from', 'Hazebrouck')->count());
        $this->assertSame(0, $events->where('section_from', 'Lille')->count());
        $this->assertSame(0, $events->where('event_date', '1993-05-16')->count());
        $this->assertSame(1, $events->where('section_from', 'Calais')->where('section_to', 'Marquise')->count());
        $kept = $events->firstWhere('event_date', '1994-05-02');
        $this->assertNotNull($kept);
        $this->assertSame('unresolved', $kept->section_resolution);
        $this->assertStringStartsWith('Portion non précisée par la source', $kept->section_resolution_note);
    }
}
