<?php

namespace Tests\Unit;

use App\Services\HeritageText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fonctions de texte de HeritageText non couvertes par HeritageTextTest :
 * normalisation, jetons de noms, découpe en phrases, cellules de tableaux
 * wiki (liens, texte brut, sections « A - B »), portions citées dans une
 * phrase et libellés français des types d'exploitation.
 */
class HeritageTextHelpersTest extends TestCase
{
    public function test_norm_and_fold(): void
    {
        $this->assertSame('chemin de fer touristique du vivarais oeuvre', HeritageText::norm('Chemin de Fer Touristique du Vivarais — Œuvre'));
        $this->assertSame('', HeritageText::norm(' — '));
        $this->assertSame('etretat pays de caux', HeritageText::fold('Étretat-Pays de Caux'));
    }

    public function test_fold_keeps_character_positions(): void
    {
        foreach (['Étretat-Pays de Caux', 'Vélorail des Gorges du Doux', 'Œuvre ferroviaire, été 1982'] as $s) {
            $this->assertSame(mb_strlen($s), strlen(HeritageText::fold($s)), $s);
        }
    }

    public function test_name_tokens_drop_generic_words_plurals_and_numbers(): void
    {
        $this->assertSame(['cevenne'], HeritageText::nameTokens('Train à vapeur des Cévennes'));
        $this->assertSame(['vendee'], HeritageText::nameTokens('Chemins de fer de la Vendée 2'));
        $this->assertSame([], HeritageText::nameTokens('Train touristique'));
        // Doublons : une seule fois.
        $this->assertSame(['doux'], HeritageText::nameTokens('Doux doux'));
    }

    public function test_name_similarity_bounds(): void
    {
        $this->assertSame(1.0, HeritageText::nameSimilarity('Train à vapeur des Cévennes', 'Train des Cévennes'));
        $this->assertSame(0.0, HeritageText::nameSimilarity('Train touristique', 'Train des Cévennes'));
        $this->assertSame(0.0, HeritageText::nameSimilarity('Vivarais', 'Cévennes'));
        $this->assertSame(HeritageText::nameSimilarity('Livradois-Forez', 'Haut-Forez'), HeritageText::nameSimilarity('Haut-Forez', 'Livradois-Forez'));
    }

    public function test_sentences_split_headings_bullets_and_paragraphs(): void
    {
        $this->assertSame(
            ['La ligne ferme en 1969', 'Le train touristique circule depuis 1982', 'Vélorail ouvert en 2005', 'Fin du texte'],
            HeritageText::sentences("== Histoire ==\nLa ligne ferme en 1969. Le train touristique circule depuis 1982.\n* Vélorail ouvert en 2005\n\nFin du texte. Fin."),
        );
        // Fragments de 3 caractères ou moins (« Fin. ») ignorés.
        $this->assertSame([], HeritageText::sentences(''));
    }

    public function test_date_matches_tell_start_from_end(): void
    {
        $this->assertSame([
            ['date' => '1982-06-03', 'precision' => 'day', 'year' => 1982, 'type' => 'start', 'pos' => 39],
            ['date' => '2005-01-01', 'precision' => 'year', 'year' => 2005, 'type' => 'end', 'pos' => 60],
        ], HeritageText::dateMatches('Le train touristique circule depuis le 3 juin 1982 jusqu\'en 2005.'));
    }

    // ─── Cellules de tableaux wiki ───────────────────────────────────────

    public function test_wiki_links_of_a_cell(): void
    {
        $this->assertSame([['Ligne de Lexos à Montauban', 'Lexos - Montauban'], ['Gare de Cordes', 'Gare de Cordes']],
            HeritageText::wikiLinks('[[Ligne de Lexos à Montauban|Lexos - Montauban]] et [[Gare de Cordes#Histoire]]'));
        $this->assertSame([], HeritageText::wikiLinks('texte sans lien'));
    }

    public function test_plain_text_of_a_cell(): void
    {
        $this->assertSame('X - 12 km gras lien fin', HeritageText::plain("[[Gare de X|X]] - {{unité|12|km}}<ref>a</ref> '''gras''' [https://x.org lien]<br/>fin"));
        $this->assertSame('', HeritageText::plain('<!-- note -->{{Refnec}}'));
    }

