<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\Material;
use App\Services\Materials\SourceCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\InMemorySourceCache;
use Tests\TestCase;

/**
 * vff:import-materials de bout en bout, sources simulées (SPARQL, wbgetentities, Commons,
 * Wikipédia) : fiches retenues ou écartées, image et droits, liens attestés, relance sans effet,
 * cache réutilisé (aucune requête à la relance).
 */
class ImportMaterialsCommandTest extends TestCase
{
    use RefreshDatabase;

    private InMemorySourceCache $cache;

    /** @var list<string> requêtes envoyées (hôte et action) */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        $this->cache = new InMemorySourceCache;
        $this->app->instance(SourceCache::class, $this->cache);
        Line::factory()->create(['slug' => 'rfn-67000', 'name' => 'Ligne de Lunéville à St-Dié', 'wikipedia_title' => 'Ligne de Lunéville à Saint-Dié']);
        Http::fake(fn (Request $request) => $this->respond($request));
    }

    public function test_the_import_creates_sheets_and_attested_links(): void
    {
        $this->artisan('vff:import-materials')
            ->expectsOutputToContain('3 série(s) candidate(s) dans Wikidata.')
            ->expectsOutputToContain('1 fiche(s) retenue(s).')
            ->expectsOutputToContain('Fiches : 1 créée(s), 0 mise(s) à jour, 0 inchangée(s).')
            ->expectsOutputToContain('Liens : 1 créé(s), 0 mis à jour, 0 retiré(s).')
            ->assertSuccessful();

        $material = Material::sole();
        $this->assertSame(['bb-15000', 'BB 15000', 'locomotive_electrique', 1971, 160, 1435], [
            $material->slug, $material->name, $material->type->value, $material->in_service_from, $material->max_speed_kmh, $material->gauge_mm,
        ]);
        $this->assertSame('Alstom', $material->builder);
        $this->assertSame('CC BY-SA 4.0', $material->image['license']);
        $this->assertSame('Florian Fèvre', $material->image['author']);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:BB_15025.jpg', $material->image['source_url']);
        $this->assertEqualsCanonicalizing(['materiel_de_metro', 'categorie_inconnue'], array_keys($this->cache->tables['skipped']));

        $link = LineMaterial::sole();
        $this->assertSame(['wikipedia', 'high'], [$link->source, $link->confidence]);
        $this->assertSame('Rames Corail tractées par des BB 15000.', $link->evidence[0]['quote']);
        $this->assertStringEndsWith('&oldid=239088070', (string) $link->source_url);
    }

    public function test_a_second_run_uses_the_cache_and_changes_nothing(): void
    {
        $this->artisan('vff:import-materials')->assertSuccessful();
        $this->requests = [];

        $this->artisan('vff:import-materials')
            ->expectsOutputToContain('Fiches : 0 créée(s), 0 mise(s) à jour, 1 inchangée(s).')
            ->expectsOutputToContain('Liens : 0 créé(s), 0 mis à jour, 0 retiré(s).')
            ->assertSuccessful();
        $this->assertSame([], $this->requests, 'tout vient du cache local');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('vff:import-materials', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Material::count());
        $this->assertSame(0, LineMaterial::count());
    }

    private function respond(Request $request): mixed
    {
        $host = (string) parse_url($request->url(), PHP_URL_HOST);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $this->requests[] = $host.' '.($query['action'] ?? 'sparql');

        return match ($host) {
            'query.wikidata.org' => Http::response(['results' => ['bindings' => $this->sparql((string) ($request->data()['query'] ?? ''))]]),
            'www.wikidata.org' => Http::response($this->wikidata($query)),
            'commons.wikimedia.org' => Http::response($this->commons($query)),
            'fr.wikipedia.org' => Http::response($this->wikipedia($query)),
            default => Http::response([], 404),
        };
    }

    /** @return list<array<string, mixed>> */
    private function sparql(string $query): array
    {
        $uri = fn (string $q): array => ['value' => "http://www.wikidata.org/entity/{$q}"];

        return match (true) {
            str_contains($query, 'VALUES ?root') => [
                ['item' => $uri('Q746572'), 'root' => $uri('Q625151')],
                ['item' => $uri('Q613769'), 'root' => $uri('Q1199344')],
            ],
            str_contains($query, 'P3438') => [],
            default => [['item' => $uri('Q746572')], ['item' => $uri('Q613769')], ['item' => $uri('Q9')]],
        };
    }

    /** @return array<string, mixed> */
    private function wikidata(array $query): array
    {
        $item = fn (string $id): array => ['mainsnak' => ['snaktype' => 'value', 'datavalue' => ['type' => 'wikibase-entityid', 'value' => ['id' => $id]]], 'rank' => 'normal'];
        $value = fn (string $type, mixed $v): array => ['mainsnak' => ['snaktype' => 'value', 'datavalue' => ['type' => $type, 'value' => $v]], 'rank' => 'normal'];
        $label = fn (string $fr): array => ['fr' => ['value' => $fr]];

        if (($query['props'] ?? '') === 'labels') {
            return ['entities' => [
                'Q309084' => ['labels' => $label('Alstom')],
                'Q13646' => ['labels' => $label('SNCF')],
                'Q1999572' => ['labels' => $label('écartement standard')],
            ]];
        }
        if (($query['props'] ?? '') === 'claims') {
            return ['entities' => ['Q1999572' => ['claims' => ['P2049' => [$value('quantity', ['amount' => '+1435', 'unit' => 'http://www.wikidata.org/entity/Q174789'])]]]]];
        }

        return ['entities' => [
            'Q746572' => [
                'labels' => $label('SNCF BB 15000'),
                'descriptions' => $label('série de 65 locomotives électriques de la SNCF'),
                'sitelinks' => ['frwiki' => ['title' => 'BB 15000']],
                'claims' => [
                    'P176' => [$item('Q309084')],
                    'P137' => [$item('Q13646')],
                    'P1064' => [$item('Q1999572')],
                    'P729' => [$value('time', ['time' => '+1971-00-00T00:00:00Z', 'precision' => 9])],
                    'P2052' => [$value('quantity', ['amount' => '+160', 'unit' => 'http://www.wikidata.org/entity/Q180154'])],
                    'P18' => [$value('string', 'BB 15025.jpg')],
                ],
            ],
            'Q613769' => ['labels' => $label('MP 59'), 'claims' => ['P137' => [$item('Q643290')]]],
            'Q9' => ['labels' => $label('Engin sans classe'), 'claims' => []],
        ]];
    }

    /** @return array<string, mixed> */
    private function commons(array $query): array
    {
        if (isset($query['titles'])) {
            return ['query' => ['pages' => [['pageid' => 42, 'title' => 'File:BB 15025.jpg']]]];
        }

        return ['query' => ['pages' => [[
            'pageid' => 42, 'title' => 'File:BB 15025.jpg',
            'imageinfo' => [[
                'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/BB_15025.jpg',
                'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/BB_15025.jpg/500px-BB_15025.jpg',
                'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:BB_15025.jpg',
                'width' => 4928, 'height' => 3264, 'mime' => 'image/jpeg',
                'extmetadata' => [
                    'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                    'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0'],
                    'Artist' => ['value' => 'Florian Fèvre'],
                ],
            ]],
        ]]]];
    }

    /** @return array<string, mixed> */
    private function wikipedia(array $query): array
    {
        if (($query['prop'] ?? '') === 'redirects') {
            return ['query' => ['pages' => []]];
        }
        $articles = [
            'Ligne de Lunéville à Saint-Dié' => "== Trafic ==\n=== Matériel roulant ===\nRames Corail tractées par des [[BB 15000]].",
            'BB 15000' => "== Conception ==\nRien sur les lignes.",
        ];
        $pages = [];
        foreach (explode('|', (string) ($query['titles'] ?? '')) as $title) {
            if (isset($articles[$title])) {
                $pages[] = ['title' => $title, 'revisions' => [['revid' => 239088070, 'slots' => ['main' => ['content' => $articles[$title]]]]]];
            }
        }

        return ['query' => ['pages' => $pages]];
    }
}
