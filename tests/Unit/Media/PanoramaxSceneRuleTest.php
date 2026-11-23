<?php

namespace Tests\Unit\Media;

use App\Services\Media\Panoramax\PanoramaxSceneBuilder;
use App\Services\Media\Panoramax\PanoramaxSelection;
use App\Services\Media\Panoramax\StationScene;
use PHPUnit\Framework\TestCase;

/**
 * Règle des abords du tri Panoramax : la photo doit voir le bâtiment ou un quai de la gare, dans le cœur de son
 * champ, à une distance plausible, sans autre bâtiment devant.
 */
class PanoramaxSceneRuleTest extends TestCase
{
    private const LAT = 48.0;

    private const LNG = 2.0;

    /**
     * Carré de `$size` mètres centré en (`$x`, `$y`) mètres.
     *
     * @return list<array{0: float, 1: float}>
     */
    private static function square(float $x, float $y, float $size): array
    {
        $h = $size / 2;

        return [[$x - $h, $y - $h], [$x + $h, $y - $h], [$x + $h, $y + $h], [$x - $h, $y + $h]];
    }

    /**
     * Photo à (`$east`, `$north`) mètres de la gare.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function photo(string $id, float $east, float $north, ?float $azimuth, int $fov = 80, array $extra = []): array
    {
        return [
            'id' => $id,
            'collection' => 'seq-'.$id,
            'geometry' => ['type' => 'Point', 'coordinates' => [
                self::LNG + rad2deg($east / (6371008.8 * cos(deg2rad(self::LAT)))),
                self::LAT + rad2deg($north / 6371008.8),
            ]],
            'links' => [['rel' => 'via', 'instance_name' => 'ign']],
            'providers' => [['name' => 'contributeur', 'roles' => ['producer']]],
            'assets' => ['thumb' => ['href' => "https://panoramax.ign.fr/api/pictures/{$id}/thumb.jpg"]],
            'properties' => array_filter([
                'license' => 'etalab-2.0', 'datetime' => '2025-05-01T10:00:00+00:00', 'view:azimuth' => $azimuth,
                'pers:interior_orientation' => ['field_of_view' => $fov],
                ...$extra,
            ], fn ($v) => $v !== null),
        ];
    }

    /** Gare : bâtiment voyageurs de 20 m centré sur le point de la gare ; maison de 10 m à 30 m au sud. */
    private static function scene(): StationScene
    {
        return new StationScene(self::LAT, self::LNG,
            targets: [self::square(0, 0, 20)],
            obstacles: [self::square(0, -30, 10)],
            platforms: [[[-60.0, 20.0], [60.0, 20.0]]],
        );
    }

    public function test_photo_facing_the_station_building_is_kept(): void
    {
        [$reason, $view] = PanoramaxSelection::assess(self::photo('face', -40, 0, 90), self::LAT, self::LNG, self::scene());

        $this->assertNull($reason);
        $this->assertNotNull($view);
        $this->assertGreaterThan(20, $view['target_deg']);
        $this->assertEqualsWithDelta(30.0, (float) $view['nearest_m'], 0.5);
    }

    public function test_photo_turned_away_or_with_the_building_at_the_edge_is_rejected(): void
    {
        $this->assertSame('not_in_view', PanoramaxSelection::rejectReason(self::photo('dos', -40, 0, 270), self::LAT, self::LNG, self::scene()));
        // Le bâtiment est à 45° du cap : au bord d'un champ de 80°, hors de son cœur (32° de part et d'autre).
        $this->assertSame('not_in_view', PanoramaxSelection::rejectReason(self::photo('bord', -40, 0, 135), self::LAT, self::LNG, self::scene()));
    }

    public function test_building_hidden_behind_another_house_is_rejected(): void
    {
        // 55 m au sud, regard au nord : la maison (30 m au sud) cache le bâtiment voyageurs.
        $this->assertSame('hidden', PanoramaxSelection::rejectReason(self::photo('cache', 0, -55, 0), self::LAT, self::LNG, self::scene()));
    }

    public function test_distance_bounds(): void
    {
        // Trop près : la photo est collée au mur (moins de 6 m).
        $this->assertSame('not_in_view', PanoramaxSelection::rejectReason(self::photo('mur', -13, 0, 90, 60), self::LAT, self::LNG, self::scene()));
        // Hors du rayon de recherche.
        $this->assertSame('too_far', PanoramaxSelection::rejectReason(self::photo('loin', -120, 0, 90), self::LAT, self::LNG, self::scene()));
    }

    public function test_platform_alone_is_not_enough(): void
    {
        $halt = new StationScene(self::LAT, self::LNG, platforms: [[[-60.0, 0.0], [60.0, 0.0]]]);
        $this->assertSame('no_station_building', PanoramaxSelection::rejectReason(self::photo('quai', 0, -15, 0), self::LAT, self::LNG, $halt));

        // Le quai vu en plus du bâtiment compte dans la note.
        [, $view] = PanoramaxSelection::assess(self::photo('quai-et-bv', -40, 15, 90), self::LAT, self::LNG, self::scene());
        $this->assertNotNull($view);
        $this->assertGreaterThan(0, $view['platform_deg']);
    }

