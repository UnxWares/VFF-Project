<?php

namespace Tests\Unit\Structures;

use App\Services\Structures\StructureGeometry;
use App\Services\Structures\TrackChains;
use PHPUnit\Framework\TestCase;

/**
 * Ouvrages reconstitués depuis les tronçons de la BD TOPO : doublons des tuiles, chaînes, bifurcation,
 * voies parallèles, positions mêlées.
 */
class TrackChainsTest extends TestCase
{
    /** Point à `m` mètres à l'est de (3 E, 45 N), décalé de `n` mètres au nord. */
    private static function p(float $m, float $n = 0.0): array
    {
        return [3.0 + $m / (111195.0 * cos(deg2rad(45))), 45.0 + $n / 111195.0];
    }

    public function test_touching_tracks_of_the_same_position_form_one_structure_and_tile_duplicates_count_once(): void
    {
        $a = ['kind' => 'tunnel', 'coords' => [self::p(0), self::p(300)]];
        $b = ['kind' => 'tunnel', 'coords' => [self::p(300), self::p(700)]];

        $chains = TrackChains::build([$a, $b, $a, ['kind' => 'ground', 'coords' => [self::p(700), self::p(900)]]]);

        $this->assertCount(1, $chains);
        $this->assertSame('tunnel', $chains[0]['kind']);
        $this->assertEqualsWithDelta(700, $chains[0]['length_m'], 1);
        $this->assertCount(3, $chains[0]['coords'], 'les deux tronçons assemblés en une polyligne');
    }

    public function test_a_tunnel_and_a_bridge_that_touch_stay_two_structures(): void
    {
        $chains = TrackChains::build([
            ['kind' => 'tunnel', 'coords' => [self::p(0), self::p(300)]],
            ['kind' => 'bridge', 'coords' => [self::p(300), self::p(500)]],
        ]);

        $this->assertEqualsCanonicalizing(['tunnel', 'bridge'], array_column($chains, 'kind'));
    }

    public function test_a_junction_inside_a_tunnel_keeps_the_longest_path(): void
    {
        $chains = TrackChains::build([
            ['kind' => 'tunnel', 'coords' => [self::p(0), self::p(500)]],
            ['kind' => 'tunnel', 'coords' => [self::p(500), self::p(1500)]],
            ['kind' => 'tunnel', 'coords' => [self::p(500), self::p(700, 150)]],
        ]);

        $this->assertCount(1, $chains);
        $this->assertEqualsWithDelta(1500, $chains[0]['length_m'], 1, 'la branche courte ne s\'ajoute pas à la longueur');
    }

    public function test_parallel_decks_are_one_structure_and_the_longest_is_kept(): void
    {
        $chains = TrackChains::build([
            ['kind' => 'bridge', 'coords' => [self::p(0), self::p(400)]],
            ['kind' => 'bridge', 'coords' => [self::p(10, 12), self::p(380, 12)]],
            ['kind' => 'bridge', 'coords' => [self::p(0, 500), self::p(400, 500)]],
        ]);

        $this->assertCount(2, $chains, 'le tablier voisin à 12 m est absorbé, celui à 500 m reste');
        $this->assertEqualsWithDelta(400, $chains[0]['length_m'], 1);
    }

    public function test_geometry_helpers(): void
    {
        $line = [self::p(0), self::p(1000)];
        $this->assertEqualsWithDelta(1000, StructureGeometry::length($line), 0.5);
        $this->assertEqualsWithDelta(500, StructureGeometry::metres(self::p(0), StructureGeometry::midpoint($line)), 0.5);
        $proj = StructureGeometry::project(self::p(250, 40), $line);
        $this->assertSame(0, $proj['segment']);
        $this->assertEqualsWithDelta(0.25, $proj['fraction'], 0.001);
        $this->assertEqualsWithDelta(40, $proj['distance_m'], 0.5);
        $this->assertEqualsWithDelta(100, StructureGeometry::distanceToLine(self::p(1100), $line), 0.5, 'au-delà du bout : distance au bout');
        $this->assertNull(StructureGeometry::project(self::p(0), [self::p(0)]));
        $this->assertStringStartsWith('LINESTRING(3.0000000 45.0000000,', StructureGeometry::wkt($line));
    }
}
