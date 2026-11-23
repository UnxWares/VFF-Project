<?php

namespace Tests\Unit\Media;

use App\Services\Media\Panoramax\PanoramaxSelection;
use App\Services\Media\Panoramax\StationScene;
use PHPUnit\Framework\TestCase;

/**
 * Frontières exactes de la règle de scène Panoramax (inclusif ou exclusif, un seuil à la fois) et de la
 * lecture des abords par rayons (StationScene::view).
 */
class PanoramaxBoundaryTest extends TestCase
{
    private const LAT = 48.0;

    private const LNG = 2.0;

    /** @return list<array{0: float, 1: float}> */
    private static function box(float $x0, float $y0, float $x1, float $y1): array
    {
        return [[$x0, $y0], [$x1, $y0], [$x1, $y1], [$x0, $y1]];
    }

    /** @param  array<string, mixed>  $extra */
    private static function photo(float $east, float $north, ?float $azimuth, int $fov = 80, array $extra = []): array
    {
        return [
            'id' => 'p',
            'collection' => 'seq',
            'geometry' => ['type' => 'Point', 'coordinates' => [
                self::LNG + rad2deg($east / (6371008.8 * cos(deg2rad(self::LAT)))),
                self::LAT + rad2deg($north / 6371008.8),
            ]],
            'links' => [['rel' => 'via', 'instance_name' => 'ign']],
            'providers' => [['name' => 'contributeur', 'roles' => ['producer']]],
            'assets' => ['thumb' => ['href' => 'https://panoramax.ign.fr/api/pictures/p/thumb.jpg']],
            'properties' => array_filter([
                'license' => 'etalab-2.0', 'datetime' => '2025-05-01T10:00:00+00:00', 'view:azimuth' => $azimuth,
                'pers:interior_orientation' => ['field_of_view' => $fov],
                ...$extra,
            ], fn ($v) => $v !== null),
        ];
    }

    private static function bigScene(): StationScene
    {
        return new StationScene(self::LAT, self::LNG, targets: [self::box(-10, 20, 10, 40)]);
    }

    public function test_tilt_limits_are_inclusive(): void
    {
        $ok = fn (array $extra) => PanoramaxSelection::rejectReason(self::photo(0, 0, 0, 80, $extra), self::LAT, self::LNG, self::bigScene());
        $this->assertNull($ok(['pers:roll' => 15]));
        $this->assertSame('tilted', $ok(['pers:roll' => 15.5]));
        $this->assertSame('tilted', $ok(['pers:roll' => -15.5]));
        $this->assertNull($ok(['pers:roll' => -15]));
        $this->assertNull($ok(['pers:pitch' => 30]));
        $this->assertSame('tilted', $ok(['pers:pitch' => 30.5]));
        $this->assertSame('tilted', $ok(['pers:pitch' => -30.5]));
    }

    public function test_panorama_accuracy_limit_is_inclusive_and_359_is_not_a_panorama(): void
    {
        $rule = fn (int $fov, ?float $acc) => PanoramaxSelection::rejectReason(
            self::photo(0, 0, 0, $fov, $acc === null ? [] : ['quality:horizontal_accuracy' => $acc]), self::LAT, self::LNG, self::bigScene());
        $this->assertNull($rule(360, 3.0));
        $this->assertSame('pano_unsure_heading', $rule(360, 3.01));
        $this->assertSame('pano_unsure_heading', $rule(360, null));
        $this->assertSame('pano_unsure_heading', $rule(361, null));
        $this->assertNull($rule(359, null), 'à 359 degrés, la précision ne compte pas');
    }

    public function test_search_radius_without_and_with_a_scene(): void
    {
        $scene = new StationScene(self::LAT, self::LNG, targets: [self::box(-10, -10, 10, 10)]);
        // Avec les abords : 110 m. Photo au sud, tournée vers le nord (cap 0).
        $this->assertNotSame('too_far', PanoramaxSelection::rejectReason(self::photo(0, -109, 0), self::LAT, self::LNG, $scene));
        $this->assertSame('too_far', PanoramaxSelection::rejectReason(self::photo(0, -111, 0), self::LAT, self::LNG, $scene));
        // Sans les abords : 60 m et 15 degrés.
        $this->assertNull(PanoramaxSelection::rejectReason(self::photo(0, -59, 0), self::LAT, self::LNG));
        $this->assertSame('too_far', PanoramaxSelection::rejectReason(self::photo(0, -61, 0), self::LAT, self::LNG));
    }

