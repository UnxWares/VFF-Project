<?php

namespace Database\Factories;

use App\Enums\MaterialType;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'CC 7100', 'CC 72000', 'BB 67000', 'BB 22200', 'BB 9200',
            'X 2700', 'X 73500', 'Z 2', 'Z 7300',
            'TGV Atlantique', 'TGV Sud-Est', 'TGV Réseau', 'TGV Duplex',
            '141 R', '231 K', '232 U',
        ]).' '.fake()->unique()->numberBetween(1, 999);

        $from = fake()->numberBetween(1900, 2020);

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'type' => fake()->randomElement(MaterialType::cases())->value,
            'builder' => fake()->randomElement(['Alstom', 'ANF', 'Schneider', 'Siemens', 'Bombardier']),
            'in_service_from' => $from,
            'in_service_to' => fake()->boolean(50) ? fake()->numberBetween($from + 10, 2030) : null,
            'description' => fake()->paragraph(1),
        ];
    }
}
