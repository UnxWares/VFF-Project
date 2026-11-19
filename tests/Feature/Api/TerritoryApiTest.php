<?php

namespace Tests\Feature\Api;

use App\Models\Accident;
use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Liste de référence des départements et des régions (GET /api/v1/departments, /regions, requêtes
 * GraphQL `departments`, `regions`), filtres `region`, compteurs par département des annuaires, et
 * liens de la fiche gare (accidents survenus près de la gare, département, lignes, gares voisines).
 *
 * Réseau : la ligne Paris-Saint-Lazare - Versailles (fiche : Yvelines ; gares à Paris et dans les
 * Yvelines, Île-de-France) et une ligne bretonne (fiche : Finistère, gare à Morlaix).
 */
class TerritoryApiTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Line $breton;

    private Station $morlaix;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
        $this->line->update(['departements' => json_encode(['Yvelines'])]);
        $this->origin->update(['department_code' => '75']);
        $this->destination->update(['department_code' => '78']);
        $this->breton = Line::factory()->create(['slug' => 'ligne-de-morlaix-a-roscoff', 'name' => 'Ligne de Morlaix à Roscoff', 'departements' => json_encode(['Finistère'])]);
        $this->morlaix = Station::factory()->create(['slug' => 'morlaix', 'name' => 'Morlaix', 'department_code' => '29',
            'geom' => Point::makeGeodetic(48.578, -3.832), 'status' => 'active']);
        foreach ([[$this->origin, $this->line], [$this->destination, $this->line], [$this->morlaix, $this->breton]] as [$station, $line]) {
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'test', 'created_at' => now(), 'updated_at' => now()]);
        }
        Accident::factory()->create(['slug' => '1933-12-23-lagny', 'department_code' => '77', 'nearest_station_id' => $this->origin->id, 'nearest_station_distance_m' => 900]);
        Accident::factory()->create(['slug' => '1910-05-01-morlaix', 'department_code' => '29', 'nearest_station_id' => $this->morlaix->id, 'nearest_station_distance_m' => 300]);
    }

    public function test_departments_list_codes_names_regions_counts_and_links(): void
    {
        $response = $this->getJson('/api/v1/departments')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=600, public')
            ->assertJsonPath('meta.total', 101)
            ->assertJsonPath('meta.source.provider', 'insee')
            ->assertJsonStructure(['data' => [['code', 'name', 'region' => ['code', 'name'], 'counts' => ['lines', 'stations', 'accidents'],
                'links' => ['self', 'lines', 'stations', 'events', 'accidents']]]]);

        $codes = $response->json('data.*.code');
        $this->assertSame(['01', '02', '03'], array_slice($codes, 0, 3));
        $this->assertContains('2A', $codes);
        $this->assertSame('976', end($codes));
        $this->assertContainsOnlyString($codes, 'codes en chaînes, jamais en nombres');

        $byCode = collect((array) $response->json('data'))->keyBy('code');
        $this->assertSame(['code' => '53', 'name' => 'Bretagne'], $byCode['29']['region']);
        $this->assertSame(['lines' => 1, 'stations' => 1, 'accidents' => 1], $byCode['29']['counts']);
        $this->assertSame(['lines' => 1, 'stations' => 1, 'accidents' => 0], $byCode['75']['counts'], 'la ligne dessert une gare à Paris');
        $this->assertSame(['lines' => 1, 'stations' => 1, 'accidents' => 0], $byCode['78']['counts']);
        $this->assertSame(0, $byCode['77']['counts']['lines']);
        $this->assertSame(1, $byCode['77']['counts']['accidents']);
        $this->assertStringEndsWith('/api/v1/lines?department=29', $byCode['29']['links']['lines']);

        // Chaque compteur de lignes est le total du filtre `department` de l'annuaire.
        foreach (['29', '75', '78', '77'] as $code) {
            $total = $this->getJson("/api/v1/lines?department={$code}&facets=true&per_page=1")->json('meta.facets.total');
            $this->assertSame($byCode[$code]['counts']['lines'], $total, "département {$code}");
        }
    }

    public function test_departments_of_a_region_one_department_and_errors(): void
    {
        $this->assertSame(['22', '29', '35', '56'], $this->getJson('/api/v1/departments?region=53')->assertOk()->json('data.*.code'));
        $this->getJson('/api/v1/departments?region=99')->assertUnprocessable()->assertJsonValidationErrors('region');

        $this->getJson('/api/v1/departments/2a')->assertOk()
            ->assertJsonPath('data.code', '2A')
            ->assertJsonPath('data.name', 'Corse-du-Sud')
            ->assertJsonPath('data.region.code', '94')
            ->assertJsonPath('data.links.region', route('api.regions.show', '94'));
        $this->getJson('/api/v1/departments/99')->assertNotFound()->assertJsonPath('message', 'Unknown department.');
        $this->getJson('/api/v1/departments/abc')->assertNotFound();
    }

    public function test_regions_list_their_departments_and_count_a_line_once(): void
    {
        $response = $this->getJson('/api/v1/regions')->assertOk()->assertJsonPath('meta.total', 18);
        $names = $response->json('data.*.name');
        $this->assertSame('Auvergne-Rhône-Alpes', $names[0], 'ordre alphabétique français');
        $this->assertSame(['Bretagne', 'Centre-Val de Loire', 'Corse'], array_slice($names, 2, 3));

        $idf = $this->getJson('/api/v1/regions/11')->assertOk()->json('data');
        $this->assertSame('Île-de-France', $idf['name']);
        $this->assertSame(['75', '77', '78', '91', '92', '93', '94', '95'], array_column($idf['departments'], 'code'));
        $this->assertSame(['lines' => 1, 'stations' => 2, 'accidents' => 1], $idf['counts'], 'ligne à Paris et dans les Yvelines comptée une fois');
        $this->assertStringEndsWith('/api/v1/departments?region=11', $idf['links']['departments']);
        $this->getJson('/api/v1/regions/99')->assertNotFound()->assertJsonPath('message', 'Unknown region.');
    }

    public function test_region_filters_of_the_lists(): void
    {
        $slugs = fn (string $url): array => $this->getJson($url)->assertOk()->json('data.*.slug');

        $this->assertSame([$this->breton->slug], $slugs('/api/v1/lines?region=53'));
        $this->assertSame([$this->line->slug], $slugs('/api/v1/lines?region=11'));
        $this->assertSame([], $slugs('/api/v1/lines?region=84'));
        $this->assertSame(['morlaix'], $slugs('/api/v1/stations?region=53'));
        $this->assertSame(['1910-05-01-morlaix'], $slugs('/api/v1/accidents?region=53'));
        $this->assertSame(['1933-12-23-lagny'], $slugs('/api/v1/accidents?region=11'));
        $this->assertSame([$this->line->slug], array_values(array_unique($this->getJson('/api/v1/events?region=11')->assertOk()->json('data.*.line.slug'))));
        $this->assertSame([], $this->getJson('/api/v1/events?region=53')->assertOk()->json('data'));

        foreach (['lines', 'stations', 'events', 'accidents'] as $list) {
            $this->getJson("/api/v1/{$list}?region=99")->assertUnprocessable()->assertJsonValidationErrors('region');
        }
    }

    public function test_directory_facets_count_by_department(): void
    {
        $lines = $this->getJson('/api/v1/lines?facets=true&department=29')->assertOk()->json('meta.facets.departments');
        $this->assertSame(['29' => 1, '75' => 1, '78' => 1], $lines, 'sans le filtre department');

        $empty = $this->getJson('/api/v1/lines?facets=true&q=introuvable')->assertOk();
        $this->assertStringContainsString('"departments":{}', $empty->getContent(), 'objet JSON même vide');

        $stations = $this->getJson('/api/v1/stations?facets=true&department=29')->assertOk();
        $this->assertSame(['active' => 1], $stations->json('meta.facets'));
        $this->assertSame(['29' => 1, '75' => 1, '78' => 1], $stations->json('meta.department_facets'));
        $this->getJson('/api/v1/stations')->assertOk()->assertJsonMissingPath('meta.department_facets');
    }

    public function test_station_sheet_links_accidents_department_lines_and_neighbors(): void
    {
        $sheet = $this->getJson('/api/v1/stations/paris-saint-lazare')->assertOk()->json('data');

        $this->assertSame(route('api.accidents.index', ['station' => 'paris-saint-lazare']), $sheet['links']['accidents']);
        $this->assertSame(route('api.departments.show', '75'), $sheet['links']['department']);
        $this->assertSame(route('api.lines.show', $this->line->slug), $sheet['lines'][0]['links']['api']);
        $this->assertSame(url('/lines/'.$this->line->slug), $sheet['lines'][0]['links']['web']);

        // Chaque lien répond, et le lien des accidents donne ceux dont la gare est la plus proche.
        foreach (['events', 'media', 'accidents', 'department'] as $key) {
            $this->getJson($sheet['links'][$key])->assertOk();
        }
        $this->assertSame(['1933-12-23-lagny'], $this->getJson($sheet['links']['accidents'])->json('data.*.slug'));
        $this->getJson('/api/v1/accidents?station=inconnue')->assertUnprocessable()->assertJsonValidationErrors('station');

        $this->origin->update(['department_code' => null]);
        Cache::flush();
        $this->assertNull($this->getJson('/api/v1/stations/paris-saint-lazare')->json('data.links.department'));
    }

    public function test_station_directory_matches_the_web_page_for_a_department(): void
    {
        $web = $this->get('/stations?dept=29')->assertOk()->viewData('page')['props'];
        $api = $this->getJson('/api/v1/stations?department=29&facets=true')->assertOk();

        $this->assertSame(array_column($web['stations']['data'], 'slug'), $api->json('data.*.slug'));
        $this->assertSame($web['stations']['total'], $api->json('meta.department_facets.29'));
        $this->assertEqualsCanonicalizing($web['departments'], array_map('strval', array_keys($api->json('meta.department_facets'))));
    }

    public function test_graphql_territories_filters_and_facets(): void
    {
        $data = $this->graphQL('{
            departments(region: "53") { code name region { code name } counts { lines stations accidents } }
            department(code: "29") { name counts { lines } }
            regions { code name departments { code name } counts { lines } }
            region(code: "11") { name counts { lines stations accidents } }
            lineDirectory(filter: { region: "53" }) { data { slug } facets { departments { code count } } }
            stationDirectory(filter: { department: "29" }) { data { slug } department_facets { code count } }
            accidents(filter: { station: "morlaix" }) { data { slug } }
        }')->assertJsonMissingPath('errors')->json('data');

        $rest = $this->getJson('/api/v1/departments?region=53')->json('data');
        $this->assertSame(array_map(fn (array $d): array => array_diff_key($d, ['links' => true]), $rest), $data['departments']);
        $this->assertSame(['name' => 'Finistère', 'counts' => ['lines' => 1]], $data['department']);
        $this->assertCount(18, $data['regions']);
        $this->assertSame(['name' => 'Île-de-France', 'counts' => ['lines' => 1, 'stations' => 2, 'accidents' => 1]], $data['region']);
        $this->assertSame([['slug' => $this->breton->slug]], $data['lineDirectory']['data']);
        $this->assertSame([['code' => '29', 'count' => 1]], $data['lineDirectory']['facets']['departments']);
        $this->assertContains(['code' => '75', 'count' => 1], $data['stationDirectory']['department_facets']);
        $this->assertSame([['slug' => '1910-05-01-morlaix']], $data['accidents']['data']);

        $this->graphQL('{ departments(region: "99") { code } }')->assertGraphQLValidationKeys(['region']);
        $this->assertNull($this->graphQL('{ department(code: "99") { code } region(code: "99") { code } }')->json('data.department'));

        $links = $this->graphQL('{ station(slug: "morlaix") { sheet { links } } }')->json('data.station.sheet.links');
        $this->assertSame(route('api.accidents.index', ['station' => 'morlaix']), $links['accidents'] ?? null);
    }
}
