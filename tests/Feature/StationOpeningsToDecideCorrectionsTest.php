<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Ouvertures « à décider » des audits de gares, tranchées le 23/11/2026 (section 48 de gares_corrections.php) : chaque gare reçoit une seule
 * correction d'ouverture, datée par la chronologie de la ligne (ou par l'article de la gare quand la gare est créée après la ligne), avec un
 * permalien et une citation. Une date de gare ne précède jamais la mise en service de sa ligne.
 */
class StationOpeningsToDecideCorrectionsTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> nom → [date, précision] */
    public static function corrections(): array
    {
        return [
            'Boissy-l\'Aillerie' => ['1868-10-04', 'day'],
            'Montgeroult-Courcelles' => ['1868-10-04', 'day'],
            'Santeuil-Le Perchay' => ['1868-10-04', 'day'],
            'Chars' => ['1868-10-04', 'day'],
            'Lavilletertre' => ['1868-10-04', 'day'],
            'Liancourt-St-Pierre' => ['1868-10-04', 'day'],
            'Chaumont-en-Vexin' => ['1868-10-04', 'day'],
            'Trie-Château' => ['1868-10-04', 'day'],
            'Le Theil-La Rouge' => ['1854-06-01', 'day'],
            'Champagné' => ['1854-06-01', 'day'],
            'Logelbach' => ['1868-12-03', 'day'],
            'Breitenbach' => ['1893-11-01', 'day'],
            'Muhlbach-sur-Munster' => ['1893-11-01', 'day'],
            'Rang-du-Fliers-Verton' => ['1847-12-21', 'day'],
            'Cap-d\'Ail' => ['1881-01-01', 'year'],
            'Èze' => ['1868-10-09', 'day'],
            'Pontarlier' => ['1860-07-24', 'day'],
            'Avesnes' => ['1869-10-30', 'day'],
            'Alès' => ['1840-08-19', 'day'],
            'Grand\'Combe-La Pise' => ['1841-01-01', 'month'],
            'Longueil-Ste-Marie' => ['1847-10-20', 'day'],
        ];
    }

    public function test_each_opening_is_corrected_once_with_its_date(): void
    {
        $all = require database_path('data/gares_corrections.php');
        foreach (self::corrections() as $name => [$date, $precision]) {
            $found = array_values(array_filter($all, fn ($c) => ($c['station']['name'] ?? null) === $name
                && ($c['action'] ?? null) === 'add' && ($c['type'] ?? null) === 'opening'));
            $this->assertCount(1, $found, "{$name} : une seule correction d'ouverture");
            $this->assertSame([$date, $precision], [$found[0]['date'], $found[0]['precision']], $name);
            $this->assertMatchesRegularExpression('/oldid=\d+/', (string) $found[0]['source_url'], "{$name} : permalien");
            $this->assertNotSame('', trim((string) $found[0]['quote']), "{$name} : citation");
            $this->assertNotEmpty($found[0]['station']['uic'] ?? null, "{$name} : identifiant UIC");
        }
    }
}
