<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileOrientation;
use PHPUnit\Framework\TestCase;

/**
 * Bornes, seuils et arrondis de ProfileOrientation : tests ajoutés après une passe de mutation
 * (Infection, tests/Mutation/Profiles).
 */
class ProfileOrientationBoundariesTest extends TestCase
{
    public function test_two_points_with_distinct_pk_are_enough_to_read_the_direction(): void
    {
        $this->assertFalse(ProfileOrientation::shouldReverse([[0, 0], [100, 500]]));
        $this->assertTrue(ProfileOrientation::shouldReverse([[0, 500], [100, 0]]));
        $this->assertTrue(ProfileOrientation::shouldReverse([[0.5, 7.5], [100.25, 2.25]]));
    }

    public function test_the_sign_of_the_slope_decides_not_its_size(): void
    {
        // Parcours décalé loin de zéro : la pente reste lue sur les écarts à la moyenne.
        $this->assertFalse(ProfileOrientation::shouldReverse([[10_000, 90_000], [12_000, 91_000], [15_000, 95_000]]));
        $this->assertTrue(ProfileOrientation::shouldReverse([[10_000, 95_000], [12_000, 91_000], [15_000, 90_000]]));
    }

    public function test_a_flat_pk_series_has_no_direction(): void
    {
        $this->assertNull(ProfileOrientation::shouldReverse([[0, 100], [50, 300], [100, 300], [150, 100]]));
    }

    public function test_the_gap_between_the_two_readings_must_reach_a_quarter_of_a_match(): void
    {
        // Écart d'un quart exactement (un mot sur quatre) : le sens est lu, pas rendu incertain.
        $this->assertFalse(ProfileOrientation::reverseByName('Alpha Beta Gamma Delta - Zulu', 'Alpha Xray Yankee Whisky', 'Quebec'));
        $this->assertTrue(ProfileOrientation::reverseByName('Zulu - Alpha Beta Gamma Delta', 'Alpha Xray Yankee Whisky', 'Quebec'));
        // En dessous d'un quart (un mot sur cinq), rien n'est décidé.
        $this->assertNull(ProfileOrientation::reverseByName('Alpha Beta Gamma Delta Epsilon - Zulu', 'Alpha Xray Yankee Whisky Victor', 'Quebec'));
    }

    public function test_two_equal_readings_leave_the_direction_open(): void
    {
        $this->assertNull(ProfileOrientation::reverseByName('Vaux - Vaux', 'Vaux', 'Vaux'));
    }

    public function test_the_reading_is_a_share_of_words_not_a_count(): void
    {
        // Lecture directe : un nom entier sur la gare de départ (part 1) ; lecture inverse : deux
        // noms d'un mot trouvés (part 2). La part l'emporte sur le nombre de mots en commun.
        $this->assertTrue(ProfileOrientation::reverseByName('Aaa Bbb Ccc - Xxx', 'Aaa Bbb Ccc Xxx', 'Aaa'));
    }

    public function test_an_empty_station_name_reads_as_no_match_instead_of_failing(): void
    {
        $this->assertFalse(ProfileOrientation::reverseByName('Ligne de Foix à Tarascon', 'Foix', ''));
        $this->assertFalse(ProfileOrientation::reverseByName('Ligne de Foix à Tarascon', 'Foix', 'Gare'));
        $this->assertTrue(ProfileOrientation::reverseByName('Ligne de Foix à Tarascon', '', 'Foix'));
        $this->assertNull(ProfileOrientation::reverseByName('Ligne de Foix à Tarascon', '', ''));
    }

    public function test_articles_and_connectors_do_not_count_as_matching_words(): void
    {
        $this->assertNull(ProfileOrientation::reverseByName('Le Mans - La Roche', 'Le Havre', 'La Rochelle'));
        $this->assertNull(ProfileOrientation::reverseByName('Foo de du des la le les l d sur en gare - Bar', 'de du des la le les l d sur en gare', 'x'));
    }

    public function test_saint_and_sainte_are_shortened_to_their_own_abbreviation(): void
    {
        $this->assertFalse(ProfileOrientation::reverseByName('Saint - Zulu', 'St', 'Quebec'));
        $this->assertFalse(ProfileOrientation::reverseByName('Sainte - Zulu', 'Ste', 'Quebec'));
        $this->assertNull(ProfileOrientation::reverseByName('Saint - Zulu', 'Ste', 'Quebec'));
        $this->assertNull(ProfileOrientation::reverseByName('Sainte - Zulu', 'St', 'Quebec'));
    }

    public function test_a_repeated_word_counts_once(): void
    {
        $this->assertNull(ProfileOrientation::reverseByName('Vaux Vaux - Vaux Nord', 'Vaux Vaux', 'Vaux'));
    }

    public function test_upper_case_station_names_match_lower_case_ones(): void
    {
        $this->assertFalse(ProfileOrientation::reverseByName('Lyon - Zulu', 'LYON', 'QUEBEC'));
        $this->assertTrue(ProfileOrientation::reverseByName('Lyon - Zulu', 'ZULU', 'LYON'));
    }

    public function test_line_ends_are_not_guessed_when_the_name_has_several_dashes(): void
    {
        $this->assertNull(ProfileOrientation::lineEnds('Paris - Lyon - Marseille'));
        $this->assertSame(['Paris', 'Lyon'], ProfileOrientation::lineEnds('Paris – Lyon'));
    }

    public function test_line_ends_are_trimmed_and_need_both_names(): void
    {
        $this->assertSame(['Paris', 'Lyon'], ProfileOrientation::lineEnds("Paris - l' Lyon"));
        $this->assertNull(ProfileOrientation::lineEnds("Paris - l'"));
        $this->assertNull(ProfileOrientation::lineEnds('Paris'));
        $this->assertNull(ProfileOrientation::lineEnds(''));
    }

    public function test_line_ends_drop_the_line_prefix_and_its_article(): void
    {
        $this->assertSame(['Aix-les-Bains', 'Revard'], ProfileOrientation::lineEnds("Ligne d'Aix-les-Bains au Revard"));
        $this->assertSame(['Foix', 'Tarascon'], ProfileOrientation::lineEnds('Embranchement de Foix à Tarascon'));
        $this->assertSame(['Rodez', 'Millau'], ProfileOrientation::lineEnds('Chemin de fer du Rodez à Millau'));
        $this->assertSame(['Tours', 'Chinon'], ProfileOrientation::lineEnds('  ligne des Tours aux Chinon'));
    }
}
