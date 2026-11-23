<?php

namespace Tests\Unit\Search;

use App\Support\Search\SearchQuery;
use App\Support\Search\SearchText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Frontières, cas limites et abréviations de la recherche commune, sans base (mutations tuées une à une). */
class SearchQueryBoundaryTest extends TestCase
{
    public function test_maximum_length_is_one_hundred_and_cuts_exactly_there(): void
    {
        $this->assertSame(100, SearchQuery::MAX_LENGTH);
        $this->assertSame(100, mb_strlen(SearchQuery::parse(str_repeat('a', 99).'b'.str_repeat('c', 50))->raw));
        $this->assertSame(str_repeat('a', 99).'b', SearchQuery::parse(str_repeat('a', 99).'b'.'zzz')->raw);
        $this->assertSame(str_repeat('a', 100), SearchQuery::parse(str_repeat('a', 100))->raw, 'exactement 100 : gardé entier');
    }

    public function test_cut_is_by_characters_not_bytes(): void
    {
        $query = SearchQuery::parse(str_repeat('é', 150));
        $this->assertSame(100, mb_strlen($query->raw));
        $this->assertSame(' '.str_repeat('e', 100), $query->key);
    }

    public function test_empty_and_punctuation_only_queries(): void
    {
        foreach (['', ' ', '---', "' ' '", '%', '_', '()'] as $raw) {
            $query = SearchQuery::parse($raw);
            $this->assertTrue($query->isEmpty(), "« {$raw} »");
            $this->assertSame('', $query->key);
            $this->assertSame('', $query->fuzzyText());
            $this->assertFalse($query->canBeFuzzy());
            $this->assertSame(['FALSE', []], $query->matchSql('k'));
        }
        $this->assertFalse(SearchQuery::parse('a')->isEmpty());
    }

    public function test_only_small_words_are_kept_as_words(): void
    {
        $this->assertSame([['la']], SearchQuery::parse('de la')->tokens, 'seul le dernier petit mot reste');
        $this->assertSame([['le']], SearchQuery::parse('le')->tokens);
    }

    #[DataProvider('abbreviations')]
    public function test_each_abbreviation_is_expanded_and_the_last_one_also_kept(string $short, string $long): void
    {
        $this->assertSame(' '.$long, SearchText::key($short));
        $this->assertSame([[$long, $short]], SearchQuery::parse($short)->tokens, 'en dernier : développée et telle quelle');
        $optional = in_array($long, SearchText::STOPWORDS, true) || in_array($long, SearchText::GENERIC_WORDS, true);
        $this->assertSame($optional ? [['paris']] : [[$long], ['paris']], SearchQuery::parse("{$short} paris")->tokens, 'ailleurs : développée seulement (un petit mot ou un mot générique tombe)');
        $this->assertSame(' un'.' '.$long.' x', SearchText::key("un {$short} x"));
        $this->assertSame(' '.$short.'a', SearchText::key("{$short}a"), 'jamais au milieu d’un mot');
        $this->assertSame(' a'.$short, SearchText::key("a{$short}"), 'jamais au milieu d’un mot');
    }

    /** @return iterable<string, array{string, string}> */
    public static function abbreviations(): iterable
    {
        foreach (['st' => 'saint', 'ste' => 'sainte', 'sts' => 'saints', 'stes' => 'saintes', 'gd' => 'grand', 'gde' => 'grande',
            'mt' => 'mont', 'ft' => 'fort', 'g' => 'gare', 's' => 'sur', 'lez' => 'les'] as $short => $long) {
            yield $short => [$short, $long];
        }
    }

    public function test_the_abbreviation_table_is_written_out(): void
    {
        $this->assertSame([
            'n d' => 'notre dame', 's s' => 'sous', 'st' => 'saint', 'ste' => 'sainte', 'sts' => 'saints', 'stes' => 'saintes',
            'gd' => 'grand', 'gde' => 'grande', 'mt' => 'mont', 'ft' => 'fort', 'g' => 'gare', 's' => 'sur', 'lez' => 'les',
        ], SearchText::ABBREVIATIONS);
    }

    public function test_slash_abbreviations_need_a_word_boundary(): void
    {
        $this->assertSame(' sur sx', SearchText::key('s/sx'), '« s/sx » : s/ puis sx, pas s/s');
        $this->assertSame(' abs x', SearchText::key('abs/x'), 'pas d’abréviation au milieu d’un mot');
        $this->assertSame(' abs sous', SearchText::key('abs s/s'));
        $this->assertSame(' sous bois', SearchText::key('s/s bois'));
        $this->assertSame(' sous', SearchText::key('(s/s)'));
        $this->assertSame(' x sous', SearchText::key('x-s/s'));
        $this->assertSame(' sur seine', SearchText::key('s/seine'));
    }

    public function test_abbreviation_written_in_full_is_not_doubled(): void
    {
        $this->assertSame([['saint']], SearchQuery::parse('saint')->tokens);
        $this->assertSame([['grand']], SearchQuery::parse('grand')->tokens);
    }

    public function test_abbreviations_can_be_kept(): void
    {
        $this->assertSame(' st gd', SearchText::key('St Gd', false));
        $this->assertSame(' saint grand', SearchText::key('St Gd', true));
    }