    public function test_station_without_building_or_platform_gets_nothing(): void
    {
        $this->assertSame('no_station_building', PanoramaxSelection::rejectReason(self::photo('vide', -40, 0, 90), self::LAT, self::LNG, new StationScene(self::LAT, self::LNG)));
    }

    public function test_missing_azimuth_and_tilted_pictures_are_rejected(): void
    {
        $this->assertSame('no_azimuth', PanoramaxSelection::rejectReason(self::photo('sans-cap', -40, 0, null), self::LAT, self::LNG, self::scene()));
        $this->assertSame('tilted', PanoramaxSelection::rejectReason(self::photo('penchee', -40, 0, 90, extra: ['pers:roll' => 25]), self::LAT, self::LNG, self::scene()));
    }

    public function test_panorama_needs_a_precise_position(): void
    {
        $vehicle = self::photo('vehicule', -40, 0, 90, 360, ['quality:horizontal_accuracy' => 2.0]);
        $handheld = self::photo('main', -40, 0, 90, 360, ['quality:horizontal_accuracy' => 4.0]);
        $unknown = self::photo('inconnue', -40, 0, 90, 360);

        $this->assertNull(PanoramaxSelection::rejectReason($vehicle, self::LAT, self::LNG, self::scene()));
        $this->assertSame('pano_unsure_heading', PanoramaxSelection::rejectReason($handheld, self::LAT, self::LNG, self::scene()));
        $this->assertSame('pano_unsure_heading', PanoramaxSelection::rejectReason($unknown, self::LAT, self::LNG, self::scene()));
        // Photo plate : la précision ne compte pas.
        $this->assertNull(PanoramaxSelection::rejectReason(self::photo('plate', -40, 0, 90, 80, ['quality:horizontal_accuracy' => 4.0]), self::LAT, self::LNG, self::scene()));
    }

    public function test_bdtopo_building_is_the_osm_target_only_when_their_centres_match(): void
    {
        $this->assertTrue(PanoramaxSceneBuilder::sameBuilding(self::square(0, 0, 20), self::square(1, 1, 18)));
        // Immeuble mitoyen : il touche la gare mais reste un obstacle.
        $this->assertFalse(PanoramaxSceneBuilder::sameBuilding(self::square(0, 0, 20), self::square(20, 0, 20)));
    }

    public function test_panorama_uses_the_front_of_the_picture(): void
    {
        $this->assertSame(50.0, PanoramaxSelection::halfFov(self::photo('p', 0, 0, 0, 360)));
        $this->assertSame(32.0, PanoramaxSelection::halfFov(self::photo('f', 0, 0, 0, 80)));
        $this->assertSame(28.0, PanoramaxSelection::halfFov(self::photo('x', 0, 0, 0, 0)));
    }

    public function test_pick_ranks_the_widest_view_first_and_keeps_the_view(): void
    {
        $rejected = [];
        $picked = PanoramaxSelection::pick([
            self::photo('large', -25, 0, 90),
            self::photo('etroite', -50, 0, 90),
            self::photo('dos', -40, 0, 270),
        ], self::LAT, self::LNG, $rejected, self::scene());

        $this->assertSame(['large', 'etroite'], array_column($picked, 'id'));
        $this->assertSame(['not_in_view' => 1], $rejected);
        $this->assertArrayHasKey('vff:view', $picked[0]);
    }

    public function test_geometry_helpers(): void
    {
        $this->assertEqualsWithDelta(10.0, (float) StationScene::raySegment(0, 0, 0, 1, -5, 10, 5, 10), 1e-9);
        $this->assertNull(StationScene::raySegment(0, 0, 0, -1, -5, 10, 5, 10));
        $this->assertTrue(StationScene::contains(self::square(0, 0, 10), 1, 1));
        $this->assertFalse(StationScene::contains(self::square(0, 0, 10), 6, 1));
        $this->assertEqualsWithDelta(100.0, StationScene::area(self::square(3, 3, 10)), 1e-9);
        $this->assertTrue(PanoramaxSceneBuilder::touches(self::square(0, 0, 10), self::square(8, 0, 10)));
        $this->assertFalse(PanoramaxSceneBuilder::touches(self::square(0, 0, 10), self::square(20, 0, 10)));
        $ring = PanoramaxSceneBuilder::outerRings(['type' => 'MultiPolygon', 'coordinates' => [[[[0, 0], [1, 0], [1, 1], [0, 0]]]]]);
        $this->assertCount(3, $ring[0]);
        $scene = new StationScene(self::LAT, self::LNG);
        [$x, $y] = $scene->project(self::LAT + rad2deg(100 / 6371008.8), self::LNG);
        $this->assertEqualsWithDelta(0.0, $x, 1e-6);
        $this->assertEqualsWithDelta(100.0, $y, 1e-6);
    }
}
