<?php

namespace Tests\Unit\Sncf;

use App\Services\Sncf\StationAttendanceDatasets;
use App\Services\Sncf\StationAttendanceMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cas limites du rattachement des gares du jeu « Fréquentation en gares » : codes postaux, Corse, doublons de fiches,
 * statuts, alias, noms vides ou non latins, colonnes des jeux.
 */
class StationAttendanceMatcherBoundaryTest extends TestCase
{
    private static function station(int $id, string $slug, string $name, ?string $uic, string $status = 'active', ?string $dep = '06'): object
    {
        return (object) ['id' => $id, 'slug' => $slug, 'name' => $name, 'uic_code' => $uic, 'status' => $status, 'department_code' => $dep];
    }

    /** @return iterable<string, array{?string, ?string}> */
    public static function postalCodes(): iterable
    {
        yield 'null' => [null, null];
        yield 'vide' => ['', null];
        yield 'espaces' => ['   ', null];
        yield '3 chiffres' => ['123', null];
        yield '4 chiffres : zéro de tête' => ['1234', '01'];
        yield '4 chiffres, 6088' => ['6088', '06'];
        yield '5 chiffres' => ['75015', '75'];
        yield '6 chiffres' => ['750150', null];
        yield 'espaces autour' => [' 75015 ', '75'];
        yield 'espace au milieu' => ['75 015', null];
        yield 'lettre' => ['7501A', null];
        yield 'Corse 20000' => ['20000', '20'];
        yield 'Corse 20999' => ['20999', '20'];
        yield 'outre-mer 971' => ['97100', '971'];
        yield 'outre-mer 976' => ['97600', '976'];
        yield 'Monaco 98000 : deux chiffres' => ['98000', '98'];
        yield '4 chiffres commençant par 97 : le zéro vient devant, ce n\'est pas l\'outre-mer' => ['9712', '09'];
        yield 'chiffres pleine chasse' => ["\u{FF10}\u{FF16}088", null];
        yield 'retour à la ligne final' => ["06088\n", '06'];
        yield 'zéro seul' => ['0000', '00'];
    }

    #[DataProvider('postalCodes')]
    public function test_department_of_a_postal_code(?string $postal, ?string $department): void
    {
        $this->assertSame($department, StationAttendanceMatcher::department($postal));
    }

    public function test_normalize_ignores_accents_case_dashes_apostrophes_spaces_and_ligatures(): void
    {
        $this->assertSame('saintetienne', StationAttendanceMatcher::normalize('Saint-Étienne'));
        $this->assertSame('saintetienne', StationAttendanceMatcher::normalize("  SAINT\u{00A0}ÉTIENNE  "));
        $this->assertSame('coeurdalene', StationAttendanceMatcher::normalize("Cœur-d'Alène"));
        $this->assertSame('coeurdalene', StationAttendanceMatcher::normalize('COEUR D’ALENE'));
        $this->assertSame('strasse', StationAttendanceMatcher::normalize('Straße'));
        $this->assertSame('paris13', StationAttendanceMatcher::normalize('Paris 13'));
        $this->assertSame('', StationAttendanceMatcher::normalize(''));
        $this->assertSame('', StationAttendanceMatcher::normalize(' - . '));
    }

    public function test_a_very_long_name_is_normalized_without_loss(): void
    {
        $name = str_repeat('Châteauneuf-sur-Loire ', 2000);

        $this->assertSame(str_repeat('chateauneufsurloire', 2000), StationAttendanceMatcher::normalize($name));
    }

    public function test_an_empty_or_symbol_only_name_never_matches_by_name(): void
    {
        $matcher = new StationAttendanceMatcher([self::station(1, 'sans-nom', '', null), self::station(2, 'tokyo', '東京', null)]);

        $this->assertSame('unmatched', $matcher->match('87000000', '', '06000')['method']);
        $this->assertSame('unmatched', $matcher->match('87000000', '???', '06000')['method']);
        $this->assertSame('unmatched', $matcher->match('87000000', '大阪', '06000')['method']);
    }

    public function test_a_station_without_uic_or_with_an_empty_uic_is_not_indexed_by_code(): void
    {
        $matcher = new StationAttendanceMatcher([self::station(1, 'a', 'Alpha', ''), self::station(2, 'b', 'Beta', null)]);

        $this->assertSame('unmatched', $matcher->match('', 'Gamma', '06000')['method']);
        $this->assertNull($matcher->match('', 'Gamma', '06000')['station']);
    }

    public function test_the_uic_rule_ignores_the_department_when_one_station_carries_the_code(): void
    {
        $matcher = new StationAttendanceMatcher([self::station(1, 'a', 'Alpha', '87000001', 'declassified', '13')]);

        $found = $matcher->match('87000001', 'Autre nom', '75001');

        $this->assertSame('uic', $found['method']);
        $this->assertSame('a', $found['station']->slug);
    }

