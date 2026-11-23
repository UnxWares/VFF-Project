<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit des fermetures 3 (23/11/2026) : 19 gares dont la date de fermeture (voyageurs ou service) était déduite de la ligne ou sans jour,
 * alors que l'article de la ligne, qui date sa chronologie par sections, donne la fermeture voyageurs de leur section (15/05/1939,
 * 31/05/1970...), la fin de l'exploitation (Maxonchamp) ou le jour de la fermeture (Barjols-Tavernes). Chacune a une correction sourcée
 * (section 49 de gares_corrections.php), une seule, datée, avec un permalien et une citation.
 */
class StationClosureAudit3CorrectionsTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string}> nom → [type, date, précision] */
    public static function corrections(): array
    {
        return [
            'Péronne-la-Chapelette' => ['closure_passenger', '1970-04-01', 'day'],
            'Beaune-la-Rolande' => ['closure_passenger', '1939-05-15', 'day'],
            'Le Bouchet' => ['closure_passenger', '1939-05-15', 'day'],
            'La Bourdigalière' => ['closure_passenger', '1939-05-15', 'day'],
            'Berthegon' => ['closure_passenger', '1939-05-15', 'day'],
            'Cernay-Doussay' => ['closure_passenger', '1939-05-15', 'day'],
            'Monts-sur-Guesnes' => ['closure_passenger', '1939-05-15', 'day'],
            'Bray-Dunes' => ['closure_passenger', '1994-08-28', 'day'],
            'La Haye-du-Puits' => ['closure_passenger', '1976-01-01', 'year'],
            'Domfront (Orne)' => ['closure_passenger', '1970-05-31', 'day'],
            'Maxonchamp' => ['closure', '1989-05-28', 'day'],
            'Barjols-Tavernes' => ['closure', '1950-01-02', 'day'],
            'Eauze' => ['closure_passenger', '1940-06-27', 'day'],
            'Montpellier - Chaptal' => ['closure_passenger', '1948-02-01', 'day'],
            'Orbe' => ['closure_passenger', '1970-05-31', 'day'],
            'Pas-de-Jeu' => ['closure_passenger', '1970-05-31', 'day'],
            'Basses-Sammarcolles' => ['closure_passenger', '1970-05-31', 'day'],
            'Beuxes' => ['closure_passenger', '1970-05-31', 'day'],
            'La Roche-Clermault' => ['closure_passenger', '1970-05-31', 'day'],
        ];
    }

    public function test_each_audited_closure_is_corrected_once_with_its_date(): void
    {
        $all = require database_path('data/gares_corrections.php');
        foreach (self::corrections() as $name => [$type, $date, $precision]) {
            $found = array_values(array_filter($all, fn ($c) => ($c['station']['name'] ?? null) === $name
                && ($c['action'] ?? null) === 'add' && str_starts_with((string) ($c['type'] ?? ''), 'closure')));
            $this->assertCount(1, $found, "{$name} : une seule correction de fermeture");
            $this->assertSame([$type, $date, $precision], [$found[0]['type'], $found[0]['date'], $found[0]['precision']], $name);
            $this->assertMatchesRegularExpression('/oldid=\d+/', (string) $found[0]['source_url'], "{$name} : permalien");
            $this->assertNotSame('', trim((string) $found[0]['quote']), "{$name} : citation");
        }
    }
}
