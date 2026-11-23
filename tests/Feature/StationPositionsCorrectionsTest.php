<?php

namespace Tests\Feature;

use App\Services\StationCorrections;
use Tests\TestCase;

/**
 * Positions des gares dont les sources s'écartent le plus (23/11/2026) : 150 corrections « position » sourcées, établies par au
 * moins deux familles de sources à moins de 70 m, avec l'élément fautif dit dans le motif.
 */
class StationPositionsCorrectionsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function section(): array
    {
        $all = require database_path('data/gares_corrections.php');

        return array_values(array_filter($all, fn ($c) => ($c['action'] ?? null) === 'position'
            && str_contains((string) ($c['reason'] ?? ''), 'règle de placement VFF')));
    }

    public function test_the_section_holds_150_valid_corrections(): void
    {
        $section = $this->section();
        $this->assertCount(150, $section);
        foreach ($section as $c) {
            $this->assertSame([], StationCorrections::validate($c), (string) $c['station']['name']);
        }
    }

    public function test_each_position_is_in_france_sourced_and_concordant(): void
    {
        $seen = [];
        foreach ($this->section() as $c) {
            $name = (string) $c['station']['name'];
            $this->assertGreaterThan(41.0, $c['lat'], $name);
            $this->assertLessThan(51.5, $c['lat'], $name);
            $this->assertGreaterThan(-5.5, $c['lng'], $name);
            $this->assertLessThan(9.7, $c['lng'], $name);
            $this->assertStringContainsString('sources concordantes à moins de 70 m', (string) $c['reason'], $name);
            $this->assertMatchesRegularExpression('#^https://(fr\.wikipedia\.org/w/index\.php\?title=.+&oldid=\d+|www\.openstreetmap\.org/(node|way|relation)/\d+|data\.geopf\.fr/)#', (string) $c['source_url'], $name);
            $key = $name.'|'.json_encode($c['station']);
            $this->assertArrayNotHasKey($key, $seen, 'doublon '.$name);
            $seen[$key] = true;
        }
    }
}