    public function test_the_uic_code_is_compared_as_a_string_without_trimming(): void
    {
        $matcher = new StationAttendanceMatcher([self::station(1, 'a', 'Alpha', '87000001')]);

        $this->assertSame('unmatched', $matcher->match(' 87000001', 'Zzz', null)['method']);
        $this->assertSame('unmatched', $matcher->match('087000001', 'Zzz', null)['method']);
    }

    public function test_duplicates_in_two_departments_none_matching_fall_back_to_the_status_of_all(): void
    {
        $matcher = new StationAttendanceMatcher([
            self::station(1, 'a-06', 'Alpha', '87000001', 'active', '06'),
            self::station(2, 'a-13', 'Alpha', '87000001', 'declassified', '13'),
        ]);

        $found = $matcher->match('87000001', 'Alpha', '75001');

        $this->assertSame('uic-status', $found['method']);
        $this->assertSame('a-06', $found['station']->slug);
    }

    public function test_two_duplicates_in_the_department_are_split_by_status(): void
    {
        $matcher = new StationAttendanceMatcher([
            self::station(1, 'a-1', 'Alpha', '87000001', 'declassified', '06'),
            self::station(2, 'a-2', 'Alpha', '87000001', 'tourist', '06'),
            self::station(3, 'a-3', 'Alpha', '87000001', 'active', '13'),
        ]);

        $found = $matcher->match('87000001', 'Alpha', '06000');

        $this->assertSame('uic-status', $found['method']);
        $this->assertSame('a-2', $found['station']->slug);
    }

    public function test_duplicates_without_any_known_department_are_split_by_status_not_by_a_null_department(): void
    {
        $matcher = new StationAttendanceMatcher([
            self::station(1, 'a-1', 'Alpha', '87000001', 'declassified', null),
            self::station(2, 'a-2', 'Alpha', '87000001', 'active', null),
        ]);

        $found = $matcher->match('87000001', 'Alpha', null);

        $this->assertSame('uic-status', $found['method']);
        $this->assertSame('a-2', $found['station']->slug);
        // Un département inconnu d'un côté ne rapproche pas deux inconnus : il ne départage rien.
        $mixed = new StationAttendanceMatcher([
            self::station(1, 'm-1', 'Mu', '87000009', 'declassified', null),
            self::station(2, 'm-2', 'Mu', '87000009', 'active', '06'),
        ]);
        $this->assertSame('uic-status', $mixed->match('87000009', 'Mu', null)['method']);
        $this->assertSame('m-2', $mixed->match('87000009', 'Mu', null)['station']->slug);
        $this->assertSame('unmatched', (new StationAttendanceMatcher([self::station(1, 'n', 'Nom', null, 'active', null)]))->match('87000000', 'Nom', null)['method']);
    }

    public function test_two_living_duplicates_or_none_are_ambiguous(): void
    {
        $two = new StationAttendanceMatcher([
            self::station(1, 'a-1', 'Alpha', '87000001', 'active', '06'),
            self::station(2, 'a-2', 'Alpha', '87000001', 'no_commercial', '06'),
        ]);
        $none = new StationAttendanceMatcher([
            self::station(1, 'a-1', 'Alpha', '87000001', 'declassified', '06'),
            self::station(2, 'a-2', 'Alpha', '87000001', 'declassified', '06'),
        ]);

        $this->assertSame(['station' => null, 'method' => 'ambiguous'], $two->match('87000001', 'Alpha', '06000'));
        $this->assertSame(['station' => null, 'method' => 'ambiguous'], $none->match('87000001', 'Alpha', '06000'));
    }

    public function test_the_alias_applies_only_when_vff_does_not_carry_the_code_and_the_target_exists(): void
    {
        $alias = 'roissy-aeroport-charles-de-gaulle-2-tgv-rer';
        $with = new StationAttendanceMatcher([self::station(1, $alias, 'Roissy', '87001479', 'active', '93')]);
        $without = new StationAttendanceMatcher([self::station(2, 'autre', 'Roissy', null, 'active', '93')]);
        $carried = new StationAttendanceMatcher([self::station(1, $alias, 'Roissy', '87001479', 'active', '93'), self::station(3, 'x', 'X', '87271494', 'active', '75')]);

        $this->assertSame('alias', $with->match('87271494', 'Aéroport', '93290')['method']);
        $this->assertSame('alias', $with->match('87271494', 'Aéroport', null)['method']);
        $this->assertSame('unmatched', $without->match('87271494', 'Aéroport', '93290')['method']);
        $this->assertSame('uic', $carried->match('87271494', 'Aéroport', '93290')['method']);
        $this->assertSame('x', $carried->match('87271494', 'Aéroport', '93290')['station']->slug);
    }

