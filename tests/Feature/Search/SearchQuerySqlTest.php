<?php

namespace Tests\Feature\Search;

use App\Models\Station;
use App\Support\Search\SearchFields;
use App\Support\Search\SearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Conditions SQL, rang et ressemblance de la recherche commune, mesurés sur des gares (une mutation par assertion). */
class SearchQuerySqlTest extends TestCase
{
    use RefreshDatabase;

    /** @var array{0: string, 1: string} */
    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keys = SearchFields::keys('station', '');
    }

    private function station(string $name, ?string $city = null): Station
    {
        // Slug fixé : celui de la fabrique contient une commune tirée au hasard, que la clé « tous les noms » indexe (un
        // test sur « pa » trouvait parfois L'Isle-Adam par le slug « gare-de-parisot-1234 »).
        return Station::factory()->create(['name' => $name, 'city' => $city ?? $name, 'alt_names' => null, 'slug' => Str::slug($name)]);
    }

    /** @return list<string> */
    private function found(string $q, bool $approximate = false, ?array $extra = null): array
    {
        $query = SearchQuery::parse($q);

        return $query->where(DB::table('stations'), $this->keys, $extra, $approximate)->pluck('name')->sort()->values()->all();
    }

    public function test_rank_goes_from_exact_to_middle_of_a_word(): void
    {
        $this->station('Paris');
        $this->station('Paris Nord');
        $this->station('Gare Paris Est');
        $this->station('Zzzville', 'Paris');
        $this->station('Hypariste');
        $query = SearchQuery::parse('paris');
        [$rank, $bindings] = $query->rankSql(...$this->keys);
        $rows = DB::table('stations')->selectRaw("name, {$rank} AS r", $bindings)->pluck('r', 'name')->all();
        $this->assertSame(0, $rows['Paris']);
        $this->assertSame(1, $rows['Paris Nord']);
        $this->assertSame(2, $rows['Gare Paris Est']);
        $this->assertSame(6, $rows['Zzzville'], 'le mot est dans un autre nom (commune)');
        $this->assertSame(7, $rows['Hypariste'], 'milieu de mot seulement');
    }

    public function test_rank_ignores_small_words_for_equality(): void
    {
        $this->station('Le Mans');
        $this->station('Mans Nord');
        [$rank, $bindings] = SearchQuery::parse('mans')->rankSql(...$this->keys);
        $rows = DB::table('stations')->selectRaw("name, {$rank} AS r", $bindings)->pluck('r', 'name')->all();
        $this->assertSame(0, $rows['Le Mans']);
        $this->assertSame(1, $rows['Mans Nord']);
    }

    public function test_rank_words_in_any_order_is_five(): void
    {
        $this->station('Hilaire Nord Saint', 'Nulle part');
        [$rank, $bindings] = SearchQuery::parse('saint hilaire')->rankSql(...$this->keys);
        $this->assertSame(5, DB::table('stations')->selectRaw("{$rank} AS r", $bindings)->value('r'));
    }

    public function test_rank_counts_the_query_without_its_generic_words_and_a_phrase_of_another_name(): void
    {
        $this->station('Paris-Nord');
        $this->station('Paris-Nord-Surface');
        $this->station('Haut Melun', 'Eisenbahnunfall von Melun');
        $this->station('Bas Melun', 'Nulle part');
        [$rank, $bindings] = SearchQuery::parse('Paris Nord station')->rankSql(...$this->keys);
        $rows = DB::table('stations')->selectRaw("name, {$rank} AS r", $bindings)->pluck('r', 'name')->all();
        $this->assertSame(0, $rows['Paris-Nord'], '« station » est facultatif : nom exact');
        $this->assertSame(1, $rows['Paris-Nord-Surface']);

        [$rank, $bindings] = SearchQuery::parse('Eisenbahnunfall von Melun')->rankSql(...$this->keys);
        $rows = DB::table('stations')->selectRaw("name, {$rank} AS r", $bindings)->pluck('r', 'name')->all();
        $this->assertSame(3, $rows['Haut Melun'], 'la requête entière est un autre nom');
        $this->assertSame(4, $rows['Bas Melun'], 'la requête sans ses mots génériques est dans le nom');
    }

    public function test_order_by_relevance_puts_the_closest_first(): void
    {
        $this->station('Gare Paris Est');
        $this->station('Paris');
        $this->station('Paris Nord');
        $names = SearchQuery::parse('paris')->orderByRelevance(DB::table('stations'), ...$this->keys)->pluck('name')->all();
        $this->assertSame(['Paris', 'Paris Nord', 'Gare Paris Est'], $names);
    }

    public function test_where_matches_a_word_start_or_inside_a_word_from_three_letters(): void
    {
        $this->station('Évires');
        $this->station('Vierzon');
        $this->assertSame(['Vierzon'], $this->found('vi'), 'deux lettres : début de mot seulement');
        $this->assertSame(['Évires'], $this->found('vire'), 'quatre lettres : dans le mot');
        $this->assertSame(['Évires'], $this->found('vir'), 'trois lettres : dans le mot');
    }

    public function test_where_requires_every_word(): void
    {
        $this->station('Saint Hilaire de Chaléons');
        $this->station('Saint Nazaire');
        $this->assertSame(['Saint Hilaire de Chaléons'], $this->found('st hilaire'));
        $this->assertSame(['Saint Hilaire de Chaléons', 'Saint Nazaire'], $this->found('saint'));
        $this->assertSame([], $this->found('saint zzz'));
        $this->assertSame([], $this->found(''), 'requête vide : rien');
        $this->assertSame([], $this->found('%'), 'joker seul : rien');
    }

    public function test_wildcards_and_quotes_are_data_not_syntax(): void
    {
        $this->station('Paris');
        $this->station("L'Isle-Adam");
        $this->assertSame(['Paris'], $this->found('pa%'), 'le % est de la ponctuation : « pa », début de mot');
        $this->assertSame([], $this->found('%ar'), 'pas un joker : « ar » ne commence aucun mot (2 lettres)');
        $this->assertSame(['Paris'], $this->found('p_ris'), 'le _ est de la ponctuation : « p » et « ris », pas un joker à un caractère');
        $this->assertSame([], $this->found('p_xx'), 'et non un joker : « xx » ne se trouve pas');
        $this->assertSame(["L'Isle-Adam"], $this->found("l'isle"));
        $this->assertSame([], $this->found("paris'; DROP TABLE stations; --"));
        $this->assertSame(1, DB::table('stations')->where('name', 'Paris')->count());
    }

    public function test_wildcards_never_widen_the_match(): void
    {
        $this->station('Abc');
        $this->station('Ab Cd');
        // Joker : « a_c » et « a%c » trouveraient Abc ; ponctuation : « a » et « c » doivent chacun commencer un mot.
        $this->assertSame(['Ab Cd'], $this->found('a_c'));
        $this->assertSame(['Ab Cd'], $this->found('a%c'));
        $this->assertSame(['Ab Cd'], $this->found('a\\c'));
        $this->assertSame([], $this->found('\\'), 'barre oblique inverse seule : rien');
        $this->assertSame([], $this->found('%%%'));
        $this->assertSame([], $this->found('_'));
        foreach (['%', '_', '\\', '%_\\', 'pa%', 'p_ris', '\\%'] as $q) {
            $query = SearchQuery::parse($q);
            foreach ($query->tokens as $alternatives) {
                foreach ($alternatives as $word) {
                    $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $word, "jeton propre pour « {$q} »");
                }
            }
            [, $bindings] = $query->matchSql('x', true);
            foreach ($bindings as $b) {
                $this->assertSame(0, preg_match('/[_\\\\]/', $b), 'aucun joker ni échappement dans les liaisons');
            }
        }
    }

    public function test_extra_match_is_or_joined_but_not_in_approximate_mode(): void
    {
        $this->station('Paris');
        $this->station('Lyon');
        $extra = ["name = 'Lyon'", []];
        $this->assertSame(['Lyon', 'Paris'], $this->found('paris', false, $extra));
        $this->assertSame(['Paris'], $this->found('paris', true, $extra), 'approchée : sans la correspondance annexe');
    }

    public function test_fuzzy_finds_typos_and_not_strangers(): void
    {
        $this->station('Angoulême');
        $this->station('Marseille');
        $this->assertSame(['Angoulême'], $this->found('angoulemme', true));
        $this->assertSame([], $this->found('zzzqqq', true));
        $this->assertSame(['Marseille'], $this->found('marseile', true));
    }

    public function test_fuzzy_looks_at_the_name_only_not_at_the_other_names(): void
    {
        Station::factory()->create(['name' => 'Zzzz', 'city' => 'Zzzz', 'alt_names' => [['name' => 'Angoulême', 'source' => 'wikidata']]]);
        $this->assertSame(['Zzzz'], $this->found('angoulem'), 'tous les mots : les autres noms comptent');
        $this->assertSame([], $this->found('angoulemme', true), 'approchée : le nom seul');
    }

    public function test_fuzzy_sets_the_session_threshold(): void
    {
        SearchQuery::parse('angoulemme')->fuzzySql('x');
        $this->assertSame((string) SearchQuery::FUZZY_THRESHOLD, DB::selectOne("SELECT current_setting('pg_trgm.word_similarity_threshold') AS v")->v);
    }

    public function test_fuzzy_order_is_most_similar_first(): void
    {
        $this->station('Angoulame');
        $this->station('Angouleme');
        $this->station('Angoulême Nord');
        $names = SearchQuery::parse('angouleme')->orderByRelevance(DB::table('stations'), ...$this->keys, approximate: true)->pluck('name')->all();
        $this->assertSame('Angouleme', $names[0]);
    }

    public function test_long_query_is_cut_and_still_runs(): void
    {
        $this->station('Paris');
        $this->assertSame(['Paris'], $this->found(str_repeat('paris ', 2000)), 'coupée à 100 caractères : 17 fois « paris »');
        $this->assertSame([], $this->found('paris '.str_repeat('x', 5000)), 'le mot de 94 lettres exigé ne se trouve pas');
    }
}
