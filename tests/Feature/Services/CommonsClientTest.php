<?php

namespace Tests\Feature\Services;

use App\Services\Media\CommonsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

/**
 * Client Wikimedia Commons / Wikidata (CommonsClient), sans réseau
 * (Http::fake) et sans attente (Sleep::fake) : paramètres envoyés, lots de 50,
 * pagination, alias de titres normalisés, reprises sur erreurs transitoires,
 * erreurs définitives remontées.
 */
class CommonsClientTest extends TestCase
{
    private CommonsClient $commons;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        $this->commons = new CommonsClient(minIntervalMs: 0);
    }

    public function test_requests_are_polite(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response(['query' => []]), 'www.wikidata.org/*' => Http::response(['entities' => []])]);

        $this->assertSame(['query' => []], $this->commons->commons(['action' => 'query']));
        $this->assertSame(['entities' => []], $this->commons->wikidata(['action' => 'wbgetentities', 'ids' => 'Q1']));

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://commons.wikimedia.org/w/api.php')
            && $r->hasHeader('User-Agent', CommonsClient::USER_AGENT) && (int) $r['maxlag'] === 5 && (int) $r['formatversion'] === 2);
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://www.wikidata.org/w/api.php') && $r['ids'] === 'Q1');
        $this->assertSame(2, $this->commons->requestCount);
        $this->assertSame(0, $this->commons->retryCount);
    }

    public function test_minimal_interval_between_two_requests(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response(['query' => []])]);
        $slow = new CommonsClient(minIntervalMs: 250);

        $slow->commons(['action' => 'query']);
        $slow->commons(['action' => 'query']);

        Sleep::assertSleptTimes(1);
    }

    public function test_sparql_returns_the_bindings(): void
    {
        Http::fake(['query.wikidata.org/*' => Http::response(['results' => ['bindings' => [['item' => ['value' => 'Q1']]]]])]);

        $this->assertSame([['item' => ['value' => 'Q1']]], $this->commons->sparql('SELECT ?item WHERE {}'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['query'] === 'SELECT ?item WHERE {}'
            && $r->hasHeader('Accept', 'application/sparql-results+json'));
    }

    public function test_category_info_maps_normalized_titles_back(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response(['query' => [
            'normalized' => [['from' => 'Category:Ligne_de_Brest', 'to' => 'Category:Ligne de Brest']],
            'pages' => [
                ['title' => 'Category:Ligne de Brest', 'categoryinfo' => ['files' => '12', 'subcats' => 3]],
                ['title' => 'Category:Absente', 'missing' => true],
            ],
        ]])]);

        $info = $this->commons->categoryInfo(['Category:Ligne_de_Brest', 'Category:Absente']);

        $this->assertSame([
            'Category:Ligne de Brest' => ['exists' => true, 'files' => 12, 'subcats' => 3],
            'Category:Ligne_de_Brest' => ['exists' => true, 'files' => 12, 'subcats' => 3],
            'Category:Absente' => ['exists' => false, 'files' => 0, 'subcats' => 0],
        ], $info);
    }

    public function test_category_members_are_paginated_up_to_the_limit(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::sequence()
            ->push(['continue' => ['cmcontinue' => 'page|2'], 'query' => ['categorymembers' => [
                ['pageid' => 1, 'title' => 'File:A.jpg', 'type' => 'file'],
                ['pageid' => 2, 'title' => 'Category:Sous'],
            ]]])
            ->push(['continue' => ['cmcontinue' => 'page|3'], 'query' => ['categorymembers' => [
                ['pageid' => 3, 'title' => 'File:B.jpg', 'type' => 'file'],
                ['pageid' => 4, 'title' => 'File:C.jpg', 'type' => 'file'],
            ]]])]);

        $members = $this->commons->categoryMembers('Category:Ligne de Brest', 'file|subcat', 3);

        $this->assertSame([
            ['pageid' => 1, 'title' => 'File:A.jpg', 'type' => 'file'],
            ['pageid' => 2, 'title' => 'Category:Sous', 'type' => 'file'],
            ['pageid' => 3, 'title' => 'File:B.jpg', 'type' => 'file'],
        ], $members);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => (int) $r['cmlimit'] === 3 && ! isset($r['cmcontinue']));
        Http::assertSent(fn (Request $r) => (int) $r['cmlimit'] === 1 && $r['cmcontinue'] === 'page|2');
    }

    public function test_geosearch_clamps_its_parameters(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response(['query' => ['geosearch' => [
            ['pageid' => '7', 'title' => 'File:Gare.jpg', 'lat' => '48.1', 'lon' => '-1.6'],
        ]]])]);

        $this->assertSame([['pageid' => 7, 'title' => 'File:Gare.jpg', 'lat' => 48.1, 'lon' => -1.6]], $this->commons->geosearch(48.123456789, -1.6, 50000, 900));
        Http::assertSent(fn (Request $r) => $r['gscoord'] === '48.123457|-1.600000' && (int) $r['gsradius'] === 10000
            && (int) $r['gslimit'] === 500 && (int) $r['gsnamespace'] === 6);

        $this->commons->geosearch(48.0, 2.0, 1);
        Http::assertSent(fn (Request $r) => (int) $r['gsradius'] === 10);
    }

    public function test_file_info_categories_and_titles(): void
    {
        Http::fake(['commons.wikimedia.org/*' => function (Request $r) {
            return match (true) {
                ($r['prop'] ?? null) === 'imageinfo' => Http::response(['query' => ['pages' => [
                    ['pageid' => 7, 'title' => 'File:Gare.jpg', 'imageinfo' => [['url' => 'https://upload/x.jpg', 'width' => 800]]],
                    ['pageid' => 8, 'title' => 'File:Perdu.jpg', 'missing' => true],
                    ['pageid' => 9, 'title' => 'File:SansInfo.jpg'],
                ]]]),
                ($r['prop'] ?? null) === 'categories' => Http::response(['query' => ['pages' => [
                    ['pageid' => 7, 'categories' => [['title' => 'Category:Gares de Bretagne'], ['title' => 'Category:Ligne de Brest']]],
                    ['pageid' => 8],
                ]]]),
                default => Http::response(['query' => ['pages' => [
                    ['pageid' => 7, 'title' => 'File:Gare.jpg'],
                    ['title' => 'File:Absent.jpg', 'missing' => true],
                ]]]),
            };
        }]);

        $this->assertSame([7 => ['title' => 'File:Gare.jpg', 'info' => ['url' => 'https://upload/x.jpg', 'width' => 800]]], $this->commons->fileInfo([7, 8, 9, 7]));
        Http::assertSent(fn (Request $r) => ($r['prop'] ?? null) === 'imageinfo' && $r['pageids'] === '7|8|9'
            && (int) $r['iiurlwidth'] === CommonsClient::THUMB_WIDTH && $r['iiextmetadatalanguage'] === 'fr');

        $this->assertSame([7 => ['Gares de Bretagne', 'Ligne de Brest'], 8 => []], $this->commons->categoriesOf([7, 8]));
        Http::assertSent(fn (Request $r) => ($r['prop'] ?? null) === 'categories' && $r['clshow'] === '!hidden');

        $this->assertSame(['File:Gare.jpg' => 7], $this->commons->resolveFileTitles(['File:Gare.jpg', 'File:Absent.jpg']));
    }

    public function test_transient_errors_are_retried(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::sequence()
            ->push('', 429, ['Retry-After' => '2'])
            ->push(['error' => ['code' => 'maxlag', 'info' => 'Waiting for db']], 200)
            ->push(['error' => ['code' => 'internal_api_error_DBQueryError', 'info' => 'x']], 200)
            ->push(['query' => ['ok' => 1]], 200)]);

        $this->assertSame(['query' => ['ok' => 1]], $this->commons->commons(['action' => 'query']));
        $this->assertSame(3, $this->commons->retryCount);
        $this->assertSame(4, $this->commons->requestCount);
        // 429 : au moins 5 s ; maxlag : 5 × 1² puis 5 × 2².
        Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(5)->seconds(), Sleep::for(20)->seconds()]);
    }

    public function test_connection_errors_are_retried_then_reported(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::failedConnection('timeout')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wikimedia injoignable après plusieurs tentatives');

        try {
            $this->commons->commons(['action' => 'query']);
        } finally {
            $this->assertSame(5, $this->commons->requestCount);
            Sleep::assertSleptTimes(5);
        }
    }

    public function test_definitive_errors_are_raised(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response(['error' => ['code' => 'badvalue', 'info' => 'Unrecognized value']], 200)]);
        try {
            $this->commons->commons(['action' => 'query']);
            $this->fail('exception attendue');
        } catch (RuntimeException $e) {
            $this->assertSame('API MediaWiki : Unrecognized value', $e->getMessage());
        }

        Http::fake(['www.wikidata.org/*' => Http::response('interdit', 403)]);
        $this->expectException(RequestException::class);
        $this->commons->wikidata(['action' => 'wbgetentities']);
    }
}