    public function test_the_alias_table_is_the_reviewed_one(): void
    {
        $this->assertSame(['87271494' => 'roissy-aeroport-charles-de-gaulle-2-tgv-rer'], StationAttendanceMatcher::ALIASES);
    }

    public function test_a_name_match_needs_a_known_department_on_both_sides(): void
    {
        $matcher = new StationAttendanceMatcher([self::station(1, 'a', 'Alpha', null, 'active', null), self::station(2, 'b', 'Beta', null, 'active', '06')]);

        $this->assertSame('unmatched', $matcher->match('87000000', 'Alpha', '06000')['method']);
        $this->assertSame('unmatched', $matcher->match('87000000', 'Beta', null)['method']);
        $this->assertSame('unmatched', $matcher->match('87000000', 'Beta', 'abc')['method']);
        $this->assertSame('unmatched', $matcher->match('87000000', 'Beta', '13000')['method']);
        $this->assertSame('name-department', $matcher->match('87000000', 'BÉTA', '06000')['method']);
    }

    public function test_two_stations_of_the_same_name_in_the_same_department_are_ambiguous_not_unmatched(): void
    {
        $matcher = new StationAttendanceMatcher([self::station(1, 'a-1', 'Alpha', null), self::station(2, 'a-2', 'Alpha', null)]);

        $this->assertSame(['station' => null, 'method' => 'ambiguous'], $matcher->match('87000000', 'Alpha', '06000'));
    }

    public function test_corsica_matches_both_departments_and_the_old_code_only_for_a_corsican_postal_code(): void
    {
        $matcher = new StationAttendanceMatcher([
            self::station(1, 'bastia', 'Bastia', null, 'active', '2B'),
            self::station(2, 'ajaccio', 'Ajaccio', null, 'active', '2A'),
            self::station(3, 'corte', 'Corte', null, 'active', '20'),
            self::station(4, 'nice', 'Nice', null, 'active', '06'),
        ]);

        $this->assertSame('bastia', $matcher->match('87000000', 'Bastia', '20200')['station']?->slug);
        $this->assertSame('ajaccio', $matcher->match('87000000', 'Ajaccio', '20000')['station']?->slug);
        $this->assertSame('corte', $matcher->match('87000000', 'Corte', '20250')['station']?->slug);
        $this->assertSame('unmatched', $matcher->match('87000000', 'Nice', '20000')['method']);
        $this->assertSame('unmatched', $matcher->match('87000000', 'Bastia', '06000')['method']);
        $this->assertSame('unmatched', $matcher->match('87000000', 'Bastia', '2B000')['method']);
    }

    public function test_the_constructor_accepts_any_iterable_and_a_later_slug_replaces_an_earlier_one(): void
    {
        $generator = (function () {
            yield self::station(1, 'a', 'Alpha', '87000001');
            yield self::station(2, 'b', 'Beta', '87000002');
        })();
        $matcher = new StationAttendanceMatcher($generator);

        $this->assertSame('b', $matcher->match('87000002', 'x', null)['station']->slug);
        $this->assertSame('unmatched', (new StationAttendanceMatcher([]))->match('87000002', 'x', null)['method']);
    }

    public function test_dataset_years_boundaries(): void
    {
        $years = StationAttendanceDatasets::years([
            'total_voyageurs_2014' => 10, 'total_voyageurs_20240' => 99, 'xtotal_voyageurs_2015' => 99,
            'total_voyageurs_2016' => 0, 'total_voyageurs_2017' => '1500', 'total_voyageurs_2018' => '12abc',
            'total_voyageurs_2019' => 1499.5, 'total_voyageurs_2020' => '', 'total_voyageurs_2021' => null,
            'total_voyageurs_non_voyageurs_2022' => 7, 'total_voyageurs_2023' => -1, 'total_voyageurs_non_voyageurs_2023' => 5,
        ]);

        $this->assertSame([
            2014 => [10, null], 2016 => [0, null], 2017 => [1500, null], 2019 => [1500, null], 2022 => [null, 7], 2023 => [null, 5],
        ], $years);
        $this->assertSame([], StationAttendanceDatasets::years([]));
        $this->assertSame([], StationAttendanceDatasets::years(['total_voyageurs_2020' => -3, 'nom_gare' => 'Z']));
    }

    public function test_series_switches_exactly_at_2025(): void
    {
        $this->assertSame('2015-2024', StationAttendanceDatasets::series(2014));
        $this->assertSame('2015-2024', StationAttendanceDatasets::series(2024));
        $this->assertSame('2025', StationAttendanceDatasets::series(2025));
        $this->assertSame('2025', StationAttendanceDatasets::series(2100));
    }
}
