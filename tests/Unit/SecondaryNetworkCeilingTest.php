<?php

namespace Tests\Unit;

use App\Services\ConsistencyChecker;
use PHPUnit\Framework\TestCase;

/**
 * Plafonds du réseau secondaire en service (network.km_out_of_range) : sourcés (2026-10-20).
 * Historail, avril 2011, p. 20 (cité par fr.wikipedia, Fermetures de lignes ferroviaires en France,
 * oldid 239176212) : « réduit de moitié en 1948 à environ 10 000 kilomètres », « quelques centaines de
 * kilomètres vers 1960 ». L'ancien plafond 1950 de 4 000 km n'avait pas de source.
 */
class SecondaryNetworkCeilingTest extends TestCase
{
    public function test_secondary_ceilings_follow_the_sourced_figures(): void
    {
        $ref = ConsistencyChecker::HISTORICAL_KM['secondaire'];

        $this->assertSame(['max' => 10000], $ref[1950], '1948 : environ 10 000 km, en baisse ensuite');
        // 1960 : quelques centaines de km d'intérêt local (≤ 1 000) + voie métrique d'intérêt général.
        $this->assertSame(['max' => 2000], $ref[1960]);
        // Le plafond ne remonte jamais d'une ère à la suivante après 1950 (réseau en déclin).
        $years = array_keys(array_filter($ref, fn ($r, $y) => $y >= 1950 && isset($r['max']), ARRAY_FILTER_USE_BOTH));
        foreach (array_slice($years, 1) as $i => $y) {
            $this->assertLessThanOrEqual($ref[$years[$i]]['max'], $ref[$y]['max'], "{$y}");
        }
    }
}
