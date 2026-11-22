<?php

namespace Tests\Feature\GraphQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Corps et lots de /graphql et /mcp (App\Http\Middleware\LimitApiRequest).
 *
 * Avant : un lot `[{query}, {query}, ...]` exécutait chaque requête sans autre limite que le débit
 * (40 `mediaFacets` en un appel, 8 secondes), et un corps de 8 Mio était analysé en entier.
 */
class RequestLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_batch_of_graphql_queries_is_refused_before_any_query_runs(): void
    {
        DB::enableQueryLog();
        $batch = array_fill(0, 40, ['query' => '{ mediaFacets { total } }']);

        $this->postJson('/graphql', $batch)
            ->assertStatus(400)
            ->assertJsonPath('errors.0.extensions.code', 'batch_too_large')
            ->assertJsonPath('errors.0.extensions.max_batch', 5);

        $this->assertSame([], array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'media')));
    }

    public function test_a_small_batch_still_runs(): void
    {
        $this->postJson('/graphql', array_fill(0, 5, ['query' => '{ __typename }']))->assertOk()->assertJsonCount(5);
        $this->postJson('/graphql', array_fill(0, 6, ['query' => '{ __typename }']))->assertStatus(400);
    }

    public function test_a_single_operation_still_runs_alone_or_in_a_list_of_one(): void
    {
        $this->postJson('/graphql', ['query' => '{ __typename }'])->assertOk()->assertJsonPath('data.__typename', 'Query');
        $this->postJson('/graphql', [['query' => '{ __typename }']])->assertOk();
    }

    public function test_an_oversized_body_is_refused_with_413(): void
    {
        config(['api.max_body_bytes' => 2048]);
        $query = '{ __typename } #'.str_repeat('a', 4000);

        $this->postJson('/graphql', ['query' => $query])
            ->assertStatus(413)
            ->assertJsonPath('errors.0.extensions.code', 'body_too_large')
            ->assertJsonPath('errors.0.extensions.max_body_bytes', 2048);
    }

    public function test_the_mcp_server_refuses_an_oversized_body_in_json_rpc_form(): void
    {
        config(['api.max_body_bytes' => 2048]);

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => ['x' => str_repeat('a', 4000)]])
            ->assertStatus(413)
            ->assertJsonPath('error.code', -32600);
    }

    public function test_a_normal_mcp_request_is_unaffected(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream'])
            ->assertOk();
    }
}
