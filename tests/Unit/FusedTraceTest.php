<?php

namespace Tests\Unit;

use App\Services\Geometry\FusedTrace;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Morceaux de tracé fusionnés : un morceau n'est « précis » que sous 3 m d'erreur estimée, jamais au-delà.
 */
class FusedTraceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function feature(array $props, array $coords = [[-0.37, 49.25], [-0.36, 49.26]]): array
    {
        return ['type' => 'Feature', 'properties' => $props, 'geometry' => ['type' => 'LineString', 'coordinates' => $coords]];
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @return array<string, mixed>
     */
    private function collection(array $features): array
    {
        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    public function test_pieces_become_source_trace_rows(): void
    {
        $trace = FusedTrace::fromArray($this->collection([
            $this->feature(['ligne' => 3181, 'chaine' => 0, 'classe' => 'precis', 'erreur_estimee_max_m' => 2.4, 'debut_m' => 0, 'fin_m' => 900]),
            $this->feature(['ligne' => 3181, 'chaine' => 0, 'classe' => 'estime', 'erreur_estimee_max_m' => 7.5, 'debut_m' => 900.4, 'fin_m' => 1500]),
        ]));
        $rows = $trace->rows();

        $this->assertCount(2, $rows);
        $this->assertSame(['fusion', '3181-0-0', 'precis', 3181], [$rows[0]['source'], $rows[0]['source_ref'], $rows[0]['layer'], $rows[0]['line_id']]);
        $this->assertSame('3181-0-900', $rows[1]['source_ref']);
        $this->assertSame('estime', $rows[1]['layer']);
        $this->assertSame(2.4, json_decode($rows[0]['properties'], true)['erreur_p90_m']);
        $geo = json_decode($rows[0]['geojson'], true);
        $this->assertSame('MultiLineString', $geo['type']);
        $this->assertSame([[-0.37, 49.25], [-0.36, 49.26]], $geo['coordinates'][0]);
    }

    /**
     * @return array<string, array{0: mixed, 1: float|null, 2: string}>
     */
    public static function layers(): array
    {
        return [
            'précis sous 3 m' => ['precis', 2.99, 'precis'],
            'précis à 3 m : estimé' => ['precis', 3.0, 'estime'],
            'précis sans erreur : estimé' => ['precis', null, 'estime'],
            'précis à erreur infinie : estimé' => ['precis', INF, 'estime'],
            'précis à erreur négative : estimé' => ['precis', -1.0, 'estime'],
            'estimé à 1 m reste estimé' => ['estime', 1.0, 'estime'],
            'classe inconnue : estimé' => ['exact', 0.5, 'estime'],
        ];
    }

    #[DataProvider('layers')]
    public function test_a_piece_is_precise_only_under_three_metres(mixed $announced, ?float $error, string $expected): void
    {
        $this->assertSame($expected, FusedTrace::layer($announced, $error));
    }

    public function test_announced_precise_piece_above_three_metres_is_read_as_estimated(): void
    {
        $trace = FusedTrace::fromArray($this->collection([
            $this->feature(['ligne' => 12, 'classe' => 'precis', 'erreur_estimee_max_m' => 4.2]),
        ]));

        $this->assertSame('estime', $trace->rows()[0]['layer']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalid(): array
    {
        $line = ['type' => 'LineString', 'coordinates' => [[0.1, 45.0], [0.2, 45.1]]];

        return [
            'pas une collection' => [['type' => 'Feature']],
            'point' => [['type' => 'FeatureCollection', 'features' => [['properties' => ['ligne' => 1], 'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]]]]]],
            'sans ligne' => [['type' => 'FeatureCollection', 'features' => [['properties' => [], 'geometry' => $line]]]],
            'ligne en texte' => [['type' => 'FeatureCollection', 'features' => [['properties' => ['ligne' => '12'], 'geometry' => $line]]]],
            'un seul point' => [['type' => 'FeatureCollection', 'features' => [['properties' => ['ligne' => 1], 'geometry' => ['type' => 'LineString', 'coordinates' => [[0.1, 45.0]]]]]]],
            'hors bornes' => [['type' => 'FeatureCollection', 'features' => [['properties' => ['ligne' => 1], 'geometry' => ['type' => 'LineString', 'coordinates' => [[700000, 6600000], [700010, 6600000]]]]]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $collection
     */
    #[DataProvider('invalid')]
    public function test_invalid_files_are_refused(array $collection): void
    {
        $this->expectException(InvalidArgumentException::class);
        FusedTrace::fromArray($collection);
    }

    public function test_unreadable_file_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FusedTrace::fromFile('/nonexistent/fusion.geojson');
    }
}
