<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\AccidentKindClassifier;
use App\Services\Accidents\WikipediaListCollector;
use App\Services\Accidents\WikipediaListReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Un lieu qui fait partie de la désignation d'un train (origine, destination, « train Sète-Bordeaux ») n'est pas le
 * lieu de l'accident. Extraits réels de la liste de Wikipédia (révisions de novembre 2026), raccourcis.
 */
class WikipediaListReaderTrainRouteTest extends TestCase
{
    private WikipediaListReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new WikipediaListReader(new AccidentKindClassifier);
    }

    /** @return array<string, mixed> */
    private function entry(string $line): array
    {
        $entries = $this->reader->read($line);
        $this->assertCount(1, $entries, $line);

        return $entries[0];
    }

    /** @return list<string> */
    private function names(array $entry): array
    {
        return array_map(fn (array $p): string => $p[0], $entry['places']);
    }

    public function test_grisolles_1908_does_not_take_the_origin_of_the_train_for_the_place(): void
    {
        $entry = $this->entry('* {{date|8|novembre|1908}} - Vers 4 heures 30, entre Toulouse et Montauban, près de la [[gare de Grisolles]], un rail se rompt au passage du train [[Sète]]-Bordeaux. Le wagon-poste quitte la voie.');

        $this->assertSame(['Grisolles'], $this->names($entry));
        $this->assertTrue($entry['approximate']);
        $this->assertSame(['Sète', 'Grisolles'], array_map(fn (array $p): string => $p[0], $entry['listed']['places']));
    }

    /** @return iterable<string, array{string, list<string>, list<string>}> */
    public static function trainDesignations(): iterable
    {
        yield 'train A-B, deux liens' => ['Près de [[Mars-la-Tour]], un train de marchandises [[Nancy]]-[[Longuyon]] déraille.', ['Mars-la-Tour'], ['Mars-la-Tour', 'Nancy', 'Longuyon']];
        yield 'omnibus A - B' => ["Dans la [[Paris-Gare-de-Lyon|gare de Lyon]] à Paris, un haut le pied percute l'omnibus [[Montargis]] - Paris.", ['Paris-Gare-de-Lyon'], ['Paris-Gare-de-Lyon', 'Montargis']];
        yield 'rapide Paris-X' => ["À l'entrée en [[Gare de Nantes|gare de Nantes]], le rapide Paris-[[Les Sables-d'Olonne (ancienne commune)|les-Sables-d'Olonne]] déraille.", ['Nantes'], ['Nantes', "Les Sables-d'Olonne"]];
        yield 'autorail A-B' => ["[[Cosne-Cours-sur-Loire]] : déraillement de l'[[autorail]] [[Nevers]]–[[Montargis]].", ['Cosne-Cours-sur-Loire'], ['Cosne-Cours-sur-Loire', 'Nevers', 'Montargis']];
        yield 'omnibus avec numéro, bouts à trait d\'union' => ["Un haut-le-pied percute en [[gare de Sérézin]] l'omnibus 3462 [[Saint-Rambert-d'Albon]]-[[Lyon]].", ['Sérézin'], ['Sérézin', "Saint-Rambert-d'Albon", 'Lyon']];
        yield 'train de nuit A-B' => ['Près de [[Villette (Meurthe-et-Moselle)|Villette]], un train de voyageurs [[Longuyon]]-[[Charleville-Mézières|Charleville]] déraille.', ['Villette'], ['Villette', 'Longuyon', 'Charleville-Mézières']];
        yield 'second bout seul lié' => ["Près de la [[gare de Taillebourg]], déraillement d'un omnibus Nantes-[[Saintes]].", ['Taillebourg'], ['Taillebourg', 'Saintes']];
        yield 'express de X' => ["En [[gare de Villepreux - Les Clayes]], un omnibus est percuté par l'express de [[Granville]].", ['Villepreux - Les Clayes'], ['Villepreux - Les Clayes', 'Granville']];
    }

    /**
     * @param  list<string>  $places
     * @param  list<string>  $listed
     */
    #[DataProvider('trainDesignations')]
    public function test_a_place_in_a_train_designation_is_not_the_place_of_the_accident(string $text, array $places, array $listed): void
    {
        $entry = $this->entry('* {{date|2|juillet|1900}} - '.$text);

        $this->assertEqualsCanonicalizing($places, $this->names($entry), $text);
        $this->assertEqualsCanonicalizing($listed, array_map(fn (array $p): string => $p[0], $entry['listed']['places']), $text);
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function realPlacesStayUnchanged(): iterable
    {
        yield 'lieu après à' => ['À [[Melun]], un train de voyageurs déraille.', ['Melun']];
        yield 'lieu après un train de marchandises' => ['Un train de marchandises déraille à [[Dijon]].', ['Dijon']];
        yield 'lieu entre deux gares' => ['Entre [[Vallon-en-Sully]] et [[Urçay]], un train déraille.', ['Vallon-en-Sully', 'Urçay']];
        yield 'lieu sans mot de train avant le tiret' => ['Sur la ligne, entre [[Clères]] - [[Bosc-le-Hard]], une collision se produit.', ['Clères', 'Bosc-le-Hard']];
        yield 'lieu en tête de puce' => ['[[Paris]] : un train pour Saint-Cloud heurte le précédent.', ['Paris']];
        yield 'train venant de X : repli conservé' => ['Un train de marchandises venant de [[Baume-les-Dames]] déraille.', ['Baume-les-Dames']];
        yield 'ligne Paris-X sans mot de train' => ['Un train déraille sur la ligne Paris-[[Lyon]].', ['Lyon']];
        yield 'train déraille entre A-B' => ['Un train déraille entre [[Clères]]-[[Bosc-le-Hard]].', ['Clères', 'Bosc-le-Hard']];
        yield 'train de X, jamais pris pour un lieu de train' => ['Un train de [[Les Ponts-de-Cé]] déraille.', ['Les Ponts-de-Cé']];
        yield 'train cité après le lieu' => ['À [[Saint-Rémy (Saône-et-Loire)|Saint-Rémy]], un train de nuit déraille.', ['Saint-Rémy']];
    }

    /** @param  list<string>  $places */
    #[DataProvider('realPlacesStayUnchanged')]
    public function test_real_places_are_kept(string $text, array $places): void
    {
        $this->assertEqualsCanonicalizing($places, $this->names($this->entry('* {{date|2|juillet|1900}} - '.$text)), $text);
    }

    public function test_the_identifier_does_not_change(): void
    {
        $entry = $this->entry('* {{date|8|novembre|1908}} - Près de la [[gare de Grisolles]], un rail se rompt au passage du train [[Sète]]-Bordeaux. Bilan : dix morts.');

        $this->assertSame('fr:1908-11-08-sete-', substr(WikipediaListCollector::externalId($entry), 0, 19));
        $this->assertSame(['Sète', 'Grisolles'], array_map(fn (array $p): string => $p[0], $entry['listed']['places']));
    }
}
