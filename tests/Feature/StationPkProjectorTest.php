<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\StationPkProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\Support\SeedsLevelCrossings;
use Tests\TestCase;

/**
 * PK projetés des rattachements gare - ligne (StationPkProjector). Les cas reprennent des défauts mesurés le 23/11/2026 :
 * tronçon SNCF partiel (553000 Ouest-Ceinture - Chartres, 5 km de Paris sur 80 km de ligne, toutes les gares au PK
 * 80557), PK 0 de schéma posé sur deux gares éloignées (097000 Champigneulles - Burthécourt), gare à des dizaines de
 * kilomètres de la ligne (Badaroux, Le Racou), PK décroissant le long du tracé, morceaux de tracé sans ancre.
 *
 * Géométrie : parallèle 48° N, 1° de longitude = 74,5 km environ.
 */
class StationPkProjectorTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;
    use SeedsLevelCrossings;

    private const LAT = 48.0;

    private function line(string $code): Line
    {
        return $this->rfnLine($code, "Ligne {$code}");
    }

    /** @param  list<array{0: float, 1: float}>  $parts  [lonDe, lonA] de chaque morceau du tracé de référence */
    private function reference(Line $line, array $parts): void
    {
        $wkt = 'MULTILINESTRING('.implode(',', array_map(fn ($p) => sprintf('(%F %F, %F %F)', $p[0], self::LAT, $p[1], self::LAT), $parts)).')';
        DB::table('line_reference_geometries')->insert([
            'line_id' => $line->id, 'length_m' => 100_000, 'sources' => '[]', 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
        ]);
    }

    private function attach(Line $line, string $slug, float $lon, ?int $pk, ?string $source = null, float $lat = self::LAT): int
    {
        $station = $this->station($slug, ucfirst($slug), $lat, $lon);

        return (int) DB::table('station_lines')->insertGetId([
            'station_id' => $station->id, 'line_id' => $line->id, 'method' => $pk === null ? 'segment' : 'source_pk',
            'pk_m' => $pk, 'pk_source' => $pk === null ? null : ($source ?? 'sncf'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array{0: ?int, 1: ?string} */
    private function pk(int $id): array
    {
        $r = DB::table('station_lines')->where('id', $id)->first(['pk_m', 'pk_source']);

        return [$r->pk_m, $r->pk_source];
    }

    public function test_a_partial_sncf_track_does_not_capture_every_station_of_the_line(): void
    {
        $line = $this->line('553000');
        $this->seedSncfShape('553000', 1_000, 7_000, self::LAT, 2.00, 2.08);
        $this->reference($line, [[2.0, 3.0]]);
        $this->attach($line, 'ancre-a', 2.2, 15_000);
        $this->attach($line, 'ancre-b', 2.8, 60_000);
        $mid = $this->attach($line, 'milieu', 2.5, null);
        $later = $this->attach($line, 'plus-loin', 2.6, null);
        $beyond = $this->attach($line, 'au-dela', 2.9, null);

        $r = (new StationPkProjector)->project();

        $this->assertSame(['projected' => 3, 'raw' => 0, 'none' => 0, 'released' => 0], $r);
        [$pkMid, $srcMid] = $this->pk($mid);
        [$pkLater] = $this->pk($later);
        [$pkBeyond] = $this->pk($beyond);
        $this->assertSame('projected', $srcMid);
        $this->assertEqualsWithDelta(37_300, $pkMid, 600);
        $this->assertEqualsWithDelta(44_700, $pkLater, 600);
        $this->assertEqualsWithDelta(67_400, $pkBeyond, 800, 'au-delà de la dernière ancre : prolongé, pas collé sur elle');
        $this->assertTrue($pkMid < $pkLater && $pkLater < $pkBeyond);
    }

    public function test_a_station_far_from_every_track_gets_no_pk(): void
    {
        $line = $this->line('810000');
        $this->reference($line, [[2.0, 3.0]]);
        $this->attach($line, 'ancre', 2.2, 15_000);
        $far = $this->attach($line, 'badaroux', 2.5, null, null, 48.5);
        $near = $this->attach($line, 'proche', 2.5, null, null, 48.001);

        $r = (new StationPkProjector)->project();

        $this->assertSame([null, null], $this->pk($far));
        $this->assertSame(1, $r['none']);
        $this->assertNotNull($this->pk($near)[0]);
    }

    public function test_a_source_pk_shared_by_distant_stations_is_recomputed_but_an_sncf_pk_is_kept(): void
    {
        $line = $this->line('097000');
        $this->reference($line, [[2.0, 3.0]]);
        $a = $this->attach($line, 'champigneulles', 2.2, 0, 'wikipedia_schema');
        $b = $this->attach($line, 'burthecourt', 2.8, 0, 'wikipedia_schema');
        $this->attach($line, 'repere', 2.5, 37_000, 'sncf');
        $origin = $this->attach($line, 'origine-sncf', 2.0, 0, 'sncf');

        $r = (new StationPkProjector)->project();

        $this->assertSame(2, $r['released'], 'les deux PK de schéma sont annulés');
        $this->assertEqualsWithDelta(14_900, $this->pk($a)[0], 800);
        $this->assertEqualsWithDelta(59_600, $this->pk($b)[0], 800);
        $this->assertSame([0, 'sncf'], $this->pk($origin), 'un PK SNCF n\'est jamais touché');
    }

    public function test_pk_decreasing_along_the_track_is_extrapolated_in_the_right_direction(): void
    {
        $line = $this->line('462000');
        $this->reference($line, [[2.0, 3.0]]);
        $this->attach($line, 'a', 2.3, 50_000);
        $this->attach($line, 'b', 2.5, 35_000);
        $beyond = $this->attach($line, 'c', 2.8, null);

        (new StationPkProjector)->project();

        $this->assertEqualsWithDelta(12_700, $this->pk($beyond)[0], 800);
    }

    public function test_pieces_without_anchor_are_chained_not_restarted_at_zero(): void
    {
        $line = $this->line('422000');
        $this->reference($line, [[2.00, 2.10], [2.30, 2.40]]);
        $first = $this->attach($line, 'un', 2.05, null);
        $second = $this->attach($line, 'deux', 2.35, null);

        $r = (new StationPkProjector)->project();

        $this->assertSame(2, $r['raw']);
        [$pk1, $src1] = $this->pk($first);
        [$pk2] = $this->pk($second);
        $this->assertSame('projected_raw', $src1);
        $this->assertGreaterThan(7_000, abs($pk2 - $pk1), 'les deux morceaux sont mis bout à bout, pas repartis de zéro');
    }

    public function test_pieces_without_anchor_use_the_anchor_polyline_of_the_line(): void
    {
        $line = $this->line('099000');
        $this->reference($line, [[2.00, 2.10], [2.15, 2.25], [2.30, 2.40]]);
        $this->attach($line, 'ancre-a', 2.05, 1_000);
        $this->attach($line, 'ancre-b', 2.35, 30_000);
        $onGap = $this->attach($line, 'entre', 2.2, null);

        (new StationPkProjector)->project();

        [$pk, $src] = $this->pk($onGap);
        $this->assertSame('projected', $src);
        $this->assertEqualsWithDelta(15_500, $pk, 2_500);
    }

    public function test_stations_beyond_the_reference_track_use_the_source_traces_of_the_line(): void
    {
        $line = $this->line('955000');
        $this->reference($line, [[2.0, 2.5]]);
        DB::table('source_traces')->insert([
            'source' => 'osm', 'source_ref' => 'w1', 'line_id' => $line->id, 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf("ST_GeomFromText('MULTILINESTRING((2.45 %F, 3.0 %F))', 4326)", self::LAT, self::LAT)),
        ]);
        $this->attach($line, 'ancre-a', 2.1, 5_000);
        $this->attach($line, 'ancre-b', 2.4, 27_000);
        $beyond = $this->attach($line, 'sur-trace', 2.55, null);

        $r = (new StationPkProjector)->project();

        $this->assertSame(0, $r['none'], 'sur le tracé de la source, pas à plus de 2 km de la ligne');
        $this->assertEqualsWithDelta(27_000 + 0.15 * 74_500, $this->pk($beyond)[0], 1_500);
    }
}
