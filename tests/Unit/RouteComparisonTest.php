<?php

namespace Tests\Unit;

use App\Enums\RouteComparisonOutcome;
use App\Services\RouteComparison;
use PHPUnit\Framework\TestCase;

/**
 * Comparaison d'un trajet entre deux époques, fonctions pures : issue, écart de longueur,
 * lignes communes ou propres à une époque, présence d'une ligne à l'autre époque.
 */
class RouteComparisonTest extends TestCase
{
    /** @param  list<array{0: ?string, 1: int}>  $lines  [slug, longueur] */
    private function route(int $distance, array $lines, array $stations): array
    {
        return [
            'distance_m' => $distance,
            'lines' => array_map(fn (array $l) => ['line_slug' => $l[0], 'line_name' => "Ligne {$l[0]}", 'line_code' => null, 'length_m' => $l[1]], $lines),
            'stations' => array_map(fn (string $s) => ['slug' => $s], $stations),
        ];
    }

    public function test_outcome_from_availability(): void
    {
        $this->assertSame(RouteComparisonOutcome::Both, RouteComparisonOutcome::fromAvailability(true, true));
        $this->assertSame(RouteComparisonOutcome::Lost, RouteComparisonOutcome::fromAvailability(true, false));
        $this->assertSame(RouteComparisonOutcome::Gained, RouteComparisonOutcome::fromAvailability(false, true));
        $this->assertSame(RouteComparisonOutcome::Neither, RouteComparisonOutcome::fromAvailability(false, false));
    }

    public function test_differences_between_two_routes(): void
    {
        $earlier = $this->route(118_722, [['rfn-610000', 117_471], ['archeo-peyrat', 1_251]], ['angouleme', 'ruelle', 'limoges']);
        $later = $this->route(241_065, [['rfn-570000', 22_203], ['rfn-566000', 6_238], ['rfn-570000', 75_586], ['archeo-peyrat', 900]], ['angouleme', 'ruffec', 'limoges']);

        $d = RouteComparison::differences($earlier, $later, ['rfn-610000' => ['later' => 'partly_open'], 'rfn-570000' => ['earlier' => 'open']]);

        $this->assertSame('both', $d['outcome']);
        $this->assertSame(122_343, $d['distance_change_m']);
        $this->assertSame(122.3, $d['distance_change_km']);
        $this->assertSame(103.0, $d['distance_change_percent']);
        $this->assertFalse($d['same_path']);
        $this->assertSame(2, $d['shared_stations']);
        // Ligne commune : longueur de la seconde époque.
        $this->assertSame([['line_slug' => 'archeo-peyrat', 'line_name' => 'Ligne archeo-peyrat', 'line_code' => null, 'length_m' => 900, 'length_km' => 0.9]], $d['shared_lines']);
        $this->assertSame([['rfn-610000', 'partly_open']], array_map(fn ($l) => [$l['line_slug'], $l['other_era']], $d['lines_only_earlier']));
        // Ligne quittée puis reprise : une seule entrée, longueurs additionnées ; absente des présences : none.
        $this->assertSame([['rfn-570000', 97_789, 'open'], ['rfn-566000', 6_238, 'none']],
            array_map(fn ($l) => [$l['line_slug'], $l['length_m'], $l['other_era']], $d['lines_only_later']));
    }

    public function test_route_lost_or_identical(): void
    {
        $route = $this->route(30_000, [['ligne-a', 30_000], [null, 400]], ['a', 'b']);

        $lost = RouteComparison::differences($route, null, []);
        $this->assertSame('lost', $lost['outcome']);
        $this->assertNull($lost['distance_change_m']);
        $this->assertNull($lost['distance_change_percent']);
        $this->assertFalse($lost['same_path']);
        $this->assertSame(['none', null], array_column($lost['lines_only_earlier'], 'other_era'), 'sans slug : rien à dire');

        $same = RouteComparison::differences($route, $route, []);
        $this->assertSame(0, $same['distance_change_m']);
        $this->assertTrue($same['same_path']);
        $this->assertSame([], $same['lines_only_earlier']);

        $this->assertSame('neither', RouteComparison::differences(null, null, [])['outcome']);
    }

    public function test_line_presence_from_lengths(): void
    {
        $this->assertSame('open', RouteComparison::presenceOf(950, 50, 1000));
        $this->assertSame('partly_open', RouteComparison::presenceOf(400, 600, 1000));
        $this->assertSame('closed', RouteComparison::presenceOf(0, 600, 1000));
        $this->assertSame('none', RouteComparison::presenceOf(0, 0, 1000));
        $this->assertSame('none', RouteComparison::presenceOf(0, 0, 0));
    }
}
