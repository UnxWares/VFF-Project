<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\LineReferenceExclusions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Retraits à la main lus par vff:build-line-references (LineReferenceExclusions) : voie écartée en entier (ni tracé
 * rattaché, ni voie de recalage), voie coupée autour d'un point (crochet en bout de voie), fragment désigné par sa
 * position retiré après le nettoyage. Chaque retrait survit à une reconstruction.
 *
 * Réseau fabriqué en Lambert-93 (autour de x = 650 000, y = 6 860 000), ligne est-ouest.
 */
class LineReferenceExclusionsTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->line = Line::factory()->create(['code' => '436000', 'name' => 'Ligne de La Chapelle-Anthenaise à Flers', 'network' => 'principal']);
    }

    /** @param  list<array{0: float|int, 1: float|int}>  $pts */
    private function trace(string $source, array $pts, string $ref): int
    {
        $layer = ['bdtopo' => 'Voie ferrée principale', 'osm' => 'abandoned', 'archeoferroviaire' => 'principal'][$source];
        $wkt = 'LINESTRING('.implode(',', array_map(fn ($p) => (self::X0 + $p[0]).' '.(self::Y0 + $p[1]), $pts)).')';

        return DB::table('source_traces')->insertGetId([
            'source' => $source, 'source_ref' => $ref, 'layer' => $layer, 'line_id' => $this->line->id,
            'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('{$wkt}', 2154), 4326))"),
        ]);
    }

    /** @return array{0: float, 1: float} lon, lat d'un point du réseau fabriqué */
    private function lonLat(float $x, float $y): array
    {
        $r = DB::selectOne('SELECT ST_X(p) AS lon, ST_Y(p) AS lat FROM ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326) p', [self::X0 + $x, self::Y0 + $y]);

        return [round((float) $r->lon, 7), round((float) $r->lat, 7)];
    }

    /** @param  list<array<string, mixed>>  $entries */
    private function build(array $entries): void
    {
        $this->app->instance(LineReferenceExclusions::class, new LineReferenceExclusions($entries));
        $this->artisan('vff:build-line-references', ['--line' => [(string) $this->line->id]])->assertSuccessful();
    }

    /** @return array<string, mixed> */
    private function entry(array $e): array
    {
        return $e + ['line' => '436000', 'reason' => 'essai', 'source' => 'https://www.openstreetmap.org/way/1', 'reviewed_at' => '2026-11-23'];
    }

    /** Ids des voies sous le tracé de référence (tracés source et dessin). */
    private function usedTraces(): array
    {
        return array_map('intval', array_column(DB::select(<<<'SQL'
            SELECT DISTINCT x::bigint AS id FROM line_reference_parts p,
                LATERAL jsonb_array_elements_text(p.source_trace_ids || COALESCE(p.matched_trace_ids, '[]'::jsonb)) x
            WHERE p.line_id = ?
            SQL, [$this->line->id]), 'id'));
    }

    private function referenceLength(): float
    {
        return (float) DB::selectOne('SELECT COALESCE(SUM(ST_Length(ST_Transform(geom, 2154))), 0) AS m FROM line_reference_parts WHERE line_id = ?', [$this->line->id])->m;
    }

    public function test_excluded_trace_is_left_out_of_the_reference_and_of_the_recalage(): void
    {
        $a = $this->trace('osm', [[0, 0], [1000, 0]], '101');
        $b = $this->trace('osm', [[1000, 0], [2000, 0]], '102');
        // Tracé grossier de la ligne, à 4 m : recalé sur la voie OSM qui le suit, quand elle est permise.
        $this->trace('archeoferroviaire', [[0, 4], [2000, 4]], 'archeo-1');

        $this->build([]);
        $this->assertContains($b, $this->usedTraces(), 'témoin : sans retrait, la voie est dans le tracé');

        $this->build([$this->entry(['kind' => 'trace', 'source_name' => 'osm', 'source_ref' => '102'])]);
        $this->assertNotContains($b, $this->usedTraces(), 'ni tracé rattaché, ni dessin du recalage');
        $this->assertContains($a, $this->usedTraces());
        // Le reste de la ligne vient du tracé grossier : la ligne n'a pas de trou.
        $this->assertEqualsWithDelta(2000, $this->referenceLength(), 40);

        // Une seconde reconstruction ne la fait pas revenir ; une autre ligne garderait la voie (pool remis).
        $this->build([$this->entry(['kind' => 'trace', 'source_name' => 'osm', 'source_ref' => '102'])]);
        $this->assertNotContains($b, $this->usedTraces());
    }

    public function test_cut_around_a_point_removes_a_hook_at_the_end_of_a_track(): void
    {
        // Voie qui finit par le début d'une courbe d'aiguillage (crochet de 15 m vers le nord).
        $this->trace('osm', [[0, 0], [1000, 0], [1008, 4], [1012, 12]], '201');
        $hook = fn () => (float) DB::selectOne(<<<'SQL'
            SELECT COALESCE(MAX(ST_Y(d.geom)), 0) - ? AS y FROM line_reference_parts p, LATERAL ST_DumpPoints(ST_Transform(p.geom, 2154)) d WHERE p.line_id = ?
            SQL, [self::Y0, $this->line->id])->y;

        $this->build([]);
        $this->assertGreaterThan(10, $hook(), 'témoin : le crochet est dans le tracé');

        $this->build([$this->entry(['kind' => 'trace', 'source_name' => 'osm', 'source_ref' => '201', 'near' => $this->lonLat(1008, 4), 'radius_m' => 15])]);
        $this->assertLessThan(0.5, $hook(), 'plus de crochet');
        $this->assertEqualsWithDelta(1000 - 7, $this->referenceLength(), 3, 'le reste de la voie est gardé');
    }

    public function test_listed_fragment_is_removed_by_position_and_recorded(): void
    {
        $this->trace('osm', [[0, 0], [2000, 0]], '301');
        // Deux bouts de voie BD TOPO isolés de 80 m (au-delà de la règle des fragments de moins de 60 m).
        $this->trace('bdtopo', [[500, 200], [580, 200]], 'TRONFERR-1');
        $this->trace('bdtopo', [[1500, 200], [1580, 200]], 'TRONFERR-2');
        $count = fn (float $x) => (int) DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS n FROM line_reference_parts p, LATERAL ST_Dump(p.geom) d
            WHERE p.line_id = ? AND ST_DWithin(ST_Transform(d.geom, 2154), ST_SetSRID(ST_MakePoint(?, ?), 2154), 5)
            SQL, [$this->line->id, self::X0 + $x, self::Y0 + 200])->n;

        $this->build([]);
        $this->assertSame(1, $count(540), 'témoin : le fragment est dans le tracé');

        $entries = [
            $this->entry(['kind' => 'fragment', 'at' => $this->lonLat(540, 201), 'max_m' => 90]),
            // Point juste, longueur maximale trop courte : rien n'est retiré, l'entrée est signalée.
            $this->entry(['kind' => 'fragment', 'at' => $this->lonLat(1540, 200), 'max_m' => 50]),
        ];
        $this->build($entries);
        $this->assertSame(0, $count(540));
        $this->assertSame(1, $count(1540));
        $this->assertGreaterThan(1990, $this->referenceLength());
        $rec = DB::table('line_reference_duplicates')->where('line_id', $this->line->id)->where('reason', 'retrait à la main')->get();
        $this->assertCount(1, $rec);
        $this->assertSame(80, $rec[0]->length_m);
        $this->assertSame([0, 1], (new LineReferenceExclusions($entries))->removeFragments($this->line->id)['missing'], 'déjà retiré, ou trop court pour max_m : rien à retirer');

        $this->build($entries);
        $this->assertSame(0, $count(540), 'une reconstruction ne le fait pas revenir');
    }

    public function test_unresolvable_entries_are_reported_and_ignored(): void
    {
        $x = new LineReferenceExclusions([
            $this->entry(['kind' => 'trace', 'source_name' => 'osm', 'source_ref' => 'absent']),
            $this->entry(['kind' => 'fragment', 'line' => '999999', 'at' => [2.3, 49.8], 'max_m' => 10]),
            $this->entry(['kind' => 'fragment', 'at' => [2.3, 49.8], 'max_m' => 10]),
            $this->entry(['kind' => 'fragment', 'at' => [2.3, 49.8], 'max_m' => 20]),
            ['kind' => 'trace'],
        ]);

        $this->assertSame([
            'retrait n° 0 (436000) : voie osm absent introuvable',
            'retrait n° 1 (999999) : ligne introuvable',
            'retrait n° 3 (436000) : doublon du retrait n° 2',
            'retrait n° 4 (?) : line manquant ; reason manquant ; source manquant ; reviewed_at manquant ; source_name manquant ; source_ref manquant',
        ], $x->errors());
        $this->assertSame([], $x->tracesFor($this->line->id));
        $this->assertCount(1, $x->resolve()[$this->line->id]['fragments']);
    }
}
