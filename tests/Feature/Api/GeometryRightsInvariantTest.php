<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Invariant des droits des tracés : aucun accès de l'API (REST, GraphQL, serveur MCP, export GTFS)
 * ne sort la géométrie d'un tronçon dont la source n'autorise pas la redistribution.
 *
 * Mini-réseau, aux ères 1930 et 2030 :
 *
 *   A ── osm ── B ── archeoferroviaire ── C ── source non déclarée ── E
 *
 * Chaque tronçon a un sommet intermédiaire unique. Ceux des deux tronçons retenus ne doivent
 * apparaître dans aucune réponse ; celui du tronçon OpenStreetMap doit sortir des exports (le test
 * n'est pas vide de sens). Les points d'accès REST sont pris dans la table de routage : un nouveau
 * point d'accès est contrôlé sans modifier ce test. Seules les tuiles vectorielles (affichage de la
 * carte, documentées comme telles) et l'itinéraire de la carte du site (`/maps/route`) dessinent tous
 * les tracés.
 */
class GeometryRightsInvariantTest extends TestCase
{
    use RefreshDatabase;

    /** Sommet du tronçon OpenStreetMap (licence ouverte) : il doit sortir des exports. */
    private const OPEN_VERTEX = [2.0531, 48.0071];

    /** Sommets des tronçons retenus (archeoferroviaire, puis source absente du registre). */
    private const WITHHELD_VERTICES = [[2.1537, 48.0123], [2.2591, 48.0177]];

    private Line $line;

    private Line $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $eras = [
            Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']),
            Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']),
        ];
        $this->line = Line::factory()->create(['slug' => 'rfn-998000', 'code' => '998000', 'name' => 'Ligne de A à C',
            'is_lgv' => false, 'network' => 'principal', 'type' => 'passenger']);
        $this->branch = Line::factory()->create(['slug' => 'rfn-997000', 'code' => '997000', 'name' => 'Ligne de C à E',
            'is_lgv' => false, 'network' => 'principal', 'type' => 'passenger']);

