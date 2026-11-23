<?php

namespace Tests\Unit\Geometry;

use App\Services\Geometry\LidarPlatformTrace;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Source « plateforme LiDAR HD » : lecture du GeoJSON, tronçons fiables, lignes au format source_traces. */
class LidarPlatformTraceTest extends TestCase
{
    /**
     * @param  list<bool>  $found
     * @param  list<float|null>  $errors
     * @return array<string, mixed>
     */
    private function collection(array $found, array $errors): array
    {
        $coords = [];
        foreach (array_keys($found) as $k) {
            $coords[] = [-0.4 + $k * 0.00005, 49.2];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['line_id' => 3181, 'part' => 2, 'source' => 'lidar-hd', 'layer' => 'plateforme', 'step_m' => 5.0],
                'geometry' => ['type' => 'LineString', 'coordinates' => $coords],
                'point_properties' => [
                    'found' => $found,
                    'error_p90_m' => $errors,
                    'kind' => array_fill(0, count($found), 'remblai'),
                ],
            ]],
        ];
    }

    #[Test]
    public function reliable_runs_keep_only_long_enough_found_stretches_under_the_error_bound(): void
    {
        $found = array_merge(array_fill(0, 10, true), [false], array_fill(0, 5, true), array_fill(0, 9, true));
        $errors = array_merge(array_fill(0, 10, 1.8), [null], array_fill(0, 5, 1.8), array_fill(0, 9, 6.0));
        $trace = LidarPlatformTrace::fromArray($this->collection($found, $errors));

        $runs = $trace->reliableRuns(2.5, 8);

        $this->assertCount(1, $runs);
        $this->assertSame(0, $runs[0]['from']);
        $this->assertSame(9, $runs[0]['to']);
        $this->assertSame(45.0, $runs[0]['length_m']);
        $this->assertSame(1.8, $runs[0]['error_p90_m']);
        $this->assertCount(10, $runs[0]['coordinates']);
        // seuil d'erreur relevé : le dernier tronçon (9 points à 6 m) passe aussi
        $this->assertCount(2, $trace->reliableRuns(6.0, 8));
    }

    #[Test]
    public function coverage_counts_found_points_under_the_bound(): void
    {
        $trace = LidarPlatformTrace::fromArray($this->collection([true, true, false, true], [1.0, 5.0, null, 2.0]));

        $this->assertSame(4, $trace->pointCount());
        $this->assertSame(0.75, $trace->coverage());
        $this->assertSame(0.5, $trace->coverage(2.5));
    }

    #[Test]
    public function source_trace_rows_follow_the_source_traces_contract(): void
    {
        $trace = LidarPlatformTrace::fromArray($this->collection(array_fill(0, 8, true), array_fill(0, 8, 2.0)));

        $rows = $trace->sourceTraceRows();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('lidar-hd', $row['source']);
        $this->assertSame('plateforme', $row['layer']);
        $this->assertSame('3181-2-0', $row['source_ref']);
        $this->assertSame(3181, $row['line_id']);
        $this->assertSame(0.8, $row['match_score']);
        $this->assertSame(35, $row['length_m']);
        $this->assertStringStartsWith('MULTILINESTRING((-0.4000000 49.2000000,', $row['wkt']);
        $this->assertSame(2.0, $row['properties']['error_p90_m']);
    }

    #[Test]
    public function malformed_input_is_refused(): void
    {
        $bad = $this->collection([true, true], [1.0, 1.0]);
        $bad['features'][0]['point_properties']['found'] = [true];

        $this->expectException(InvalidArgumentException::class);
        LidarPlatformTrace::fromArray($bad);
    }

    #[Test]
    public function a_non_collection_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LidarPlatformTrace::fromArray(['type' => 'Feature']);
    }
}
