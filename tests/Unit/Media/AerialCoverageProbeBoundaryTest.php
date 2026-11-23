<?php

namespace Tests\Unit\Media;

use App\Services\Media\Aerial\AerialCoverageProbe;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Cas limites de la sonde de couverture IGN : groupes de tailles impaires, réponses vides ou mal formées, trois essais,
 * attributs lus par période, format des points, aucune requête pour une liste vide.
 */
class AerialCoverageProbeBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        Http::preventStrayRequests();
    }

    /** @return array<string, mixed> */
    private static function features(array ...$properties): array
    {
        return ['type' => 'FeatureCollection', 'features' => array_map(fn (array $p): array => ['type' => 'Feature', 'properties' => $p], $properties)];
    }

    public function test_an_empty_list_sends_nothing(): void
    {
        $probe = new AerialCoverageProbe;

        $this->assertSame([], $probe->probe('1950-1965', []));
        $this->assertSame(0, $probe->requests);
        Http::assertNothingSent();
    }

    public function test_one_point_covered_gives_the_date_and_the_mission_of_its_period(): void
    {
        $body = self::features(['nom' => 'M1', 'begin_date' => '1960-05-31Z', 'pva' => 'x', 'date_vol' => 'y']);
        Http::fake(function () use (&$body) {
            return Http::response($body);
        });
        $probe = new AerialCoverageProbe;

        $old = $probe->probe('1950-1965', [[7, 45.0, 1.0]]);
        $this->assertSame([7 => ['covered' => true, 'date' => '1960-05-31Z', 'pva' => 'M1']], $old);

        $body = self::features(['pva' => 1957, 'date_vol' => '1970-01-02Z', 'nom' => 'z', 'begin_date' => 'w']);
        $mid = $probe->probe('1965-1980', [[8, 45.0, 1.0]]);
        $this->assertSame([8 => ['covered' => true, 'date' => '1970-01-02Z', 'pva' => 1957]], $mid);
    }

    public function test_one_point_not_covered_and_a_missing_attribute(): void
    {
        $probe = new AerialCoverageProbe;
        $body = self::features();
        Http::fake(function () use (&$body) {
            return Http::response($body);
        });
        $this->assertSame([1 => ['covered' => false, 'date' => null, 'pva' => null]], $probe->probe('1980-1995', [[1, 45.0, 1.0]]));

        $body = self::features([]);
        $this->assertSame([2 => ['covered' => true, 'date' => null, 'pva' => null]], $probe->probe('1980-1995', [[2, 45.0, 1.0]]));
    }

    public function test_a_group_with_an_empty_answer_costs_one_request_and_covers_nobody(): void
    {
        Http::fake(['*' => Http::response(self::features())]);
        $probe = new AerialCoverageProbe;

        $res = $probe->probe('1980-1995', [[1, 1.0, 1.0], [2, 2.0, 2.0], [3, 3.0, 3.0]]);

        $this->assertSame([1, 2, 3], array_keys($res));
        $this->assertSame([false, false, false], array_column($res, 'covered'));
        $this->assertSame(1, $probe->requests);
    }

    public function test_an_odd_group_is_split_in_a_half_and_the_rest(): void
    {
        // Points 1 à 3 : seul le 2 est couvert. 3 points : 1 requête, puis [1] (1) et [2, 3] (1), puis [2] et [3] (2).
        Http::fake(fn (Request $r) => Http::response(str_contains(urldecode($r->url()), 'POINT(2.000000 2.000000)') ? self::features(['pva' => 1]) : self::features()));
        $probe = new AerialCoverageProbe;

        $res = $probe->probe('1980-1995', [[1, 1.0, 1.0], [2, 2.0, 2.0], [3, 3.0, 3.0]]);

        $this->assertSame([1 => false, 2 => true, 3 => false], array_map(fn ($r) => $r['covered'], $res));
        $this->assertSame(5, $probe->requests);
    }

    public function test_the_odd_group_splits_with_the_smaller_half_first(): void
    {
        // Seul le point 3 est couvert : [1] seul (1 requête), [2, 3] (1), puis [2] et [3] (2), plus la racine : 5 requêtes.
        Http::fake(fn (Request $r) => Http::response(str_contains(urldecode($r->url()), 'POINT(3.000000 3.000000)') ? self::features(['pva' => 1]) : self::features()));
        $probe = new AerialCoverageProbe;

        $res = $probe->probe('1980-1995', [[1, 1.0, 1.0], [2, 2.0, 2.0], [3, 3.0, 3.0]]);

        $this->assertSame([1 => false, 2 => false, 3 => true], array_map(fn ($r) => $r['covered'], $res));
        $this->assertSame(5, $probe->requests);
    }

    public function test_ids_are_kept_whatever_they_are_and_points_use_longitude_then_latitude_with_six_decimals(): void
    {
        Http::fake(['*' => Http::response(self::features())]);

        (new AerialCoverageProbe)->probe('1950-1965', [[42, 45.123456789, -1.5], [0, 0.0, 0.0]]);

        Http::assertSent(function (Request $r): bool {
            $filter = urldecode($r->url());

            return str_contains($filter, 'INTERSECTS(geom,SRID=4326;POINT(-1.500000 45.123457)) OR INTERSECTS(geom,SRID=4326;POINT(0.000000 0.000000))');
        });
    }

    public function test_each_period_has_its_layer_geometry_and_attributes(): void
    {
        Http::fake(['*' => Http::response(self::features())]);
        $probe = new AerialCoverageProbe;
        foreach (AerialCoverageProbe::LAYERS as $period => [$layer, $geom, $mission, $date]) {
            $probe->probe($period, [[1, 45.0, 1.0]]);
            Http::assertSent(function (Request $r) use ($layer, $geom, $mission, $date): bool {
                $url = urldecode($r->url());

                return str_contains($url, 'TYPENAMES='.$layer) && str_contains($url, "INTERSECTS({$geom},") && str_contains($url, "propertyName={$mission},{$date}")
                    && preg_match('/count=1(&|$)/', $url) === 1 && str_contains($url, 'outputFormat=application/json');
            });
        }
        $this->assertSame(['1950-1965', '1965-1980', '1980-1995'], array_keys(AerialCoverageProbe::LAYERS));
    }

    public function test_an_error_is_retried_three_times_then_the_point_is_unknown(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $probe = new AerialCoverageProbe;

        $this->assertSame([5 => null], $probe->probe('1950-1965', [[5, 45.0, 1.0]]));
        $this->assertSame(3, $probe->requests);
        $this->assertSame(3, $probe->errors);
        Sleep::assertSleptTimes(3);
    }

    public function test_a_failing_group_makes_every_point_unknown_without_splitting(): void
    {
        Http::fake(['*' => Http::response('boom', 503)]);
        $probe = new AerialCoverageProbe;

        $res = $probe->probe('1950-1965', [[1, 1.0, 1.0], [2, 2.0, 2.0], [3, 3.0, 3.0]]);

        $this->assertSame([1 => null, 2 => null, 3 => null], $res);
        $this->assertSame(3, $probe->requests);
    }

    public function test_a_success_after_one_failure_counts_one_error_and_two_requests(): void
    {
        Http::fakeSequence()->push('boom', 500)->push(self::features(['pva' => 9]));
        $probe = new AerialCoverageProbe;

        $res = $probe->probe('1980-1995', [[1, 45.0, 1.0]]);

        $this->assertTrue($res[1]['covered']);
        $this->assertSame([2, 1], [$probe->requests, $probe->errors]);
    }

    public function test_a_body_that_is_not_a_feature_collection_is_an_error_not_a_coverage(): void
    {
        $body = '';
        Http::fake(function () use (&$body) {
            return Http::response($body, 200, ['Content-Type' => 'application/json']);
        });
        foreach (['not json', '[]', '{}', '{"features": "x"}', '{"features": null}'] as $body) {
            $probe = new AerialCoverageProbe;

            $this->assertSame([1 => null], $probe->probe('1980-1995', [[1, 45.0, 1.0]]), $body);
            $this->assertSame(1, $probe->errors, $body);
        }
    }

    public function test_features_without_properties_still_count_as_covered(): void
    {
        Http::fake(['*' => Http::response(['features' => [['type' => 'Feature'], ['properties' => 'x']]])]);

        $res = (new AerialCoverageProbe)->probe('1980-1995', [[1, 45.0, 1.0]]);

        $this->assertSame([1 => ['covered' => true, 'date' => null, 'pva' => null]], $res);
    }

    public function test_the_received_bytes_are_added_up(): void
    {
        Http::fake(['*' => Http::response(self::features())]);
        $probe = new AerialCoverageProbe;

        $probe->probe('1980-1995', [[1, 45.0, 1.0]]);
        $first = $probe->bytes;
        $probe->probe('1980-1995', [[2, 45.0, 1.0]]);

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first * 2, $probe->bytes);
    }

    public function test_the_user_agent_is_the_projects_honest_one(): void
    {
        Http::fake(['*' => Http::response(self::features())]);

        (new AerialCoverageProbe)->probe('1980-1995', [[1, 45.0, 1.0]]);

        Http::assertSent(fn (Request $r): bool => str_contains($r->header('User-Agent')[0] ?? '', 'VFF'));
    }
}
