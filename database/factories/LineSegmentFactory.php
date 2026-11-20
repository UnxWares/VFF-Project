<?php

namespace Database\Factories;

use App\Enums\SegmentStatus;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LineSegment>
 */
class LineSegmentFactory extends Factory
{
    public function definition(): array
    {
        // 3-5 points aléatoires dans le territoire métropolitain français.
        $count = fake()->numberBetween(3, 5);
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $lat = fake()->randomFloat(4, 42.5, 51.0);
            $lng = fake()->randomFloat(4, -4.5, 7.5);
            $points[] = Point::makeGeodetic($lat, $lng);
        }

        return [
            'line_id' => Line::factory(),
            'era_id' => Era::factory(),
            'geom' => LineString::make($points),
            'status' => fake()->randomElement(SegmentStatus::cases())->value,
            'length_meters' => null, // calculé via ST_Length au besoin
            'max_speed_kmh' => fake()->randomElement([80, 100, 120, 140, 160, 200, 220, 320]),
            'electrified' => fake()->boolean(70),
            'voltage_v' => fake()->randomElement([null, 1500, 25000]),
            'gauge_mm' => 1435,
        ];
    }

    public function inService(): static
    {
        return $this->state(fn () => ['status' => SegmentStatus::InService->value]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => ['status' => SegmentStatus::Abandoned->value]);
    }
}
