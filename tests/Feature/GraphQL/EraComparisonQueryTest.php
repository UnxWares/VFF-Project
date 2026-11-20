<?php

namespace Tests\Feature\GraphQL;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * GraphQL `eraComparison(from, to)` : même résumé et mêmes noms de champs que l'API REST
 * `GET /api/v1/eras/{year}/compare/{other}`.
 */
class EraComparisonQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const QUERY = '
        query ($from: Int!, $to: Int!) {
            eraComparison(from: $from, to: $to) {
                from to
                changes { opened { km lines } closed { km lines } removed { km lines } kept { km lines } }
                open_km { from to }
                links { self map tiles sources }
            }
        }';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $e1930 = Era::factory()->create(['year' => 1930]);
        $e1980 = Era::factory()->create(['year' => 1980]);
        Era::factory()->current()->create(['year' => 2030]);
        // Ligne => [statut en 1930, statut en 1980 (null : absente)], longueur en mètres.
        $lat = 48.4;
        foreach (['kept' => ['in_service', 'in_service', 10_000], 'closed' => ['in_service', 'abandoned', 4_000],
            'gone' => ['in_service', null, 3_000], 'new' => [null, 'in_service', 1_500]] as $name => [$before, $after, $meters]) {
            $line = Line::factory()->create(['slug' => "ligne-{$name}"]);
            $lat += 0.05;
            foreach ([[$e1930, $before], [$e1980, $after]] as [$era, $status]) {
                if ($status !== null) {
                    DB::table('line_segments')->insert([
                        'line_id' => $line->id, 'era_id' => $era->id, 'status' => $status, 'traffic_type' => 'mixed', 'length_meters' => $meters,
                        'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.0 %F, 2.6 %F)', 4326)", $lat, $lat)),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function test_comparison_matches_the_rest_summary_field_for_field(): void
    {
        $graphql = $this->graphQL(self::QUERY, ['from' => 1930, 'to' => 1980])->assertJsonMissingPath('errors')->json('data.eraComparison');
        $rest = $this->getJson('/api/v1/eras/1930/compare/1980')->assertOk()->json('data');

        $this->assertSame($rest, $graphql);
        $this->assertSame(['km' => 4, 'lines' => 1], $graphql['changes']['closed']);
        $this->assertSame(['from' => 17, 'to' => 12], $graphql['open_km']);
    }

    public function test_years_in_any_order_give_the_same_comparison(): void
    {
        $this->assertSame(
            $this->graphQL(self::QUERY, ['from' => 1930, 'to' => 1980])->json('data'),
            $this->graphQL(self::QUERY, ['from' => 1980, 'to' => 1930])->json('data'),
        );
    }

    public function test_unknown_era_and_identical_years_are_query_errors(): void
    {
        $this->graphQL(self::QUERY, ['from' => 1930, 'to' => 1935])->assertJsonPath('errors.0.message', 'Aucune ère 1935.');
        $this->graphQL(self::QUERY, ['from' => 1980, 'to' => 1980])->assertJsonPath('errors.0.message', 'Choisissez deux ères différentes à comparer.');
    }
}
