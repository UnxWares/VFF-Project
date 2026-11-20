<?php

namespace Tests\Unit;

use App\Services\EraTimeline;
use PHPUnit\Framework\TestCase;

/**
 * Règle générale « ouverture sans voyageurs » (contrôle qualité du 2026-10-20) : aucun voyageur en
 * France avant le premier service voyageurs (Saint-Étienne - Andrézieux, 01/03/1832). L'ère 1830
 * (état au 31/12/1830) est fret, même pour une ouverture sans précision du trafic.
 */
class EraTimelineFirstPassengersTest extends TestCase
{
    private static function ev(string $type, string $date, string $source = 'manual'): array
    {
        return ['type' => $type, 'date' => $date, 'source' => $source];
    }

    public function test_generic_opening_before_1832_is_freight_until_passengers_exist(): void
    {
        // Saint-Étienne - Andrézieux, ouverte le 30/06/1827 (« opening » d'OpenHistoricalMap).
        $t = new EraTimeline([self::ev('opening', '1827-06-30', 'ohm')]);
        $this->assertSame('freight', $t->stateAt(1830)['traffic']);
        $this->assertSame('mixed', $t->stateAt(1840)['traffic'], 'voyageurs ⇒ mixte une fois le service voyageurs possible');
    }

    public function test_sourced_freight_opening_then_passenger_opening(): void
    {
        // Givors - Rive-de-Gier : fret le 28/06/1830, voyageurs le 03/04/1832.
        $t = new EraTimeline([self::ev('opening_freight', '1830-06-28'), self::ev('opening_passenger', '1832-04-03')]);
        $this->assertSame('freight', $t->stateAt(1830)['traffic']);
        $this->assertSame('mixed', $t->stateAt(1840)['traffic']);
    }

    public function test_rule_does_not_touch_later_openings(): void
    {
        $t = new EraTimeline([self::ev('opening', '1837-08-24')]);
        $this->assertSame('mixed', $t->stateAt(1840)['traffic']);
        $this->assertSame('1832-03-01', EraTimeline::FIRST_PASSENGER_SERVICE);
    }
}