        foreach ($eras as $era) {
            $this->segment($this->line, $era, 'osm', [[2.00, 48.0], self::OPEN_VERTEX, [2.10, 48.0]], 0);
            $this->segment($this->line, $era, 'archeoferroviaire', [[2.10, 48.0], self::WITHHELD_VERTICES[0], [2.20, 48.0]], 10_000);
            $this->segment($this->branch, $era, 'source-inconnue', [[2.20, 48.0], self::WITHHELD_VERTICES[1], [2.30, 48.0]], 0);
        }
        $stations = [];
        foreach (['a' => 2.00, 'b' => 2.10, 'c' => 2.20, 'e' => 2.30] as $slug => $lng) {
            $stations[$slug] = Station::factory()->create(['slug' => $slug, 'name' => 'Gare '.strtoupper($slug),
                'city' => 'Ville '.strtoupper($slug), 'geom' => Point::makeGeodetic(48.0, $lng), 'status' => 'active']);
            foreach ($eras as $era) {
                DB::table('station_eras')->insert(['station_id' => $stations[$slug]->id, 'era_id' => $era->id,
                    'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        // Arrêts des lignes (trajets du GTFS).
        foreach ($eras as $era) {
            foreach ([[$this->line, ['a', 'b', 'c']], [$this->branch, ['c', 'e']]] as [$line, $slugs]) {
                foreach ($slugs as $i => $slug) {
                    DB::table('line_stops')->insert(['line_id' => $line->id, 'era_id' => $era->id,
                        'station_id' => $stations[$slug]->id, 'position' => $i, 'pk_m' => $i * 7_500,
                        'effective_status' => 'active', 'is_terminal' => $i === 0 || $i === count($slugs) - 1,
                        'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
        $this->artisan('vff:build-network-graph')->assertSuccessful();
    }

    /** @param  list<array{0: float, 1: float}>  $lonLat */
    private function segment(Line $line, Era $era, string $source, array $lonLat, int $pkStart): void
    {
        LineSegment::factory()->create([
            'line_id' => $line->id, 'era_id' => $era->id, 'source' => $source,
            'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false, 'gauge_mm' => 1435,
            'pk_start_m' => $pkStart, 'pk_end_m' => $pkStart + 7_500, 'length_meters' => 7_500,
            'geom' => LineString::make(array_map(fn (array $c): Point => Point::makeGeodetic($c[1], $c[0]), $lonLat)),
        ]);
    }

    /**
     * Le sommet apparaît-il dans le texte (JSON, GeoJSON, GPX, KML, CSV), aux décimales près ?
     *
     * @param  array{0: float, 1: float}  $vertex
     */
    private function contains(string $body, array $vertex): bool
    {
        return str_contains($body, (string) $vertex[0]) && str_contains($body, (string) $vertex[1]);
    }

    private function assertNoWithheldGeometry(string $body, string $where): void
    {
        foreach (self::WITHHELD_VERTICES as $vertex) {
            $this->assertFalse($this->contains($body, $vertex), "{$where} : géométrie d'une source non redistribuable");
        }
    }

    /** @param  TestResponse<Response>  $response */
    private function body(TestResponse $response): string
    {
        $base = $response->baseResponse;
        if ($base instanceof StreamedResponse) {
            return $response->streamedContent();
        }
        if ($base instanceof BinaryFileResponse) {
            $path = $base->getFile()->getPathname();
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                return (string) file_get_contents($path);
            }
            $text = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $text .= $zip->getNameIndex($i)."\n".$zip->getFromIndex($i)."\n";
            }
            $zip->close();

            return $text;
        }

        return (string) $response->getContent();
    }

    /** Points d'accès GET de l'API REST, paramètres de chemin remplis ; tuiles vectorielles exclues (affichage). */
    private function restUrls(): array
    {
        $values = ['year' => '1930', 'other' => '2030', 'line' => $this->line->slug, 'station' => 'b', 'provider' => 'archeoferroviaire',
            'event' => '1', 'media' => '1', 'material' => 'inconnu', 'accident' => 'inconnu'];
        $query = '?geometry=true&era=1930&compare=2030&from=a&to=e&lat=48.0&lng=2.15&q=Ligne';
        $urls = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var RouteDefinition $route */
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/') || ! in_array('GET', $route->methods(), true) || str_ends_with($uri, '.mvt')) {
                continue;
            }
            $missing = array_diff($route->parameterNames(), array_keys($values));
            $this->assertSame([], $missing, "{$uri} : paramètre de chemin à ajouter au test");
            $path = preg_replace_callback('/\{(\w+)\??\}/', fn (array $m): string => $values[$m[1]], $uri);
            $urls[] = '/'.$path.$query;
            if (str_contains($uri, '{line}')) {
                $urls[] = '/'.str_replace($this->line->slug, $this->branch->slug, $path).$query;
            }
            if (str_contains($uri, '{year}')) {
                $urls[] = '/'.str_replace('1930', '2030', $path).$query;
            }
        }

        return $urls;
    }

    public function test_no_rest_endpoint_returns_a_withheld_geometry(): void
    {
        $urls = $this->restUrls();
        $this->assertGreaterThan(40, count($urls), 'tous les points d\'accès de l\'API sont parcourus');

        $open = 0;
        foreach ($urls as $url) {
            $body = $this->body($this->get($url, ['Accept' => 'application/json']));
            $this->assertNoWithheldGeometry($body, $url);
            $open += (int) $this->contains($body, self::OPEN_VERTEX);
        }
        // Le tracé OpenStreetMap sort bien : GeoJSON de l'ère, de la ligne, tronçons, GPX, KML, fiche, itinéraire.
        $this->assertGreaterThanOrEqual(8, $open);
    }

    public function test_each_geometry_export_says_what_it_withholds(): void
    {
        $era = json_decode($this->body($this->get('/api/v1/eras/1930/lines.geojson')), true);
        $this->assertCount(3, $era['features'], 'les tronçons retenus restent, avec leurs propriétés');
        $withheld = array_values(array_filter($era['features'], fn (array $f): bool => $f['properties']['geometry_withheld']));
        $this->assertCount(2, $withheld);
        $this->assertNull($withheld[0]['geometry']);
        $this->assertEqualsCanonicalizing(['licence_not_open', 'unknown_source'], array_column(array_column($withheld, 'properties'), 'geometry_withheld_reason'));
        $this->assertEqualsCanonicalizing(['licence_not_open', 'unknown_source'], array_column($era['withheld'], 'reason'));
        $this->assertSame(15_000, array_sum(array_column($era['withheld'], 'length_m')));

        $portions = $this->getJson("/api/v1/lines/{$this->line->slug}?geometry=true")->assertOk()->json('data.portions');
        $this->assertSame([false, true], array_column($portions, 'geometry_withheld'));
        $this->assertSame([null, 'licence_not_open'], array_column($portions, 'geometry_withheld_reason'));

        $route = $this->getJson('/api/v1/route?from=a&to=e&era=1930')->assertOk();
        $route->assertJsonPath('geometry_withheld', true);
        $this->assertGreaterThan(14_000, $route->json('geometry_withheld_m'));
        $this->assertSame(4, count($route->json('stations')), 'les gares restent toutes données');

        $gtfs = $this->body($this->get('/api/v1/eras/1930/gtfs.zip'));
        $this->assertStringNotContainsString("shape-{$this->line->id}", $gtfs, 'pas de forme amputée pour la ligne A-C');
        $this->assertStringNotContainsString("shape-{$this->branch->id}", $gtfs);
    }

    public function test_the_site_map_route_and_the_switch_still_draw_every_route(): void
    {
        // Carte du site : affichage, comme les tuiles vectorielles.
        $display = $this->getJson('/maps/route?from=a&to=e&era=1930')->assertOk();
        $display->assertJsonPath('geometry_withheld', false);
        $this->assertTrue($this->contains((string) $display->getContent(), self::WITHHELD_VERTICES[0]));
        $compare = (string) $this->getJson('/maps/route/compare?from=a&to=e&era=1930&compare=2030')->assertOk()->getContent();
        $this->assertTrue($this->contains($compare, self::WITHHELD_VERTICES[1]));

        // Retour arrière par la seule valeur de configuration : tout sort de nouveau.
        config(['api.withhold_unlicensed_geometry' => false]);
        $era = $this->body($this->get('/api/v1/eras/1930/lines.geojson'));
        $this->assertTrue($this->contains($era, self::WITHHELD_VERTICES[0]));
        $this->assertTrue($this->contains($era, self::WITHHELD_VERTICES[1]));
        $this->assertTrue($this->contains((string) $this->get("/api/v1/lines/{$this->line->slug}/gpx?era=1930")->getContent(), self::WITHHELD_VERTICES[0]));
        $this->getJson('/api/v1/route?from=a&to=e&era=1930')->assertJsonPath('geometry_withheld', false);
        $this->assertStringContainsString("shape-{$this->line->id}", $this->body($this->get('/api/v1/eras/1930/gtfs.zip')));
    }

    public function test_no_graphql_field_returns_a_withheld_geometry(): void
    {
        $query = <<<'GQL'
            query {
                a: line(slug: "rfn-998000") {
                    sheet(geometry: true) { portions }
                    segments { source geojson }
                }
                b: line(slug: "rfn-997000") {
                    sheet(geometry: true) { portions }
                    segments(era: 1930) { source geojson }
                }
                route(from: "a", to: "e", era: 1930) { distance_m stations { slug lat lng } }
                routeComparison(from: "a", to: "e", era: 1930, compare: 2030) { earlier { route { distance_m } } }
            }
            GQL;
        $response = $this->postJson('/graphql', ['query' => $query])->assertOk();
        $this->assertNull($response->json('errors'), (string) json_encode($response->json('errors')));

        $body = (string) $response->getContent();
        $this->assertNoWithheldGeometry($body, 'GraphQL');
        $this->assertTrue($this->contains($body, self::OPEN_VERTEX), 'le tronçon OpenStreetMap sort');
        $given = [];
        foreach ((array) $response->json('data.a.segments') as $segment) {
            $given[$segment['source']] = $segment['geojson'] !== null;
        }
        $this->assertSame(['osm' => true, 'archeoferroviaire' => false], $given);
    }

    public function test_no_mcp_tool_returns_a_withheld_geometry(): void
    {
        $tools = $this->mcp('tools/list')->json('result.tools');
        $this->assertGreaterThan(15, count($tools));

        $values = ['line' => $this->line->slug, 'slug' => $this->line->slug, 'station' => 'b', 'from' => 'a', 'to' => 'e',
            'year' => 1930, 'era' => 1930, 'compare_year' => 2030, 'other_year' => 2030, 'from_year' => 1930, 'to_year' => 2030,
            'query' => 'Ligne', 'q' => 'Ligne', 'lat' => 48.0, 'lng' => 2.15, 'lang' => 'fr', 'provider' => 'archeoferroviaire'];
        foreach ($tools as $tool) {
            $properties = array_keys($tool['inputSchema']['properties'] ?? []);
            $arguments = array_intersect_key($values, array_flip($properties));
            $body = (string) $this->mcp('tools/call', ['name' => $tool['name'], 'arguments' => (object) $arguments])->getContent();
            $this->assertNoWithheldGeometry($body, 'MCP '.$tool['name']);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    private function mcp(string $method, array $params = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params])->assertOk();
    }
}