    /** @return array<string, array{0: string, 1: array{from: ?string, to: ?string, pk_from: ?int, pk_to: ?int}}> */
    public static function sectionProvider(): array
    {
        $none = ['from' => null, 'to' => null, 'pk_from' => null, 'pk_to' => null];

        return [
            'deux lieux' => ['Anduze - Saint-Jean-du-Gard', ['from' => 'Anduze', 'to' => 'Saint-Jean-du-Gard', 'pk_from' => null, 'pk_to' => null]],
            // PK de la source entre parenthèses : pas une borne.
            'PK entre parenthèses' => ['Anduze - Saint-Jean-du-Gard (697,710 - 711,357)', ['from' => 'Anduze', 'to' => 'Saint-Jean-du-Gard', 'pk_from' => null, 'pk_to' => null]],
            'lien protégé' => ['[[Gare de Lugarde - Marchastel]] - Allanche', ['from' => 'Gare de Lugarde - Marchastel', 'to' => 'Allanche', 'pk_from' => null, 'pk_to' => null]],
            'points kilométriques' => ['km 527,5 - km 540', ['from' => null, 'to' => null, 'pk_from' => 527500, 'pk_to' => 540000]],
            'de … à …' => ['de Saint-Jean à Anduze', ['from' => 'Saint-Jean', 'to' => 'Anduze', 'pk_from' => null, 'pk_to' => null]],
            'trois lieux : extrêmes' => ['Tournon - Lamastre - Le Cheylard', ['from' => 'Tournon', 'to' => 'Le Cheylard', 'pk_from' => null, 'pk_to' => null]],
            'un seul lieu' => ['Anduze', $none],
            'vide' => ['', $none],
        ];
    }

    #[DataProvider('sectionProvider')]
    public function test_parse_section_of_a_cell(string $cell, array $expected): void
    {
        $this->assertSame($expected, HeritageText::parseSection($cell));
    }

    public function test_section_bounds_ignore_kilometre_points(): void
    {
        $this->assertSame(['Anduze', 'Saint-Jean-du-Gard'], HeritageText::sectionBounds('Anduze - Saint-Jean-du-Gard (697,710 - 711,357)'));
        $this->assertSame([null, null], HeritageText::sectionBounds('Anduze'));
    }

    /** @return array<string, array{0: string, 1: array{0: ?string, 1: ?string}}> */
    public static function sentenceSectionProvider(): array
    {
        return [
            'la section A - B' => ['Le train circule sur la section Anduze - Saint-Jean-du-Gard depuis 1982', ['Anduze', 'Saint-Jean-du-Gard']],
            'entre les gares de' => ['entre les gares de Cordesse-Igornay et Manlay, soit 10 km', ['Cordesse-Igornay', 'Manlay']],
            'le tronçon de … à …' => ['Il exploite le tronçon de Lamastre à Tournon.', ['Lamastre', 'Tournon']],
            'aucune portion' => ['aucune section', [null, null]],
        ];
    }

    #[DataProvider('sentenceSectionProvider')]
    public function test_section_in_a_sentence(string $sentence, array $expected): void
    {
        $this->assertSame($expected, HeritageText::sectionInSentence($sentence));
    }

    public function test_kind_labels(): void
    {
        $this->assertSame('vélorail', HeritageText::kindLabel('velorail'));
        $this->assertSame('musée ferroviaire roulant', HeritageText::kindLabel('museum'));
        $this->assertSame('train touristique', HeritageText::kindLabel('tourist_train'));
        $this->assertSame('train touristique', HeritageText::kindLabel('inconnu'));
    }

    public function test_infobox_operator_from_the_name_and_bounded_dates(): void
    {
        $facts = HeritageText::infoboxFacts([
            'nom' => 'Train à Vapeur des Cévennes (Sàrl CITEV)',
            'date création' => '3 juin 1982',
            'date fin' => '1970',
        ], 2026);

        $this->assertSame('1982-06-03', $facts['start']['date']);
        // Fin avant le début : écartée.
        $this->assertArrayNotHasKey('end', $facts);
        $this->assertSame('Sàrl CITEV', $facts['operator']);
        $this->assertSame([], HeritageText::infoboxFacts(['date création' => '2090', 'exploitant' => 'oui'], 2026));
    }
}
