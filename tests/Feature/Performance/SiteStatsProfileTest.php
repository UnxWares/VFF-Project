<?php

namespace Tests\Feature\Performance;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Services\SiteStats;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Profil du réseau de l'accueil (SiteStats::profile) : kilomètres ouverts et nombre de lignes par ère. Sur la
 * base complète, les 330 000 tronçons ouverts étaient triés avec leur tracé, lu pour le seul repli ST_Length (2,3 s à
 * froid, 280 Mo écrits sur disque) ; la longueur lue avant le regroupement : 0,3 à 0,8 s.
 */
class SiteStatsProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_reads_each_segment_length_before_grouping_without_sorting_its_track(): void
    {
        $old = Era::factory()->create(['year' => 1900]);
        Era::factory()->create(['year' => 1910]);
        $now = Era::factory()->current()->create(['year' => 2030]);
        [$a, $b] = Line::factory()->count(2)->create();
        $track = fn (float $from, float $to) => LineString::make([Point::makeGeodetic(46.5, $from), Point::makeGeodetic(46.5, $to)]);
        // 1900 : 12 km mesurés et un tronçon sans longueur (0,1° de longitude à 46,5° N : 7 676 m géodésiques).
        LineSegment::factory()->inService()->create(['line_id' => $a->id, 'era_id' => $old->id, 'length_meters' => 12_000, 'geom' => $track(2.0, 2.1)]);
        LineSegment::factory()->inService()->create(['line_id' => $a->id, 'era_id' => $old->id, 'length_meters' => null, 'geom' => $track(2.1, 2.2)]);
        // 2030 : deux lignes ouvertes, une troisième voie fermée qui ne compte pas.
        LineSegment::factory()->create(['line_id' => $a->id, 'era_id' => $now->id, 'status' => 'single_track', 'length_meters' => 5_000, 'geom' => $track(2.0, 2.1)]);
        LineSegment::factory()->inService()->create(['line_id' => $b->id, 'era_id' => $now->id, 'length_meters' => 3_000, 'geom' => $track(3.0, 3.1)]);
        LineSegment::factory()->abandoned()->create(['line_id' => $b->id, 'era_id' => $now->id, 'length_meters' => 40_000, 'geom' => $track(3.1, 3.2)]);
        Cache::flush();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $profile = app(SiteStats::class)->profile();

        $this->assertSame([
            ['year' => 1900, 'km' => 20, 'lines' => 1, 'current' => false],
            ['year' => 1910, 'km' => 0, 'lines' => 0, 'current' => false],
            ['year' => 2030, 'km' => 8, 'lines' => 2, 'current' => true],
        ], $profile);
        $segments = array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'line_segments')
            && ! str_contains($sql, 'MAX(updated_at)')));
        $this->assertCount(1, $segments, 'une lecture des tronçons');
        $this->assertMatchesRegularExpression('/COALESCE\(length_meters, ST_Length\(geom::geography\)\) AS m\s+FROM line_segments WHERE status IN \(\?, \?\) OFFSET 0\) seg\s+GROUP BY era_id/',
            $segments[0], 'la longueur de chaque tronçon est lue avant le regroupement, sans garder son tracé dans le tri');
    }
}
