<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Services\Materials\AttestedSeriesFinder;
use App\Services\Materials\WikidataMaterialRules;
use App\Services\Materials\WikidataMaterialSource;
use App\Services\Materials\WikipediaArticleSource;
use App\Services\Materials\WikipediaMaterialSections;
use App\Services\Media\CommonsClient;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\InMemorySourceCache;
use Tests\TestCase;

/**
 * Séries que Wikidata ne rattache pas à la France : retenues seulement si un lien posé dans une section
 * utile d'un article de ligne, ou une déclaration Wikidata (P3438), atteste leur circulation, et si
 * Wikidata les reconnaît comme séries de matériel (pas une gare ni une compagnie citée dans la section).
 */
class AttestedSeriesFinderTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> requêtes envoyées à Wikipédia */
    private array $wikipediaRequests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        Line::factory()->create(['slug' => 'rfn-1', 'name' => 'LGV Est européenne', 'wikipedia_title' => 'LGV Est européenne']);
        Line::factory()->create(['slug' => 'rfn-2', 'name' => 'Ligne de Strasbourg à Kehl', 'wikipedia_title' => 'Ligne de Strasbourg à Kehl']);
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->wikipediaRequests[] = $query;
            $items = ['DB 407' => 'Q3011142', 'Gare de Strasbourg-Ville' => 'Q1', 'BB 15000' => 'Q746572'];
            $titles = explode('|', (string) ($query['titles'] ?? ''));
            // « ICE 3 » redirige vers « DB 407 » : l'élément est celui de la page finale.
            $pages = array_map(fn (string $t): array => $t === 'ICE 3' ? ['title' => 'DB 407', 'pageprops' => ['wikibase_item' => 'Q3011142']]
                : ['title' => $t] + (isset($items[$t]) ? ['pageprops' => ['wikibase_item' => $items[$t]]] : ['missing' => true]), $titles);

            return Http::response(['query' => ['redirects' => in_array('ICE 3', $titles, true) ? [['from' => 'ICE 3', 'to' => 'DB 407']] : [], 'pages' => $pages]]);
        });
    }

    private function finder(array $vehicleUsage = []): AttestedSeriesFinder
    {
        $cache = new InMemorySourceCache;
        $wikidata = new class(new CommonsClient(0), $cache, WikidataMaterialRules::default(), 0, $vehicleUsage) extends WikidataMaterialSource
        {
            /** @param list<array{subject: string, material: string, from: ?string, to: ?string}> $usage */
            public function __construct(CommonsClient $client, InMemorySourceCache $cache, WikidataMaterialRules $rules, int $pauseMs, private readonly array $usage)
            {
                parent::__construct($client, $cache, $rules, $pauseMs);
            }

            public function vehicleUsage(bool $refresh = false): array
            {
                return $this->usage;
            }

            public function seriesAmong(array $qids, bool $refresh = false): array
            {
                // Seules les rames sont des séries de matériel : la gare (Q1) n'en est pas une.
                return array_map(fn (string $q): bool => in_array($q, ['Q3011142', 'Q9999'], true), array_combine($qids, $qids));
            }
        };
        $wikipedia = new class(app(WikimediaClient::class), $cache, 0) extends WikipediaArticleSource
        {
            public function sections(string $cacheName, array $titles, string $pattern, bool $refresh = false): array
            {
                $articles = [
                    'LGV Est européenne' => "== Trafic ==\n=== Trains circulant sur la ligne ===\nLes [[ICE 3]] relient [[Gare de Strasbourg-Ville|Strasbourg]] à Francfort. Des [[BB 15000]] circulent aussi.\n=== Projets ===\n[[Siemens Velaro]] sont prévus.",
                    'Ligne de Strasbourg à Kehl' => "== Exploitation ==\nLes rames [[DB 407]] la parcourent.\nElle ne sera jamais parcourue par des [[Autre rame]].",
                ];
                $out = [];
                foreach ($titles as $title) {
                    $out[$title] = isset($articles[$title])
                        ? ['title' => $title, 'revid' => 1, 'sections' => WikipediaMaterialSections::sections($articles[$title], $pattern)]
                        : null;
                }

                return $out;
            }
        };

        return new AttestedSeriesFinder($wikidata, $wikipedia);
    }

    public function test_a_series_cited_by_a_line_article_is_attested_once_wikidata_confirms_it_is_a_series(): void
    {
        $found = $this->finder()->find(['Q746572']);

        // « ICE 3 » (redirection) et « DB 407 » désignent le même élément, attesté par le premier article qui le cite ;
        // la gare n'est pas une série ; BB 15000 est déjà rattachée à la France.
        $this->assertSame(['Q3011142'], array_keys($found));
        $this->assertSame(['source' => 'wikipedia', 'ref' => 'LGV Est européenne'], $found['Q3011142']);
    }

    public function test_doubtful_sections_and_sentences_do_not_attest_anything(): void
    {
        $found = $this->finder()->find(['Q746572']);

        $this->assertArrayNotHasKey('Q9999', $found);
        $titles = array_merge(...array_map(fn (array $q): array => explode('|', (string) ($q['titles'] ?? '')), $this->wikipediaRequests));
        $this->assertNotContains('Siemens Velaro', $titles, 'section « Projets »');
        $this->assertNotContains('Autre rame', $titles, 'phrase au futur ou négative');
    }

    public function test_a_wikidata_vehicle_declaration_on_a_french_line_attests_a_series(): void
    {
        $found = $this->finder([['subject' => 'Q125217063', 'material' => 'Q9999', 'from' => null, 'to' => null]])->find(['Q746572']);

        $this->assertSame(['source' => 'wikidata', 'ref' => 'Q125217063#P3438'], $found['Q9999']);
    }
}
