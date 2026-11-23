<?php

namespace Tests\Feature\Materials;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\LineSegment;
use App\Models\Material;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carte des lignes d'une série : tracé allégé par ligne, confiance du lien, source et périodes, servie par l'API
 * (droits des tracés respectés) et par la route de la page (affichage de la carte du site).
 */
class MaterialLinesMapTest extends TestCase
{
    use RefreshDatabase;

    private const REVISION = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lun%C3%A9ville&oldid=239088070';

    protected function setUp(): void
    {
        parent::setUp();
        $material = Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'in_service_from' => 1971]);
        $era = Era::factory()->create(['year' => 1990]);
        $this->link($material, $era, 'rfn-67000', 'Ligne de Lunéville', 'osm', 'high', true);
        $this->link($material, $era, 'rfn-1000', 'Ligne ouverte et fermée', 'archeoferroviaire', 'medium', false);
        $this->link($material, $era, 'rfn-2000', 'Ligne sans tracé libre', 'archeoferroviaire', 'medium', false, onlyWithheld: true);
    }

    private function link(Material $material, Era $era, string $slug, string $name, string $source, string $confidence, bool $dated, bool $onlyWithheld = false): void
    {
        $line = Line::factory()->create(['slug' => $slug, 'name' => $name]);
        $geom = fn (float $d) => LineString::make([Point::makeGeodetic(48.80 + $d, 2.10), Point::makeGeodetic(48.70 + $d, 2.00)]);
        if (! $onlyWithheld) {
            LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $era->id, 'source' => 'osm', 'geom' => $geom(0)]);
        }
        if ($slug !== 'rfn-67000') {
            LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $era->id, 'source' => $source, 'geom' => $geom(0.5)]);
        }
        $link = LineMaterial::create([
            'line_id' => $line->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => $confidence, 'source_url' => self::REVISION,
            'evidence' => [['kind' => 'wikipedia_line_article', 'article' => $name, 'quote' => 'Des BB 15000 assurent les trains.']],
        ]);
        if ($dated) {
            LineMaterialPeriod::create([
                'line_material_id' => $link->id, 'start_date' => '1982-08-20', 'start_precision' => 'day', 'end_date' => '1986-05-30', 'end_precision' => 'day',
                'confidence' => 'high', 'origin' => 'line_article', 'source' => 'wikipedia', 'source_url' => self::REVISION, 'fingerprint' => sha1($slug),
            ]);
        }
    }

    /**
     * Lignes de la réponse, indexées par slug.
     *
     * @return array<string, array<string, mixed>>
     */
    private function linesOf(string $url): array
    {
        $out = [];
        foreach ((array) $this->getJson($url)->assertOk()->json('lines') as $line) {
            $out[(string) $line['slug']] = (array) $line;
        }

        return $out;
    }

    public function test_the_api_serves_one_light_geometry_per_line_with_confidence_source_and_periods(): void
    {
        $this->getJson('/api/v1/materials/bb-15000/map')->assertOk()
            ->assertHeader('Cache-Control', 'max-age=600, public')
            ->assertJsonPath('material.slug', 'bb-15000')
            ->assertJsonCount(3, 'lines');
        $lines = $this->linesOf('/api/v1/materials/bb-15000/map');

        $lunéville = $lines['rfn-67000'];
        $this->assertSame('high', $lunéville['confidence']);
        $this->assertSame(self::REVISION, $lunéville['source_url']);
        $this->assertSame('MultiLineString', $lunéville['geometry']['type']);
        $this->assertFalse($lunéville['geometry_withheld']);
        $this->assertSame([[
            'start_date' => '1982-08-20', 'start_precision' => 'day', 'end_date' => '1986-05-30', 'end_precision' => 'day', 'source_url' => self::REVISION,
        ]], $lunéville['periods']);
        $this->assertSame('Des BB 15000 assurent les trains.', $lunéville['quote']);
    }

    public function test_the_api_withholds_geometry_whose_licence_does_not_allow_redistribution(): void
    {
        $lines = $this->linesOf('/api/v1/materials/bb-15000/map');

        // Une partie du tracé est sous licence non ouverte : le reste est servi, le manque est signalé.
        $this->assertTrue($lines['rfn-1000']['geometry_withheld']);
        $this->assertNotNull($lines['rfn-1000']['geometry']);
        // Tout le tracé est retenu : pas de géométrie du tout.
        $this->assertTrue($lines['rfn-2000']['geometry_withheld']);
        $this->assertNull($lines['rfn-2000']['geometry']);
        $this->assertSame([], $lines['rfn-2000']['periods'], 'une ligne attestée sans date n\'a aucune période');
    }

    public function test_the_page_route_draws_the_whole_map_and_both_routes_know_unknown_series(): void
    {
        $lines = $this->linesOf('/materials/bb-15000/map.json');
        $this->assertNotNull($lines['rfn-2000']['geometry']);

        $this->getJson('/api/v1/materials/inconnu/map')->assertNotFound();
        $this->getJson('/materials/inconnu/map.json')->assertNotFound();
    }

    public function test_the_fiche_gives_the_page_the_map_address(): void
    {
        $this->get('/materials/bb-15000')->assertInertia(fn ($page) => $page->where('map_url', '/materials/bb-15000/map.json'));
    }
}
