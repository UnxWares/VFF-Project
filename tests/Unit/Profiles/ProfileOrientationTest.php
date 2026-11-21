<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileOrientation;
use PHPUnit\Framework\TestCase;

class ProfileOrientationTest extends TestCase
{
    public function test_a_parcours_whose_pk_grow_with_the_distance_is_kept(): void
    {
        $this->assertFalse(ProfileOrientation::shouldReverse([[0, 0], [4000, 4200], [10_000, 10_100]]));
    }

    public function test_a_parcours_whose_pk_shrink_with_the_distance_is_reversed(): void
    {
        $this->assertTrue(ProfileOrientation::shouldReverse([[0, 10_100], [6000, 4200], [10_000, 0]]));
    }

    public function test_rack_line_drawn_from_the_top_is_reversed(): void
    {
        // Chamonix - Montenvers : gares de Montenvers (distance 20 m, PK 5261) à Chamonix (5149 m, PK 99).
        $this->assertTrue(ProfileOrientation::shouldReverse([[20, 5261], [1543, 3677], [3215, 2044], [5055, 213], [5149, 99]]));
    }

    public function test_one_misplaced_station_does_not_flip_the_decision(): void
    {
        $this->assertFalse(ProfileOrientation::shouldReverse([[0, 0], [2000, 2000], [4000, 9000], [6000, 6000], [8000, 8000]]));
    }

    public function test_without_two_distinct_pk_the_direction_is_left_to_the_caller(): void
    {
        $this->assertNull(ProfileOrientation::shouldReverse([]));
        $this->assertNull(ProfileOrientation::shouldReverse([[100, 5000]]));
        $this->assertNull(ProfileOrientation::shouldReverse([[100, 5000], [900, 5000]]));
        $this->assertNull(ProfileOrientation::shouldReverse([[100, 5000], [100, 6000]]));
    }

    public function test_line_name_gives_the_direction(): void
    {
        // Aix-les-Bains - Revard : la ligne monte d'Aix, parcours enregistré depuis le Revard.
        $this->assertTrue(ProfileOrientation::reverseByName("Ligne d'Aix-les-Bains au Revard", 'Le Revard', 'Aix-les-Bains-le-Revard'));
        $this->assertFalse(ProfileOrientation::reverseByName("Ligne d'Aix-les-Bains au Revard", 'Aix-les-Bains', 'Le Revard'));
        $this->assertTrue(ProfileOrientation::reverseByName('Ligne de Saint-Michel-de-Maurienne à Mont-Cenis', 'Gran Croce', 'St-Michel-Valloire'));
        $this->assertFalse(ProfileOrientation::reverseByName('Ligne de Chamonix à Montenvers', 'Chamonix-Mont-Blanc', 'Montenvers - Mer de Glace'));
        $this->assertFalse(ProfileOrientation::reverseByName('Ligne de Saint-Agrève au Cheylard', 'Saint-Agrève', 'Le Cheylard'));
        $this->assertTrue(ProfileOrientation::reverseByName('Ligne de La Voulte-sur-Rhône au Cheylard', 'Le Cheylard', 'La Voulte-sur-Rhône'));
        $this->assertFalse(ProfileOrientation::reverseByName('Paris - Lyon', 'Paris-Gare-de-Lyon', 'Lyon-Perrache'));
    }

    public function test_name_without_two_ends_or_matching_stations_gives_nothing(): void
    {
        $this->assertNull(ProfileOrientation::reverseByName('Ligne de Paris-Nord', 'Paris', 'Lyon'));
        $this->assertNull(ProfileOrientation::reverseByName('Ligne de Foo à Bar', 'Paris', 'Lyon'));
        $this->assertSame(['Saint-Agrève', 'Cheylard'], ProfileOrientation::lineEnds('Ligne de Saint-Agrève au Cheylard'));
    }
}
