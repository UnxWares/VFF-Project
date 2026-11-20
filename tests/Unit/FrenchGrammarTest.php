<?php

namespace Tests\Unit;

use App\Support\FrenchGrammar;
use PHPUnit\Framework\TestCase;

/**
 * Grammaire française des textes générés des fiches (App\Support\FrenchGrammar) : élision et
 * contraction devant un lieu, « 1er » du mois, citations intactes.
 */
class FrenchGrammarTest extends TestCase
{
    public function test_article_before_a_place_is_elided_or_contracted(): void
    {
        $this->assertSame('d’Ambert', FrenchGrammar::de('Ambert'));
        $this->assertSame('d’Évry', FrenchGrammar::de('Évry'));
        $this->assertSame('du Puley', FrenchGrammar::de('Le Puley'));
        $this->assertSame('du Lac-de-Malaguet', FrenchGrammar::de('Le-Lac-de-Malaguet'));
        $this->assertSame('des Aubrais', FrenchGrammar::de('Les Aubrais'));
        $this->assertSame('de La Ferté-Macé', FrenchGrammar::de('La Ferté-Macé'), 'pas de contraction devant « La »');
        $this->assertSame('de Troyes', FrenchGrammar::de('Troyes'));
        $this->assertSame('de Hazebrouck', FrenchGrammar::de('Hazebrouck'), 'h laissé tel quel');
        $this->assertSame('au Mans', FrenchGrammar::a('Le Mans'));
        $this->assertSame('aux Aubrais', FrenchGrammar::a('Les Aubrais'));
        $this->assertSame('à Arlanc', FrenchGrammar::a('Arlanc'));
    }

    public function test_portion_titles_and_inline_texts(): void
    {
        $this->assertSame('D’Ambert à Darsac', FrenchGrammar::between('Ambert', 'Darsac', true));
        $this->assertSame('Du Mans à Chartres', FrenchGrammar::between('Le Mans', 'Chartres', true));
        $this->assertSame('de Laval au Mans', FrenchGrammar::between('Laval', 'Le Mans'));
        $this->assertSame('Près d’Évry', FrenchGrammar::near('Évry', true));
        $this->assertSame('près du Genest', FrenchGrammar::near('Le Genest'));
    }

    public function test_generated_text_is_fixed_but_quotes_are_not(): void
    {
        $this->assertSame(
            'Ouverture d’Ambert au Mans (Wikipédia, texte, « Histoire de Le Mans »)',
            FrenchGrammar::fix('Ouverture de Ambert à Le Mans (Wikipédia, texte, « Histoire de Le Mans »)'),
        );
        $this->assertSame('Mise en service du Puley à Genouilly, près d’Evry', FrenchGrammar::fix('Mise en service de Le Puley à Genouilly, près de Evry'));
        $this->assertSame('« De Arlanc »', FrenchGrammar::fix('« De Arlanc »'), 'citation intacte');
        $this->assertSame('D’Alleins à Noves', FrenchGrammar::fix('De Alleins à Noves'));
        // Mot qui finit par « de » : pas d'élision au milieu d'un mot.
        $this->assertSame('Aude Ambert', FrenchGrammar::fix('Aude Ambert'));
    }

    public function test_first_day_of_the_month(): void
    {
        $this->assertSame('le 1er décembre 1950', FrenchGrammar::fix('le 1 décembre 1950'));
        $this->assertSame('11 décembre 1950, 21 mai 1900', FrenchGrammar::fix('11 décembre 1950, 21 mai 1900'));
        $this->assertSame('1er août 1914', FrenchGrammar::firstOfMonth('1 août 1914'));
    }
}
