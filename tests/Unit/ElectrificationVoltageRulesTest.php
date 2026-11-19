<?php

namespace Tests\Unit;

use App\Services\Traction\ElectrificationTimeline as T;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tension d'une électrification (ElectrificationTimeline::voltageFor), règle par règle :
 * tension actuelle de la décennie 2020, tension citée, tension d'un événement voisin, tension
 * actuelle compatible, règle d'époque (1 500 V entre 1921 et 1950).
 */
class ElectrificationVoltageRulesTest extends TestCase
{
    private static function event(string $date, ?int $voltage = null, string $scope = 'located', ?int $hint = null): array
    {
        return ['date' => $date, 'voltage_v' => $voltage, 'scope' => $scope, 'kind' => 'electrification', 'voltage_hint' => $hint];
    }

    /** @param  list<array<string, mixed>>  $events */
    private function voltage(array $events, array $current, array $event, int $year): ?int
    {
        $timeline = new T($events, $current);

        return (new ReflectionMethod($timeline, 'voltageFor'))->invoke($timeline, $event, $year);
    }

    private const TODAY_25KV = ['state' => true, 'voltage_v' => 25000, 'source' => 'sncf'];

    private const UNKNOWN = ['state' => null, 'voltage_v' => null, 'source' => null];

    public function test_in_the_2020s_the_tension_observed_today_wins(): void
    {
        $event = self::event('1960-01-01', 1500);

        $this->assertSame(25000, $this->voltage([$event], self::TODAY_25KV, $event, 2025));
    }

    public function test_a_later_event_keeps_the_tension_of_the_event_itself(): void
    {
        // Ré-électrification postérieure : la tension actuelle ne vaut que pour la dernière.
        $first = self::event('1960-01-01', 1500);
        $later = self::event('1975-01-01', 25000);

        $this->assertSame(1500, $this->voltage([$first, $later], self::TODAY_25KV, $first, 2025));
        $this->assertSame(25000, $this->voltage([$first, $later], self::TODAY_25KV, $later, 2025));
    }

    public function test_before_the_2020s_the_cited_tension_wins(): void
    {
        $event = self::event('1960-01-01', 1500);

        $this->assertSame(1500, $this->voltage([$event], self::TODAY_25KV, $event, 1970));
    }

    public function test_a_neighbour_hint_is_used_before_1951_for_a_pioneer_tension(): void
    {
        $event = self::event('1935-01-01', null, 'neighbour', 650);

        $this->assertSame(650, $this->voltage([$event], self::TODAY_25KV, $event, 1940));
    }

    public function test_the_tension_of_today_is_used_when_nothing_else_is_known_and_it_fits_the_era(): void
    {
        // 1 500 V actuels : compatibles avec une électrification des années 1930.
        $event = self::event('1935-01-01');
        $today = ['state' => true, 'voltage_v' => 1500, 'source' => 'sncf'];

        $this->assertSame(1500, $this->voltage([$event], $today, $event, 1940));
    }

    public function test_25kv_today_is_not_projected_onto_an_electrification_before_1951(): void
    {
        $event = self::event('1935-01-01');

        $this->assertSame(1500, $this->voltage([$event], self::TODAY_25KV, $event, 1940), 'règle d\'époque : 1 500 V entre 1921 et 1950');
    }

    public function test_the_tension_is_unknown_before_the_1920_decision_without_any_source(): void
    {
        $event = self::event('1910-01-01');

        $this->assertNull($this->voltage([$event], self::UNKNOWN, $event, 1915));
    }

    public function test_the_tension_is_unknown_after_1950_without_any_source(): void
    {
        $event = self::event('1985-01-01');

        $this->assertNull($this->voltage([$event], self::UNKNOWN, $event, 1990));
    }
}
