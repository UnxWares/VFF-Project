<?php

namespace Tests\Unit\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Services\Accidents\AccidentKindClassifier;
use App\Services\Accidents\WikipediaListReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Faits d'une page « Liste d'accidents ferroviaires en France » : une puce par accident, texte
 * libre. Seuls des faits sont lus ; ce qui n'est pas certain (bilan approché, en fourchette,
 * contradictoire) reste inconnu. Les puces d'essai ont la forme de celles de la liste, avec un
 * texte inventé pour l'occasion.
 */
class WikipediaListReaderTest extends TestCase
{
    private WikipediaListReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new WikipediaListReader(new AccidentKindClassifier);
    }

    /** @return iterable<string, array{string, ?string, string}> */
    public static function dates(): iterable
    {
        yield 'modèle jour, mois, année' => ['{{date|8|mai|1842}} - Texte', '1842-05-08', ' - Texte'];
        yield 'premier du mois' => ['{{date|1er|mars|1846}} - Texte', '1846-03-01', ' - Texte'];
        yield 'modèle en un argument' => ['{{date|22 novembre 1842}} - Texte', '1842-11-22', ' - Texte'];
        yield 'modèle date- numérique' => ['{{Date-|14|11|2015}} - Texte', '2015-11-14', ' - Texte'];
        yield 'précédée de Le' => ['Le {{Date-|17|8|2016}} - Texte', '2016-08-17', ' - Texte'];
        yield 'nuit du 8 au 9' => ['Nuit du 8 au {{Date-|9|mars|2012}} - Texte', '2012-03-09', ' - Texte'];
        yield 'jour hors du modèle' => ['24 {{date||janvier|1977}} - Texte', '1977-01-24', ' - Texte'];
        yield 'lien daté' => ['[[1941|28 décembre 1941]] - Texte', '1941-12-28', ' - Texte'];
        yield 'date en clair avec Le' => ['le 11 septembre 2026 : Texte', '2026-09-11', ' : Texte'];
        yield 'date en clair' => ['8 novembre 2026 : Texte', '2026-11-08', ' : Texte'];
        yield 'jour impossible' => ['{{date|31|février|1900}} - Texte', null, ''];
        yield 'année seule' => ['Fin [[1963]]. Texte', null, ''];
        yield 'puce de renvoi' => ['[[Accident ferroviaire]]', null, ''];
    }

    #[DataProvider('dates')]
    public function test_reads_the_date_at_the_start_of_a_bullet(string $line, ?string $date, string $rest): void
    {
        $found = $this->reader->date($line);

        $this->assertSame($date === null ? null : [$date, $rest], $found);
    }

    public function test_reads_only_facts_and_keeps_none_of_the_text(): void
    {
        $wikitext = <<<'WIKI'
            Introduction de la page.

            == 1905 ==

            * {{date|30|mars|1905}} - Sur la [[Ligne de Sceaux|ligne de Sceaux]], près de [[Bourg-la-Reine]], un train déraille entre [[Arcueil]] et [[Gare de Bagneux|Bagneux]]. On dénombrera trois morts et neuf blessés<ref>Un journal du jour.</ref>.
            ** sous-puce ignorée
            * [[Accident ferroviaire]]

            == Septembre ==

            * {{date|24|septembre|1905}} - [[Langon (Gironde)|Langon]] : collision entre deux trains de marchandises.
            WIKI;

        $entries = $this->reader->read($wikitext);

        $this->assertCount(2, $entries);
        $first = $entries[0];
        $this->assertSame('1905-03-30', $first['date']);
        $this->assertSame(AccidentDatePrecision::Day, $first['precision']);
        $this->assertSame('1905', $first['anchor']);
        $this->assertSame(['Ligne de Sceaux'], $first['line_articles']);
        $this->assertSame('Bagneux', $first['station']);
        $this->assertSame(AccidentKind::Derailment, $first['kind']);
        $this->assertSame(3, $first['deaths']);
        $this->assertSame(9, $first['injuries']);
        $this->assertFalse($first['urban']);
        // « près de » et « entre » désignent un lieu voisin : ils passent après un lieu cité sans cette réserve.
        $this->assertSame([['Bagneux', null], ['Bourg-la-Reine', null], ['Arcueil', null]], $first['places']);
        $this->assertArrayNotHasKey('text', $first);
        $this->assertStringNotContainsString('journal', (string) json_encode($first));

        $second = $entries[1];
        $this->assertSame('Septembre', $second['anchor']);
        $this->assertSame([['Langon', '33']], $second['places']);
        $this->assertSame(AccidentKind::Collision, $second['kind']);
        $this->assertNull($second['deaths']);
    }

    public function test_reads_an_unlinked_place_and_its_department_in_head_position(): void
    {
        $entries = $this->reader->read('* {{date-|7 Octobre 1991}} - Courtonne-La-Meurdrac (Calvados) : collision sur un passage à niveau, deux morts.');

        $this->assertSame([['Courtonne-La-Meurdrac', '14']], $entries[0]['places']);
        $this->assertSame(2, $entries[0]['deaths']);
        $this->assertSame(AccidentKind::LevelCrossing, $entries[0]['kind']);
    }

    public function test_reads_the_department_that_follows_a_linked_place(): void
    {
        $entries = $this->reader->read('* {{Date-|12|10|2011}} - [[Saint-Médard-sur-Ille]] ([[Ille-et-Vilaine]]) : collision avec un camion.');

        $this->assertSame([['Saint-Médard-sur-Ille', '35']], $entries[0]['places']);
    }

    public function test_ignores_links_that_are_not_places(): void
    {
        $entries = $this->reader->read('* {{date|3|mai|1900}} - [[Meudon]] : le [[TGV]] et la [[Compagnie du chemin de fer du Nord]] suivent la [[ligne de Paris à Lille]], un [[serre-frein]] est blessé.');

        $this->assertSame([['Meudon', null]], $entries[0]['places']);
        $this->assertSame(['Ligne de Paris à Lille'], $entries[0]['line_articles']);
    }

    /** @return iterable<string, array{string, ?AccidentKind}> */
    public static function kinds(): iterable
    {
        yield 'personne heurtée' => ['un TER percute fatalement une dame de 82 ans qui traversait la voie', AccidentKind::PersonStruck];
        yield 'ouvriers sur un chantier' => ['un TER percute trois ouvriers sur un chantier', AccidentKind::PersonStruck];
        yield 'passage à niveau avec un camion' => ['un train percute un camion à un passage à niveau', AccidentKind::LevelCrossing];
        yield 'collision à un passage à niveau' => ['collision entre un autocar et un TER à un passage à niveau', AccidentKind::LevelCrossing];
        yield 'piéton à un passage à niveau' => ['un train percute un piéton à un passage à niveau', AccidentKind::LevelCrossing];
        yield 'déraillement avec un agent' => ['le train déraille et percute un agent', AccidentKind::Derailment];
        yield 'déraillement simple' => ['un train de voyageurs déraille', AccidentKind::Derailment];
        yield 'verbe de collision' => ['un express percute un train de marchandises à l\'arrêt', AccidentKind::Collision];
        yield 'train qui se renverse' => ['la locomotive et six wagons se renversent', AccidentKind::Derailment];
        yield 'verbe sans nature' => ['le train arrive en gare avec du retard', null];
        // Faux heurts de personne : les victimes sont les voyageurs d'une collision entre trains.
        yield 'voyageurs écrasés par une collision' => ['un express tamponne un train de marchandises à l\'arrêt, la locomotive écrasant les voyageurs de la voiture de tête, 12 morts', AccidentKind::Collision];
        yield 'participe présent' => ['la machine se couche sur le quai, renversant les voyageurs qui attendaient', AccidentKind::Derailment];
        yield 'train heurté puis voyageurs' => ['un rapide heurte un omnibus et fauche les voyageurs qui en descendaient', AccidentKind::Collision];
        yield 'deux trains se télescopent' => ['deux trains se télescopent, écrasant des voyageurs', AccidentKind::Collision];
        yield 'piéton fauché' => ['un train fauche un piéton qui traversait les voies', AccidentKind::PersonStruck];
        yield 'train percuté au passif' => ['un train de voyageurs arrêté par suite de l\'avarie d\'une de ses deux machines est percuté par celui qui le suivait. Dans le train tamponné, un voyageur est tué, six sont blessés', AccidentKind::Collision];
        yield 'train tamponné' => ['le train tamponné, deux voyageurs sont tués', AccidentKind::Collision];
    }

    #[DataProvider('kinds')]
    public function test_reads_the_kind(string $text, ?AccidentKind $kind): void
    {
        $entries = $this->reader->read("* {{date|1|mai|1950}} - [[Meudon]] : {$text}.");

        $this->assertSame($kind, $entries[0]['kind']);
    }

    public function test_flags_urban_networks(): void
    {
        $metro = $this->reader->read('* {{date|10|août|1903}} - [[Paris]] : incendie dans le métro, de nombreuses victimes.');
        $tramTrain = $this->reader->read('* {{date|10|août|2010}} - [[Nantes]] : un tram-train déraille.');

        $this->assertTrue($metro[0]['urban']);
        $this->assertFalse($tramTrain[0]['urban']);
    }

    /** @return iterable<string, array{string, ?int}> */
    public static function deathTolls(): iterable
    {
        yield 'chiffres' => ['bilan : 57 morts', 57];
        yield 'lettres' => ['trois morts', 3];
        yield 'un mort' => ['un mort et deux blessés', 1];
        yield 'composé' => ['vingt et un morts', 21];
        yield 'quatre-vingt-dix' => ['quatre-vingt-dix morts', 90];
        yield 'deux cents' => ['deux cents tués', 200];
        yield 'millier avec espace' => ['1 200 morts', 1200];
        yield 'répété à l\'identique' => ['trois morts, soit trois morts au total', 3];
        yield 'approximatif' => ['environ trente morts', null];
        yield 'au moins' => ['au moins dix morts', null];
        yield 'dizaine' => ['une dizaine de morts', null];
        yield 'fourchette' => ['de neuf à dix-huit morts', null];
        yield 'deux valeurs' => ['trois morts, puis cinq morts', null];
        yield 'aucun nombre' => ['le mécanicien est tué', null];
        yield 'verbe avoir' => ['il y a cinq morts', 5];
    }

    #[DataProvider('deathTolls')]
    public function test_reads_a_toll_only_when_it_is_certain(string $text, ?int $deaths): void
    {
        $entries = $this->reader->read("* {{date|1|mai|1950}} - [[Meudon]] : {$text}.");

        $this->assertSame($deaths, $entries[0]['deaths']);
    }

    public function test_reads_injuries_apart_from_deaths(): void
    {
        $entries = $this->reader->read('* {{date|1|mai|1950}} - [[Meudon]] : deux morts et quatre blessés graves.');

        $this->assertSame(2, $entries[0]['deaths']);
        $this->assertSame(4, $entries[0]['injuries']);
    }

    public function test_numbers_in_words(): void
    {
        $this->assertSame(17, $this->reader->number('dix-sept'));
        $this->assertSame(81, $this->reader->number('quatre-vingt-un'));
        $this->assertSame(32, $this->reader->number('trente-deux'));
        $this->assertSame(142, $this->reader->number('cent quarante-deux'));
        $this->assertNull($this->reader->number('beaucoup'));
    }

    public function test_finds_a_department_by_its_name(): void
    {
        $this->assertSame('60', $this->reader->department('Oise (département)'));
        $this->assertSame('2A', $this->reader->department('Corse-du-Sud'));
        $this->assertNull($this->reader->department('Roanne'));
    }
}
