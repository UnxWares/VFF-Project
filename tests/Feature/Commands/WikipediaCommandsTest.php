<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Services\WikipediaLineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Import d'un article Wikipédia (vff:import-wikipedia-line) et enrichissement
 * des fiches (vff:enrich-line-wikipedia), sur réponses simulées : ligne
 * créée ou retrouvée, événements typés (fermeture aux voyageurs ≠ fermeture
 * totale), précision de date, provenance, rejouabilité, erreurs.
 */
class WikipediaCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const URL = 'https://fr.wikipedia.org/wiki/Ligne_de_Montsecret_%C3%A0_Vassy';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    private function fakeArticle(): void
    {
        Http::fake(['fr.wikipedia.org/w/api.php*' => Http::response([
            'parse' => ['title' => 'Ligne de Montsecret à Vassy', 'pageid' => 1, 'wikitext' => $this->fixture('Wikipedia/ligne-de-montsecret-a-vassy.wikitext')],
        ])]);
    }

    /** @return list<array{string, string, string, string}> [type, date, précision, source] */
    private function events(): array
    {
        return DB::table('line_events')->orderBy('event_date')->get(['type', 'event_date', 'date_precision', 'source'])
            ->map(fn ($e) => [$e->type, $e->event_date, $e->date_precision, $e->source])->all();
    }

    public function test_article_creates_the_line_and_typed_dated_events(): void
    {
        $this->fakeArticle();

        $this->artisan('vff:import-wikipedia-line', ['url' => self::URL])
            ->expectsOutputToContain('Article : Ligne_de_Montsecret_à_Vassy')
            ->expectsOutputToContain('Code RFN détecté : 431 000')
            ->expectsOutputToContain('4 événement(s) persisté(s), ligne créée')
            ->assertSuccessful();

        $line = DB::table('lines')->first();
        $this->assertSame(['ligne-de-montsecret-a-vassy', 'Ligne de Montsecret à Vassy', '431 000', 'État'], [$line->slug, $line->name, $line->code, $line->historical_operator]);
        $this->assertStringStartsWith('La ligne de Montsecret à Vassy est une ancienne ligne de chemin de fer française', $line->description);
        $this->assertStringNotContainsString("'''", $line->description);

        $this->assertSame([
            ['opening', '1891-07-12', 'day', 'wikipedia'],
            // Fermeture aux SEULS voyageurs : pas une fermeture totale (le fret continue).
            ['closure_passenger', '1938-10-02', 'day', 'wikipedia'],
            // « 31 février » n'existe pas : année seule, jamais un faux 3 mars.
            ['closure_freight', '1950-01-01', 'year', 'wikipedia'],
            ['dismantling', '1955-01-01', 'year', 'wikipedia'],
        ], $this->events());
        // « ouverte aux voyageurs en 1892 » n'est PAS une fermeture ; l'infobox (fermeture = 1938) ne crée pas de fermeture totale.
        $this->assertSame(0, DB::table('line_events')->where('type', 'closure')->count());
        $this->assertSame([self::URL], DB::table('line_events')->distinct()->pluck('source_url')->all());

        Http::assertSent(fn (Request $r) => $r['action'] === 'parse' && $r['page'] === 'Ligne_de_Montsecret_à_Vassy' && (int) $r['formatversion'] === 2);
    }

    public function test_article_import_is_replayable_and_reuses_an_existing_line_by_code(): void
    {
        $this->fakeArticle();
        $existing = $this->rfnLine('431000', 'Ligne de Montsecret à Vassy', ['historical_operator' => 'Compagnie de l’Ouest', 'description' => null]);

        $this->artisan('vff:import-wikipedia-line', ['url' => self::URL])->expectsOutputToContain('Line existante enrichie (description)')->assertSuccessful();
        $snapshot = $this->snapshot(['lines', 'line_events']);
        $this->artisan('vff:import-wikipedia-line', ['url' => self::URL])->expectsOutputToContain('Line existante déjà complète')->assertSuccessful();

        $this->assertSame($snapshot, $this->snapshot(['lines', 'line_events']));
        $this->assertSame(1, DB::table('lines')->count());
        // Un champ déjà renseigné n'est jamais écrasé.
        $this->assertSame('Compagnie de l’Ouest', DB::table('lines')->where('id', $existing->id)->value('historical_operator'));
        $this->assertSame(4, DB::table('line_events')->where('line_id', $existing->id)->count());
    }

    public function test_article_dry_run_writes_nothing(): void
    {
        $this->fakeArticle();

        $this->artisan('vff:import-wikipedia-line', ['url' => self::URL, '--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Création Line')
            ->expectsOutputToContain('4 événement(s) à persister (dry-run)')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('lines')->count());
        $this->assertSame(0, DB::table('line_events')->count());
    }

    /** @return iterable<string, array{string, mixed, int, string}> */
    public static function failures(): iterable
    {
        yield 'URL hors Wikipédia FR' => ['https://en.wikipedia.org/wiki/Line', [], 200, 'URL invalide. Attendu: https://fr.wikipedia.org/wiki/<Titre>'];
        yield 'HTTP 500' => [self::URL, 'oops', 500, 'Impossible de récupérer le wikitext.'];
        yield 'page absente' => [self::URL, ['error' => ['code' => 'missingtitle', 'info' => "The page you specified doesn't exist."]], 200, 'Impossible de récupérer le wikitext.'];
        yield 'ancien format' => [self::URL, ['parse' => ['wikitext' => ['*' => '...']]], 200, 'Impossible de récupérer le wikitext.'];
    }

    #[DataProvider('failures')]
    public function test_article_import_fails_cleanly(string $url, mixed $body, int $status, string $error): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response($body, $status)]);

        $this->artisan('vff:import-wikipedia-line', ['url' => $url])->expectsOutputToContain($error)->assertFailed();
        $this->assertSame(0, DB::table('lines')->count());
    }

    // ─── vff:enrich-line-wikipedia ──────────────────────────────────────

    /** @param  array<string, mixed>  $metadata */
    private function stubParser(array $metadata, bool $found = true): void
    {
        $parser = $this->createStub(WikipediaLineParser::class);
        $parser->method('fetchAndParse')->willReturn(! $found ? ['found' => false] : [
            'found' => true,
            'title' => 'Ligne de Montsecret à Vassy',
            'url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Montsecret_à_Vassy',
            'metadata' => $metadata,
            'events' => [['type' => 'opening'], ['type' => 'closure_passenger']],
            'companies' => [['name' => 'Compagnie de l’Ouest', 'from_year' => 1886, 'to_year' => 1908], ['name' => 'État', 'from_year' => 1909, 'to_year' => 1937]],
            'regions' => ['Normandie'],
            'departements' => ['Calvados'],
        ]);
        $this->app->instance(WikipediaLineParser::class, $parser);
    }

    public function test_enrich_line_stores_wikipedia_metadata_and_sources(): void
    {
        $this->stubParser(['longueur' => '12', 'écartement' => '1 435 mm', 'nombre de voies act' => 'Voie unique (1)', 'vitesse' => '70 km/h', 'trafic' => 'Fret, TER']);
        $line = $this->rfnLine('431000', 'Ligne de Montsecret à Vassy');
        $known = $this->track($line, Era::where('is_current', true)->first(), [[48.8, -0.7], [48.85, -0.65]], ['traffic_type' => 'freight']);
        $unknown = $this->track($line, Era::where('is_current', true)->first(), [[48.85, -0.65], [48.9, -0.6]], ['traffic_type' => 'unknown']);
        $this->rfnLine('999999', 'Ligne 999999'); // anonyme : ignorée

        $this->artisan('vff:enrich-line-wikipedia', ['--sleep' => 0])
            ->expectsOutputToContain('Enrichissement Wikipedia : 1 lignes')
            ->expectsOutputToContain('✓ 1 lignes enrichies, 0 non trouvées (2 événements repérés')
            ->assertSuccessful();

        /** @var \stdClass $row */
        $row = DB::table('lines')->find($line->id);
        $this->assertSame(['Ligne de Montsecret à Vassy', 12_000, 1435, 1, 70, 1886], [$row->wikipedia_title, $row->length_official_m, $row->gauge_mm_default, $row->nb_voies_default, $row->max_speed_kmh_official, $row->concession_year]);
        $this->assertSame(['Normandie'], json_decode($row->regions, true));
        $this->assertSame('État', json_decode($row->historical_operators, true)[1]['name']);
        // Trafic Wikipédia (fret + TER) : complète seulement le tronçon inconnu, en mixte.
        $this->assertSame('freight', DB::table('line_segments')->where('id', $known->id)->value('traffic_type'));
        $this->assertSame('mixed', DB::table('line_segments')->where('id', $unknown->id)->value('traffic_type'));
        $this->assertSame('mixed', $row->type);

        $source = DB::table('line_sources')->where('line_id', $line->id)->first();
        $this->assertSame('wikipedia', $source->source_type);
        $this->assertSame(['length_official_m', 'gauge_mm_default', 'nb_voies_default', 'max_speed_kmh_official', 'historical_operators', 'regions', 'departements', 'concession_year'], json_decode($source->fields_provided, true));
        // Aucun événement écrit ici (vff:import-wikipedia-chronology s'en charge).
        $this->assertSame(0, DB::table('line_events')->count());

        $this->artisan('vff:enrich-line-wikipedia', ['--sleep' => 0])->assertSuccessful();
        $this->assertSame(1, DB::table('line_sources')->count());
    }

    public function test_passenger_only_traffic_is_mixed_on_a_classic_line_and_passenger_on_a_high_speed_line(): void
    {
        $era = Era::where('is_current', true)->first();
        $this->stubParser(['trafic' => 'TGV, Ouigo']);
        $classic = $this->rfnLine('431000', 'Ligne de Montsecret à Vassy');
        $lgv = $this->rfnLine('752000', 'LGV Sud-Est'); // is_lgv encore false : reconnue par son nom
        foreach ([$classic, $lgv] as $i => $l) {
            $this->track($l, $era, [[47.0 + $i, 4.0], [47.1 + $i, 4.0]], ['traffic_type' => 'unknown']);
        }

        $this->artisan('vff:enrich-line-wikipedia', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('mixed', DB::table('line_segments')->where('line_id', $classic->id)->value('traffic_type'));
        $this->assertSame('passenger', DB::table('line_segments')->where('line_id', $lgv->id)->value('traffic_type'));
    }

    public function test_enrich_line_counts_articles_not_found_and_respects_the_slug_option(): void
    {
        $this->stubParser([], found: false);
        $line = $this->rfnLine('431000', 'Ligne de Montsecret à Vassy');
        $this->rfnLine('432000', 'Ligne de Caen à Vire');

        $this->artisan('vff:enrich-line-wikipedia', ['--slug' => $line->slug, '--sleep' => 0])
            ->expectsOutputToContain('Enrichissement Wikipedia : 1 lignes')
            ->expectsOutputToContain('✓ 0 lignes enrichies, 1 non trouvées')
            ->assertSuccessful();
        $this->assertNull(DB::table('lines')->where('id', $line->id)->value('wikipedia_title'));
    }
}
