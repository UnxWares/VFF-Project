<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-wikipedia-chronology, garde-fous de rattachement (sans réseau, WikimediaClient simulé) :
 *   - article choisi par Wikidata, titre enregistré, jamais l'article d'une autre ligne (numéro d'infobox
 *     différent du code) ;
 *   - homonymes : une ligne SANS code n'adopte pas l'article d'une ligne VFF à code, et perd le titre
 *     homonyme enregistré par erreur avec les événements tirés de cet article ;
 *   - options --line, --only-missing, --limit, --dry-run ; rejouable ; les événements d'une autre
 *     source ne sont jamais supprimés.
 */
class ImportWikipediaChronologyGuardsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const TITLE = 'Ligne de Montsecret à Vassy';

    private function article(string $number = '431 000'): string
    {
        return <<<WIKI
            {{Infobox Ligne ferroviaire
            | nomligne = Montsecret à Vassy
            | numéro = {$number}
            }}
            La '''ligne de Montsecret à Vassy''' est une ancienne ligne de chemin de fer française.

            == Histoire ==
            La ligne est mise en service le {{date|12 juillet 1891}}. Elle est fermée aux voyageurs le 2 octobre 1938.
            WIKI;
    }

    /**
     * @param  array<int|string, list<string>>  $wikidata  code → titres
     * @param  array<string, string>  $pages  titre → wikitext
     */
    private function wiki(array $wikidata, array $pages): void
    {
        $wiki = $this->createStub(WikimediaClient::class);
        $wiki->method('wikidataLineCodes')->willReturn($wikidata);
        $wiki->method('search')->willReturn([]);
        $wiki->method('fetchPages')->willReturnCallback(fn (array $titles) => collect($pages)
            ->only($titles)
            ->map(fn (string $content, string $title) => ['title' => $title, 'revid' => 7, 'content' => $content])
            ->all());
        $this->app->instance(WikimediaClient::class, $wiki);
    }

    private function wikipediaEvents(int $lineId): array
    {
        return DB::table('line_events')->where('line_id', $lineId)->where('source', 'wikipedia')->orderBy('event_date')
            ->get(['type', 'event_date', 'source_url'])->map(fn ($e) => [$e->type, $e->event_date])->all();
    }

    public function test_the_article_found_through_wikidata_gives_dated_events_and_its_title(): void
    {
        $line = Line::factory()->create(['code' => '431000', 'name' => 'Ligne de Montsecret à Vassy', 'wikipedia_title' => null, 'network' => 'principal']);
        $this->wiki(['431000' => [self::TITLE]], [self::TITLE => $this->article()]);

        $this->artisan('vff:import-wikipedia-chronology', ['--line' => '431000'])->assertSuccessful();

        $this->assertSame(self::TITLE, $line->fresh()->wikipedia_title);
        $this->assertSame([['opening', '1891-07-12'], ['closure_passenger', '1938-10-02']], $this->wikipediaEvents($line->id));
        $this->assertSame(
            'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Montsecret_%C3%A0_Vassy&oldid=7',
            DB::table('line_events')->where('line_id', $line->id)->value('source_url'),
            'provenance : permalien de la révision lue',
        );
    }

    public function test_rerunning_changes_nothing_and_removes_only_obsolete_wikipedia_events(): void
    {
        $line = Line::factory()->create(['code' => '431000', 'name' => 'Ligne de Montsecret à Vassy', 'network' => 'principal']);
        $this->wiki(['431000' => [self::TITLE]], [self::TITLE => $this->article()]);
        $manual = DB::table('line_events')->insertGetId(['line_id' => $line->id, 'type' => 'concession', 'event_date' => '1880-01-01',
            'date_precision' => 'year', 'description' => 'Concession', 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
        $obsolete = DB::table('line_events')->insertGetId(['line_id' => $line->id, 'type' => 'opening', 'event_date' => '1850-01-01',
            'date_precision' => 'year', 'description' => 'Ancienne lecture', 'source' => 'wikipedia', 'fingerprint' => 'wikipedia:obsolete',
            'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('vff:import-wikipedia-chronology', ['--line' => '431000'])->assertSuccessful();
        $first = $this->snapshot(['line_events']);
        $this->artisan('vff:import-wikipedia-chronology', ['--line' => '431000'])->assertSuccessful();

        $this->assertSame($first, $this->snapshot(['line_events']));
        $this->assertDatabaseMissing('line_events', ['id' => $obsolete]);
        $this->assertDatabaseHas('line_events', ['id' => $manual, 'source' => 'manual']);
    }

    public function test_an_article_announcing_another_code_is_rejected(): void
    {
        $line = Line::factory()->create(['code' => '431000', 'name' => 'Ligne de Montsecret à Vassy', 'network' => 'principal']);
        $this->wiki(['431000' => [self::TITLE]], [self::TITLE => $this->article('999 000')]);

        $this->artisan('vff:import-wikipedia-chronology', ['--line' => '431000'])
            ->expectsOutputToContain('0/1 lignes avec article')
            ->assertSuccessful();

        $this->assertSame([], $this->wikipediaEvents($line->id));
        $this->assertNull($line->fresh()->wikipedia_title);
    }

    public function test_a_line_without_code_never_adopts_the_article_of_a_coded_line(): void
    {
        Line::factory()->create(['code' => '431000', 'name' => 'Ligne de Montsecret à Vassy', 'wikipedia_title' => self::TITLE, 'network' => 'principal']);
        $secondary = Line::factory()->create(['code' => null, 'name' => 'Montsecret - Vassy (voie métrique)', 'wikipedia_title' => self::TITLE, 'network' => 'secondaire']);
        DB::table('line_events')->insert(['line_id' => $secondary->id, 'type' => 'opening', 'event_date' => '1891-07-12', 'date_precision' => 'day',
            'description' => 'Ouverture', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Montsecret_%C3%A0_Vassy&oldid=3',
            'fingerprint' => 'wikipedia:homonyme', 'created_at' => now(), 'updated_at' => now()]);
        $this->wiki([], [self::TITLE => $this->article()]);

        $this->artisan('vff:import-wikipedia-chronology', ['--line' => (string) $secondary->id])->assertSuccessful();

        $this->assertNull($secondary->fresh()->wikipedia_title, 'le titre homonyme est retiré');
        $this->assertSame([], $this->wikipediaEvents($secondary->id), 'avec les événements tirés de cet article');
    }

    public function test_dry_run_writes_nothing(): void
    {
        Line::factory()->create(['code' => '431000', 'name' => 'Ligne de Montsecret à Vassy', 'network' => 'principal']);
        $this->wiki(['431000' => [self::TITLE]], [self::TITLE => $this->article()]);

        $before = $this->snapshot(['lines', 'line_events']);
        $this->artisan('vff:import-wikipedia-chronology', ['--line' => '431000', '--dry-run' => true])
            ->expectsOutputToContain('2 événements')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot(['lines', 'line_events']));
    }

    public function test_only_missing_skips_lines_that_already_have_wikipedia_events(): void
    {
        $done = Line::factory()->create(['code' => '431000', 'name' => 'Ligne de Montsecret à Vassy', 'network' => 'principal']);
        DB::table('line_events')->insert(['line_id' => $done->id, 'type' => 'opening', 'event_date' => '1891-07-12', 'date_precision' => 'day',
            'description' => 'Ouverture', 'source' => 'wikipedia', 'fingerprint' => 'wikipedia:deja', 'created_at' => now(), 'updated_at' => now()]);
        $this->wiki(['431000' => [self::TITLE]], [self::TITLE => $this->article()]);

        $this->artisan('vff:import-wikipedia-chronology', ['--only-missing' => true])->expectsOutputToContain('Aucune ligne.')->assertFailed();
    }

    public function test_limit_caps_the_number_of_lines(): void
    {
        foreach (['431000', '432000', '433000'] as $code) {
            Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}", 'network' => 'principal']);
        }
        $this->wiki([], []);

        $this->artisan('vff:import-wikipedia-chronology', ['--limit' => 2])->expectsOutputToContain('Lignes à traiter : 2')->assertSuccessful();
    }

    public function test_an_unknown_line_is_an_error(): void
    {
        $this->wiki([], []);

        $this->artisan('vff:import-wikipedia-chronology', ['--line' => 'inconnue'])->expectsOutputToContain('Aucune ligne.')->assertFailed();
    }
}
