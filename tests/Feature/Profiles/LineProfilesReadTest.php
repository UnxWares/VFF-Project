<?php

namespace Tests\Feature\Profiles;

use App\Models\Line;
use App\Models\LineProfile;
use App\Services\Profiles\LineProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lecture du profil par LineProfiles (allègement, parties de terrain, altitude à un PK, position
 * arrondie) : tests ajoutés après une passe de mutation (Infection, tests/Mutation/Profiles).
 */
class LineProfilesReadTest extends TestCase
{
    use RefreshDatabase;

    private LineProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $line = Line::factory()->create(['slug' => 'ligne-lecture']);
        $this->profile = LineProfile::create([
            'line_id' => $line->id, 'source' => 'bdtopo-rge-alti', 'resource' => 'ign_rge_alti_wld', 'step_m' => 100,
            'length_m' => 5000, 'trace_length_m' => 5000, 'covered_m' => 5000, 'off_path_m' => 0, 'pk_anchors' => 2,
            'branches' => [], 'structures' => [], 'altitude_sources' => [], 'gaps' => [], 'stations' => [],
            'min_altitude_m' => 0, 'max_altitude_m' => 0, 'ascent_m' => 0, 'descent_m' => 0,
            'computed_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $points  seq implicite ; distance_m, pk_m, altitude_m, source, interpolated, structure, piece, lat, lng, trace_source
     */
    private function points(array $points): void
    {
        foreach ($points as $seq => $p) {
            DB::table('line_profile_points')->insert([
                'line_profile_id' => $this->profile->id, 'line_id' => $this->profile->line_id, 'seq' => $seq,
                'distance_m' => $p['distance_m'] ?? $seq * 100, 'pk_m' => $p['pk_m'] ?? null, 'altitude_m' => $p['altitude_m'] ?? null,
                'source' => $p['source'] ?? null, 'interpolated' => $p['interpolated'] ?? false, 'structure' => $p['structure'] ?? null,
                'piece' => $p['piece'] ?? 0, 'branch' => null, 'trace_source' => $p['trace_source'] ?? 'osm',
                'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $p['lng'] ?? 2.0, $p['lat'] ?? 48.0)),
            ]);
        }
    }

    public function test_downsampling_keeps_the_ends_the_first_extremes_and_the_edges_of_holes_and_gaps(): void
    {
        $same = [[0, 1.0], [100, 2.0], [200, 3.0]];
        $this->assertSame($same, LineProfiles::downsample($same, 3));
        $this->assertSame([], LineProfiles::downsample($same, 0));

        // Minimum et maximum répétés : le premier de chaque est gardé.
        $ties = [[0, 5.0], [100, 3.0], [200, 3.0], [300, 9.0], [400, 9.0], [500, 4.0]];
        $this->assertSame([[0, 5.0], [100, 3.0], [300, 9.0], [500, 4.0]], LineProfiles::downsample($ties, 4));

        // Trou d'altitudes : les deux points de chaque bord sont gardés.
        $holes = [[0, 1.0], [100, 2.0], [200, null], [300, null], [400, 5.0], [500, 6.0], [600, 7.0]];
        $this->assertSame([[0, 1.0], [100, 2.0], [200, null], [300, null], [400, 5.0], [600, 7.0]], LineProfiles::downsample($holes, 6));

        // Lacune du parcours entre deux morceaux : le dernier point d'un côté et le premier de l'autre.
        $gapped = [[0, 1.0], [100, 2.0], [200, 3.0], [600, 4.0], [700, 5.0], [800, 6.0]];
        $this->assertSame([[0, 1.0], [200, 3.0], [600, 4.0], [800, 6.0]], LineProfiles::downsample($gapped, 5, [['from_m' => 250, 'to_m' => 550]]));
    }

    public function test_the_rest_of_the_budget_goes_to_evenly_spaced_points(): void
    {
        $line = array_map(fn (int $i): array => [$i * 100, 100.0 + $i], range(0, 10));

        // Altitudes croissantes : bouts gardés, trois points répartis (2,5 arrondi à 3, 5 et 7,5 arrondi à 8).
        $this->assertSame([0, 300, 500, 800, 1000], array_column(LineProfiles::downsample($line, 5), 0));
        $this->assertSame([0, 500, 1000], array_column(LineProfiles::downsample($line, 3), 0));
        $this->assertSame([0, 1000], array_column(LineProfiles::downsample($line, 2), 0));
    }

    public function test_terrain_ranges_are_runs_of_terrain_points_inside_one_piece(): void
    {
        $this->points([
            ['source' => 'rge_alti'], ['source' => 'rge_alti'], ['source' => 'bdtopo'],
            ['source' => 'rge_alti'], ['source' => 'rge_alti', 'piece' => 1], ['source' => 'rge_alti', 'piece' => 1],
        ]);

        $this->assertSame(
            [['from_m' => 0, 'to_m' => 100], ['from_m' => 300, 'to_m' => 300], ['from_m' => 400, 'to_m' => 500]],
            app(LineProfiles::class)->terrainRanges($this->profile),
        );
    }

    public function test_a_profile_without_terrain_points_has_no_terrain_range(): void
    {
        $this->points([['source' => 'bdtopo'], ['source' => 'bdtopo']]);
        $this->assertSame([], app(LineProfiles::class)->terrainRanges($this->profile));
    }

    public function test_the_altitude_at_a_pk_is_interpolated_between_the_two_framing_points(): void
    {
        $this->points([
            ['distance_m' => 1000, 'pk_m' => 1000, 'altitude_m' => 101.0, 'source' => 'bdtopo', 'structure' => 'bridge', 'lat' => 48.1234567, 'lng' => 2.1234567],
            ['distance_m' => 1600, 'pk_m' => 1500, 'altitude_m' => 104.0, 'source' => 'rge_alti', 'interpolated' => true, 'structure' => 'tunnel', 'lat' => 48.2345678, 'lng' => 2.2345678],
        ]);
        $service = app(LineProfiles::class);

        $at = $service->altitudeAtPk($this->profile, 1100);

        // À un cinquième du chemin : 101 + 0,2 × 3 = 101,6 m ; distance 1 120 m ; position arrondie au millionième.
        $this->assertSame(1100, $at['pk_m']);
        $this->assertSame(101.6, $at['altitude_m']);
        $this->assertSame(1120, $at['distance_m']);
        $this->assertTrue($at['interpolated']);
        $this->assertSame(['bdtopo', 'rge_alti'], $at['sources']);
        $this->assertSame('bridge', $at['structure']);
        $this->assertSame(48.145679, $at['lat']);
        $this->assertSame(2.145679, $at['lng']);
        $this->assertNull($at['position_withheld']);

        // Au point lui-même : ses propres valeurs, sans division par zéro.
        $exact = $service->altitudeAtPk($this->profile, 1500);
        $this->assertSame(104.0, $exact['altitude_m']);
        $this->assertSame(1600, $exact['distance_m']);
        $this->assertSame(['rge_alti'], $exact['sources']);
        $this->assertSame('tunnel', $exact['structure']);
        $this->assertSame(48.234568, $exact['lat']);

        // Hors du profil : rien.
        $this->assertNull($service->altitudeAtPk($this->profile, 900));
        $this->assertNull($service->altitudeAtPk($this->profile, 1600));
    }

    public function test_the_altitude_at_a_pk_is_refused_across_pieces_and_without_altitude(): void
    {
        $this->points([
            ['pk_m' => 1000, 'altitude_m' => 101.0, 'piece' => 0],
            ['pk_m' => 1500, 'altitude_m' => 104.0, 'piece' => 1],
            ['pk_m' => 2000, 'altitude_m' => null, 'piece' => 1],
        ]);
        $service = app(LineProfiles::class);

        $this->assertNull($service->altitudeAtPk($this->profile, 1200));
        $this->assertNull($service->altitudeAtPk($this->profile, 1700));
        $this->assertNotNull($service->altitudeAtPk($this->profile, 1500));
    }

    public function test_detailed_points_round_their_position_to_the_millionth_of_a_degree(): void
    {
        $this->points([['lat' => 48.1234567, 'lng' => 2.7654321, 'altitude_m' => 12.5, 'pk_m' => 10]]);

        $points = app(LineProfiles::class)->detailedPoints($this->profile);

        $this->assertCount(1, $points);
        $this->assertSame(48.123457, $points[0]['lat']);
        $this->assertSame(2.765432, $points[0]['lng']);
        $this->assertSame(12.5, $points[0]['altitude_m']);
        $this->assertSame(10, $points[0]['pk_m']);
    }
}