    public function test_facing_limit_without_a_scene(): void
    {
        // Photo à 40 m à l'ouest de la gare : la gare est au cap 90.
        $this->assertNull(PanoramaxSelection::rejectReason(self::photo(-40, 0, 104.5), self::LAT, self::LNG));
        $this->assertSame('facing_away', PanoramaxSelection::rejectReason(self::photo(-40, 0, 105.5), self::LAT, self::LNG));
        $this->assertSame('facing_away', PanoramaxSelection::rejectReason(self::photo(-40, 0, 74.5), self::LAT, self::LNG));
        $this->assertNull(PanoramaxSelection::rejectReason(self::photo(-40, 0, 75.5), self::LAT, self::LNG));
    }

    public function test_angle_wraps_around_north(): void
    {
        $this->assertEqualsWithDelta(10.0, PanoramaxSelection::angle(self::photo(0, -40, 350.0), self::LAT, self::LNG), 0.01);
        $this->assertEqualsWithDelta(10.0, PanoramaxSelection::angle(self::photo(0, -40, 10.0), self::LAT, self::LNG), 0.01);
        $this->assertEqualsWithDelta(170.0, PanoramaxSelection::angle(self::photo(0, -40, 170.0), self::LAT, self::LNG), 0.01);
        $this->assertEqualsWithDelta(170.0, PanoramaxSelection::angle(self::photo(0, -40, 190.0), self::LAT, self::LNG), 0.01);
        $this->assertSame(90.0, PanoramaxSelection::angle(self::photo(0, -40, null), self::LAT, self::LNG));
    }

    public function test_half_fov_bounds(): void
    {
        $half = fn (int $fov) => PanoramaxSelection::halfFov(self::photo(0, 0, 0, $fov));
        $this->assertSame(72.0, $half(180));
        $this->assertSame(72.0, $half(200), 'plafonné à 180');
        $this->assertSame(72.0, $half(359));
        $this->assertSame(50.0, $half(360));
        $this->assertSame(50.0, $half(400));
        $this->assertSame(28.0, $half(0));
        $this->assertEqualsWithDelta(0.8, $half(2), 1e-9);
    }

    public function test_target_width_threshold_is_inclusive(): void
    {
        // Azimut 0,5 : rayons à ±0,5, ±1,5... Un bâtiment de 10,6 m à 30 m en couvre exactement 20, un de 9,6 m, 18.
        $wide = new StationScene(self::LAT, self::LNG, targets: [self::box(-5.3, 30, 5.3, 40)]);
        $narrow = new StationScene(self::LAT, self::LNG, targets: [self::box(-4.8, 30, 4.8, 40)]);
        $this->assertSame(20.0, $wide->view(0, 0, 0.5, 32, 6, 50, 40)['target_deg']);
        $this->assertSame(18.0, $narrow->view(0, 0, 0.5, 32, 6, 50, 40)['target_deg']);
        $this->assertNull(PanoramaxSelection::rejectReason(self::photo(0, 0, 0.5, 80), self::LAT, self::LNG, $wide));
        $this->assertSame('not_in_view', PanoramaxSelection::rejectReason(self::photo(0, 0, 0.5, 80), self::LAT, self::LNG, $narrow));
    }

