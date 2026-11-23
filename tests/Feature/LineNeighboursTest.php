<?php

namespace Tests\Feature;

use App\Models\Station;
use App\Services\LineNeighbours;
use App\Services\StationSheet;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Gares précédente et suivante le long d'une ligne desservie (App\Services\LineNeighbours), lues par
 * StationSheet::lines dans la requête des lignes : ordre par PK, distance, état d'aujourd'hui, dernière ère
 * ouverte d'une gare fermée, bouts de ligne, PK inconnu, PK incohérent avec la position.
 */
class LineNeighboursTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    /** @var array<string, Station> */
    private array $stops = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    private function stop(string $slug, int $km, ?int $pk, string $status = 'active', array $extra = []): Station
    {
        // 0,009 degré de latitude : un kilomètre environ.
        $s = Station::factory()->create(['slug' => $slug, 'name' => $slug, 'status' => $status,
            'geom' => Point::makeGeodetic(45.0 + $km * 0.009, 1.0)] + $extra);
        DB::table('station_lines')->insert([
            'station_id' => $s->id, 'line_id' => $this->line->id, 'method' => 'line_stops', 'pk_m' => $pk,
            'pk_source' => 'sncf', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->stops[$slug] = $s;
    }

    /** @return array<string, mixed>|null */
    private function neighboursOf(string $slug): ?array
    {
        $lines = app(StationSheet::class)->lines($this->stops[$slug]->id);
        $this->assertCount(1, $lines);

        return LineNeighbours::of($lines[0]);
    }

    public function test_previous_and_next_follow_the_pk_with_distance_state_and_last_open_era(): void
    {
        $this->stop('a', 0, 10_000);
        $this->stop('b', 4, 14_000, 'declassified');
        $this->stop('c', 9, 19_000);
        DB::table('station_eras')->insert([
            ['station_id' => $this->stops['b']->id, 'era_id' => $this->pastEra->id, 'status' => 'active', 'uncertain' => false, 'created_at' => now(), 'updated_at' => now()],
            ['station_id' => $this->stops['b']->id, 'era_id' => $this->currentEra->id, 'status' => 'declassified', 'uncertain' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $n = $this->neighboursOf('c');
        $this->assertSame('b', $n['previous']['slug']);
        $this->assertSame(5_000, $n['previous']['distance_m']);
        $this->assertSame('declassified', $n['previous']['status']);
        $this->assertSame($this->pastEra->year, $n['previous']['last_open_era']);
        $this->assertNull($n['next']);

        $n = $this->neighboursOf('b');
        $this->assertSame(['a', 'c'], [$n['previous']['slug'], $n['next']['slug']]);
        $this->assertNull($n['next']['last_open_era']);
    }

    public function test_a_gone_station_counts_as_gone(): void
    {
        $this->stop('a', 0, 0);
        $this->stop('b', 3, 3_000, 'declassified', ['source_status' => 'gone']);

        $this->assertSame('gone', $this->neighboursOf('a')['next']['status']);
    }

    public function test_nothing_without_pk_and_technical_or_never_served_stations_are_skipped(): void
    {
        $this->stop('a', 0, null);
        $this->stop('b', 3, 3_000, 'active', ['provenance' => ['kind' => 'technical']]);
        $this->stop('c', 4, 4_000);
        DB::table('station_lines')->where('station_id', $this->stops['c']->id)->update(['years_source' => 'never_served']);
        $this->stop('d', 6, 6_000);
        $this->stop('e', 1, 500);

        $this->assertNull($this->neighboursOf('a'));
        // b (point technique) et c (jamais desservie) sont écartées : la précédente de d est e.
        $this->assertSame('e', $this->neighboursOf('d')['previous']['slug']);
    }

    public function test_a_pk_far_from_the_position_is_not_a_neighbour(): void
    {
        $this->stop('a', 0, 10_000);
        // 1 km de PK mais 90 km à vol d'oiseau : PK faux, jamais voisine.
        $this->stop('far', 100, 11_000);
        $this->stop('b', 5, 15_000);

        $n = $this->neighboursOf('a');
        $this->assertSame('b', $n['next']['slug']);
    }
}
