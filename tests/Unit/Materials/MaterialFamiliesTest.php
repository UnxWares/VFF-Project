<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\MaterialFamilies;
use PHPUnit\Framework\TestCase;

/**
 * Familles de séries qui partagent un article (Z 56600 « Omneo Premium » et Z 55500 vers Regio 2N) : une phrase
 * qui nomme une version revient à la version, jamais à la famille.
 */
class MaterialFamiliesTest extends TestCase
{
    private const REGIO_2N = 27;

    private const Z_55500 = 629;

    private const Z_56600 = 632;

    private const BB_15000 = 64;

    private function families(): MaterialFamilies
    {
        return MaterialFamilies::from([
            self::REGIO_2N => ['name' => 'Regio 2N', 'title' => 'Regio 2N', 'designations' => ['Bombardier Omneo', 'Régio 2N', 'Z 57000']],
            self::Z_55500 => ['name' => 'Z 55500', 'title' => 'Z 55500', 'designations' => ['SNCF Class Z 55500', 'Regio 2N Z 55500']],
            self::Z_56600 => ['name' => 'Z 56600', 'title' => 'Z 56600', 'designations' => ['Regio 2N Z 56600', 'Omneo Premium', 'Bombardier Regio 2N Omneo Premium']],
            self::BB_15000 => ['name' => 'BB 15000', 'title' => 'BB 15000', 'designations' => null],
        ], ['Z 55500' => 'Regio 2N', 'Z 56600' => 'Regio 2N', 'BB 15000' => 'BB 15000']);
    }

    public function test_a_sentence_naming_a_version_goes_to_the_version_not_to_the_family(): void
    {
        $families = $this->families();
        $omneo = 'Commandées à 40 exemplaires, ces rames Omneo Premium (Z 56600) permettent d\'atteindre 200 km/h.';

        $this->assertSame([self::Z_56600], $families->attribute([self::REGIO_2N], $omneo), 'lien [[Regio 2N|Omneo Premium]] : la version nommée');
        $this->assertSame([self::Z_56600], $families->attribute([self::REGIO_2N], 'Les dessertes sont assurées avec des Omneo Premium, version grande ligne de la plateforme Régio2N.'), 'désignation seule, famille aussi nommée');
        $this->assertSame([self::Z_56600], $families->attribute([self::Z_56600, self::REGIO_2N], 'Les Z56600 assurent la relation.'), 'lien vers la redirection Z 56600 : plus de doublon avec la famille');
        $this->assertSame([self::Z_55500], $families->attribute([self::REGIO_2N], 'La ligne est desservie par des Z 26500 et Z 55500.'));
    }

    public function test_a_sentence_naming_only_the_family_stays_with_the_family(): void
    {
        $families = $this->families();

        $this->assertSame([self::REGIO_2N], $families->attribute([self::REGIO_2N], 'Elles ont depuis été remplacées par des rames Regio 2N.'));
        $this->assertSame([self::REGIO_2N], $families->attribute([self::REGIO_2N], 'Rames Regio 2N Z 57000 de la ligne R.'), 'Z 57000 n\'est pas une version importée ici');
        $this->assertSame([self::REGIO_2N], $families->attribute([self::REGIO_2N], 'Bombardier Omneo, plateforme commune.'), '« Omneo » seul ne nomme pas l\'Omneo Premium');
        $this->assertSame([self::BB_15000], $families->attribute([self::BB_15000], 'Les BB 15000 et les Omneo Premium.'), 'série sans version');
        $this->assertSame([], $families->named(self::Z_56600, 'Omneo Premium'), 'une version n\'est pas une famille');
    }

    public function test_designations_that_contain_the_family_name_do_not_name_a_version(): void
    {
        $families = $this->families();

        $this->assertSame([], $families->named(self::REGIO_2N, 'Une rame Regio 2N arrive en gare.'), '« Regio 2N Z 56600 » ne doit pas rendre « Regio 2N » propre aux Z 56600');
    }

    public function test_the_article_of_the_family_gives_the_sentences_of_a_version_to_the_version(): void
    {
        $families = $this->families();
        $quote = 'Les Z 56600 sont progressivement mises en service, depuis le 17 février 2020, sur les lignes Paris - Caen - Cherbourg.';

        $this->assertFalse($families->speaksOf(self::REGIO_2N, 'Regio 2N', 'Regio 2N', 'Regio 2N', $quote), 'article de la famille, phrase d\'une version');
        $this->assertTrue($families->speaksOf(self::REGIO_2N, 'Regio 2N', 'Regio 2N', 'Regio 2N', 'Les Regio 2N circulent sur la ligne N.'));
        $this->assertTrue($families->speaksOf(self::Z_56600, 'Z 56600', 'Regio 2N', 'Z 56600', $quote), 'par le nom');
        $this->assertTrue($families->speaksOf(self::Z_56600, 'Z 56600', 'Regio 2N', 'Z 56600', 'Les rames Omneo Premium remplacent les Corail.'), 'par la désignation');
        $this->assertFalse($families->speaksOf(self::Z_56600, 'Z 56600', 'Regio 2N', 'Z 56600', 'Les Regio 2N circulent sur la ligne N.'));
    }

    public function test_a_version_named_by_the_sub_section_title_gets_the_sentence(): void
    {
        $families = $this->families();
        $quote = MaterialFamilies::inSection('Dessertes réalisées ou prévues > Z 56600', 'Paris-Saint-Lazare - Caen - Cherbourg ;');

        $this->assertFalse($families->speaksOf(self::REGIO_2N, 'Regio 2N', 'Regio 2N', 'Regio 2N', $quote), 'sous-section d\'une version');
        $this->assertTrue($families->speaksOf(self::Z_56600, 'Z 56600', 'Regio 2N', 'Z 56600', $quote));
        $this->assertTrue($families->speaksOf(self::REGIO_2N, 'Regio 2N', 'Regio 2N', 'Regio 2N', MaterialFamilies::inSection('Utilisation > Normandie', 'Paris - Rouen ;')));
        $this->assertSame("Z 56600\nPhrase.", MaterialFamilies::inSection('Dessertes > Z 56600', 'Phrase.'));
    }
}
