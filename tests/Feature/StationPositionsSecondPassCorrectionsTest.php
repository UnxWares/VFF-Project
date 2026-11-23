<?php

namespace Tests\Feature;

use App\Services\StationCorrections;
use Tests\TestCase;

/**
 * Positions des gares à écart fort, deuxième passe (23/11/2026) : 132 corrections « position » dont deux familles de
 * sources indépendantes concordent à moins de 70 m, l'élément fautif étant dit dans le motif ; les gares fermées ne sont
 * retenues que si les coordonnées de leur article concordent.
 */
class StationPositionsSecondPassCorrectionsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function section(): array
    {
        $all = require database_path('data/gares_corrections.php');

        return array_values(array_filter($all, fn ($c) => ($c['action'] ?? null) === 'position'
            && str_contains((string) ($c['reason'] ?? ''), 'deuxième passe')));
    }

    public function test_the_section_holds_132_valid_corrections(): void
    {
        $section = $this->section();
        $this->assertCount(132, $section);
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
            $key = json_encode($c['station']);
            $this->assertArrayNotHasKey($key, $seen, 'doublon '.$name);
            $seen[$key] = true;
        }
    }

    public function test_each_reason_counts_two_independent_families_within_70_metres(): void
    {
        foreach ($this->section() as $c) {
            $name = (string) $c['station']['name'];
            [$concordant] = explode(' ; à ', (string) $c['reason'], 2);
            $families = [
                'wikipedia' => preg_match('/article Wikipédia|Wikidata Q/', $concordant) === 1,
                'bdtopo' => str_contains($concordant, 'BD TOPO'),
                'osm' => str_contains($concordant, 'OSM '),
                'sncf' => str_contains($concordant, 'liste SNCF'),
            ];
            $this->assertGreaterThanOrEqual(2, count(array_filter($families)), $name);
            preg_match_all('/ à (\d+) m/', $concordant, $m);
            foreach ($m[1] as $metres) {
                $this->assertLessThanOrEqual(70, (int) $metres, $name);
            }
        }
    }

    public function test_the_cited_source_is_among_the_concordant_ones(): void
    {
        foreach ($this->section() as $c) {
            $name = (string) $c['station']['name'];
            $url = (string) $c['source_url'];
            if (preg_match('/oldid=(\d+)$/', $url, $m) === 1) {
                $this->assertStringContainsString('(oldid '.$m[1].')', (string) $c['reason'], $name);
                $this->assertMatchesRegularExpression('/ : latitude = -?\d+\.\d{6}, longitude = -?\d+\.\d{6}$/', (string) $c['quote'], $name);
            } elseif (preg_match('#openstreetmap\.org/(node|way|relation)/(\d+)$#', $url, $m) === 1) {
                $this->assertStringContainsString('OSM '.$m[1][0].$m[2].' à', (string) $c['reason'], $name);
            }
        }
    }
}
