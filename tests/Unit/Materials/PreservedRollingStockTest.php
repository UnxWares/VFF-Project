<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\PreservedRollingStock;
use PHPUnit\Framework\TestCase;

/** Matériel préservé : cohérence du fichier versionné et lecture par le service (sans base ni réseau). */
class PreservedRollingStockTest extends TestCase
{
    /** @return array{venues: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>, others: list<array<string, mixed>>} */
    private static function data(): array
    {
        /** @var array{venues: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>, others: list<array<string, mixed>>} $data */
        $data = require dirname(__DIR__, 3).'/database/data/preserved_rolling_stock.php';

        return $data;
    }

    public function test_every_unit_points_to_a_known_venue_with_a_valid_state_and_a_source(): void
    {
        $data = self::data();
        foreach ($data['units'] as $slug => $units) {
            $seen = [];
            foreach ($units as $unit) {
                $label = $slug.' '.$unit['number'];
                $this->assertNotSame('', trim((string) $unit['number']), $label);
                $this->assertArrayNotHasKey(preg_replace('/\W+/u', '', $unit['number']), $seen, "{$label} apparaît deux fois");
                $seen[preg_replace('/\W+/u', '', $unit['number'])] = true;
                if ($unit['venue'] !== null) {
                    $this->assertArrayHasKey($unit['venue'], $data['venues'], $label);
                }
                $this->assertContains($unit['state'], [null, ...PreservedRollingStock::STATES], $label);
                $this->assertNotEmpty($unit['src'], "{$label} sans source");
                foreach ($unit['src'] as $src) {
                    $this->assertArrayHasKey($src[0], PreservedRollingStock::SOURCES, $label);
                }
            }
        }
    }

    public function test_venues_have_a_known_type_and_are_used(): void
    {
        $data = self::data();
        $used = [];
        foreach ($data['units'] as $units) {
            foreach ($units as $unit) {
                if ($unit['venue'] !== null) {
                    $used[$unit['venue']] = true;
                }
            }
        }
        foreach ($data['others'] as $other) {
            $used[$other['venue']] = true;
        }
        foreach ($data['venues'] as $key => $venue) {
            $this->assertContains($venue['type'], PreservedRollingStock::VENUE_TYPES, $key);
            $this->assertNotSame('', trim((string) $venue['name']), $key);
            $this->assertArrayHasKey($key, $used, "{$key} n'est utilisé par aucune pièce");
        }
    }

    public function test_protection_dates_come_from_a_palissy_record_and_are_real_dates(): void
    {
        $data = self::data();
        $all = [...array_merge(...array_values($data['units'])), ...$data['others']];
        $checked = 0;
        foreach ($all as $unit) {
            $mh = $unit['mh'] ?? null;
            if ($mh === null) {
                continue;
            }
            $this->assertContains($mh['kind'], [null, 'classified', 'listed', 'declassified']);
            if ($mh['date'] !== null) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $mh['date']);
                $this->assertTrue(checkdate((int) substr($mh['date'], 5, 2), (int) substr($mh['date'], 8, 2), (int) substr($mh['date'], 0, 4)));
                $this->assertSame((int) substr($mh['date'], 0, 4), $mh['year']);
                $this->assertMatchesRegularExpression('/^P[AM]\d{8}$/', (string) $mh['ref'], 'une date de protection vient de Palissy');
                $checked++;
            }
        }
        $this->assertGreaterThan(100, $checked);
    }

    /** Dix exemplaires vérifiés à la main sur leurs sources (23/11/2026). */
    public function test_ten_hand_checked_units_are_there_with_their_facts(): void
    {
        $service = new PreservedRollingStock(null, self::data());
        $find = function (string $slug, string $number) use ($service): array {
            foreach ($service->forMaterial($slug) as $unit) {
                if ($unit['number'] === $number) {
                    return $unit;
                }
            }
            $this->fail("{$slug} {$number} absent");
        };

        $u = $find('141-r', '141 R 1199');
        $this->assertSame('PM35000721', $u['protection']['ref']);
        $this->assertSame('1984-12-06', $u['protection']['date']);
        $this->assertSame('restoration', $u['state']);
        $this->assertStringContainsString('AAATV', $u['venue']['name']);

        $u = $find('241-p-17', '241 P 17');
        $this->assertSame('1990-03-27', $u['protection']['date']);
        $this->assertStringContainsString('Creusot', $u['venue']['name']);

        $u = $find('231-g-entre-2-et-285', '231 G 558');
        $this->assertSame('classified', $u['protection']['kind']);
        $this->assertSame('1984-06-08', $u['protection']['date']);
        $this->assertSame('Sotteville-lès-Rouen', $u['venue']['commune']);

        $this->assertSame('Cité du train', $find('bb-9003-9004', 'BB 9004')['venue']['name']);
        $this->assertSame('Cité du train', $find('cc-7100', 'CC 7107')['venue']['name']);
        $u = $find('cc-40100', 'CC 40101');
        $this->assertSame('Cité du train', $u['venue']['name']);
        $this->assertSame('exposed', $u['state']);
        $this->assertStringContainsString('EHPT', $find('x-2800', 'X 2804')['venue']['name']);
        $this->assertSame('Cité du train', $find('z-4100', 'Z 4156')['venue']['name']);
        $u = $find('2d2-5500', '2D2 5525');
        $this->assertSame('1990-03-27', $u['protection']['date']);
        $this->assertSame('Bagneux', $u['venue']['commune']);
        $this->assertSame('Technicentre Sud-Est Européen', $find('tgv-sud-est', 'Rame 01')['venue']['name']);
    }

    public function test_a_tourist_railway_link_is_only_given_when_the_catalogue_page_knows_the_operation(): void
    {
        $without = new PreservedRollingStock(null, self::data());
        foreach ($without->forMaterial('241-p') as $unit) {
            $this->assertNull($unit['venue']['tourist_railway'] ?? null, 'sans la page des lignes touristiques, aucun lien');
        }
    }
}
