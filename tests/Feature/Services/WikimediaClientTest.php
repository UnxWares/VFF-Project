<?php

namespace Tests\Feature\Services;

use App\Services\WikimediaClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Client MediaWiki / Wikidata (WikimediaClient), sans réseau : Http::fake et
 * Sleep::fake (les reprises n'attendent pas).
 *   - politesse : User-Agent VFF, maxlag=5, reprise sur 429 / 503 / maxlag ;
 *   - lots de 50 titres, redirections et titres normalisés ramenés au titre demandé ;
 *   - index infobox et table Wikidata mis en cache ; une panne n'est PAS mise
 *     en cache (correctif : avant, la table vide restait 24 h).
 */
class WikimediaClientTest extends TestCase
{
    private WikimediaClient $wiki;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        Cache::flush();
        $this->wiki = new WikimediaClient;
    }

    /** @return array<string, mixed> */
    private static function page(string $title, int $revid, string $content): array
    {
        return ['title' => $title, 'revisions' => [['revid' => $revid, 'slots' => ['main' => ['content' => $content]]]]];
    }

    public function test_api_adds_polite_defaults_and_returns_the_json(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response(['query' => ['ok' => true]])]);

        $this->assertSame(['query' => ['ok' => true]], $this->wiki->api(['action' => 'query']));

        Http::assertSent(fn (Request $r) => $r->hasHeader('User-Agent', WikimediaClient::USER_AGENT)
            && $r['format'] === 'json' && (int) $r['formatversion'] === 2 && (int) $r['maxlag'] === 5 && $r['action'] === 'query');
        Sleep::assertNeverSlept();
    }

    public function test_api_waits_and_retries_on_overload(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::sequence()
            ->push('', 429, ['Retry-After' => '7'])
            ->push(['error' => ['code' => 'maxlag', 'info' => 'Waiting']], 200)
            ->push('', 503)
            ->push(['batchcomplete' => true], 200)]);

        $this->assertSame(['batchcomplete' => true], $this->wiki->api(['action' => 'query']));

        Http::assertSentCount(4);
        Sleep::assertSequence([Sleep::for(7)->seconds(), Sleep::for(5)->seconds(), Sleep::for(15)->seconds()]);
    }

    public function test_api_gives_up_on_persistent_http_errors(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response('panne', 500)]);

        try {
            $this->wiki->api(['action' => 'query']);
            $this->fail('exception attendue');
        } catch (RuntimeException $e) {
            $this->assertSame('MediaWiki HTTP 500', $e->getMessage());
        }
        Http::assertSentCount(6);
        Sleep::assertSleptTimes(5);
    }

    public function test_api_gives_up_after_six_overloads(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response(['error' => ['code' => 'maxlag']], 200)]);

        $this->expectExceptionMessage('MediaWiki : trop de reprises (maxlag).');
        $this->wiki->api(['action' => 'query']);
    }

    public function test_fetch_pages_follows_redirects_and_keys_by_requested_title(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::sequence()
            ->push(['continue' => ['rvcontinue' => '42|1'], 'query' => [
                'normalized' => [['from' => 'ligne de Brest', 'to' => 'Ligne de Brest']],
                'redirects' => [['from' => 'Ligne de Brest', 'to' => 'Ligne de Paris-Montparnasse à Brest']],
                'pages' => [self::page('Ligne de Paris-Montparnasse à Brest', 101, '{{Infobox}} Texte')],
            ]])
            ->push(['query' => ['pages' => [
                self::page('Ligne de Carhaix à Morlaix', 202, 'Autre'),
                ['title' => 'Absente', 'missing' => true],
                ['title' => 'Vide', 'revisions' => [['revid' => 3, 'slots' => ['main' => ['content' => '']]]]],
            ]]])]);

        $pages = $this->wiki->fetchPages(['ligne de Brest', 'Ligne de Carhaix à Morlaix', 'Absente', 'Vide', 'ligne de Brest'], leadOnly: true);

        $this->assertSame([
            'ligne de Brest' => ['title' => 'Ligne de Paris-Montparnasse à Brest', 'revid' => 101, 'content' => '{{Infobox}} Texte'],
            'Ligne de Carhaix à Morlaix' => ['title' => 'Ligne de Carhaix à Morlaix', 'revid' => 202, 'content' => 'Autre'],
        ], $pages);
        Http::assertSent(fn (Request $r) => $r['titles'] === 'ligne de Brest|Ligne de Carhaix à Morlaix|Absente|Vide'
            && (int) $r['rvsection'] === 0 && ! isset($r['rvcontinue']));
        Http::assertSent(fn (Request $r) => ($r['rvcontinue'] ?? null) === '42|1');
    }

    public function test_fetch_pages_sends_titles_by_batches_of_fifty(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response(['query' => ['pages' => []]])]);
        $titles = array_map(fn ($i) => "Page {$i}", range(1, 51));

        $this->assertSame([], $this->wiki->fetchPages($titles));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => count(explode('|', $r['titles'])) === 50 && ! isset($r['rvsection']));
        Http::assertSent(fn (Request $r) => $r['titles'] === 'Page 51');
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function infoboxCodeProvider(): array
    {
        return [
            'espace' => ["{{Infobox Ligne ferroviaire\n| numéro = 956 000\n| nom = X\n}}", ['956000']],
            'modèle' => ["| numéro = {{numéro|956000}}\n", ['956000']],
            'espace insécable et point' => ["| numéro = 420\u{00A0}000 / 421.000\n", ['420000', '421000']],
            'référence ignorée, doublon fusionné' => ["| numéro = 956 000<ref>SNCF 123 456</ref> 956000\n", ['956000']],
            'suite sur la ligne suivante' => ["| numéro = 956 000\n  et 957 000\n| nom = X", ['956000', '957000']],
            'pas un code à 6 chiffres' => ["| numéro = 4 000\n", []],
            'pas de champ' => ['{{Infobox Ligne ferroviaire | nom = X }}', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('infoboxCodeProvider')]
    public function test_codes_from_infobox(string $wikitext, array $expected): void
    {
        $this->assertSame($expected, WikimediaClient::codesFromInfobox($wikitext));
    }

    public function test_infobox_index_is_built_once_and_cached(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::sequence()
            ->push(['continue' => ['gcontinue' => 'x'], 'query' => ['pages' => [
                self::page('Ligne A', 1, '| numéro = 956 000'),
                ['title' => 'Ligne sans contenu'],
            ]]])
            ->push(['query' => ['pages' => [self::page('Ligne B', 2, '| numéro = 956 000 / 420 000')]]])
            ->push(['query' => ['pages' => [self::page('Ligne C', 3, '| numéro = 111 000')]]])]);

        $index = $this->wiki->infoboxIndex();

        $this->assertSame(['codes' => ['956000' => ['Ligne A', 'Ligne B'], '420000' => ['Ligne B']],
            'titles' => ['Ligne A', 'Ligne sans contenu', 'Ligne B']], $index);
        $this->assertSame($index, $this->wiki->infoboxIndex(), 'cache : aucune nouvelle requête');
        Http::assertSentCount(2);

        $this->assertSame(['111000' => ['Ligne C']], $this->wiki->infoboxIndex(refresh: true)['codes']);
        Http::assertSentCount(3);
    }

    public function test_wikidata_line_codes_are_parsed_and_cached(): void
    {
        Http::fake(['query.wikidata.org/*' => Http::response(['results' => ['bindings' => [
            ['code' => ['value' => '956 000'], 'article' => ['value' => 'https://fr.wikipedia.org/wiki/Ligne_de_Savenay_%C3%A0_Landerneau']],
            ['code' => ['value' => '956000'], 'article' => ['value' => 'https://fr.wikipedia.org/wiki/Ligne_de_Savenay_%C3%A0_Landerneau']],
            ['code' => ['value' => '420.000'], 'article' => ['value' => 'https://fr.wikipedia.org/wiki/Ligne_A']],
            ['code' => ['value' => '12'], 'article' => ['value' => 'https://fr.wikipedia.org/wiki/Trop_court']],
        ]]])]);

        $map = $this->wiki->wikidataLineCodes();

        $this->assertSame(['956000' => ['Ligne de Savenay à Landerneau'], '420000' => ['Ligne A']], $map);
        $this->assertSame($map, $this->wiki->wikidataLineCodes());
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->hasHeader('Accept', 'application/sparql-results+json')
            && str_contains($r['query'], 'wdt:P1671'));
    }

    public function test_wikidata_outage_is_not_cached(): void
    {
        Http::fake(['query.wikidata.org/*' => Http::sequence()
            ->push('', 503)->push('', 503)->push('', 503)->push('', 503)
            ->push(['results' => ['bindings' => [['code' => ['value' => '956000'], 'article' => ['value' => 'https://fr.wikipedia.org/wiki/L']]]]])]);

        $this->assertSame([], $this->wiki->wikidataLineCodes());
        Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(10)->seconds(), Sleep::for(15)->seconds()]);
        $this->assertSame(['956000' => ['L']], $this->wiki->wikidataLineCodes(), 'appel suivant : nouvelle requête');
    }

    public function test_search_is_cached_per_query_and_limit(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response(['query' => ['search' => [['title' => 'Ligne A'], ['title' => 'Ligne B']]]])]);

        $this->assertSame(['Ligne A', 'Ligne B'], $this->wiki->search('ligne', 2));
        $this->assertSame(['Ligne A', 'Ligne B'], $this->wiki->search('ligne', 2));
        Http::assertSentCount(1);
        $this->wiki->search('ligne', 3);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['srsearch'] === 'ligne' && (int) $r['srlimit'] === 3 && (int) $r['srnamespace'] === 0);
    }

    public function test_permalink(): void
    {
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Ligne_de_Savenay_%C3%A0_Landerneau&oldid=123',
            WikimediaClient::permalink('Ligne de Savenay à Landerneau', 123));
    }
}
