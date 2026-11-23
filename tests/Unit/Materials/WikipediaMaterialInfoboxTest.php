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

    /** @return array<string, array{array<string, string>, ?int, ?int}> */
    public static function serviceYears(): array
    {
        return [
            'plage : la plus ancienne' => [['mise en service' => 'de 1963-1968'], 1963, null],
            'années en liens' => [['mise en service' => 'de 1996 dans les chemins de fer à 2000 dans les chemins de fer', 'retrait' => 'de 2025 dans les chemins de fer à 2026 dans les chemins de fer'], 1996, 2026],
            'jour en lettres' => [['mise en service' => '29 avril 1952', 'retrait' => '15 décembre 1970'], 1952, 1970],
            'construction entre parenthèses ignorée' => [['mise en service' => '2006 (construit en 1995)'], 2006, null],
            'transformation ignorée' => [['livraison' => '31 Transformation : 1997'], null, null],
            'autre série nommée' => [['mise en service' => 'BB 8051 : juin 1951 ; BB 10001 : 1953'], null, null],
            'le premier champ renseigné fait foi' => [['mise en service' => 'BB 8051 : juin 1951 ; BB 10001 : 1953', 'période de service' => '1951 - 1964'], null, 1964],
            'livraison à défaut' => [['livraison' => '1925-26'], 1925, null],
            'période de service en dernier' => [['période de service' => '1935-1976'], 1935, 1976],
            'année à venir : rien' => [['mise en service' => 'Fin 2028'], null, null],
            'depuis : pas de retrait' => [['mise en service' => '1980', 'retrait' => 'depuis 2011'], 1980, null],
            'effectif positif : pas de retrait' => [['mise en service' => '1980', 'retrait' => 'de 2012 à 2017', 'effectif' => '42 (au 25/10/2021)'], 1980, null],
            'retrait par sous-série : rien' => [['mise en service' => '1980', 'retrait' => 'MI 84 : ; de 2012 à 2017'], 1980, null],
            'retrait avant la mise en service : rien' => [['mise en service' => '1990', 'retrait' => '1970'], 1990, null],
            'sans année' => [['mise en service' => 'Depuis le début'], null, null],
        ];
    }

    /** @param array<string, string> $parameters */
    #[DataProvider('serviceYears')]
    public function test_service_years_are_only_those_the_article_writes(array $parameters, ?int $from, ?int $to): void
    {
        $years = WikipediaMaterialInfobox::serviceYears($parameters);

        $this->assertSame($from, $years['from']['year'] ?? null);
        $this->assertSame($to, $years['to']['year'] ?? null);
    }

    public function test_the_service_year_keeps_the_field_it_comes_from_and_a_quote(): void
    {
        $years = WikipediaMaterialInfobox::serviceYears(['livraison' => 'de 1967 à 1977', 'retrait' => 'radiées en 1973']);

        $this->assertSame(['year' => 1967, 'field' => 'livraison', 'basis' => 'livraison de la première unité', 'quote' => 'de 1967 à 1977'], $years['from']);
        $this->assertSame(['year' => 1973, 'field' => 'retrait', 'basis' => 'retrait', 'quote' => 'radiées en 1973'], $years['to']);
    }

    /** @return array<string, array{array<string, string>, ?int}> */
    public static function unitsBuilt(): array
    {
        return [
            'nombre seul' => [['nombre' => '232'], 232],
            'avec le nom de l\'engin' => [['construction' => '80 locomotives'], 80],
            'flèche de commentaire' => [['nombre' => '232 exemplaires-->'], 232],
            'production totale' => [['production totale' => '36'], 36],
            'une année dans construction' => [['construction' => '1912'], null],
            'des années' => [['construction' => '1984-1986'], null],
            'commandées' => [['construction' => '50 rames commandées'], null],
            'somme' => [['nombre' => '19 + 57'], null],
            'article de famille' => [['nombre' => '211', 'effectif' => 'Z 24500 : 145 ; Z 26500 : 66'], null],
            'zéro' => [['production totale' => '0'], null],
        ];
    }

    /** @param array<string, string> $parameters */
    #[DataProvider('unitsBuilt')]
    public function test_the_number_built_is_read_only_when_written_alone(array $parameters, ?int $expected): void
    {
        $this->assertSame($expected, WikipediaMaterialInfobox::unitsBuilt($parameters)['value'] ?? null);
    }

    public function test_the_builder_is_read_only_when_it_is_a_plain_name(): void
    {
        $this->assertSame('Alsthom, SACM', WikipediaMaterialInfobox::builder(['constructeur' => 'Alsthom, SACM']));
        $this->assertNull(WikipediaMaterialInfobox::builder(['constructeur' => 'choisi ultérieurement']));
        $this->assertNull(WikipediaMaterialInfobox::builder(['constructeur' => 'Locomotives : 1952 (Alsthom)']));
        $this->assertNull(WikipediaMaterialInfobox::builder(['constructeur' => 'Voir tableau ci-dessous']));
        $this->assertNull(WikipediaMaterialInfobox::builder([]));
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
