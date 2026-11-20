<?php

namespace Tests\Feature\Services;

use App\Services\WikipediaLineParser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Recherche et lecture d'un article de ligne sur Wikipédia (WikipediaLineParser::fetchAndParse),
 * sans réseau (Http::fake) : candidats de titre, rejet des pages d'homonymie, User-Agent VFF,
 * article introuvable ou vide ; puis métadonnées tirées de l'infobox (exploitants, régions).
 */
class WikipediaLineParserFetchTest extends TestCase
{
    private const WIKITEXT = <<<'WIKI'
        {{Infobox Ligne ferroviaire
        | nom = Ligne de Paris à Lyon
        | régions = Île-de-France, Bourgogne-Franche-Comté
        | départements = Seine-et-Marne - Yonne
        | concessionnaire1 = PLM
        | dateconc1 = 1857
        | finconc1 = 1938
        | concessionnaireact = SNCF
        | dateconcact = 1938
        }}
        == Histoire ==
        La ligne est ouverte le {{date|1 janvier 1860}}.
        WIKI;

    /** Réponses simulées : opensearch → titres selon la recherche ; parse → wikitext. */
    private function fakeWikipedia(array $titlesBySearch, ?string $wikitext = self::WIKITEXT): void
    {
        Http::fake(['fr.wikipedia.org/w/api.php*' => function (Request $request) use ($titlesBySearch, $wikitext) {
            if (($request['action'] ?? '') === 'opensearch') {
                return Http::response(['x', $titlesBySearch[$request['search']] ?? []]);
            }

            return Http::response($wikitext === null ? ['error' => ['code' => 'missingtitle']] : ['parse' => ['wikitext' => ['*' => $wikitext]]]);
        }]);
    }

    private function searches(): array
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $r) => ($r['action'] ?? '') === 'opensearch')
            ->map(fn (Request $r) => $r['search'])
            ->values()
            ->all();
    }

    public function test_finds_the_article_and_reads_its_infobox_and_events(): void
    {
        $this->fakeWikipedia(['Ligne de Paris à Lyon' => ['Ligne de Paris à Lyon']]);

        $r = (new WikipediaLineParser)->fetchAndParse('Ligne de Paris à Lyon');

        $this->assertTrue($r['found']);
        $this->assertSame('Ligne de Paris à Lyon', $r['title']);
        $this->assertSame('https://fr.wikipedia.org/wiki/Ligne_de_Paris_à_Lyon', $r['url']);
        $this->assertSame(['Île-de-France', 'Bourgogne-Franche-Comté'], $r['regions']);
        $this->assertSame(['Seine-et-Marne', 'Yonne'], $r['departements']);
        $this->assertSame('Ligne de Paris à Lyon', $r['metadata']['nom']);
        $this->assertSame(self::WIKITEXT, $r['wikitext']);
        $this->assertContains('1860-01-01', array_column($r['events'], 'date'));
    }

    public function test_every_request_identifies_vff_and_is_polite(): void
    {
        $this->fakeWikipedia(['Ligne de Paris à Lyon' => ['Ligne de Paris à Lyon']]);

        (new WikipediaLineParser)->fetchAndParse('Ligne de Paris à Lyon');

        Http::assertSent(fn (Request $r) => str_contains($r->header('User-Agent')[0] ?? '', 'VFF-Bot') && (int) $r['maxlag'] === 5);
        $this->assertStringNotContainsString('@', Http::recorded()->first()[0]->header('User-Agent')[0]);
    }

    public function test_a_search_without_any_line_article_gives_not_found(): void
    {
        // Ni « Ligne … » ni « LGV … » : des pages sans rapport ne sont jamais prises pour la ligne.
        $this->fakeWikipedia(['Ligne de Paris à Lyon' => ['Paris', 'Lyon (homonymie)']]);

        $this->assertSame(['found' => false], (new WikipediaLineParser)->fetchAndParse('Ligne de Paris à Lyon'));
    }

    public function test_a_disambiguation_page_is_never_chosen(): void
    {
        $this->fakeWikipedia(['Ligne de Paris à Lyon' => ['Ligne (homonymie)', 'Ligne de Paris à Lyon-Perrache']]);

        $this->assertSame('Ligne de Paris à Lyon-Perrache', (new WikipediaLineParser)->fetchAndParse('Ligne de Paris à Lyon')['title']);
    }

    public function test_an_article_whose_text_cannot_be_read_gives_not_found(): void
    {
        $this->fakeWikipedia(['Ligne de Paris à Lyon' => ['Ligne de Paris à Lyon']], wikitext: null);

        $this->assertSame(['found' => false], (new WikipediaLineParser)->fetchAndParse('Ligne de Paris à Lyon'));
    }

    public function test_a_short_name_is_also_tried_in_its_long_form(): void
    {
        $this->fakeWikipedia(['Ligne de Paris à Lyon' => ['Ligne de Paris à Lyon']]);

        $r = (new WikipediaLineParser)->fetchAndParse('Paris - Lyon');

        $this->assertSame(['Paris - Lyon', 'Ligne de Paris à Lyon'], $this->searches());
        $this->assertTrue($r['found']);
    }

    public function test_typographic_dashes_and_apostrophes_are_normalised_before_the_second_search(): void
    {
        $this->fakeWikipedia([]);

        (new WikipediaLineParser)->fetchAndParse('Ligne d’Orléans – Tours');

        $this->assertContains("Ligne d'Orléans - Tours", $this->searches());
    }

    public function test_a_lgv_is_also_searched_under_its_usual_name(): void
    {
        $this->fakeWikipedia(['LGV Paris-Sud-Est' => ['LGV Paris-Sud-Est']]);

        $r = (new WikipediaLineParser)->fetchAndParse('Ligne de Paris-Sud-Est (LGV)');

        $this->assertTrue($r['found']);
        $this->assertSame('LGV Paris-Sud-Est', $r['title']);
        $this->assertSame(['Ligne de Paris-Sud-Est (LGV)', 'Ligne de Paris-Sud-Est', 'LGV Paris-Sud-Est'], $this->searches());
    }

    // ── Exploitants et listes de l'infobox ──────────────────────────────────

    public function test_companies_come_from_the_numbered_and_current_concessionaires(): void
    {
        $parser = new WikipediaLineParser;
        $companies = $parser->parseCompanies('', $parser->parseInfobox(self::WIKITEXT));

        $this->assertSame([
            ['operator' => 'PLM', 'from_year' => 1857, 'to_year' => 1938],
            ['operator' => 'SNCF', 'from_year' => 1938, 'to_year' => null],
        ], $companies);
    }

    public function test_companies_fall_back_to_free_form_fields(): void
    {
        $companies = (new WikipediaLineParser)->parseCompanies('', ['exploitée par' => "Compagnie du Nord, État\nSNCF"]);

        $this->assertSame(['Compagnie du Nord', 'État', 'SNCF'], array_column($companies, 'operator'));
        $this->assertSame([null], array_unique(array_column($companies, 'from_year')));
    }

    public function test_companies_are_empty_without_any_field(): void
    {
        $this->assertSame([], (new WikipediaLineParser)->parseCompanies('', ['nom' => 'Ligne']));
    }

    public function test_regions_and_departements_are_empty_without_the_field(): void
    {
        $parser = new WikipediaLineParser;

        $this->assertSame([], $parser->parseRegions([]));
        $this->assertSame([], $parser->parseDepartements(['nom' => 'Ligne']));
    }
}
