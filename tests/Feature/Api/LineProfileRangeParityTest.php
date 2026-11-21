<?php

namespace Tests\Feature\Api;

use App\Services\Profiles\GeoplateformeClient;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Référentiel d'altitude d'une ligne entre deux PK : `GET /api/v1/lines/{line}/profile/points`,
 * `LineProfile.samples` en GraphQL et `pk_from_m` / `pk_to_m` de l'outil MCP `line_profile` donnent
 * les mêmes points. Services de l'IGN simulés, comme dans LineProfilesTest.
 */
class LineProfileRangeParityTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        DB::table('line_reference_parts')->insert([
            'line_id' => $this->line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => 15_880,
            'geom' => DB::raw("(SELECT ST_Multi(geom) FROM line_segments WHERE line_id = {$this->line->id} LIMIT 1)"),
        ]);
        DB::table('station_lines')->insert([
            ['station_id' => $this->origin->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => 0, 'pk_source' => 'sncf'],
            ['station_id' => $this->destination->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => 17_250, 'pk_source' => 'sncf'],
        ]);
        DB::statement("INSERT INTO commune_contours (code_insee, nom, departement, geom) VALUES
            ('99999', 'Commune de test', '99', ST_GeomFromText('MULTIPOLYGON(((2.0 48.7, 2.5 48.7, 2.5 49.0, 2.0 49.0, 2.0 48.7)))', 4326))");
        $this->cacheDir = sys_get_temp_dir().'/vff-profil-range-test-'.bin2hex(random_bytes(4));
        $this->app->instance(GeoplateformeClient::class, new GeoplateformeClient($this->cacheDir, 0));
        Http::fake([
            'data.geopf.fr/wfs/*' => Http::response(['type' => 'FeatureCollection', 'features' => []]),
            'data.geopf.fr/altimetrie/*' => fn (Request $request) => Http::response([
                'elevations' => array_fill(0, count(explode('|', (string) $request['lon'])), 120.0),
            ]),
        ]);
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            (new Filesystem)->deleteDirectory($this->cacheDir);
        }
    }

    /** @return array<string, mixed> */
    private function mcp(array $arguments, bool $error = false): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_profile', 'arguments' => ['line' => $this->line->slug] + $arguments]])
            ->assertOk()->json('result');
        $this->assertSame($error, $result['isError'] ?? false, $result['content'][0]['text'] ?? '');

        return json_decode($result['content'][0]['text'], true) ?? [];
    }

    public function test_the_three_apis_give_the_same_points_between_two_pk(): void
    {
        $rest = $this->getJson("/api/v1/lines/{$this->line->slug}/profile/points?pk_from_m=3000&pk_to_m=6000&max_points=20")->assertOk()->json('points');
        $this->assertNotEmpty($rest);
        $this->assertLessThanOrEqual(20, count($rest));

        $graphql = $this->graphQL('{ line(slug: "'.$this->line->slug.'") { profile { samples(pk_from_m: 3000, pk_to_m: 6000, max_points: 20) { seq distance_m pk_m altitude_m source interpolated structure piece branch lat lng position_withheld } } } }')
            ->assertGraphQLErrorFree()->json('data.line.profile.samples');
        $this->assertSame(array_column($rest, 'seq'), array_column($graphql, 'seq'));
        $this->assertSame(array_column($rest, 'pk_m'), array_column($graphql, 'pk_m'));

        $mcp = $this->mcp(['pk_from_m' => 3000, 'pk_to_m' => 6000, 'max_points' => 20]);
        $this->assertSame(array_column($rest, 'seq'), array_column($mcp['profile']['samples'], 'seq'));
        $this->assertSame(array_column($rest, 'pk_m'), array_column($mcp['profile']['samples'], 'pk_m'));
        foreach ($mcp['profile']['samples'] as $sample) {
            $this->assertGreaterThanOrEqual(3000, $sample['pk_m']);
            $this->assertLessThanOrEqual(6000, $sample['pk_m']);
        }
    }

    public function test_the_range_is_optional_and_checked(): void
    {
        $this->assertArrayNotHasKey('samples', $this->mcp(['max_points' => 10])['profile']);
        $this->assertArrayHasKey('samples', $this->mcp(['pk_from_m' => 3000, 'max_points' => 10])['profile']);

        // Borne de fin avant celle de début : refusée par l'outil comme par l'API REST.
        $this->getJson("/api/v1/lines/{$this->line->slug}/profile/points?pk_from_m=6000&pk_to_m=3000")->assertUnprocessable();
        $this->mcp(['pk_from_m' => 6000, 'pk_to_m' => 3000], error: true);
    }
}
