<?php

namespace Tests\Feature\GraphQL;

use App\GraphQL\Complexity\QueryCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * `Query.mediaFacets` lance quatre agrégats sur toute la table des médias (environ 130 ms sur la base
 * de développement, 71 000 médias). Compté comme un champ calculé (10 points), il passait 450 fois
 * dans une seule requête par des alias (100 alias : 15 s mesurées le 21/11/2026). Il compte donc
 * comme un champ lourd : une vingtaine d'appels au plus par requête.
 */
class MediaFacetsCostTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private ?int $complexity = null;

    protected function setUp(): void
    {
        parent::setUp();
        Event::listen(BuildExtensionsResponse::class, function (BuildExtensionsResponse $event): void {
            $this->complexity = $event->queryComplexity;
        });
    }

    public function test_one_call_costs_as_a_heavy_field(): void
    {
        $this->graphQL('{ mediaFacets { total } }')->assertGraphQLErrorFree();

        $this->assertSame(QueryCost::HEAVY + 1, $this->complexity);
    }

    public function test_many_aliased_calls_are_refused_before_any_sql_query(): void
    {
        $query = '{ '.implode(' ', array_map(fn (int $i) => "a{$i}: mediaFacets { total }", range(1, 30))).' }';
        DB::enableQueryLog();

        $response = $this->graphQL($query);

        $this->assertSame([], array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $sql) => str_contains($sql, '"media"'),
        ));
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('errors.0.extensions.code', 'query_too_complex');
        $response->assertJsonPath('errors.0.extensions.complexity', 30 * (QueryCost::HEAVY + 1));
    }
}