    public function test_notre_dame_and_slash_forms(): void
    {
        $this->assertSame(' notre dame', SearchText::key('N.D.'));
        $this->assertSame(' notre dame', SearchText::key('N-D'));
        $this->assertSame(' sous', SearchText::key('s/s'));
        $this->assertSame(' ss', SearchText::key('ss'));
        $this->assertSame(' sur', SearchText::key('s/'));
        $this->assertSame(' a sous', SearchText::key('a s/s'), '« a s/s » lu « a sous »');
        $this->assertSame(' abs', SearchText::key('abs'), 'pas de « s » isolé dans abs');
        $this->assertSame(' sous x', SearchText::key('S/S x'));
        $this->assertSame(' sur x', SearchText::key('s/x'), 's/x : sur x');
    }

    public function test_null_and_empty_keys(): void
    {
        $this->assertSame('', SearchText::key(null));
        $this->assertSame('', SearchText::key(''));
        $this->assertSame('', SearchText::core(''));
        $this->assertSame('', SearchText::core(' '));
        $this->assertSame('', SearchText::core(' de la du'));
    }

    public function test_unicode_and_exotic_input(): void
    {
        $this->assertSame(' saint denis', SearchText::key('Saint‑Denis'), 'trait d’union insécable');
        $this->assertSame(' strasse', SearchText::key('Straße'));
        $this->assertSame(' cote d azur', SearchText::key('CÔTE D’AZUR'));
        $this->assertSame(' zurich', SearchText::key('Zürich'));
        $this->assertSame(' tokyo', SearchText::key('Tokyo'));
        $this->assertSame('', SearchText::key('東京'), 'sans lettres latines : vide, sans erreur');
        $this->assertSame('', SearchText::key("\u{0000}\u{200B}"));
        $this->assertSame(' a b', SearchText::key("a\u{200B}b"), 'espace de largeur nulle : séparateur');
    }

    public function test_letter_digit_boundaries(): void
    {
        $this->assertSame(' bb 7600', SearchText::key('BB7600'));
        $this->assertSame(' 7600 bb', SearchText::key('7600BB'));
        $this->assertSame(' a 1 b 2', SearchText::key('A1B2'));
        $this->assertSame(' 12 34', SearchText::key('12-34'));
        $this->assertSame(' 1 e', SearchText::key('1e'));
    }

    public function test_core_keeps_order_and_leading_space(): void
    {
        $this->assertSame(' mans paris', SearchText::core(' le mans de paris'));
        foreach (SearchText::STOPWORDS as $stop) {
            $this->assertSame('', SearchText::core(" {$stop}"), $stop);
            $this->assertSame(' x', SearchText::core(" {$stop} x"), $stop);
        }
        $this->assertSame(' lez', SearchText::core(' lez'), '« lez » n’est pas un petit mot');
    }

    public function test_stopwords_contract(): void
    {
        $this->assertSame(['de', 'du', 'des', 'd', 'la', 'le', 'les', 'l', 'en', 'et', 'a', 'au', 'aux'], SearchText::STOPWORDS);
    }

    public function test_match_sql_anywhere_threshold_is_three_letters(): void
    {
        $this->assertSame(['% vi%'], SearchQuery::parse('vi')->matchSql('k', anywhere: true)[1]);
        $this->assertSame(['%vir%'], SearchQuery::parse('vir')->matchSql('k', anywhere: true)[1]);
        $this->assertSame(['%vire%'], SearchQuery::parse('vire')->matchSql('k', anywhere: true)[1]);
        $this->assertSame(['% vir%'], SearchQuery::parse('vir')->matchSql('k')[1], 'sans anywhere : début de mot');
    }

    public function test_match_sql_with_alternatives_or_joins_them(): void
    {
        [$sql, $bindings] = SearchQuery::parse('st')->matchSql('k');
        $this->assertSame('(k LIKE ? OR k LIKE ?)', $sql);
        $this->assertSame(['% saint%', '% st%'], $bindings);
    }

    public function test_digits_accepts_only_digits_and_spaces(): void
    {
        $this->assertSame('87481234', SearchQuery::parse(' 87481234 ')->digits());
        $this->assertSame('1', SearchQuery::parse('1')->digits());
        $this->assertSame('123', SearchQuery::parse("1 2\t3")->digits());
        $this->assertNull(SearchQuery::parse('')->digits());
        $this->assertNull(SearchQuery::parse('   ')->digits());
        $this->assertNull(SearchQuery::parse('12a')->digits());
        $this->assertNull(SearchQuery::parse('12-3')->digits());
    }

    public function test_can_be_fuzzy_needs_three_consecutive_letters(): void
    {
        $this->assertFalse(SearchQuery::parse('ab')->canBeFuzzy());
        $this->assertFalse(SearchQuery::parse('a b c')->canBeFuzzy());
        $this->assertTrue(SearchQuery::parse('abc')->canBeFuzzy());
        $this->assertTrue(SearchQuery::parse('12 abc')->canBeFuzzy());
    }

    public function test_fuzzy_text_uses_the_core_then_the_key(): void
    {
        $this->assertSame('mans', SearchQuery::parse('Le Mans')->fuzzyText());
        $this->assertSame('de la', SearchQuery::parse('de la')->fuzzyText(), 'que des petits mots : la clé entière');
    }

    public function test_core_property(): void
    {
        $this->assertSame(' mans', SearchQuery::parse('Le Mans')->core);
        $this->assertSame(' le mans', SearchQuery::parse('Le Mans')->key);
    }

    public function test_threshold_value(): void
    {
        $this->assertSame(0.6, SearchQuery::FUZZY_THRESHOLD);
    }
}
