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
    /**
     * Départements tirés par les fabriques (gares, accidents) : de vrais codes INSEE, qu'aucun test ne
     * filtre ni ne pose lui-même. Un code tiré au hasard parmi tous (`##`) a déjà fait échouer en CI un
     * filtre par département, quand il tombait sur celui du test. Vérifié par FactoryDepartmentsTest.
     */
    public const array RESERVED_DEPARTMENTS = ['07', '09', '46', '52', '58', '70', '88', '90'];

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
            'department_code' => fake()->randomElement(self::RESERVED_DEPARTMENTS),
            'opened_at' => fake()->dateTimeBetween('-150 years', '-50 years')->format('Y-m-d'),
            'closed_at' => null,
            'description' => fake()->paragraph(1),
        ];
    }
}
