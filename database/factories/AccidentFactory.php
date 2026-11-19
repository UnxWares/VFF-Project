<?php

namespace Database\Factories;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Enums\AccidentLocationPrecision;
use App\Enums\AccidentReviewStatus;
use App\Models\Accident;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Accident fictif pour les tests (jamais en base de développement : les vrais accidents viennent
 * de vff:import-accidents).
 *
 * @extends Factory<Accident>
 */
class AccidentFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-150 years', '-1 year');
        $commune = fake()->city();
        $kind = fake()->randomElement(AccidentKind::cases());

        return [
            'slug' => $date->format('Y-m-d').'-'.Str::slug($commune).'-'.fake()->unique()->numberBetween(1, 99999),
            'wikidata_id' => null,
            'occurred_on' => $date->format('Y-m-d'),
            'date_precision' => AccidentDatePrecision::Day->value,
            'kind' => $kind->value,
            'name' => ['fr' => "Accident de {$commune}", 'en' => "{$commune} rail accident", 'de' => "Eisenbahnunfall von {$commune}"],
            'commune_insee' => fake()->numerify('#####'),
            'commune_name' => $commune,
            'department_code' => fake()->numerify('##'),
            'geom' => Point::makeGeodetic(fake()->randomFloat(4, 43.0, 50.5), fake()->randomFloat(4, -1.5, 7.0)),
            'location_precision' => AccidentLocationPrecision::Exact->value,
            'location_source' => 'wikidata',
            'deaths' => fake()->numberBetween(0, 40),
            'injuries' => fake()->optional(0.7)->numberBetween(0, 200),
            'toll_source' => 'wikidata',
            'trains' => null,
            'wikipedia' => null,
            'summary' => ['fr' => 'Résumé.', 'en' => 'Summary.', 'de' => 'Zusammenfassung.'],
            'review_status' => AccidentReviewStatus::Ok->value,
            'review_notes' => null,
            'provenance' => ['date' => 'wikidata', 'location' => 'wikidata', 'deaths' => 'wikidata'],
            'imported_at' => now(),
        ];
    }

    public function on(string $date, AccidentDatePrecision $precision = AccidentDatePrecision::Day): static
    {
        return $this->state(fn () => ['occurred_on' => $date, 'date_precision' => $precision->value]);
    }

    public function at(float $lat, float $lng): static
    {
        return $this->state(fn () => ['geom' => Point::makeGeodetic($lat, $lng)]);
    }

    public function toReview(string $code = 'toll_mismatch', string $detail = ''): static
    {
        return $this->state(fn () => [
            'review_status' => AccidentReviewStatus::ToReview->value,
            'review_notes' => [['code' => $code, 'detail' => $detail]],
        ]);
    }
}
