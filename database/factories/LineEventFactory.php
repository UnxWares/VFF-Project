<?php

namespace Database\Factories;

use App\Enums\LineEventType;
use App\Models\Line;
use App\Models\LineEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LineEvent>
 */
class LineEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'line_id' => Line::factory(),
            'type' => fake()->randomElement(LineEventType::cases())->value,
            'event_date' => fake()->dateTimeBetween('-150 years', 'now')->format('Y-m-d'),
            'description' => fake()->sentence(8),
            'source' => fake()->optional(0.6)->company(),
        ];
    }
}
