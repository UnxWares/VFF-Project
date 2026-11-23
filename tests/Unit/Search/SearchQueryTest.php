<?php

namespace Tests\Unit\Search;

use App\Support\Search\SearchQuery;
use App\Support\Search\SearchText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Normalisation PHP de la recherche (SearchText) et lecture d'une requête (SearchQuery), sans base. */
class SearchQueryTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function keys(): array
    {
        return [
            'tirets et accents' => ['St-Hilaire-de-Chaléons', ' saint hilaire de chaleons'],
            'saint en toutes lettres' => ['Saint Hilaire de Chaleons', ' saint hilaire de chaleons'],
            'sainte' => ['Ste-Pazanne', ' sainte pazanne'],
            'saints, saintes' => ['Sts-Geosmes Stes-Maries', ' saints geosmes saintes maries'],
            'notre-dame abrégé' => ['N.-D.-de-Riez', ' notre dame de riez'],
            'sous abrégé' => ['Fontenay-s/s-Bois', ' fontenay sous bois'],
            'sur abrégé' => ['Ste-Colombe-s/-Seine', ' sainte colombe sur seine'],
            'sur abrégé collé' => ['Aixe s/Vienne', ' aixe sur vienne'],
            'lès et lez' => ['Woelfling-lez-Sarreguemines Villeneuve-lès-Béziers', ' woelfling les sarreguemines villeneuve les beziers'],
            'grand, mont, fort' => ['Gd-Mt-Ft Gde', ' grand mont fort grande'],
            'apostrophes droite et typographique' => ["L'Isle-Adam Mont d’Est", ' l isle adam mont d est'],
            'ligature' => ['Wœlfling', ' woelfling'],
            'lettres et chiffres' => ['BB7600 Z 5300', ' bb 7600 z 5300'],
            'espaces multiples' => ["  Paris \t  Nord  ", ' paris nord'],
            'vide' => ['  - ', ''],
        ];
    }

    #[DataProvider('keys')]
    public function test_key(string $text, string $key): void
    {
        $this->assertSame($key, SearchText::key($text));
    }

    public function test_core_drops_small_words(): void
    {
        $this->assertSame(' mans', SearchText::core(SearchText::key('Le Mans')));
        $this->assertSame(' pierre albigny', SearchText::core(SearchText::key("Pierre-d'Albigny")));
        $this->assertSame('', SearchText::core(SearchText::key('de la')));
    }

    public function test_small_words_are_optional_except_the_last_one(): void
    {
        $this->assertSame([['saint'], ['hilaire'], ['c']], SearchQuery::parse('saint hilaire de c')->tokens);
        $this->assertSame([['saint'], ['hilaire'], ['de']], SearchQuery::parse('saint hilaire de')->tokens);
        $this->assertSame([['le']], SearchQuery::parse('le')->tokens);
    }

    public function test_last_abbreviation_also_counts_as_itself(): void
    {
        $this->assertSame([['saint', 'st']], SearchQuery::parse('st')->tokens);
        $this->assertSame([['saint'], ['hilaire']], SearchQuery::parse('st hilaire')->tokens);
    }

    public function test_digits(): void
    {
        $this->assertSame('570000', SearchQuery::parse('570 000')->digits());
        $this->assertNull(SearchQuery::parse('bb 7600')->digits());
        $this->assertFalse(SearchQuery::parse('570 000')->canBeFuzzy());
        $this->assertFalse(SearchQuery::parse('12-7-2013')->canBeFuzzy());
        $this->assertTrue(SearchQuery::parse('angoulemme')->canBeFuzzy());
    }

    public function test_match_requires_every_word_at_a_word_start(): void
    {
        [$sql, $bindings] = SearchQuery::parse('chaleons st hil')->matchSql('k');
        $this->assertSame('(k LIKE ?) AND (k LIKE ?) AND (k LIKE ?)', $sql);
        $this->assertSame(['% chaleons%', '% saint%', '% hil%'], $bindings);
        $this->assertSame(['FALSE', []], SearchQuery::parse('%')->matchSql('k'));
    }

    public function test_long_query_is_cut_to_the_maximum_length(): void
    {
        // Une adresse de 6 000 caractères donnait 1 500 conditions LIKE par catégorie (7 s pour /stations).
        $words = implode(' ', array_map(fn (int $i) => 'mot'.chr(97 + $i % 26).chr(97 + intdiv($i, 26) % 26), range(1, 1500)));
        $search = SearchQuery::parse($words);
        $this->assertSame(SearchQuery::MAX_LENGTH, mb_strlen($search->raw));
        $this->assertLessThanOrEqual(intdiv(SearchQuery::MAX_LENGTH, 2), count($search->tokens));
        $this->assertSame(SearchQuery::parse(mb_substr($words, 0, SearchQuery::MAX_LENGTH))->tokens, $search->tokens);
        $this->assertSame([['saint'], ['hilaire']], SearchQuery::parse('St-Hilaire')->tokens, 'requête courte inchangée');
    }

    public function test_generic_words_are_optional_even_last_unless_alone(): void
    {
        $this->assertSame([['paris'], ['nord']], SearchQuery::parse('Paris Nord station')->tokens);
        $this->assertSame([['lagny']], SearchQuery::parse('Lagny rail accident')->tokens);
        $this->assertSame([['melun']], SearchQuery::parse('Eisenbahnunfall von Melun')->tokens);
        $this->assertSame([['gare']], SearchQuery::parse('gare')->tokens, 'seul, le mot générique est exigé');
        $this->assertSame([['die']], SearchQuery::parse('gare de Die')->tokens, 'Die est une gare, pas un article');
    }

    public function test_foreign_city_names_also_count_as_the_french_name(): void
    {
        $this->assertSame([['strassburg', 'strasbourg']], SearchQuery::parse('Bahnhof Straßburg')->tokens);
        $this->assertSame([['mulhausen', 'mulhouse']], SearchQuery::parse('Mülhausen')->tokens);
        $this->assertSame('strasbourg', SearchQuery::parse('Bahnhof Straßburg')->fuzzyText());
    }

    public function test_digits_ignore_generic_words(): void
    {
        $this->assertSame('830000', SearchQuery::parse('ligne 830 000')->digits());
        $this->assertSame('87686006', SearchQuery::parse('UIC 87686006')->digits());
        $this->assertNull(SearchQuery::parse('ligne')->digits());
        $this->assertNull(SearchQuery::parse('linea 830000')->digits(), 'seuls les mots génériques entiers tombent');
    }

    public function test_split_year_keeps_the_other_words(): void
    {
        [$year, $rest] = SearchQuery::parse('Lagny 1933')->splitYear();
        $this->assertSame(1933, $year);
        $this->assertSame([['lagny']], $rest->tokens);
        [$year, $rest] = SearchQuery::parse('accident 1933')->splitYear();
        $this->assertSame(1933, $year);
        $this->assertTrue($rest->isEmpty(), 'rien d\'autre : tous les accidents de l\'année');
        $this->assertNull(SearchQuery::parse('Z 5300')->splitYear()[0], 'hors des années 1800 à 2099');
        $this->assertNull(SearchQuery::parse('antenne de hessange')->splitYear()[0]);
    }

    public function test_rank_keys_follows_the_sql_rank(): void
    {
        $q = SearchQuery::parse('tvm 430');
        $this->assertSame(0, $q->rankKeys([SearchText::key('TVM 430')], SearchText::key('TVM 430 tvm 430')));
        $this->assertSame(1, $q->rankKeys([SearchText::key('TVM 430 numérique')], SearchText::key('TVM 430 numérique')));
        $this->assertSame(5, SearchQuery::parse('430 tvm')->rankKeys([SearchText::key('TVM 430')], SearchText::key('TVM 430')));
        $this->assertSame(3, SearchQuery::parse('block automatique')->rankKeys([SearchText::key('BAL')], SearchText::key('BAL Block automatique lumineux')), 'dans le développé');
        $this->assertNull($q->rankKeys([SearchText::key('KVB')], SearchText::key('KVB Contrôle de vitesse par balises')));
    }
}
