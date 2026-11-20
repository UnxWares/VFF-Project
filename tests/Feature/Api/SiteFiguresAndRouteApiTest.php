<?php

namespace Tests\Feature\Api;

use App\Models\LineSegment;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Ce que le site montre et que l'API donne aussi : chiffres de l'accueil (`/stats`, requête GraphQL `stats`),
 * tracé GeoJSON d'une ligne (`/lines/{line}/geojson`), point kilométrique (`Line.pk`), recherche GraphQL,
 * liens de la fiche ligne vers les autres points d'accès.
 */
class SiteFiguresAndRouteApiTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
    }

    public function test_stats_publish_the_home_page_figures_and_the_network_curve(): void
    {
        $response = $this->getJson('/api/v1/stats')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=600, public')
            ->assertJsonPath('data.lines_count', 1)
            ->assertJsonPath('data.stations_count', 2)
            ->assertJsonPath('data.materials_count', 1)
            ->assertJsonPath('data.eras_count', 2)
            ->assertJsonStructure(['data' => ['lines_count', 'stations_count', 'materials_count', 'eras_count', 'total_km',
                'profile' => [['year', 'km', 'lines', 'current']]], 'links' => ['eras', 'web']]);

        $this->assertSame([1960, 2030], $response->json('data.profile.*.year'));
        $this->assertSame([false, true], $response->json('data.profile.*.current'));
        $this->assertSame([1, 1], $response->json('data.profile.*.lines'));
        $this->assertGreaterThan(10, $response->json('data.profile.0.km'));
        $this->assertSame($response->json('data.profile.0.km'), $this->getJson('/api/v1/eras/1960')->json('data.stats.open_km'), 'même chiffre que la fiche de l\'ère');

        // La page d'accueil porte les mêmes valeurs (une seule construction).
        $home = $this->get('/')->assertOk()->viewData('page')['props'];
        $this->assertSame($response->json('data.lines_count'), $home['stats']['lines_count']);
        $this->assertSame($response->json('data.profile'), $home['profile']);
    }

    public function test_the_data_fingerprint_is_computed_once_per_call_of_the_figures(): void
    {
        $fingerprints = 0;
        DB::listen(function (QueryExecuted $query) use (&$fingerprints): void {
            $fingerprints += str_contains($query->sql, 'MAX(updated_at) FROM line_segments') ? 1 : 0;
        });

        $this->getJson('/api/v1/stats')->assertOk();
        $this->assertSame(1, $fingerprints, 'compteurs et courbe partagent la même empreinte');

        $this->graphQL('{ stats { lines_count profile { year } } }')->assertJsonMissingPath('errors');
        $this->assertSame(2, $fingerprints);
    }

    public function test_graphql_stats_match_the_rest_figures(): void
    {
        $rest = $this->getJson('/api/v1/stats')->json('data');
        $graphql = $this->graphQL('{ stats { lines_count stations_count materials_count eras_count total_km profile { year km lines current } } }')
            ->assertJsonMissingPath('errors')->json('data.stats');

        $this->assertSame($rest, $graphql);
    }

    public function test_line_geojson_is_a_feature_collection_with_provenance_and_validates_the_era(): void
    {
        // Seule une source à licence ouverte (registre des droits) livre sa géométrie.
        LineSegment::query()->update(['source' => 'osm']);
        $response = $this->getJson("/api/v1/lines/{$this->line->slug}/geojson?era=1960")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/geo+json')
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('era_year', 1960)
            ->assertJsonPath('line.slug', $this->line->slug)
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.type', 'Feature')
            ->assertJsonPath('features.0.geometry.type', 'LineString')
            ->assertJsonPath('features.0.properties.status', 'in_service')
            ->assertJsonPath('features.0.properties.voltage_v', 1500)
            ->assertJsonStructure(['features' => [['id', 'properties' => ['pk_start_m', 'length_m', 'source', 'provenance' => ['provider', 'license', 'license_status']]]]]);
        $this->assertArrayNotHasKey('geometry', $response->json('features.0.properties'));
        $this->assertArrayNotHasKey('id', $response->json('features.0.properties'));

        // Même tronçons que /segments, dans le même ordre.
        $segments = $this->getJson("/api/v1/lines/{$this->line->slug}/segments?era=1960")->json('data');
        $this->assertSame(array_column($segments, 'id'), $response->json('features.*.id'));

        $this->getJson("/api/v1/lines/{$this->line->slug}/geojson")->assertOk()->assertJsonPath('era_year', 2030);
        $this->getJson("/api/v1/lines/{$this->line->slug}/geojson?era=1935")->assertNotFound();
        $this->getJson("/api/v1/lines/{$this->line->slug}/geojson?era=vers")->assertUnprocessable()->assertJsonValidationErrors(['era']);
        $this->getJson('/api/v1/lines/ligne-inconnue/geojson')->assertNotFound();
    }

    public function test_line_sheet_links_lead_to_every_endpoint_of_the_line(): void
    {
        $links = $this->getJson("/api/v1/lines/{$this->line->slug}")->assertOk()->json('data.links');
        $slug = $this->line->slug;

        foreach (['geojson' => "/lines/{$slug}/geojson", 'profile' => "/lines/{$slug}/profile", 'level_crossings' => "/lines/{$slug}/level-crossings",
            'materials' => "/lines/{$slug}/materials", 'signalling' => "/lines/{$slug}/signalling", 'stops' => "/lines/{$slug}/stops"] as $key => $path) {
            $this->assertStringEndsWith('/api/v1'.$path, $links[$key], $key);
        }
        $this->assertStringEndsWith("/api/v1/accidents?line={$slug}", $links['accidents']);
        // Chaque lien répond (pas de 404 sur le chemin lui-même ; 404 de profil absent toléré).
        foreach (['geojson', 'level_crossings', 'materials', 'signalling', 'stops', 'accidents'] as $key) {
            $this->getJson($links[$key])->assertOk();
        }
    }

    public function test_graphql_pk_matches_the_rest_conversion_and_reports_unknown_eras(): void
    {
        $rest = $this->getJson("/api/v1/lines/{$this->line->slug}/pk?lat=48.853&lng=2.24&era=1960")->assertOk()->json();
        $graphql = $this->graphQL('query ($slug: String!) { line(slug: $slug) { pk(lat: 48.853, lng: 2.24, era: 1960) { pk_m pk_label total_m snap { lat lng distance_from_input_m } } } }', ['slug' => $this->line->slug])
            ->assertJsonMissingPath('errors')->json('data.line.pk');

        $this->assertSame($rest['pk_m'], $graphql['pk_m']);
        $this->assertSame($rest['pk_label'], $graphql['pk_label']);
        $this->assertSame($rest['total_m'], $graphql['total_m']);
        $this->assertEqualsWithDelta($rest['snap']['distance_from_input_m'], $graphql['snap']['distance_from_input_m'], 0.001);

        $this->graphQL('query ($slug: String!) { line(slug: $slug) { pk(lat: 48.8, lng: 2.2, era: 1935) { pk_m } } }', ['slug' => $this->line->slug])
            ->assertJsonPath('errors.0.message', 'Aucune ère 1935.');
        $this->graphQL('query ($slug: String!) { line(slug: $slug) { pk(lat: 148.8, lng: 2.2) { pk_m } } }', ['slug' => $this->line->slug])
            ->assertGraphQLValidationKeys(['lat']);
    }

    public function test_graphql_search_is_the_rest_search(): void
    {
        $rest = $this->getJson('/api/v1/search?q=saint-lazare')->assertOk()->json();
        $graphql = $this->graphQL('{ search(query: "saint-lazare") { lines { slug name code } stations { slug name city department_code lat lng } materials { slug name kind builder } } }')
            ->assertJsonMissingPath('errors')->json('data.search');

        $this->assertSame(array_column($rest['lines'], 'slug'), array_column($graphql['lines'], 'slug'));
        $this->assertSame(array_column($rest['stations'], 'slug'), array_column($graphql['stations'], 'slug'));
        $this->assertSame('paris-saint-lazare', $graphql['stations'][0]['slug']);

        $this->graphQL('{ search(query: "x") { lines { slug } } }')->assertJsonPath('data.search.lines', []);
        $this->graphQL('{ search(query: "paris", limit: 0) { lines { slug } } }')->assertGraphQLValidationKeys(['limit']);
    }
}
