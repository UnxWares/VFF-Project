<?php

namespace Database\Factories;

use App\Enums\StationType;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Station>
 */
class StationFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->city();
        $name = "Gare de {$city}";

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'name' => $name,
            'alt_names' => null,
            'type' => fake()->randomElement(StationType::cases())->value,
            'geom' => Point::makeGeodetic(
                fake()->randomFloat(4, 42.5, 51.0),
                fake()->randomFloat(4, -4.5, 7.5),
            ),
            'city' => $city,
            'department_code' => fake()->numerify('##'),
            'opened_at' => fake()->dateTimeBetween('-150 years', '-50 years')->format('Y-m-d'),
            'closed_at' => null,
            'description' => fake()->paragraph(1),
        ];
    }
}
