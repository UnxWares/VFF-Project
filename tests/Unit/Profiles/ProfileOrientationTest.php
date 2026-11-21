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

    public function test_a_station_name_with_a_dash_does_not_move_the_cut_of_a_to_form(): void
    {
        // « à » prime : la gare « Moutiers - Salins » reste entière côté B.
        $this->assertSame(['Bourg - Saint-Maurice', 'Moûtiers - Salins'], ProfileOrientation::lineEnds('Ligne de Bourg - Saint-Maurice à Moûtiers - Salins'));
        $this->assertFalse(ProfileOrientation::reverseByName('Ligne de Bourg - Saint-Maurice à Moûtiers - Salins', 'Bourg-Saint-Maurice', 'Moûtiers - Salins - Brides'));
        $this->assertTrue(ProfileOrientation::reverseByName('Ligne de Bourg - Saint-Maurice à Moûtiers - Salins', 'Moûtiers - Salins - Brides', 'Bourg-Saint-Maurice'));
    }

    public function test_a_dash_name_with_several_dashes_is_cut_only_where_both_pieces_are_stations(): void
    {
        $name = 'Ligne de Bourg-Saint-Maurice - Moûtiers - Salins';
        $this->assertNull(ProfileOrientation::lineEnds($name));
        $stations = ['Bourg-Saint-Maurice', 'Aime-la-Plagne', 'Moûtiers - Salins - Brides'];
        $this->assertFalse(ProfileOrientation::reverseByName($name, 'Bourg-Saint-Maurice', 'Moûtiers - Salins - Brides', $stations));
        $this->assertTrue(ProfileOrientation::reverseByName($name, 'Moûtiers - Salins - Brides', 'Bourg-Saint-Maurice', $stations));
    }

    public function test_a_dash_name_whose_pieces_are_not_stations_of_the_line_gives_nothing(): void
    {
        $this->assertNull(ProfileOrientation::reverseByName('X - Y', 'Paris', 'Lyon', ['Paris', 'Lyon']));
        $this->assertNull(ProfileOrientation::reverseByName('Alpha - Bravo - Charlie', 'Paris', 'Lyon'));
    }

    public function test_ambiguous_cuts_that_disagree_give_nothing_and_those_that_agree_give_the_direction(): void
    {
        $stations = ['Alpha', 'Alpha - Bravo', 'Charlie', 'Bravo - Charlie'];
        // Coupures « Alpha | Bravo - Charlie » et « Alpha - Bravo | Charlie » : même sens.
        $this->assertFalse(ProfileOrientation::reverseByName('Alpha - Bravo - Charlie', 'Alpha', 'Charlie', $stations));
        $this->assertTrue(ProfileOrientation::reverseByName('Alpha - Bravo - Charlie', 'Charlie', 'Alpha', $stations));
        // Bouts qui ne départagent pas : rien.
        $this->assertNull(ProfileOrientation::reverseByName('Alpha - Bravo - Charlie', 'Delta', 'Echo', $stations));
    }
}
