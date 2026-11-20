<?php

namespace Database\Factories;

use App\Models\Era;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Era>
 */
class EraFactory extends Factory
{
    public function definition(): array
    {
        $year = fake()->numberBetween(1900, 2030);
        $year = (int) (round($year / 10) * 10);

        return [
            'year' => $year,
            'label' => "Décennie {$year}",
            'description' => fake()->sentence(12),
            'is_current' => false,
            'editable_until' => null,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => [
            'is_current' => true,
            'editable_until' => '2029-12-31',
        ]);
    }
}
