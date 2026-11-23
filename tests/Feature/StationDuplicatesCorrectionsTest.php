<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Doublons de fiches de gares du 23/11/2026 : trois fiches écartées du réseau (enregistrement SNCF désaffecté, bâtiment cadastral
 * pris pour une gare, second élément Wikidata de la même gare), fermeture voyageurs de Saint-Samson et lien faux avec la ligne de Lison
 * à Lamballe.
 */
class StationDuplicatesCorrectionsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function corrections(): array
    {
        return require database_path('data/gares_corrections.php');
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> libellé → [nom, clé, valeur, source] */
    public static function excludedDuplicates(): array
    {
        return [
            'Monteux-les-Escampades' => ['Monteux-les-Escampades', 'uic', '87025098', 'oldid=241491038'],
            'Aniche Gare' => ['Aniche Gare', 'osm', 'w96473701', 'oldid=235030291'],
            'Limours-État' => ['Limours-État', 'wikidata', 'Q106409717', 'oldid=204050069'],
        ];
    }

    #[DataProvider('excludedDuplicates')]
    public function test_each_duplicate_is_excluded_with_a_sourced_reason(string $name, string $key, string $value, string $oldid): void
    {
        $found = array_values(array_filter($this->corrections(), fn ($c) => ($c['station']['name'] ?? null) === $name
            && ($c['station'][$key] ?? null) === $value));
        $this->assertCount(1, $found, $name);
        $this->assertSame(['kind', 'excluded'], [$found[0]['action'], $found[0]['kind']], $name);
        $this->assertStringContainsString($oldid, (string) $found[0]['source_url'], $name);
        $this->assertNotSame('', trim((string) $found[0]['reason']), $name);
    }

    public function test_saint_samson_closes_to_passengers_on_27_september_1986(): void
    {
        $found = array_values(array_filter($this->corrections(), fn ($c) => ($c['station']['name'] ?? null) === 'Saint-Samson'));
        $this->assertCount(1, $found);
        $this->assertSame(['add', 'closure_passenger', '1986-09-27', 'day'], [$found[0]['action'], $found[0]['type'], $found[0]['date'], $found[0]['precision']]);
        $this->assertStringContainsString('oldid=238645019', (string) $found[0]['source_url']);
    }

    public function test_saint_samson_is_unlinked_from_the_lison_lamballe_line(): void
    {
        $found = array_values(array_filter(require database_path('data/station_line_exclusions.php'),
            fn ($e) => $e['station'] === 'saint-samson'));
        $this->assertCount(1, $found);
        $this->assertSame('rfn-415000', $found[0]['line']);
        $this->assertStringContainsString('oldid=238645019', $found[0]['source_url']);
    }
}
