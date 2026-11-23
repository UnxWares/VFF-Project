<?php

namespace Tests\Unit\Search;

use App\Support\Search\SearchHighlight;
use App\Support\Search\SearchQuery;
use PHPUnit\Framework\TestCase;

/** Passage trouvé dans un nom (page de résultats) : même lecture que la recherche (SearchText::key). */
class SearchHighlightTest extends TestCase
{
    /** Nom rendu avec les passages marqués entre crochets. */
    private function marked(string $name, string $query): string
    {
        return implode('', array_map(
            fn (array $s): string => $s['mark'] ? "[{$s['text']}]" : $s['text'],
            SearchHighlight::segments($name, SearchQuery::parse($query)),
        ));
    }

    public function test_word_starts_accents_and_abbreviations(): void
    {
        $this->assertSame('[St]-[Hilaire]-de-[C]haléons', $this->marked('St-Hilaire-de-Chaléons', 'saint hilaire de c'));
        $this->assertSame('[Saint]-[Hilaire]-de-[C]haléons', $this->marked('Saint-Hilaire-de-Chaléons', 'st hilaire c'));
        $this->assertSame('[Bréti]gny-sur-Orge', $this->marked('Brétigny-sur-Orge', 'breti'));
        $this->assertSame('[St]rasbourg', $this->marked('Strasbourg', 'st'));
    }

    public function test_middle_of_word_letters_and_digits(): void
    {
        $this->assertSame('É[vire]s', $this->marked('Évires', 'vire'));
        $this->assertSame('[BB] [7600]', $this->marked('BB 7600', 'bb7600'));
        $this->assertSame('[BB][7600]', $this->marked('BB7600', 'bb 7600'));
    }

    public function test_nothing_found_or_empty_query(): void
    {
        $this->assertSame([['text' => 'Lyon', 'mark' => false]], SearchHighlight::segments('Lyon', SearchQuery::parse('paris')));
        $this->assertSame([['text' => 'Lyon', 'mark' => false]], SearchHighlight::segments('Lyon', SearchQuery::parse('')));
        // Petit mot facultatif de la requête (« le », pas en dernier) : pas marqué.
        $this->assertSame('Le [Mans]', $this->marked('Le Mans', 'le mans'));
    }
}
