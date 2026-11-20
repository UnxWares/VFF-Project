<?php

namespace Tests\Unit;

use App\Services\LineChronicle;
use PHPUnit\Framework\TestCase;

/**
 * Chronique d'une ligne (App\Services\LineChronicle::build, calcul pur) : portions,
 * notes de source, statut de chaque événement, accords et désaccords entre sources.
 */
class LineChronicleTest extends TestCase
{
    private static function ev(int $id, string $type, string $date, array $extra = []): array
    {
        return $extra + ['id' => $id, 'type' => $type, 'date' => $date, 'precision' => strlen($date) === 4 ? 'year' : 'day',
            'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne&oldid=1',
            'scope' => 'localized', 'section_from' => null, 'section_to' => null, 'section_length_m' => null];
    }

    public function test_place_keys_ignore_case_accents_order_and_saint(): void
    {
        $this->assertSame('saint florentin vergigny', LineChronicle::placeKey('St-Florentin-Vergigny'));
        $this->assertSame(LineChronicle::placeKey('Bagnoles-de-l’Orne'), LineChronicle::placeKey('bagnoles de l orne'));
        $this->assertNull(LineChronicle::placeKey('  '));
        $a = LineChronicle::portionKey(['scope' => 'localized', 'section_from' => 'Couterne', 'section_to' => "Bagnoles-de-l'Orne"]);
        $b = LineChronicle::portionKey(['scope' => 'localized', 'section_from' => "Bagnoles-de-l'Orne", 'section_to' => 'Couterne']);
        $this->assertSame($a, $b);
        $this->assertSame('whole', LineChronicle::portionKey(['scope' => 'whole', 'section_from' => null, 'section_to' => null]));
        $this->assertSame('traced', LineChronicle::portionKey(['scope' => 'localized', 'section_from' => null, 'section_to' => null]));
        $this->assertSame('unspecified', LineChronicle::portionKey(['scope' => 'unlocated', 'section_from' => null, 'section_to' => null]));
    }

    public function test_statuses_notes_and_closure_order_conflict(): void
    {
        // 434000 : « fermée en 1940 » (Wikipédia, texte) contre « fermée aux voyageurs le
        // 17/02/1941 » et « au fret en 1954 » (Wikipédia, liste), même portion.
        $p = ['section_from' => "Bagnoles-de-l'Orne", 'section_to' => 'Couterne'];
        $r = LineChronicle::build([
            self::ev(1, 'opening', '1869-12-06', ['source' => 'manual', 'source_url' => 'https://example.org/a']),
            self::ev(2, 'closure', '1940', $p),
            self::ev(3, 'closure_passenger', '1941-02-17', ['section_from' => 'Couterne', 'section_to' => "Bagnoles-de-l'Orne"]),
            self::ev(4, 'closure_freight', '1954', $p),
            self::ev(5, 'concession', '1866', ['scope' => 'whole']),
            self::ev(6, 'opening', '1964', ['scope' => 'ignored']),
            self::ev(7, 'closure', '1960', ['scope' => 'unlocated', 'section_from' => 'Ailleurs']),
        ], [1, 2]);

        $byId = array_column($r['events'], null, 'id');
        $this->assertSame('retained', $byId[1]['status']);
        $this->assertSame('retained', $byId[2]['status']);
        $this->assertSame('conflict', $byId[3]['status']);
        $this->assertSame('conflict', $byId[4]['status']);
        $this->assertSame('context', $byId[5]['status']);
        $this->assertSame('ignored', $byId[6]['status']);
        $this->assertSame('unlocated', $byId[7]['status']);
        $this->assertEqualsCanonicalizing([3, 4], $byId[2]['conflicts_with']);
        $this->assertContains('closure_order', array_column($r['conflicts'], 'family'));
        // Notes : une par source + lien, numérotées dans l'ordre d'apparition.
        $this->assertSame([1, 2], array_column($r['notes'], 'n'));
        $this->assertSame(1, $byId[1]['note']);
        $this->assertSame(2, $byId[2]['note']);
        $this->assertSame(2, $byId[3]['note']);
        // Portions : toute la ligne d'abord, le non localisé à la fin.
        $keys = array_column($r['groups'], 'key');
        $this->assertSame('whole', $keys[0]);
        $this->assertContains('bagnoles de l orne|couterne', $keys);
    }

    public function test_same_fact_same_date_agrees_and_different_date_conflicts(): void
    {
        $r = LineChronicle::build([
            self::ev(1, 'opening', '1981-09-27', ['scope' => 'whole']),
            self::ev(2, 'opening', '1981', ['scope' => 'whole', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q585219']),
            self::ev(3, 'opening', '2024-11-12', ['scope' => 'whole']),
        ], [1]);
        $byId = array_column($r['events'], null, 'id');
        $this->assertSame([2], $byId[1]['agrees_with']);
        $this->assertSame([3], $byId[1]['conflicts_with']);
        $this->assertSame('agrees', $byId[2]['status']);
        $this->assertSame('conflict', $byId[3]['status'], 'ouverture « 2024 » de la LGV Sud-Est : signalée, pas tranchée en silence');
    }

    public function test_ambiguous_portions_are_never_compared(): void
    {
        // « près de St-Florentin » : une portion qui y finit (1983) et une qui en part (1981).
        $r = LineChronicle::build([
            self::ev(1, 'opening', '1981-09-27', ['source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/1', 'section_from' => 'St-Florentin-Vergigny']),
            self::ev(2, 'opening', '1983-09-25', ['source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/2', 'section_to' => 'St-Florentin-Vergigny']),
            self::ev(3, 'opening', '1983-09-25', ['source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/3']),
            self::ev(4, 'opening', '1981-09-27', ['source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/4']),
        ], [1, 2, 3, 4]);
        foreach ($r['events'] as $e) {
            $this->assertSame([], $e['conflicts_with'], "événement {$e['id']}");
        }
        $this->assertSame([], $r['conflicts']);
        // Objets OpenHistoricalMap : une seule note, avec tous les liens.
        $this->assertCount(1, $r['notes']);
        $this->assertCount(4, $r['notes'][0]['urls']);
    }

    public function test_same_date_at_common_precision(): void
    {
        $this->assertTrue(LineChronicle::sameDate(['date' => '1981-09-27', 'precision' => 'day'], ['date' => '1981-01-01', 'precision' => 'year']));
        $this->assertTrue(LineChronicle::sameDate(['date' => '1981-09-27', 'precision' => 'day'], ['date' => '1981-09-01', 'precision' => 'month']));
        $this->assertFalse(LineChronicle::sameDate(['date' => '1981-09-27', 'precision' => 'day'], ['date' => '1981-09-22', 'precision' => 'day']));
    }

    public function test_dashes_follow_the_style_guide(): void
    {
        $this->assertSame('Paris - Lyon', LineChronicle::dashes('Paris — Lyon'));
        $this->assertSame('Paris - Lyon', LineChronicle::dashes('Paris – Lyon'));
        $this->assertSame('a-b', LineChronicle::dashes('a--b'));
        $this->assertSame('Saint-Étienne', LineChronicle::dashes('Saint-Étienne'));
    }

    public function test_families(): void
    {
        $this->assertSame('opening', LineChronicle::family('opening_passenger'));
        $this->assertSame('reopening', LineChronicle::family('reopening_freight'));
        $this->assertSame('declassification', LineChronicle::family('declassification'));
        $this->assertSame('dismantling', LineChronicle::family('conversion'));
        $this->assertSame('other', LineChronicle::family('accident'));
    }
}
