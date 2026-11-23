<?php

namespace Tests\Unit\Heritage;

use App\Services\Heritage\StructureHeritageMatcher;
use PHPUnit\Framework\TestCase;

/** Rapprochement des notices Mérimée d'ouvrages d'art et des ouvrages de VFF : points, seuil et garde-fous. */
class StructureHeritageMatcherTest extends TestCase
{
    /** @return list<object> */
    private static function structures(): array
    {
        $s = fn (int $id, string $kind, ?string $name, float $lat, float $lon, ?int $length, bool $onLine, ?string $ref = null): object => (object) [
            'id' => $id, 'kind' => $kind, 'name' => $name, 'lat' => $lat, 'lon' => $lon, 'length_m' => $length, 'on_line' => $onLine, 'merimee_ref' => $ref, 'wikidata_id' => null];

        return [
            $s(1, 'viaduct', 'viaduc de Garabit', 44.9746, 3.1775, 564, true),
            $s(2, 'viaduct', 'viaduc de Barajol', 45.3035, 2.6939, 311, true, 'PA00093595'),
            $s(3, 'viaduct', 'viaduc de Barajol', 45.3040, 2.6944, null, true),
            $s(4, 'tunnel', 'tunnel ferroviaire du Fréjus', 45.1680, 6.6660, 13700, true),
            $s(5, 'bridge', 'pont de Noblat', 45.8350, 1.4900, 80, true),
            $s(6, 'bridge', 'pont de la Dore', 45.0, 3.0, 50, false),
        ];
    }

    /** @return array<string, mixed> */
    private static function record(string $ref, string $kind, string $title, float $lat, float $lng, array $extra = []): array
    {
        return $extra + ['ref' => $ref, 'kind' => $kind, 'title' => $title, 'denomination' => '', 'commune' => 'X', 'department' => '15', 'lat' => $lat, 'lng' => $lng,
            'protection' => 'registered', 'protection_date' => '1984-12-28', 'scope' => null, 'designations' => [['kind' => 'registered', 'date' => '1984-12-28']],
            'architects' => [['name' => 'Gustave Eiffel', 'role' => 'ingénieur']], 'built_from' => 1880, 'built_to' => null, 'built_period' => null, 'rail_text' => false];
    }

    public function test_a_viaduct_with_the_same_name_on_the_same_spot_is_attached_with_its_points_explained(): void
    {
        $result = (new StructureHeritageMatcher(self::structures()))->build([self::record('PA00093592', 'viaduct', 'Viaduc de Garabit (également sur commune de Ruynes-en-Margeride)', 44.9750, 3.1770)], '2026-11-19');

        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];
        $this->assertSame([1, 'PA00093592', 'registered'], [$row['structure_id'], $row['ref'], $row['protection']]);
        $this->assertSame('Gustave Eiffel', $row['architects'][0]['name']);
        $this->assertSame(105, $row['match_score']);
        $this->assertSame(['même genre +15', 'nom +45', 'écart 0 m +30', 'porté par une ligne +15'], $result['scored'][0]['reasons']);
    }

    public function test_the_reference_already_carried_by_a_structure_is_a_sure_match(): void
    {
        $result = (new StructureHeritageMatcher(self::structures()))->build([self::record('PA00093595', 'viaduct', 'Viaduc', 10.0, 10.0)]);

        $this->assertSame(2, $result['rows'][0]['structure_id']);
        $this->assertSame(100, $result['rows'][0]['match_score']);
    }

    public function test_a_spelling_of_the_name_with_a_plural_still_matches_and_two_pieces_of_the_same_viaduct_are_not_ambiguous(): void
    {
        $result = (new StructureHeritageMatcher(self::structures()))->build([self::record('PA00093587', 'viaduct', 'Viaduc de Barajols', 45.3036, 2.6940)]);

        $this->assertSame(2, $result['rows'][0]['structure_id']);
        $this->assertSame([], $result['unmatched']);
    }

    public function test_a_tunnel_is_never_matched_with_a_bridge_and_a_long_tunnel_is_matched_by_its_length(): void
    {
        $matcher = new StructureHeritageMatcher(self::structures());
        $tunnel = $matcher->match(self::record('PA1', 'tunnel', 'Tunnel du Mont-Fréjus', 45.19, 6.66));

        $this->assertSame(4, $tunnel['structure']->id);
        $this->assertNotNull($tunnel['score']);
        $this->assertNull($matcher->match(self::record('PA2', 'tunnel', 'Tunnel de Garabit', 44.9746, 3.1775)), 'un tunnel loin de tout tunnel, notice sans chemin de fer : rien');
    }

    public function test_a_road_bridge_without_any_railway_trace_is_ignored_and_a_railway_text_makes_it_reportable(): void
    {
        $matcher = new StructureHeritageMatcher(self::structures());

        $this->assertNull($matcher->match(self::record('PA3', 'bridge', 'Pont de la Dore', 45.0, 3.0)), 'ouvrage non porté par une ligne, notice sans chemin de fer');
        $this->assertSame(['why' => 'no-structure'], $matcher->match(self::record('PA4', 'bridge', 'Pont du Mars', 10.0, 10.0, ['rail_text' => true])));
    }

    public function test_an_old_stone_bridge_is_not_a_railway_bridge_even_with_a_shared_name(): void
    {
        $matcher = new StructureHeritageMatcher(self::structures());
        $old = self::record('PA5', 'bridge', 'Pont de Noblat, dit aussi le Vieux Pont', 45.8351, 1.4901, ['built_from' => null, 'built_period' => '15e siècle', 'rail_text' => true]);
        $recent = self::record('PA6', 'bridge', 'Pont de Noblat', 45.8351, 1.4901, ['built_from' => 1860]);

        $this->assertTrue(StructureHeritageMatcher::predatesRailways($old));
        $this->assertNull($matcher->match($old));
        $this->assertSame(5, $matcher->match($recent)['structure']->id);
        $this->assertFalse(StructureHeritageMatcher::predatesRailways(self::record('PA7', 'viaduct', 'Viaduc du Vieux Chemin', 0.0, 0.0, ['built_from' => 1790])));
    }

    public function test_two_different_names_lose_points_and_stay_below_the_threshold(): void
    {
        $found = (new StructureHeritageMatcher(self::structures()))->match(self::record('PA8', 'viaduct', 'Viaduc des Fades', 44.9746, 3.1775));

        $this->assertSame(['why' => 'low-score'], $found);
    }

    public function test_core_words_drop_generic_words_and_the_parenthesis(): void
    {
        $this->assertSame(['garabit'], StructureHeritageMatcher::core('Viaduc de Garabit (également sur commune de Ruynes-en-Margeride)'));
        $this->assertSame(['noblat'], StructureHeritageMatcher::core('Pont de Noblat, dit aussi le Vieux Pont, sur la Vienne'));
        $this->assertSame(45, StructureHeritageMatcher::nameScore(['barajols'], 'viaduc de Barajol'));
        $this->assertSame(35, StructureHeritageMatcher::nameScore(['busseau', 'creuse'], 'viaduc de Busseau'));
        $this->assertSame(-30, StructureHeritageMatcher::nameScore(['fades'], 'viaduc de Garabit'));
        $this->assertSame(0, StructureHeritageMatcher::nameScore(['fades'], ''));
    }
}
