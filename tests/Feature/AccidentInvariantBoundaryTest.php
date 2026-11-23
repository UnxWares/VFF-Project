<?php

namespace Tests\Feature;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Services\Accidents\AccidentImporter;
use App\Services\DataInvariants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Frontières de l'invariant accidents.same_day_nearby : jour, précision, nature, distance, publication, couples relus. */
class AccidentInvariantBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function violations(): int
    {
        $r = (new DataInvariants(['accidents.same_day_nearby']))->run()[0];
        $this->assertArrayNotHasKey('error', $r);

        return $r['count'];
    }

    private function accident(string $slug, string $date, float $lat, float $lng, array $over = []): Accident
    {
        return Accident::factory()->on($date)->at($lat, $lng)->create($over + ['slug' => $slug, 'kind' => AccidentKind::Derailment->value]);
    }

    public function test_the_pair_is_counted_only_when_every_condition_holds(): void
    {
        $this->accident('2001-01-01-a', '2001-01-01', 48.0, 2.0);
        $this->assertSame(0, $this->violations(), 'une seule fiche');
        $this->accident('2001-01-01-b', '2001-01-01', 48.1, 2.0);
        $this->assertSame(1, $this->violations());
    }

    public function test_other_day_or_precision_or_kind_is_not_a_pair(): void
    {
        $this->accident('2001-01-01-a', '2001-01-01', 48.0, 2.0);
        $this->accident('2001-01-02-b', '2001-01-02', 48.1, 2.0);
        $this->assertSame(0, $this->violations(), 'un jour d\'écart');
        $this->accident('2001-01-01-c', '2001-01-01', 48.1, 2.0, ['kind' => AccidentKind::Collision->value]);
        $this->assertSame(0, $this->violations(), 'autre nature');
        $this->accident('2001-01-01-d', '2001-01-01', 48.1, 2.0, ['date_precision' => AccidentDatePrecision::Month->value]);
        $this->assertSame(0, $this->violations(), 'date au mois : jamais comparée au jour');
        $this->accident('2001-01-01-e', '2001-01-01', 48.1, 2.0, ['date_precision' => AccidentDatePrecision::Month->value, 'slug' => '2001-01-01-f']);
        $this->assertSame(0, $this->violations(), 'deux fiches au mois : pas non plus');
    }

    public function test_distance_limit_is_150_kilometres(): void
    {
        $this->accident('2001-01-01-a', '2001-01-01', 48.0, 2.0);
        $this->accident('2001-01-01-near', '2001-01-01', 48.0 + 148 / 111.1949, 2.0);
        $this->assertSame(1, $this->violations());
        $this->accident('2001-01-01-far', '2001-01-01', 48.0 - 152 / 111.1949, 2.0);
        $this->assertSame(1, $this->violations(), 'à 152 km : hors du rayon, et à 300 km de la première');
        $this->assertSame(150000, AccidentImporter::ROUTE_MAX_M);
    }

    public function test_alias_unlocated_and_unpublished_records_are_not_counted(): void
    {
        $a = $this->accident('2001-01-01-a', '2001-01-01', 48.0, 2.0);
        $b = $this->accident('2001-01-01-b', '2001-01-01', 48.1, 2.0);
        $this->assertSame(1, $this->violations());

        $b->forceFill(['alias_of_id' => $a->id])->save();
        $this->assertSame(0, $this->violations(), 'alias de l\'autre');
        $b->forceFill(['alias_of_id' => null])->save();
        $a->forceFill(['alias_of_id' => $b->id])->save();
        $this->assertSame(0, $this->violations(), 'le premier est l\'alias');
        $a->forceFill(['alias_of_id' => null])->save();
        $this->assertSame(1, $this->violations());

        Accident::query()->withoutGlobalScopes()->whereKey($b->id)->update(['geom' => null]);
        $this->assertSame(0, $this->violations(), 'sans position : pas de distance');
    }

    public function test_a_person_struck_record_without_source_or_context_is_not_published_so_not_counted(): void
    {
        $this->accident('2001-01-01-a', '2001-01-01', 48.0, 2.0, ['kind' => AccidentKind::PersonStruck->value]);
        $this->accident('2001-01-01-b', '2001-01-01', 48.1, 2.0, ['kind' => AccidentKind::PersonStruck->value]);
        $this->assertSame(0, $this->violations());
        Accident::query()->withoutGlobalScopes()->where('slug', '2001-01-01-a')->update(['context_checked_on' => '2026-11-22']);
        $this->assertSame(0, $this->violations(), 'une seule des deux publiée');
        Accident::query()->withoutGlobalScopes()->where('slug', '2001-01-01-b')->update(['context_checked_on' => '2026-11-22']);
        $this->assertSame(1, $this->violations());
    }

    public function test_a_reviewed_pair_is_excluded_whichever_record_was_created_first(): void
    {
        foreach (require database_path('data/accident_distinct_pairs.php') as [$x, $y]) {
            // Le second slug du couple est créé d'abord : son identifiant est le plus petit.
            $this->accident($y, substr($y, 0, 10), 44.68, -0.40);
            $this->accident($x, substr($x, 0, 10), 44.74, -0.53);
        }
        $this->assertSame(0, $this->violations());
        // Un voisin non relu du même jour et de même nature, lui, est compté.
        [$x] = (require database_path('data/accident_distinct_pairs.php'))[0];
        $this->accident('9999-99-99-x', substr($x, 0, 10), 44.7, -0.5);
        $this->assertGreaterThanOrEqual(1, $this->violations());
    }

    public function test_the_conditions_hold_for_the_older_record_of_the_pair_too(): void
    {
        // Le premier créé (identifiant le plus petit) porte la particularité, le second est ordinaire.
        $first = $this->accident('2001-01-01-a', '2001-01-01', 48.0, 2.0, ['date_precision' => AccidentDatePrecision::Month->value]);
        $this->accident('2001-01-01-b', '2001-01-01', 48.1, 2.0);
        $this->assertSame(0, $this->violations(), 'premier au mois');
        $first->forceFill(['date_precision' => AccidentDatePrecision::Day->value])->save();
        $this->assertSame(1, $this->violations());
        Accident::query()->withoutGlobalScopes()->whereKey($first->id)->update(['geom' => null]);
        $this->assertSame(0, $this->violations(), 'premier sans position');
    }

    public function test_an_unpublished_older_record_is_not_counted(): void
    {
        $this->accident('2001-01-01-a', '2001-01-01', 48.0, 2.0, ['kind' => AccidentKind::PersonStruck->value]);
        $this->accident('2001-01-01-b', '2001-01-01', 48.1, 2.0, ['kind' => AccidentKind::PersonStruck->value]);
        Accident::query()->withoutGlobalScopes()->where('slug', '2001-01-01-b')->update(['context_checked_on' => '2026-11-22']);
        $this->assertSame(0, $this->violations(), 'le plus ancien n\'est pas publiable');
    }
}