    public function test_hidden_threshold_is_inclusive(): void
    {
        $target = self::box(-5.3, 30, 5.3, 40);
        $house20 = new StationScene(self::LAT, self::LNG, targets: [$target], obstacles: [self::box(-2.65, 15, 2.65, 20)]);
        $house18 = new StationScene(self::LAT, self::LNG, targets: [$target], obstacles: [self::box(-2.4, 15, 2.4, 20)]);
        $this->assertSame(20.0, $house20->view(0, 0, 0.5, 32, 6, 50, 40)['hidden_deg']);
        $this->assertSame('hidden', PanoramaxSelection::rejectReason(self::photo(0, 0, 0.5, 80), self::LAT, self::LNG, $house20));
        $this->assertSame('not_in_view', PanoramaxSelection::rejectReason(self::photo(0, 0, 0.5, 80), self::LAT, self::LNG, $house18));
    }

    public function test_rays_include_both_ends_of_the_field(): void
    {
        // Champ de 20 degrés : rayons de -10 à +10 compris, soit 21 sur un bâtiment très large.
        $scene = new StationScene(self::LAT, self::LNG, targets: [self::box(-100, 30, 100, 40)]);
        $this->assertSame(21.0, $scene->view(0, 0, 0, 10, 6, 50, 40)['target_deg']);
        $this->assertSame(2.0, $scene->view(0, 0, 0, 0.5, 6, 50, 40)['target_deg'], 'au moins un rayon, et le bord compris');
    }

    public function test_target_distance_window_is_inclusive(): void
    {
        $at = fn (float $y, float $min = 6, float $max = 50) => (new StationScene(self::LAT, self::LNG, targets: [self::box(-100, $y, 100, $y + 5)]))
            ->view(0, 0, 0, 0.0, $min, $max, 40);
        $this->assertGreaterThan(0, $at(6.0)['target_deg'], 'à 6 m exactement : vu');
        $this->assertSame(0.0, $at(5.9)['target_deg'], 'à moins de 6 m : trop près');
        $this->assertGreaterThan(0, $at(49.9)['target_deg']);
        $this->assertSame(0.0, $at(50.1)['target_deg'], 'au-delà de 50 m : trop loin');
        $this->assertSame(50.0, $at(50.0)['nearest_m'], 'à 50 m exactement : vu');
        $this->assertSame(6.0, $at(6.0)['nearest_m']);
        $this->assertNull($at(50.1)['nearest_m']);
    }

    public function test_building_beyond_the_distance_window_behind_a_house_is_not_hidden(): void
    {
        $scene = new StationScene(self::LAT, self::LNG, targets: [self::box(-100, 60, 100, 70)], obstacles: [self::box(-100, 20, 100, 25)]);
        $this->assertSame(0.0, $scene->view(0, 0, 0, 10, 6, 50, 40)['hidden_deg']);
    }

    public function test_hidden_width_ignores_a_building_whose_first_edge_hit_is_beyond_the_distance_window(): void
    {
        // Bâtiment en L dont la boîte commence à 10 m mais dont le premier bord touché par le rayon est à 55 m.
        $l = [[-50.0, 10.0], [-45.0, 10.0], [-45.0, 55.0], [5.0, 55.0], [5.0, 60.0], [-50.0, 60.0]];
        $scene = new StationScene(self::LAT, self::LNG, targets: [$l], obstacles: [self::box(-100, 20, 100, 25)]);
        $this->assertSame(0.0, $scene->view(0, 0, 0, 0.0, 6, 50, 40)['hidden_deg']);
        $this->assertNull($scene->view(0, 0, 0, 0.0, 6, 50, 40)['nearest_m']);
    }

    public function test_platforms_count_up_to_their_distance_and_before_any_building(): void
    {
        $platform = fn (float $y) => [[-100.0, $y], [100.0, $y]];
        $seen = new StationScene(self::LAT, self::LNG, targets: [self::box(-5, 200, 5, 210)], platforms: [$platform(40.0)]);
        $this->assertSame(1.0, $seen->view(0, 0, 0, 5, 6, 50, 40)['platform_deg'], 'à 40 m exactement : compté (le rayon droit seul)');
        $far = new StationScene(self::LAT, self::LNG, targets: [self::box(-5, 200, 5, 210)], platforms: [$platform(41.0)]);
        $this->assertSame(0.0, $far->view(0, 0, 0, 5, 6, 50, 40)['platform_deg']);
        $behind = new StationScene(self::LAT, self::LNG, targets: [self::box(-100, 20, 100, 25)], platforms: [$platform(30.0)]);
        $this->assertSame(0.0, $behind->view(0, 0, 0, 5, 6, 50, 40)['platform_deg'], 'un bâtiment devant le quai le cache');
        $same = new StationScene(self::LAT, self::LNG, targets: [self::box(-100, 30, 100, 35)], platforms: [$platform(30.0)]);
        $this->assertSame(0.0, $same->view(0, 0, 0, 5, 6, 50, 40)['platform_deg'], 'bâtiment et quai à la même distance : le bâtiment gagne');
    }

