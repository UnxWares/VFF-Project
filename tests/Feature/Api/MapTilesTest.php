<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Support\MapTiles;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tuiles vectorielles (revue de la PR #18, point 6) : servies jusqu'au zoom 14 seulement, comme la carte
 * du site le demande, et tracés simplifiés à la tolérance d'affichage du zoom à partir du zoom 10, comme
 * la documentation de l'API le dit (MapTiles).
 */
class MapTilesTest extends TestCase
{
    use RefreshDatabase;

    /** Point de départ du tracé témoin, près d'Altkirch (Haut-Rhin). */
    private const LAT = 47.6241;

    private const LNG = 7.2203;

    protected function setUp(): void
    {
        parent::setUp();
        $old = Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);
        $today = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $line = Line::factory()->create(['slug' => 'rfn-134000', 'code' => '134000', 'name' => 'Ligne d\'Altkirch à Ferrette',
            'is_lgv' => false, 'network' => 'principal']);

        // Même tronçon aux deux ères : en zigzag de 20 m crête à crête en 1930 (30 m en Web Mercator),
        // droit en 2030. Simplifié à la tolérance du zoom 10 (38 m), le zigzag devient la droite ; au
        // zoom 14 (0,6 m), il reste.
        $mPerDegLng = 111_320 * cos(deg2rad(self::LAT));
        $zigzag = [];
        for ($i = 0; $i <= 200; $i++) {
            $zigzag[] = [self::LNG + $i * 5 / $mPerDegLng, self::LAT + ($i % 2 === 0 ? 10 : -10) / 111_320];
        }
        $straight = [[self::LNG, self::LAT + 10 / 111_320], [self::LNG + 1000 / $mPerDegLng, self::LAT + 10 / 111_320]];
        foreach ([[$old, $zigzag], [$today, $straight]] as [$era, $lonLat]) {
            LineSegment::factory()->create([
                'line_id' => $line->id, 'era_id' => $era->id, 'source' => 'osm', 'status' => 'in_service',
                'traffic_type' => 'mixed', 'is_heritage' => false, 'gauge_mm' => 1435, 'electrified' => false,
                'voltage_v' => null, 'max_speed_kmh' => 100, 'pk_start_m' => 0, 'pk_end_m' => 1000, 'length_meters' => 1000,
                'geom' => LineString::make(array_map(fn (array $c): Point => Point::makeGeodetic($c[1], $c[0]), $lonLat)),
            ]);
        }
    }

    /** @return array{0: int, 1: int} colonne et rangée XYZ de la tuile du zoom `$z` qui contient le tracé témoin */
    private static function tileOf(int $z): array
    {
        $n = 2 ** $z;
        $lat = deg2rad(self::LAT);

        return [(int) floor((self::LNG + 180) / 360 * $n), (int) floor((1 - log(tan($lat) + 1 / cos($lat)) / M_PI) / 2 * $n)];
    }

    private function tile(int $year, int $z): string
    {
        [$x, $y] = self::tileOf($z);

        return (string) $this->get("/api/v1/eras/{$year}/tiles/{$z}/{$x}/{$y}.mvt")->assertOk()->getContent();
    }

    public function test_track_geometry_is_simplified_to_the_display_tolerance_of_the_zoom(): void
    {
        // Zoom 10 : le zigzag simplifié pèse comme le tracé droit, à quelques octets de propriétés près
        // (sans simplification, la grille de la tuile, 9,6 m, garde le zigzag : environ 400 octets de plus).
        $this->assertNotSame('', $this->tile(1930, 10));
        $this->assertLessThan(16, abs(strlen($this->tile(1930, 10)) - strlen($this->tile(2030, 10))));

        // Zoom 14 : la tolérance (une unité de la grille, 0,6 m) garde le zigzag.
        $this->assertGreaterThan(strlen($this->tile(2030, 14)) + 200, strlen($this->tile(1930, 14)));
    }

    /** @return array<string, array{0: string}> */
    public static function tileRoutes(): array
    {
        return [
            'ère' => ['/api/v1/eras/1930/tiles/%d/%d/%d.mvt'],
            'comparaison' => ['/api/v1/eras/1930/compare/2030/tiles/%d/%d/%d.mvt'],
            'superposition' => ['/api/v1/eras/stacked/tiles/%d/%d/%d.mvt'],
            'signalisation' => ['/api/v1/eras/2030/signalling/tiles/%d/%d/%d.mvt'],
            'signalisation passée' => ['/api/v1/eras/1930/signalling/tiles/%d/%d/%d.mvt'],
            'voies vertes' => ['/api/v1/greenways/tiles/%d/%d/%d.mvt'],
            'passages à niveau' => ['/api/v1/level-crossings/tiles/%d/%d/%d.mvt'],
        ];
    }

    #[DataProvider('tileRoutes')]
    public function test_tiles_stop_at_the_zoom_of_the_site_map(string $route): void
    {
        [$x, $y] = self::tileOf(14);
        $this->get(sprintf($route, 14, $x, $y))->assertOk()->assertHeader('Content-Type', 'application/x-protobuf');
        // Au-delà, MapLibre agrandit la tuile 14 : le serveur n'en produit pas de plus fine.
        $this->get(sprintf($route, 15, $x * 2, $y * 2))->assertNotFound();
        $this->get(sprintf($route, 22, $x * 256, $y * 256))->assertNotFound();
    }

    public function test_the_tolerance_is_half_a_pixel_then_one_grid_unit(): void
    {
        // Sous le zoom 10, aucune simplification : le rendu de la France entière ne change pas.
        $this->assertNull(MapTiles::tolerance(6));
        $this->assertNull(MapTiles::tolerance(9));
        $this->assertSame('ST_Transform(ls.geom, 3857)', MapTiles::displaySql('ls.geom', 6));
        $this->assertEqualsWithDelta(38.2, MapTiles::tolerance(10), 0.1);
        $this->assertEqualsWithDelta(4.78, MapTiles::tolerance(13), 0.01);
        $this->assertEqualsWithDelta(0.597, MapTiles::tolerance(14), 0.001);
        $this->assertTrue(MapTiles::onGrid(14, 16383, 16383));
        $this->assertFalse(MapTiles::onGrid(15, 0, 0));
        $this->assertFalse(MapTiles::onGrid(8, 256, 0));
    }
}
