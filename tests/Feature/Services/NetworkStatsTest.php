<?php

namespace Tests\Feature\Services;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Material;
use App\Models\Station;
use App\Services\NetworkStats;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Agrégats du réseau (NetworkStats) : route-km SNCF exploités, lignes actives
 * et répartition du trafic par ère, gares non déclassées, ères cartographiées,
 * empreinte des données (clé de cache des stats de la home).
 */
class NetworkStatsTest extends TestCase
{
    use RefreshDatabase;

    private NetworkStats $stats;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stats = new NetworkStats;
    }

    private function segment(Line $line, Era $era, string $status, string $traffic): void
    {
        LineSegment::factory()->create([
            'line_id' => $line->id, 'era_id' => $era->id, 'status' => $status, 'traffic_type' => $traffic,
            'geom' => LineString::make([Point::makeGeodetic(47.0, 2.0), Point::makeGeodetic(47.0, 2.1)]),
        ]);
    }

    public function test_empty_database_gives_zeros_and_no_current_era(): void
    {
        $this->assertSame(0.0, $this->stats->totalRouteKm());
        $this->assertSame(0, $this->stats->activeLinesCount());
        $this->assertSame([], $this->stats->byTrafficType());
        $this->assertSame(0, $this->stats->stationsCount());
        $this->assertSame(0, $this->stats->mappedErasCount());
    }

    public function test_route_km_only_counts_operated_sncf_geometries(): void
    {
        foreach (['Exploitée' => 'LINESTRING(2.0 47.0, 2.1 47.0)', 'Fermée' => 'LINESTRING(3.0 47.0, 3.5 47.0)'] as $libelle => $wkt) {
            DB::statement("INSERT INTO sncf_line_geometries (code_ligne, libelle, geom, created_at, updated_at)
                VALUES ('100000', ?, ST_GeomFromText(?, 4326), now(), now())", [$libelle, $wkt]);
        }

        // 0,1° de longitude à 47° N ≈ 7,59 km (géodésique) ; la portion fermée est ignorée.
        $this->assertEqualsWithDelta(7.59, $this->stats->totalRouteKm(), 0.02);
        $this->assertSame($this->stats->totalRouteKm(), $this->stats->totalRouteKm(42), 'ère sans effet sur la géométrie SNCF');
    }

    public function test_active_lines_and_traffic_split_are_scoped_to_the_era(): void
    {
        $past = Era::factory()->create(['year' => 1950]);
        $current = Era::factory()->current()->create(['year' => 2030]);
        [$a, $b, $c] = Line::factory()->count(3)->create()->all();

        $this->segment($a, $current, 'in_service', 'mixed');
        $this->segment($a, $current, 'single_track', 'passenger'); // même ligne, deux trafics
        $this->segment($b, $current, 'in_service', 'unknown');      // trafic inconnu
        $this->segment($c, $current, 'abandoned', 'freight');       // fermé : ignoré
        $this->segment($c, $past, 'in_service', 'freight');

        $this->assertSame(2, $this->stats->activeLinesCount());
        $this->assertSame(2, $this->stats->activeLinesCount($current->id));
        $this->assertSame(1, $this->stats->activeLinesCount($past->id));

        $split = $this->stats->byTrafficType();
        ksort($split);
        $this->assertSame(['mixed' => 1, 'passenger' => 1, 'unknown' => 1], $split);
        $this->assertSame(['freight' => 1], $this->stats->byTrafficType($past->id));
        $this->assertSame(2, $this->stats->mappedErasCount());
    }

    public function test_seeded_but_empty_eras_are_not_mapped(): void
    {
        $mapped = Era::factory()->current()->create(['year' => 2030]);
        Era::factory()->create(['year' => 1900]);
        $this->segment(Line::factory()->create(), $mapped, 'abandoned', 'unknown');

        $this->assertSame(1, $this->stats->mappedErasCount());
        $this->assertSame(0, $this->stats->activeLinesCount());
    }

    public function test_stations_count_excludes_declassified_stations(): void
    {
        Station::factory()->create(['status' => 'active']);
        Station::factory()->create(['status' => 'no_commercial']);
        Station::factory()->create(['status' => 'declassified']);

        $this->assertSame(2, $this->stats->stationsCount());
    }

    public function test_fingerprint_changes_when_network_data_changes(): void
    {
        $empty = $this->stats->dataFingerprint();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $empty);
        $this->assertSame($empty, $this->stats->dataFingerprint(), 'stable sans écriture');

        $line = Line::factory()->create();
        $withLine = $this->stats->dataFingerprint();
        $this->assertNotSame($empty, $withLine);

        Material::factory()->create();
        $this->assertNotSame($withLine, $this->stats->dataFingerprint());

        $era = Era::factory()->create();
        $this->segment($line, $era, 'in_service', 'mixed');
        $withSegment = $this->stats->dataFingerprint();
        DB::table('line_segments')->update(['updated_at' => now()->addDay()]);
        $this->assertNotSame($withSegment, $this->stats->dataFingerprint(), 'une réécriture de tronçon change l\'empreinte');
    }
}
