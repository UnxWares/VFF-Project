<?php

namespace Tests\Feature;

use App\Services\StationCorrections;
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

    /** @return list<array<string, mixed>> */
    private function section40bis(): array
    {
        return array_values(array_filter($this->corrections(), fn ($c) => str_contains((string) ($c['reason'] ?? ''), '') && (
            ($c['action'] === 'merge' && ! in_array($c['station']['name'], ['Pleine-Fougères', 'Condé-sur-Vire'], true))
            || ($c['action'] === 'kind' && ($c['kind'] ?? '') === 'excluded' && isset($c['quote']) && ($c['station']['osm'] ?? $c['station']['wikidata'] ?? '') !== '' && ! isset($c['station']['near']) && ($c['station']['wikidata'] ?? 'Q72573709') === 'Q72573709' && ! isset($c['station']['uic'])
                && in_array($c['station']['name'], ['Bistrot de la gare', 'Le Club', 'Cinéma de la Gare', 'Maison des associations', 'Ancienne halle aux marchandises de la gare de Trévoux', 'Virville-Manneville', 'Galerie Villette', 'Hall 3 (Belcier)', 'Gare centre cote clairmarais', 'Ancien bâtiment de la gare de Choisy-le-Roi'], true))
        )));
    }

    public function test_second_duplicates_review_writes_seventeen_merges_and_ten_exclusions(): void
    {
        $found = $this->section40bis();
        $this->assertCount(17, array_filter($found, fn ($c) => $c['action'] === 'merge'));
        $this->assertCount(10, array_filter($found, fn ($c) => $c['action'] === 'kind'));
    }

    public function test_second_duplicates_review_entries_are_valid_and_sourced(): void
    {
        foreach ($this->section40bis() as $c) {
            $this->assertSame([], StationCorrections::validate($c), $c['station']['name']);
            $this->assertNotSame('', trim((string) $c['reason']), $c['station']['name']);
            $this->assertMatchesRegularExpression('#^https://(fr\.wikipedia\.org/w/index\.php\?title=.+&oldid=\d+|www\.wikidata\.org/wiki/Q\d+|www\.openstreetmap\.org/(node|way)/\d+)$#', (string) $c['source_url'], $c['station']['name']);
        }
    }

    public function test_merges_name_two_different_stations_with_a_position_rule(): void
    {
        foreach (array_filter($this->section40bis(), fn ($c) => $c['action'] === 'merge') as $c) {
            $this->assertContains($c['position'], ['kept', 'absorbed'], $c['station']['name']);
            $this->assertNotSame($c['station'], $c['absorbed'], $c['station']['name']);
        }
    }
}
