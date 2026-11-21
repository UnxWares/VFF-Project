<?php

namespace Tests\Feature\GraphQL;

use App\GraphQL\Complexity\QueryCost;
use GraphQL\Type\Introspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Limites de complexité et de profondeur de POST /graphql (config/lighthouse.php, barème
 * App\GraphQL\Complexity\QueryCost) : les requêtes des tests et de la doc passent avec de la marge,
 * les requêtes abusives sont refusées avant toute requête SQL, avec un message qui dit quoi changer.
 */
class QueryLimitsTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    /** Coût calculé par Lighthouse pour la dernière requête (null : règle désactivée). */
    private ?int $complexity = null;

    protected function setUp(): void
    {
        parent::setUp();
        Event::listen(BuildExtensionsResponse::class, function (BuildExtensionsResponse $event): void {
            $this->complexity = $event->queryComplexity;
        });
    }

    public function test_limits_are_set(): void
    {
        $this->assertSame(5000, config('lighthouse.security.max_query_complexity'));
        $this->assertSame(15, config('lighthouse.security.max_query_depth'));
    }

    /**
     * Requêtes légitimes, avec leur coût mesuré : exemples de /docs/api et requêtes les plus coûteuses
     * des tests (la plus chère : 3 401, les lignes vertes d'une page de 100 lignes).
     *
     * @return array<string, array{string, int}>
     */
    public static function legitimateQueries(): array
    {
        return [
            'exemple de /docs/api' => ['{ line(slug: "rfn-405000") { name code events { type event_date } } }', 24],
            'exemple curl de /docs/api' => ['{ line(slug: "rfn-405000") { name code } }', 3],
            'lignes vertes de 100 lignes' => ['{ lines(first: 100) { data { slug greenway_length_m greenways { name ways { osm_way_id } } } } }', 3401],
            '100 événements et 5 images chacun' => ['{ lineEvents(first: 100) { edges { node { id type event_date media(first: 5) { edges { node { title thumb_url } } } } } } }', 2601],
            'annuaire de 100 lignes' => ['{ lineDirectory(first: 100) { data { slug name regions departements } facets { networks { code count } traffics { code count } departments { code count } } } }', 1501],
            'page par défaut (20 lignes)' => ['{ lines { data { slug name } } }', 61],
            'fiche, profil et audit d’une ligne' => ['{ line(slug: "rfn-405000") { sheet { names { name } } profile { stations { name } } audit { segments { worst_grade } } } }', 766],
        ];
    }

    #[DataProvider('legitimateQueries')]
    public function test_legitimate_queries_pass_with_their_measured_cost(string $query, int $cost): void
    {
        $this->graphQL($query)->assertGraphQLErrorFree();

        $this->assertSame($cost, $this->complexity);
        $this->assertLessThan(config('lighthouse.security.max_query_complexity'), $cost);
    }

    public function test_full_introspection_passes(): void
    {
        $this->graphQL(Introspection::getIntrospectionQuery([
            'directiveIsRepeatable' => true,
            'schemaDescription' => true,
            'inputValueDeprecation' => true,
        ]))->assertGraphQLErrorFree();
    }

    /** @return array<string, array{string, int}> */
    public static function abusiveQueries(): array
    {
        return [
            '100 fiches de ligne' => ['{ lines(first: 100) { data { sheet(geometry: true) { names { name } } } } }', 25301],
            '100 audits et profils (revue de nuit du 21/11)' => ['{ lines(first: 100) { data { audit { segments { evidence } } profile { samples { pk_m altitude_m } } } } }', 53301],
            'listes multipliées (séries, lignes, séries…)' => ['{ materials(first: 100) { edges { node { lines { materials { lines { materials { name } } } } } } } }', 1111301],
            'tracés de 100 lignes, toutes ères' => ['{ lines(first: 100) { data { segments { geojson } } } }', 10201],
        ];
    }

    #[DataProvider('abusiveQueries')]
    public function test_abusive_queries_are_refused_before_any_sql_query(string $query, int $cost): void
    {
        DB::enableQueryLog();

        $response = $this->graphQL($query);

        $this->assertSame([], array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $sql) => preg_match('/from "(lines|materials|line_segments|line_profiles)"/', $sql) === 1,
        ));
        $response->assertJsonPath('data', null);
        $this->assertSame(['code' => 'query_too_complex', 'complexity' => $cost, 'max_complexity' => 5000], $this->extensions($this->limitError($response)));
    }

    public function test_the_refusal_says_what_to_change_in_english_by_default(): void
    {
        $error = $this->limitError($this->graphQL('{ lines(first: 100) { data { sheet { names { name } } } } }'));

        $this->assertSame(
            'Query too expensive: 25301 points, the limit is 5000. Ask for fewer items per page (first), drop nested lists or heavy fields (sheet, profile, audit, route), or split the query into several.',
            $error['message'],
        );
    }

    public function test_the_refusal_follows_accept_language(): void
    {
        $query = ['query' => '{ lines(first: 100) { data { sheet { names { name } } } } }'];

        $fr = $this->limitError($this->postJson('/graphql', $query, ['Accept-Language' => 'fr-FR,fr;q=0.9']));
        $this->assertStringStartsWith('Requête trop coûteuse : 25301 points pour 5000 au plus.', $fr['message']);

        $de = $this->limitError($this->postJson('/graphql', $query, ['Accept-Language' => 'de']));
        $this->assertStringStartsWith('Abfrage zu aufwendig: 25301 Punkte, erlaubt sind höchstens 5000.', $de['message']);
    }

    public function test_too_deep_queries_get_their_own_code_and_message(): void
    {
        // Chaîne sans liste (média → ligne → média…) : profondeur 16 pour un coût modeste.
        $query = '{ mediaItem(id: 1) { '.str_repeat('event { media(first: 1) { edges { node { ', 4).'id'.str_repeat(' } } } }', 4).' } }';

        $errors = $this->graphQL($query)->json('errors');

        $this->assertCount(1, $errors);
        $this->assertSame(['code' => 'query_too_deep', 'depth' => 16, 'max_depth' => 15], $this->extensions($errors[0]));
        $this->assertSame('Query nested too deeply: 16 levels below the root, the limit is 15. Split it into several queries.', $errors[0]['message']);
    }

    public function test_other_validation_errors_are_left_untouched(): void
    {
        $this->graphQL('{ lines { data { unknown_field } } }')
            ->assertGraphQLErrorMessage('Cannot query field "unknown_field" on type "Line".');
    }

    public function test_cost_model(): void
    {
        $cost = new QueryCost;

        $this->assertSame(1 + 3 * QueryCost::LIST_SIZE, $cost->list(3, []));
        $this->assertSame(1 + 3 * 7, $cost->paginated(3, ['first' => 7]));
        $this->assertSame(1 + 3 * QueryCost::LIST_SIZE, $cost->paginated(3, []));
        $this->assertSame(QueryCost::COMPUTED + 3, $cost->computed(3, []));
        $this->assertSame(QueryCost::HEAVY + 3, $cost->heavy(3, []));
    }

    /**
     * Toute relation sans pagination ou paginée par Lighthouse sans barème déclare son coût : sans cela,
     * une nouvelle relation compterait pour un seul élément et rouvrirait les listes multipliées.
     */
    public function test_every_relation_list_of_the_schema_declares_its_cost(): void
    {
        $lines = file(base_path('graphql/schema.graphql'), FILE_IGNORE_NEW_LINES) ?: [];
        $missing = array_filter(
            $lines,
            fn (string $line) => preg_match('/\s@(hasMany|belongsToMany|morphMany|hasManyThrough|morphToMany|all)\b/', $line) === 1
                && ! str_contains($line, '@complexity('),
        );

        $this->assertSame([], array_values($missing));
    }

    /**
     * Extensions d'une erreur, sans `file` et `line` que le mode debug des tests ajoute.
     *
     * @param  array{extensions: array<string, mixed>}  $error
     * @return array<string, mixed>
     */
    private function extensions(array $error): array
    {
        return array_diff_key($error['extensions'], ['file' => true, 'line' => true]);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array{message: string, extensions: array<string, mixed>}
     */
    private function limitError(TestResponse $response): array
    {
        /** @var list<array{message: string, extensions: array<string, mixed>}> $all */
        $all = (array) $response->json('errors');
        $errors = array_values(array_filter($all, fn (array $error) => ($error['extensions']['code'] ?? null) === 'query_too_complex'));
        $this->assertCount(1, $errors, (string) $response->getContent());

        return $errors[0];
    }
}
