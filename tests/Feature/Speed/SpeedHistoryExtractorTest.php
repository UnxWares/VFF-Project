<?php

namespace Tests\Feature\Speed;

use App\Services\Speed\SpeedHistoryExtractor;
use Tests\TestCase;

/**
 * Lecture des changements de vitesse datés dans un article : faits sûrs seulement, phrases réelles d'articles.
 */
class SpeedHistoryExtractorTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function facts(string $text, string $title = 'Ligne de Vichy à Riom'): array
    {
        $facts = app(SpeedHistoryExtractor::class)->extract("== Histoire ==\n".$text, $title, new \DateTimeImmutable('2026-11-23'));

        return array_values(array_filter($facts, fn (array $f): bool => $f['confidence'] !== 'low'));
    }

    public function test_two_dated_lowerings_in_one_sentence_give_two_facts_with_previous_speeds(): void
    {
        $facts = $this->facts('La vitesse de circulation est abaissée en 2015 de 70 à 60 km/h, puis en 2018 de 60 à 50 km/h.');

        $this->assertSame([['2015-01-01', 70, 60], ['2018-01-01', 60, 50]],
            array_map(fn (array $f) => [$f['date'], $f['previous_speed_kmh'], $f['speed_kmh']], $facts));
        $this->assertSame(['lower', 'lower'], array_column($facts, 'kind'));
    }

    public function test_a_clause_without_its_own_date_does_not_borrow_the_date_of_the_next_one(): void
    {
        $facts = $this->facts('La vitesse limite est portée à 140 km/h entre Ennezat-Clerlande et Riom, puis en 1976 à 160 km/h.');

        $this->assertSame([160], array_column($facts, 'speed_kmh'));
        $this->assertSame('1976-01-01', $facts[0]['date']);
    }

    public function test_a_day_precise_raise_on_a_named_section_is_highly_reliable(): void
    {
        $facts = $this->facts('Depuis le 13 décembre 2009, la limitation de vitesse a été relevée à 210 km/h entre les gares de Châtelaudren - Plouagat et Guingamp.',
            'Ligne de Paris-Montparnasse à Brest');

        $this->assertCount(1, $facts);
        $this->assertSame(['raise', 210, '2009-12-13', 'day', 'high', 'section'],
            [$facts[0]['kind'], $facts[0]['speed_kmh'], $facts[0]['date'], $facts[0]['precision'], $facts[0]['confidence'], $facts[0]['scope']]);
    }

    public function test_future_projects_train_speeds_averages_and_temporary_limits_are_ignored(): void
    {
        $this->assertSame([], $this->facts(implode("\n", [
            'Le relèvement de la vitesse jusqu’à 130 km/h sera effectif à partir du changement de service à l’été 2019.',
            'Les contrats de plan 2007-2013 prévoient un relèvement de la vitesse maximale autorisée à 220 km/h.',
            'À son lancement en 1971, le Trans-Europ-Express L’Aquitaine assure la liaison à 200 km/h.',
            'En 2019, il faut environ une heure pour la parcourir, soit une moyenne de 67 km/h.',
            'En août 2026, la vitesse étant réduite à 40 km/h à la suite de la canicule.',
            'Le 4 août 1974, le train aborde à 120 km/h une courbe limitée à 40 km/h et déraille.',
            'En 2022, la vitesse est limitée à 140 km/h pour les AGC et les X 73500 en sens impair.',
        ])));
    }

    public function test_a_dated_permitted_speed_is_a_state_and_a_dated_list_item_keeps_its_date(): void
    {
        $facts = $this->facts("En 2013, la vitesse limite autorisée est de 60 km/h sur ce raccordement.\n* Septembre 1982 : mise à 90 km/h de la ligne ; réouverture à l'année.");

        $this->assertSame([['state', 60, '2013-01-01', 'medium'], ['raise', 90, '1982-09-01', 'high']],
            array_map(fn (array $f) => [$f['kind'], $f['speed_kmh'], $f['date'], $f['confidence']], $facts));
    }
}
