<?php

namespace Database\Factories;

use App\Enums\LineType;
use App\Models\Line;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Line>
 */
class LineFactory extends Factory
{
    public function definition(): array
    {
        $cityA = fake()->randomElement(['Paris', 'Lyon', 'Bordeaux', 'Toulouse', 'Marseille', 'Lille', 'Nantes', 'Strasbourg']);
        $cityB = fake()->randomElement(['Brest', 'Nice', 'Hendaye', 'Calais', 'Modane', 'Cherbourg', 'Perpignan', 'Le Havre']);
        $name = "{$cityA}-{$cityB}";

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'name' => $name,
            'alt_names' => null,
            'code' => fake()->numerify('### ###'),
            'type' => fake()->randomElement(LineType::cases())->value,
            'historical_operator' => fake()->randomElement([
                'Compagnie de l\'Ouest',
                'Compagnie du Nord',
                'Compagnie du Midi',
                'Compagnie du PLM',
                'État',
                'SNCF',
            ]),
            'description' => fake()->paragraph(2),
        ];
    }
}
