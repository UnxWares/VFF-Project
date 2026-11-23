<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit des fermetures du 23/11/2026 : neuf gares fermées sur une ligne encore ouverte dont la date de fermeture venait d'un
 * événement de la ligne (déclassement, fin du fret, tracé) et non de la gare. Chacune a une correction sourcée, datée avant
 * la date déduite.
 */
class StationClosureAuditCorrectionsTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string}> nom → [type, date, précision] */
    public static function corrections(): array
    {
        return [
            'Dommartin-Lissieu' => ['closure', '2012-01-01', 'year'],
            'Bercy-Ceinture' => ['closure', '1942-10-05', 'day'],
            'Fresnoy-Morangles' => ['closure', '1935-12-18', 'day'],
            'Gavarry' => ['closure_passenger', '1940-05-24', 'day'],
            'Romanswiller' => ['closure', '1988-01-01', 'year'],
            'Gattières' => ['closure', '1944-01-01', 'year'],
            'Vence' => ['closure', '1944-01-01', 'year'],
            'Loup' => ['closure', '1944-01-01', 'year'],
            'Peymeinade' => ['closure', '1944-01-01', 'year'],
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
        }
    }
}
