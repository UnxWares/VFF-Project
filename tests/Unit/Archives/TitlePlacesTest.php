<?php

namespace Tests\Unit\Archives;

use App\Services\Archives\TitlePlaces;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lieux dans les titres de documents anciens : clés de comparaison et garde-fous contre les noms composés
 * (« Bourg Madame » n'est pas Bourg, « rue de Paris » n'est pas Paris).
 */
class TitlePlacesTest extends TestCase
{
    public function test_keys_fold_accents_saint_and_leading_articles(): void
    {
        $this->assertSame('angouleme', TitlePlaces::key('Angoulême'));
        $this->assertSame('havre', TitlePlaces::key('Le Havre'));
        $this->assertSame('aigle', TitlePlaces::key("L'Aigle"));
        $this->assertSame('saint junien', TitlePlaces::key('St-Junien'));
        $this->assertSame('sainte foy l argentiere', TitlePlaces::key('Ste-Foy-l’Argentière'));
        // Mots courants et installations ferroviaires : jamais des lieux.
        $this->assertNull(TitlePlaces::key('Port'));
        $this->assertNull(TitlePlaces::key('Chemin de Fer de Charente-Limousine'));
        $this->assertNull(TitlePlaces::key('la sucrerie de Chevry-Cossigny'));
        $this->assertNull(TitlePlaces::key(' - '));
    }

    public function test_query_terms_drop_the_article_and_punctuation(): void
    {
        $this->assertSame('Havre', TitlePlaces::queryTerm('Le Havre'));
        $this->assertSame('Aigle', TitlePlaces::queryTerm("L'Aigle"));
        $this->assertSame('Saint Germain des Fossés', TitlePlaces::queryTerm('Saint-Germain-des-Fossés'));
        $this->assertSame('Paris', TitlePlaces::queryTerm('"Paris"'));
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function titles(): iterable
    {
        yield 'trajet simple' => ['Chemin de fer de Paris au Hâvre', ['paris', 'havre']];
        yield 'élision' => ["Utilité du chemin de fer direct d'Angoulême à Limoges", ['angouleme', 'limoges']];
        yield 'nom composé collé' => ['Chemins de fer de Paris-Lyon-Méditerranée. Nice', []];
        yield 'nom composé séparé par une espace' => ['Chemin de fer de Villefranche à Bourg Madame', ['villefranche']];
        yield 'mot de liaison suivi d’un nom' => ['Chemin de fer de Villefranche de Conflent à Bourg', ['bourg']];
        yield 'voie urbaine' => ['[Tramway n° 17 dans la rue de Paris, arrivant place Gambetta au Havre]', ['havre']];
        yield 'précédé de Saint' => ['Gare de Saint Denis et de Rouen', ['rouen']];
        yield 'minuscule : pas un nom propre' => ['Le sens de la marche vers Paris', ['paris']];
        yield 'titre en capitales' => ['CHEMIN DE FER DE PARIS A ROUEN', ['paris', 'rouen']];
        yield 'tiret de séparation' => ['Ligne Paris - Rouen', ['paris', 'rouen']];
    }

    /** @param list<string> $expected */
    #[DataProvider('titles')]
    public function test_only_whole_place_names_are_found(string $title, array $expected): void
    {
        $keys = array_fill_keys(['paris', 'havre', 'angouleme', 'limoges', 'lyon', 'villefranche', 'bourg', 'rouen', 'denis', 'sens'], true);
        $found = TitlePlaces::find(TitlePlaces::tokens($title), $keys, 3, TitlePlaces::allCaps($title));

        $this->assertSame($expected, array_column($found, 'key'));
    }

    public function test_the_longest_known_name_wins_at_each_position(): void
    {
        $keys = ['saint germain des fosses' => true, 'saint germain' => true, 'darsac' => true];
        $found = TitlePlaces::find(TitlePlaces::tokens('Ligne de Saint-Germain-des-Fossés à Darsac'), $keys, 4);

        $this->assertSame(['saint germain des fosses', 'darsac'], array_column($found, 'key'));
    }
}
