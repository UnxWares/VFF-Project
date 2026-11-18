<?php

namespace Database\Factories;

use App\Enums\ContributionAction;
use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contribution>
 */
class ContributionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['line', 'line_segment', 'station', 'material', 'line_event', 'media']),
            'action' => fake()->randomElement(ContributionAction::cases())->value,
            'target_type' => null,
            'target_id' => null,
            'payload' => ['note' => fake()->sentence()],
            'status' => ContributionStatus::Pending->value,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ContributionStatus::Approved->value,
            'reviewed_by' => User::factory()->validator(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ContributionStatus::Rejected->value,
            'reviewed_by' => User::factory()->validator(),
            'reviewed_at' => now(),
            'review_notes' => fake()->sentence(),
        ]);
    }
}
