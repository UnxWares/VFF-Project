<?php

namespace Tests\Feature\Web;

use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\ValidatesJsonLd;
use Tests\TestCase;

/**
 * Données structurées schema.org (JSON-LD) de chaque type de page publique, lues dans le HTML que reçoit un
 * robot : validées hors ligne (ValidatesJsonLd : propriétés obligatoires et propriétés admises), traduites selon
 * la langue de la page, sans fait d'une source dont la licence ne permet pas la redistribution, et sous la politique
 * de sécurité bloquante (nonce du gabarit).
 */
class JsonLdPagesTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;
    use ValidatesJsonLd;

    /** @param  TestResponse<Response>  $response */
    private function blocks(TestResponse $response): array
    {
        $doc = HTMLDocument::createFromString((string) $response->getContent(), LIBXML_NOERROR);
        $out = [];
        foreach ($doc->querySelectorAll('head script[type="application/ld+json"][data-vff-seo]') as $script) {
            $data = json_decode($script->textContent, true);
            $this->assertIsArray($data);
            $this->assertValidJsonLd($data, (string) $response->baseResponse->getStatusCode());
            $out[] = $data;
        }

        return $out;
    }

    /** @return array<string, mixed> premier bloc de ce type (@type), au premier niveau ou dans un @graph */
    private function ofType(array $blocks, string $type): array
    {
        foreach ($blocks as $block) {
            foreach ($block['@graph'] ?? [$block] as $node) {
                if (in_array($type, (array) ($node['@type'] ?? []), true)) {
                    return $node;
                }
            }
        }
        $this->fail("aucun bloc {$type}");
    }

    public function test_home_has_the_website_search_and_the_publisher_logo(): void
    {
        $response = $this->withHeader('Accept-Language', 'en')->get('/')->assertOk();
        $blocks = $this->blocks($response);

        $site = $this->ofType($blocks, 'WebSite');
        $this->assertStringEndsWith('/search?q={search_term_string}', $site['potentialAction']['target']['urlTemplate']);
        $this->assertSame('en', $site['inLanguage']);
        $org = $this->ofType($blocks, 'Organization');
        $this->assertSame('UnxWares SAS', $org['legalName']);
        $this->assertStringEndsWith('/icons/icon-512.png', $org['logo']['url']);
        $this->assertFileExists(public_path('icons/icon-512.png'));
    }

    public function test_the_script_carries_the_nonce_of_the_policy_and_the_blocking_policy_is_set(): void
    {
        config(['csp.enforce' => true]);

        $response = $this->get('/')->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        preg_match("/script-src [^;]*'nonce-([^']+)'/", $csp, $m);
        $this->assertNotEmpty($m, 'le nonce est dans script-src');
        $this->assertStringContainsString('type="application/ld+json" nonce="'.$m[1].'">', (string) $response->getContent());
    }

    public function test_five_line_sheets(): void
    {
        $this->buildRailNetwork();
        foreach ([$this->origin, $this->destination] as $pk => $station) {
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => $pk * 20000,
                'created_at' => now(), 'updated_at' => now()]);
        }
        $slugs = [$this->line->slug];
        foreach ([1, 2, 3, 4] as $i) {
            $slugs[] = Line::factory()->create(['slug' => "rfn-90{$i}000", 'code' => "90{$i}000", 'name' => "Ligne d'essai {$i}"])->slug;
        }
        DB::table('line_sources')->insert(['line_id' => Line::where('slug', 'rfn-901000')->value('id'), 'source_type' => 'wikipedia-intro',
            'source_url' => 'https://fr.wikipedia.org/wiki/X', 'fetched_at' => now(), 'fields_provided' => json_encode(['title' => 'X']),
            'created_at' => now(), 'updated_at' => now()]);
        Line::where('slug', 'rfn-901000')->update(['description' => 'Extrait de Wikipédia.']);
        Line::where('slug', 'rfn-902000')->update(['description' => 'Texte de source inconnue.']);

        foreach ($slugs as $slug) {
            $place = $this->ofType($this->blocks($this->get("/lines/{$slug}")->assertOk()), 'Place');
            $this->assertSame(url("/lines/{$slug}").'#line', $place['@id']);
        }
        $this->assertSame('Extrait de Wikipédia.', $this->ofType($this->blocks($this->get('/lines/rfn-901000')), 'Place')['description']);
        $this->assertArrayNotHasKey('description', $this->ofType($this->blocks($this->get('/lines/rfn-902000')), 'Place'), 'extrait sans source déclarée : pas dans les données structurées');
        $terminals = $this->ofType($this->blocks($this->get('/lines/'.$this->line->slug)), 'Place')['containsPlace'] ?? [];
        $this->assertContains($this->origin->name, array_column($terminals, 'name'));
    }

    public function test_five_station_sheets_with_dates_only_when_sourced_and_publishable(): void
    {
        $cases = [
            'sure' => ['1849-09-01', 'month', ['opened_at' => 'wikidata'], '1849-09'],
            'jour' => ['1849-09-02', 'day', ['opened_at' => 'osm'], '1849-09-02'],
            'incertaine' => ['1849-09-01', 'day', ['opened_at' => 'lignes-oubliees'], null],
            'deduite' => ['1849-09-01', 'day', ['opened_at' => 'inferred:line_event'], null],
            'intervalle' => ['1849-01-01', 'range', ['opened_at' => 'wikidata'], null],
        ];
        foreach ($cases as $slug => [$date, $precision, $provenance, $expected]) {
            Station::factory()->create(['slug' => $slug, 'name' => "Gare {$slug}", 'city' => 'Testville', 'opened_at' => $date,
                'opened_precision' => $precision, 'provenance' => $provenance, 'wikidata_id' => 'Q42', 'description' => 'Texte de source inconnue.']);
            $station = $this->ofType($this->blocks($this->withHeader('Accept-Language', 'fr')->get("/stations/{$slug}")->assertOk()), 'TrainStation');

            $this->assertSame('https://www.wikidata.org/wiki/Q42', $station['sameAs'][0]);
            $this->assertSame('Testville', $station['address']['addressLocality']);
            $this->assertArrayNotHasKey('description', $station);
            if ($expected === null) {
                $this->assertArrayNotHasKey('additionalProperty', $station, $slug);
            } else {
                $this->assertSame([['@type' => 'PropertyValue', 'name' => 'Date d’ouverture', 'value' => $expected]], $station['additionalProperty'], $slug);
            }
        }
    }

    public function test_five_accident_sheets_in_the_language_of_the_page(): void
    {
        foreach (['1842-05-08', '1891-06-14', '1910-01-01', '1957-12-04', '2013-07-12'] as $i => $date) {
            Accident::factory()->on($date)->create(['slug' => "{$date}-lieu-{$i}", 'name' => ['fr' => "Accident {$i}", 'en' => "Accident {$i} EN", 'de' => "Unfall {$i}"],
                'summary' => ['fr' => 'Un déraillement.', 'en' => 'A derailment.', 'de' => 'Eine Entgleisung.']]);
            $event = $this->ofType($this->blocks($this->withHeader('Accept-Language', 'de')->get("/accidents/{$date}-lieu-{$i}")->assertOk()), 'Event');
            $this->assertSame("Unfall {$i}", $event['name']);
            $this->assertSame($date, $event['startDate']);
        }
    }

    public function test_five_material_sheets(): void
    {
        foreach (['bb-15000', 'bb-26000', 'x-2800', 'z-5300', 'tgv-sud-est'] as $slug) {
            Material::factory()->fromWikidata()->create(['slug' => $slug, 'name' => strtoupper($slug), 'type' => 'locomotive_electrique']);
            $material = $this->ofType($this->blocks($this->get("/materials/{$slug}")->assertOk()), 'Thing');
            $this->assertSame(strtoupper($slug), $material['name']);
        }
    }

    public function test_commune_sheets_are_a_city_with_its_stations(): void
    {
        $this->buildRailNetwork();
        foreach ([['99100', 'Testville', 'testville', 2.30, 2.35], ['99101', 'Voisineville', 'voisineville', 2.20, 2.30]] as [$insee, $name, $norm, $west, $east]) {
            DB::table('communes')->insert(['code_insee' => $insee, 'type' => 'commune-actuelle', 'nom' => $name, 'nom_norm' => $norm, 'departement' => '75',
                'population' => 1000, 'created_at' => now(), 'updated_at' => now(), 'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, 48.87), 4326)', ($west + $east) / 2))]);
            DB::table('commune_contours')->insert(['code_insee' => $insee, 'nom' => $name, 'departement' => '75', 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw(sprintf("ST_Multi(ST_GeomFromText('POLYGON((%F 48.80, %F 48.80, %F 48.89, %F 48.89, %F 48.80))', 4326))", $west, $east, $east, $west, $west))]);
            DB::table('commune_rail')->insert(['code_insee' => $insee, 'stations_count' => $insee === '99100' ? 1 : 0, 'lines_count' => 1, 'built_at' => now()]);
        }

        $blocks = $this->blocks($this->get('/communes/99100-testville')->assertOk());
        $city = $this->ofType($blocks, 'City');
        $this->assertSame('Testville', $city['name']);
        $this->assertSame('99100', $city['identifier']['value']);
        $this->assertSame($this->origin->name, $city['containsPlace'][0]['name']);
        $this->assertSame('BreadcrumbList', $this->ofType($blocks, 'BreadcrumbList')['@type']);

        $this->assertSame('Voisineville', $this->ofType($this->blocks($this->get('/communes/99101-voisineville')->assertOk()), 'City')['name']);
    }

    public function test_open_data_page_has_an_odbl_dataset(): void
    {
        $root = sys_get_temp_dir().'/vff-jsonld-open-data-'.bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents("{$root}/catalogue.json", json_encode(['format' => 1, 'datasets' => [
            ['id' => 'events', 'kind' => 'events', 'generated_on' => '2026-11-20', 'archive' => ['name' => 'events.zip', 'bytes' => 1000],
                'files' => [['name' => 'events.csv', 'bytes' => 4000, 'rows' => 10]], 'credits' => [['key' => 'wikipedia', 'name' => 'Wikipédia', 'licence' => 'CC BY-SA 4.0']]],
        ]]));
        config(['open_data.root' => $root]);

        try {
            $blocks = $this->blocks($this->withHeader('Accept-Language', 'en')->get('/open-data')->assertOk());
        } finally {
            unlink("{$root}/catalogue.json");
            rmdir($root);
        }

        $dataset = $this->ofType($blocks, 'Dataset');
        $this->assertSame('https://opendatacommons.org/licenses/odbl/1-0/', $dataset['license']);
        $this->assertSame('Open data', $dataset['name']);
        $this->assertSame('Dated events', $dataset['hasPart'][0]['name']);
        $this->assertStringEndsWith('/downloads/open-data/events.zip', $dataset['hasPart'][0]['distribution'][0]['contentUrl']);
        $this->assertStringNotContainsString('Wikip', json_encode($dataset), 'aucune source nommée');
    }
}
