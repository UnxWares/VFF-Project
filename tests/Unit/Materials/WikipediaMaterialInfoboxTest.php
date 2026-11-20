<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\WikipediaMaterialInfobox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Infobox « Matériel ferroviaire » des articles Wikipédia : catégorie annoncée (bimode) et effectif en
 * service daté. Seul ce que l'article écrit est repris.
 */
class WikipediaMaterialInfoboxTest extends TestCase
{
    private const REGIOLIS = <<<'WIKI'
        {{Infobox Matériel ferroviaire/Début
         | Type    = automotrice bimode
         | modèle  = Régiolis
         | légende = Régiolis type Z51500 en [[gare d'Arc-et-Senans]].
        }}
        {{Infobox Matériel ferroviaire/Identification
         | Type               = automotrice bimode
         | Exploitant         = [[SNCF Voyageurs]] et sociétés filles
         | Désignation        = {{Boîte déroulante infobox début|titre=Multi séries}}
        * B 83500 (bimode périurbain)
        {{Boîte déroulante infobox fin}}
         | Type2              = automotrice, matériel bi-mode
         | Motorisation       = électrique ou bimode
         | Effectif           = 427 <small>au {{date|25 04 2026}}</small>
        }}
        Le Régiolis est une famille d'automotrices.
        WIKI;

    public function test_the_parameters_of_the_start_and_identification_blocks_are_read_as_text(): void
    {
        $parameters = WikipediaMaterialInfobox::parameters(self::REGIOLIS);

        $this->assertSame('automotrice bimode', $parameters['type']);
        $this->assertSame('automotrice, matériel bi-mode', $parameters['type2']);
        $this->assertSame('électrique ou bimode', $parameters['motorisation']);
        $this->assertSame("Régiolis type Z51500 en gare d'Arc-et-Senans.", $parameters['légende']);
        $this->assertSame('427 au 25 04 2026', $parameters['effectif']);
        $this->assertStringContainsString('B 83500', $parameters['désignation'], 'un modèle imbriqué ne coupe pas le paramètre');
    }

    public function test_an_article_without_the_infobox_gives_nothing(): void
    {
        $this->assertSame([], WikipediaMaterialInfobox::parameters("{{Infobox Ligne ferroviaire\n | nom = X\n}}\nTexte."));
        $this->assertSame([], WikipediaMaterialInfobox::parameters(''));
    }

    public function test_only_the_type_says_bimode_not_the_family_motorisation(): void
    {
        $this->assertTrue(WikipediaMaterialInfobox::saysBimode(['type' => 'automotrice bimode']));
        $this->assertTrue(WikipediaMaterialInfobox::saysBimode(['type' => 'automotrice', 'type2' => 'automotrice, matériel bi-mode']));
        // Un article de famille écrit « électrique, diesel, bimode » dans la motorisation : la série n'est pas bimode pour autant.
        $this->assertFalse(WikipediaMaterialInfobox::saysBimode(['type' => 'automotrice régionale', 'motorisation' => 'électrique, diesel, bimode']));
        $this->assertFalse(WikipediaMaterialInfobox::saysBimode([]));
    }

    /** @return array<string, array{?string, ?array{count: int, as_of: string, precision: string}}> */
    public static function fleets(): array
    {
        return [
            'date en modèle' => ['1 au 13 07 2026', ['count' => 1, 'as_of' => '2026-07-13', 'precision' => 'day']],
            'date avec barres' => ['12 rames (au 15/6/2026)', ['count' => 12, 'as_of' => '2026-06-15', 'precision' => 'day']],
            'mois en lettres' => ['699 en Septembre 2023', ['count' => 699, 'as_of' => '2023-09', 'precision' => 'month']],
            'jour en lettres' => ['3 au 1er janvier 2025', ['count' => 3, 'as_of' => '2025-01-01', 'precision' => 'day']],
            'année seule' => ['78 rames (en 2026)', ['count' => 78, 'as_of' => '2026', 'precision' => 'year']],
            'milliers' => ['1 200 rames au 02 05 2024', ['count' => 1200, 'as_of' => '2024-05-02', 'precision' => 'day']],
            'zéro' => ['0 (au 07 Mai 2026)', ['count' => 0, 'as_of' => '2026-05-07', 'precision' => 'day']],
            'environ' => ['environ 40 en 2024', ['count' => 40, 'as_of' => '2024', 'precision' => 'year']],
            'sans date : rien' => ['28 rames', null],
            'sans nombre : rien' => ['encore en service en 2026', null],
            'absent' => [null, null],
        ];
    }

    /** @param array{count: int, as_of: string, precision: string}|null $expected */
    #[DataProvider('fleets')]
    public function test_the_fleet_needs_a_number_and_a_date(?string $text, ?array $expected): void
    {
        $this->assertSame($expected, WikipediaMaterialInfobox::fleet($text));
    }
}
