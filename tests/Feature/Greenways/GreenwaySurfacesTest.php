<?php

namespace Tests\Feature\Greenways;

use App\Models\Era;
use App\Models\Line;
use App\Services\Greenways\GreenwayTiles;
use App\Services\Greenways\LineGreenways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Voies vertes d'une ligne : section de la fiche (propriété `greenways`, absente sans rattachement),
 * API REST (`/lines/{line}/greenways`, tuiles), GraphQL (`Line.greenways`), groupement par voie verte
 * nommée, provenance OpenStreetMap sous ODbL.
 */
class GreenwaySurfacesTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Line $line;

    private Line $bare;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-436000', 'code' => '436000', 'name' => 'Ligne de La Chapelle-Anthenaise à Flers']);
        $this->bare = Line::factory()->create(['slug' => 'rfn-1000', 'code' => '001000', 'name' => 'Ligne sans voie verte']);

        // Itinéraire OSM nommé (deux ways, deux noms de way différents), un way isolé daté, un way sans nom.
        $this->greenway(11, 'Voie Verte de Mayenne', 'Voie verte de Mayenne à Javron', 253186, 'path', 11200, 11206, 100, null);
        $this->greenway(12, 'Chemin de halage', 'Voie verte de Mayenne à Javron', 253186, 'path', 3961, 3962, 100, null);
        $this->greenway(13, 'Voie verte Flers-Domfront', null, null, 'cycleway', 4000, 4500, 89, 2006);
        $this->greenway(14, null, null, null, 'footway', 400, 700, 57, null);
        $this->greenway(15, null, null, null, 'cycleway', 300, 300, 100, null);
    }

    private function greenway(int $way, ?string $name, ?string $relationName, ?int $relation, string $highway, int $shared, int $length, int $percent, ?int $year, ?Line $line = null): void
    {
        DB::insert("INSERT INTO line_greenways (line_id, osm_way_id, osm_relation_id, name, relation_name, highway, shared_length_m, way_length_m,
                shared_percent, opened_year, source, osm_seen_at, created_at, updated_at, geom)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'osm', '2026-11-18', now(), now(),
                ST_Multi(ST_GeomFromText(?, 4326)))", [
            ($line ?? $this->line)->id, $way, $relation, $name, $relationName, $highway, $shared, $length, $percent, $year,
            sprintf('LINESTRING(%F 48.5, %F 48.5)', 1.0 + $way / 100, 1.0 + $way / 100 + 0.01),
        ]);
    }

    public function test_a_name_that_is_not_an_itinerary_never_names_a_group(): void
    {
        // Relation « Camping » (lieu), way nommé d'après une rue, way d'un type de voie : aucun ne donne son nom.
        $this->greenway(21, null, 'Camping', 900, 'cycleway', 700, 700, 100, null, $this->bare);
        $this->greenway(22, 'Rue du Denier', null, null, 'cycleway', 500, 500, 100, null, $this->bare);
        $this->greenway(23, 'Voie Verte', 'Le Lac', 901, 'path', 400, 400, 100, null, $this->bare);
        // Relation « Camping » mais way au nom d'itinéraire : le nom du way nomme le groupe, sans lien de relation.
        $this->greenway(24, 'Voie verte du Marais', 'Camping', 900, 'path', 300, 300, 100, null, $this->bare);

        $groups = app(LineGreenways::class)->groups($this->bare);

        $this->assertSame([null, 'Voie verte du Marais'], array_column($groups, 'name'));
        $this->assertSame([21, 22, 23], array_column($groups[0]['ways'], 'osm_way_id'));
        $this->assertSame('https://www.openstreetmap.org/way/21', $groups[0]['osm_url']);
        $this->assertNull($groups[0]['osm_relation_id']);
        $this->assertSame('https://www.openstreetmap.org/way/24', $groups[1]['osm_url']);
        // Le nom OSM du way reste celui d'OpenStreetMap dans le détail.
        $this->assertSame('Rue du Denier', $groups[0]['ways'][1]['name']);
    }

    public function test_the_service_groups_ways_by_named_greenway_longest_first(): void
    {
        $groups = app(LineGreenways::class)->groups($this->line);

        $this->assertSame(['Voie verte de Mayenne à Javron', 'Voie verte Flers-Domfront', null], array_column($groups, 'name'));
        $mayenne = $groups[0];
        $this->assertSame(15161, $mayenne['shared_length_m']);
        $this->assertSame(2, $mayenne['way_count']);
        $this->assertSame(253186, $mayenne['osm_relation_id']);
        $this->assertSame('https://www.openstreetmap.org/relation/253186', $mayenne['osm_url']);
        $this->assertSame([11, 12], array_column($mayenne['ways'], 'osm_way_id'));
        // Sans itinéraire : lien vers le way qui suit le plus longtemps l'emprise.
        $this->assertSame('https://www.openstreetmap.org/way/13', $groups[1]['osm_url']);
        $this->assertSame(2006, $groups[1]['opened_year']);
        // Les ways sans nom forment un groupe, lié à son way le plus long.
        $this->assertSame(2, $groups[2]['way_count']);
        $this->assertSame(700, $groups[2]['shared_length_m']);
        $this->assertSame('https://www.openstreetmap.org/way/14', $groups[2]['osm_url']);
        $this->assertSame([], app(LineGreenways::class)->groups($this->bare));
    }

    public function test_the_line_sheet_has_the_section_only_when_a_greenway_is_linked(): void
    {
        $this->get('/lines/rfn-436000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lines/Show')
            ->where('greenways.total_length_m', 15161 + 4000 + 700)
            ->where('greenways.osm_date', '2026-11-18')
            ->where('greenways.items.0', [
                'name' => 'Voie verte de Mayenne à Javron', 'shared_length_m' => 15161, 'way_count' => 2, 'opened_year' => null,
                'osm_url' => 'https://www.openstreetmap.org/relation/253186',
            ])
            ->has('greenways.items', 3));

        $this->get('/lines/rfn-1000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('greenways.total_length_m', 0)
            ->where('greenways.osm_date', null)
            ->where('greenways.items', []));
    }

    public function test_the_rest_endpoint_gives_the_greenways_with_provenance_and_the_odbl_attribution(): void
    {
        $response = $this->getJson('/api/v1/lines/rfn-436000/greenways')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->assertJsonPath('line', ['slug' => 'rfn-436000', 'code' => '436000', 'name' => 'Ligne de La Chapelle-Anthenaise à Flers'])
            ->assertJsonPath('total_shared_length_m', 19861)
            ->assertJsonPath('osm_date', '2026-11-18')
            ->assertJsonPath('attribution', '© contributeurs OpenStreetMap, ODbL 1.0 (https://www.openstreetmap.org/copyright)')
            ->assertJsonPath('provenance.provider', 'osm')
            ->assertJsonPath('provenance.name', 'OpenStreetMap')
            ->assertJsonPath('provenance.license_status', 'verified')
            ->assertJsonCount(3, 'greenways');

        $this->assertStringContainsString('ODbL', $response->json('provenance.license'));
        $this->assertSame([
            'osm_way_id' => 11, 'osm_url' => 'https://www.openstreetmap.org/way/11', 'name' => 'Voie Verte de Mayenne', 'highway' => 'path',
            'shared_length_m' => 11200, 'way_length_m' => 11206, 'shared_percent' => 100, 'opened_year' => null,
        ], $response->json('greenways.0.ways.0'));
    }

    public function test_the_rest_endpoint_is_empty_for_a_line_without_greenway_and_404_for_an_unknown_line(): void
    {
        $this->getJson('/api/v1/lines/rfn-1000/greenways')->assertOk()
            ->assertJsonPath('greenways', [])
            ->assertJsonPath('total_shared_length_m', 0)
            ->assertJsonPath('osm_date', null)
            ->assertJsonPath('provenance.provider', 'osm');
        $this->getJson('/api/v1/lines/ligne-inconnue/greenways')->assertNotFound();
    }

    public function test_the_line_sheet_api_links_to_the_greenways(): void
    {
        $this->assertStringEndsWith('/api/v1/lines/rfn-436000/greenways', $this->getJson('/api/v1/lines/rfn-436000')->assertOk()->json('data.links.greenways'));
    }

    public function test_graphql_gives_the_same_greenways_with_provenance(): void
    {
        $this->graphQL('{ line(slug: "rfn-436000") { greenway_length_m greenways {
                name shared_length_m way_count opened_year osm_relation_id osm_url attribution provenance { provider license_status }
                ways { osm_way_id osm_url name highway way_length_m shared_length_m shared_percent opened_year }
            } } }')
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.line.greenway_length_m', 19861)
            ->assertJsonCount(3, 'data.line.greenways')
            ->assertJsonPath('data.line.greenways.0.name', 'Voie verte de Mayenne à Javron')
            ->assertJsonPath('data.line.greenways.0.osm_relation_id', 253186)
            ->assertJsonPath('data.line.greenways.0.attribution', LineGreenways::ATTRIBUTION)
            ->assertJsonPath('data.line.greenways.0.provenance', ['provider' => 'osm', 'license_status' => 'verified'])
            ->assertJsonPath('data.line.greenways.0.ways.0', [
                'osm_way_id' => '11', 'osm_url' => 'https://www.openstreetmap.org/way/11', 'name' => 'Voie Verte de Mayenne', 'highway' => 'path',
                'way_length_m' => 11206, 'shared_length_m' => 11200, 'shared_percent' => 100, 'opened_year' => null,
            ])
            ->assertJsonPath('data.line.greenways.2.name', null);

        $this->graphQL('{ line(slug: "rfn-1000") { greenway_length_m greenways { name } } }')
            ->assertJsonPath('data.line', ['greenway_length_m' => 0, 'greenways' => []]);
    }

    public function test_tiles_are_empty_below_the_minimum_zoom_and_carry_the_way_and_the_line(): void
    {
        $this->assertSame('', $this->get('/api/v1/greenways/tiles/3/4/2.mvt')->assertOk()->getContent());

        [$x, $y] = self::tileOf(48.5, 1.11, 9);
        $response = $this->get("/api/v1/greenways/tiles/9/{$x}/{$y}.mvt")->assertOk()
            ->assertHeader('Content-Type', 'application/x-protobuf')
            ->assertHeader('Access-Control-Allow-Origin', '*');
        $tile = (string) $response->getContent();
        $this->assertStringContainsString('vff-greenways', $tile);
        $this->assertStringContainsString('rfn-436000', $tile);
        $this->assertStringContainsString('Voie verte de Mayenne à Javron', $tile);
        $this->assertStringContainsString('way_id', $tile);

        $this->get('/api/v1/greenways/tiles/3/9/9.mvt')->assertNotFound();
    }

    public function test_tiles_are_cached_until_the_command_invalidates_them(): void
    {
        $tiles = app(GreenwayTiles::class);
        [$x, $y] = self::tileOf(48.5, 1.11, 9);
        $this->assertStringNotContainsString('Nouvelle voie', $tiles->tile(9, $x, $y));

        DB::table('line_greenways')->where('osm_way_id', 11)->update(['name' => 'Nouvelle voie', 'relation_name' => null]);
        $this->assertStringNotContainsString('Nouvelle voie', $tiles->tile(9, $x, $y), 'tuile en cache');

        $tiles->invalidate();
        $this->assertStringContainsString('Nouvelle voie', $tiles->tile(9, $x, $y));
    }

    /** @return array{0: int, 1: int} colonne et rangée XYZ d'un point */
    private static function tileOf(float $lat, float $lon, int $z): array
    {
        $n = 2 ** $z;
        $x = (int) floor(($lon + 180) / 360 * $n);
        $y = (int) floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n);

        return [$x, $y];
    }
}
