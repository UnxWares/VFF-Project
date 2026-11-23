<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Anomalies de gares du 23/11/2026 : trois événements de lignes encore exploitées lus comme des fermetures de la ligne entière
 * (neutralisés) et six corrections de gares (retour de 1941 à 1946, gare non rouverte, arrêt d'une ligne métrique, passerelle).
 */
class StationAnomaliesCorrectionsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function lineCorrections(): array
    {
        return require database_path('data/line_event_corrections_gares_anomalies.php');
    }

    public function test_the_three_wrong_line_events_are_neutralized_with_a_permalink(): void
    {
        $c = $this->lineCorrections();
        $this->assertSame(
            ['903000:closure_passenger:1939-05-15', '903000:closure:1962-01-01', '753000:dismantling:1944-01-01'],
            array_map(fn ($x) => $x['line'].':'.$x['match']['type'].':'.$x['match']['date'], $c),
        );
        foreach ($c as $x) {
            $this->assertSame('neutralize', $x['action']);
            $this->assertSame('wikipedia', $x['match']['source']);
            $this->assertMatchesRegularExpression('/oldid=\d+/', $x['source_url']);
            $this->assertNotSame('', trim($x['quote']));
            $this->assertNotSame('', trim($x['reason']));
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> nom → [type, date, précision] */
    public static function stationCorrections(): array
    {
        return [
            'Belvèze' => ['closure_passenger', '1946-05-06', 'day'],
            'Moulin-Neuf' => ['closure_passenger', '1946-05-06', 'day'],
            'Mirepoix' => ['closure_passenger', '1946-05-06', 'day'],
            'Bourogne' => ['closure_passenger', '1992-01-01', 'year'],
            'CdN' => ['closure', '1948-05-31', 'day'],
        ];
    }

    public function test_each_station_has_one_dated_sourced_correction(): void
    {
        $all = require database_path('data/gares_corrections.php');
        foreach (self::stationCorrections() as $name => [$type, $date, $precision]) {
            $found = array_values(array_filter($all, fn ($c) => ($c['station']['name'] ?? null) === $name
                && ($c['action'] ?? null) === 'add' && str_starts_with((string) ($c['type'] ?? ''), 'closure')
                && $c['date'] === $date));
            $this->assertCount(1, $found, "{$name} : une correction de fermeture");
            $this->assertSame([$type, $date, $precision], [$found[0]['type'], $found[0]['date'], $found[0]['precision']], $name);
            $this->assertMatchesRegularExpression('/oldid=\d+/', (string) $found[0]['source_url'], "{$name} : permalien");
        }
    }

    public function test_the_footbridge_of_massy_palaiseau_is_excluded_from_the_network(): void
    {
        $all = require database_path('data/gares_corrections.php');
        $found = array_values(array_filter($all, fn ($c) => ($c['station']['osm'] ?? null) === 'w417349802'));
        $this->assertCount(1, $found);
        $this->assertSame(['kind', 'excluded'], [$found[0]['action'], $found[0]['kind']]);
    }
}
