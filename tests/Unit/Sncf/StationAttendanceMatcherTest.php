<?php

namespace Tests\Unit\Sncf;

use App\Services\Sncf\StationAttendanceDatasets;
use App\Services\Sncf\StationAttendanceMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Rattachement d'une gare du jeu « Fréquentation en gares » à une gare de VFF : UIC d'abord, jamais par la seule commune. */
class StationAttendanceMatcherTest extends TestCase
{
    /** @return list<object> */
    private static function stations(): array
    {
        $s = fn (int $id, string $slug, string $name, ?string $uic, string $status, ?string $dep): object => (object) [
            'id' => $id, 'slug' => $slug, 'name' => $name, 'uic_code' => $uic, 'status' => $status, 'department_code' => $dep];

        return [
            $s(1, 'angouleme', 'Angoulême', '87584003', 'active', '16'),
            $s(2, 'grasse', 'Grasse', '87757724', 'declassified', '13'),
            $s(3, 'grasse-06', 'Grasse', '87757724', 'active', '06'),
            $s(4, 'virville-manneville', 'Virville-Manneville', '87413336', 'declassified', '76'),
            $s(5, 'virville-manneville-14', 'Virville-Manneville', '87413336', 'declassified', '14'),
            $s(6, 'nice-pont-michel', 'Nice-Pont-Michel', '87520299', 'active', '06'),
            $s(7, 'nice-ville', 'Nice-Ville', '87756056', 'active', '06'),
            $s(8, 'roissy-aeroport-charles-de-gaulle-2-tgv-rer', 'Roissy-Aéroport-Charles-de-Gaulle 2-TGV (RER)', '87001479', 'active', '93'),
            $s(9, 'saint-etienne-12', 'Saint-Étienne', null, 'active', '42'),
            $s(10, 'saint-etienne-69', 'Saint-Étienne', null, 'active', '69'),
        ];
    }

    /** @return iterable<string, array{string, string, ?string, ?string, string}> */
    public static function cases(): iterable
    {
        yield 'code UIC identique' => ['87584003', 'Angoulême', '16000', 'angouleme', 'uic'];
        yield 'doublon de fiches : le département du code postal tranche' => ['87757724', 'Grasse', '06130', 'grasse-06', 'uic-department'];
        yield 'doublon, département inconnu : la seule fiche non déclassée' => ['87757724', 'Grasse', null, 'grasse-06', 'uic-status'];
        yield 'doublon, deux fiches déclassées, aucun département : ambigu' => ['87413336', 'Virville-Manneville', null, null, 'ambiguous'];
        yield 'doublon départagé par le département' => ['87413336', 'Virville-Manneville', '76840', 'virville-manneville', 'uic-department'];
        yield 'code inconnu, même nom et même département' => ['87000000', 'Nice Pont Michel', '06088', 'nice-pont-michel', 'name-department'];
        yield 'code postal à 4 chiffres (zéro de tête perdu)' => ['87000000', 'Nice Pont Michel', '6088', 'nice-pont-michel', 'name-department'];
        yield 'alias vérifié à la main' => ['87271494', 'Aéroport Charles de Gaulle 2 TGV', '93290', 'roissy-aeroport-charles-de-gaulle-2-tgv-rer', 'alias'];
        yield 'même nom dans deux départements : jamais la commune seule' => ['87000001', 'Saint-Étienne', null, null, 'unmatched'];
        yield 'même nom, département précisé' => ['87000001', 'Saint-Étienne', '42000', 'saint-etienne-12', 'name-department'];
        yield 'aucune gare' => ['87000002', 'Monaco - Monte-Carlo', '98000', null, 'unmatched'];
    }

    #[DataProvider('cases')]
    public function test_match_rules(string $uic, string $name, ?string $postal, ?string $slug, string $method): void
    {
        $found = (new StationAttendanceMatcher(self::stations()))->match($uic, $name, $postal);

        $this->assertSame($method, $found['method']);
        $this->assertSame($slug, $found['station']?->slug);
    }

    public function test_department_of_a_postal_code(): void
    {
        $this->assertSame('06', StationAttendanceMatcher::department('6340'));
        $this->assertSame('75', StationAttendanceMatcher::department('75015'));
        $this->assertSame('971', StationAttendanceMatcher::department('97100'));
        $this->assertNull(StationAttendanceMatcher::department(null));
        $this->assertNull(StationAttendanceMatcher::department('abc'));
    }

    public function test_corsica_matches_2a_and_2b(): void
    {
        $matcher = new StationAttendanceMatcher([(object) ['id' => 1, 'slug' => 'ajaccio', 'name' => 'Ajaccio', 'uic_code' => null, 'status' => 'active', 'department_code' => '2A']]);

        $this->assertSame('ajaccio', $matcher->match('87000000', 'Ajaccio', '20000')['station']?->slug);
    }

    public function test_dataset_years_read_every_column_naming(): void
    {
        $years = StationAttendanceDatasets::years([
            'nom_gare' => 'X', 'total_voyageurs_2024' => 1000, 'total_voyageurs_non_voyageurs_2024' => 1250,
            'totalvoyageurs2017' => 800, 'total_voyageurs_non_voyageurs_2017' => null, 'total_voyageurs_2020' => -5, 'total_voyageurs_2025' => 2.0e6,
        ]);

        $this->assertSame([2017 => [800, null], 2024 => [1000, 1250], 2025 => [2000000, null]], $years);
        $this->assertSame('2015-2024', StationAttendanceDatasets::series(2024));
        $this->assertSame('2025', StationAttendanceDatasets::series(2025));
    }
}