    public function test_two_platforms_on_a_ray_count_once(): void
    {
        $scene = new StationScene(self::LAT, self::LNG, targets: [self::box(-5, 200, 5, 210)], platforms: [[[-100.0, 10.0], [100.0, 10.0]], [[-100.0, 20.0], [100.0, 20.0]]]);
        $this->assertSame(11.0, $scene->view(0, 0, 0, 5, 6, 50, 40)['platform_deg']);
    }

    public function test_a_house_beyond_the_building_reach_still_hides_a_far_platform(): void
    {
        // Portée de lecture des bâtiments : la plus grande des deux distances (ici celle du quai, 40 m).
        $scene = new StationScene(self::LAT, self::LNG, targets: [self::box(-100, 200, 100, 210)], obstacles: [self::box(-100, 20, 100, 25)], platforms: [[[-100.0, 30.0], [100.0, 30.0]]]);
        $this->assertSame(0.0, $scene->view(0, 0, 0, 1, 6, 10, 40)['platform_deg']);
    }

    public function test_zone_and_distance_to_the_station_building(): void
    {
        $this->assertFalse((new StationScene(self::LAT, self::LNG))->inZone(0, 0), 'zone inconnue : hors zone');
        $zoned = new StationScene(self::LAT, self::LNG, zone: self::box(-10, -10, 10, 10));
        $this->assertTrue($zoned->inZone(0, 0));
        $this->assertFalse($zoned->inZone(11, 0));
        $scene = new StationScene(self::LAT, self::LNG, targets: [self::box(-10, -10, 10, 10)]);
        $this->assertSame(0.0, $scene->targetDistance(0, 0), 'dans le bâtiment : 0');
        $this->assertEqualsWithDelta(5.0, (float) $scene->targetDistance(15, 0), 1e-9);
        $this->assertNull((new StationScene(self::LAT, self::LNG))->targetDistance(0, 0));
    }

    public function test_a_scene_without_building_is_empty_even_with_platforms(): void
    {
        $this->assertTrue((new StationScene(self::LAT, self::LNG, platforms: [[[0.0, 0.0], [1.0, 1.0]]]))->isEmpty());
        $this->assertFalse((new StationScene(self::LAT, self::LNG, targets: [self::box(0, 0, 1, 1)]))->isEmpty());
    }

    public function test_score_thresholds(): void
    {
        $view = fn (float $platform) => ['vff:view' => ['target_deg' => 40.0, 'platform_deg' => $platform, 'hidden_deg' => 0.0, 'nearest_m' => 20.0]];
        $base = self::photo(0, 0, 0, 80);
        $a = PanoramaxSelection::score($base + $view(15.0), self::LAT, self::LNG);
        $b = PanoramaxSelection::score($base + $view(14.9), self::LAT, self::LNG);
        $this->assertEqualsWithDelta(0.5, $a - $b, 1e-9, 'quai vu sur 15 degrés : bonus');
        $acc = fn (float $v) => PanoramaxSelection::score(self::photo(0, 0, 0, 80, ['quality:horizontal_accuracy' => $v]), self::LAT, self::LNG);
        $this->assertEqualsWithDelta(0.0, $acc(5.0) - $acc(4.0), 1e-9, '5 m : pas de malus');
        $this->assertEqualsWithDelta(0.5, $acc(5.0) - $acc(5.1), 1e-9, 'au-dessus de 5 m : malus');
    }
}
