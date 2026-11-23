<?php

namespace Tests\Unit\Heritage;

use App\Services\Heritage\MerimeeSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Lecture d'une notice Mérimée : élément de gare, protection, auteurs, datation. */
class MerimeeSourceTest extends TestCase
{
    /** @return iterable<string, array{array<string, string>, ?string}> */
    public static function elements(): iterable
    {
        yield 'gare' => [['Denomination_de_l_edifice' => 'gare', 'Titre_editorial_de_la_notice' => 'Gare de Tours'], 'building'];
        yield 'gare et musée' => [['Denomination_de_l_edifice' => 'gare;musée', 'Titre_editorial_de_la_notice' => 'Ancienne gare d\'Orsay'], 'building'];
        yield 'gare routière' => [['Denomination_de_l_edifice' => 'gare routière', 'Titre_editorial_de_la_notice' => 'Gare routière'], null];
        yield 'gare maritime' => [['Denomination_de_l_edifice' => 'gare maritime', 'Titre_editorial_de_la_notice' => 'Gare maritime'], null];
        yield 'hôtel de voyageurs' => [['Denomination_de_l_edifice' => 'hôtel de voyageurs', 'Titre_editorial_de_la_notice' => 'Hôtel de la Gare'], null];
        yield 'remise ferroviaire' => [['Denomination_de_l_edifice' => 'remise ferroviaire', 'Titre_editorial_de_la_notice' => 'Dépôt SNCF de Mohon'], 'works'];
        yield 'rotonde de dépôt' => [['Denomination_de_l_edifice' => 'maison', 'Titre_editorial_de_la_notice' => 'Rotonde SNCF'], 'works'];
        yield 'rotonde sans chemin de fer' => [['Denomination_de_l_edifice' => 'maison', 'Titre_editorial_de_la_notice' => 'Rotonde de la Vignotte'], null];
        yield 'pont ferroviaire' => [['Denomination_de_l_edifice' => 'pont;passerelle', 'Titre_editorial_de_la_notice' => 'Pont ferroviaire Saint-Jean'], null];
        yield 'château' => [['Denomination_de_l_edifice' => 'château', 'Titre_editorial_de_la_notice' => 'Château de la Garenne'], null];
    }

    /** @param  array<string, string>  $row */
    #[DataProvider('elements')]
    public function test_element(array $row, ?string $expected): void
    {
        $this->assertSame($expected, MerimeeSource::element($row));
    }

    public function test_designations_keep_classified_and_registered_and_drop_radiated_ones(): void
    {
        $this->assertSame([['kind' => 'registered', 'date' => '1984-12-28']], MerimeeSource::designations('1984/12/28 : inscrit MH'));
        $this->assertSame(
            [['kind' => 'classified', 'date' => '1913-02-15'], ['kind' => 'registered', 'date' => '1980-01-01']],
            MerimeeSource::designations('1913/02/15 : classé MH;1980/01/01 : inscrit MH'),
        );
        $this->assertSame([], MerimeeSource::designations('1990/01/01 : déclassé MH'));
        $this->assertSame([], MerimeeSource::designations(''));
    }

    public function test_date_refuses_an_impossible_day(): void
    {
        $this->assertSame('2010-07-05', MerimeeSource::date('2010/07/05 : inscrit MH'));
        $this->assertNull(MerimeeSource::date('2010/13/45'));
        $this->assertNull(MerimeeSource::date('sans date'));
    }

    public function test_architects_swap_two_word_names_and_title_case_capitals(): void
    {
        $this->assertSame(
            [['name' => 'Victor Laloux', 'role' => 'architecte'], ['name' => 'Jean Dupont', 'role' => 'sculpteur']],
            MerimeeSource::architects('Laloux Victor (architecte);Dupont Jean (sculpteur)'),
        );
        $this->assertSame([['name' => 'Bachmann', 'role' => 'architecte']], MerimeeSource::architects('BACHMANN (architecte)'));
        // Nom composé : laissé tel que la base l'écrit.
        $this->assertSame([['name' => 'Le Gac Jean', 'role' => 'peintre']], MerimeeSource::architects('Le Gac Jean (peintre)'));
        $this->assertSame([], MerimeeSource::architects('sans rôle'));
    }

    public function test_years_and_period(): void
    {
        $this->assertSame([1896, 1898], MerimeeSource::years('1896;1898'));
        $this->assertSame([1925, null], MerimeeSource::years('1925'));
        $this->assertSame([null, null], MerimeeSource::years('4e quart 19e siècle'));
        $this->assertSame('4e quart 19e siècle', MerimeeSource::period('4e quart 19e siècle;1er quart 20e siècle'));
        $this->assertNull(MerimeeSource::period(''));
    }

    public function test_the_fixture_keeps_railway_buildings_with_a_protection_or_a_label_only(): void
    {
        $records = (new MerimeeSource)->railwayRecords(__DIR__.'/../../Fixtures/Heritage/merimee.csv');
        $byRef = array_column($records, null, 'ref');

        // Hôtel, gare routière, pont, protection radiée : écartés.
        $this->assertSame(['PA00000001', 'PA00000002', 'PA00000003', 'PA00000004', 'PA00000007'], array_keys($byRef));
        $alpha = $byRef['PA00000001'];
        $this->assertSame('registered', $alpha['protection']);
        $this->assertSame('1984-12-28', $alpha['protection_date']);
        $this->assertSame('partial', $alpha['scope']);
        $this->assertSame([1896, 1898], [$alpha['built_from'], $alpha['built_to']]);
        $this->assertSame(48.8, $alpha['lat']);
        $this->assertNull($byRef['PA00000002']['scope']);
        $this->assertSame('classified', $byRef['PA00000002']['protection']);
        // Label seul : pas de protection, une désignation `acr` datée.
        $this->assertNull($byRef['PA00000004']['protection']);
        $this->assertSame([['kind' => 'acr', 'date' => '2018-05-04']], $byRef['PA00000004']['designations']);
        $this->assertSame('works', $byRef['PA00000003']['element']);
    }
}
