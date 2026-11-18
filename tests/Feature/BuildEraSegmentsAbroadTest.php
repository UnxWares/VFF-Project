<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:build-era-segments, ère courante : un tracé de ligne disparue (archeoferroviaire) sans
 * observation actuelle est disparu par défaut EN FRANCE ; à l'étranger, aucune source d'état
 * actuel (SNCF, IGN, OSM France) n'y observe la voie, l'état suit donc la chronologie (176000 :
 * Niedaltdorf - Dillingen, en service en Sarre ; 3332 Wintersdorf - Rastatt). 2026-10-20.
 */
class BuildEraSegmentsAbroadTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Era> */
    private array $eras = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([2010, 2020, 2030] as $year) {
            $this->eras[$year] = $year === 2030 ? Era::factory()->current()->create(['year' => $year]) : Era::factory()->create(['year' => $year]);
        }
    }

    private function km(Line $line, int $year): array
    {
        return DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $this->eras[$year]->id)
            ->selectRaw("status || ' ' || CASE WHEN ST_X(ST_Centroid(geom)) < 2.10 THEN 'france' ELSE 'etranger' END AS k, SUM(ST_Length(geom::geography)) / 1000.0 AS km")
            ->groupBy('k')->orderBy('k')->pluck('km', 'k')->map(fn ($v) => round((float) $v, 1))->all();
    }

    public function test_old_alignment_abroad_follows_the_timeline_but_disappears_in_france(): void
    {
        $lat = 49.3;
        $geom = sprintf("ST_GeomFromText('MULTILINESTRING((2.00 %F, 2.10 %F, 2.20 %F))', 4326)", $lat, $lat, $lat);
        // France : une commune couvre la moitié ouest (2,00 - 2,10) ; la moitié est (au-delà de 300 m) est à l'étranger.
        DB::table('commune_contours')->insert(['code_insee' => '57000', 'nom' => 'Commune frontalière', 'departement' => '57',
            'geom' => DB::raw(sprintf('ST_Multi(ST_MakeEnvelope(1.95, %F, 2.10, %F, 4326))', $lat - 0.05, $lat + 0.05))]);
        $line = Line::factory()->create(['name' => 'Ligne de Alpha à Bravo', 'code' => null, 'network' => 'principal', 'is_lgv' => false, 'gauge_mm_default' => 1435]);
        DB::table('line_reference_geometries')->insert(['line_id' => $line->id, 'length_m' => 14600, 'sources' => '[]', 'geom' => DB::raw($geom),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_reference_parts')->insert(['line_id' => $line->id, 'source' => 'archeoferroviaire', 'source_trace_ids' => '[]', 'length_m' => 14600,
            'geom' => DB::raw($geom), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'opening', 'event_date' => '1901-07-01', 'description' => 'Ouverture',
            'source' => 'wikipedia', 'created_at' => now(), 'updated_at' => now()]);
        // Même tracé SANS aucune date : jamais montré, ni en France ni à l'étranger (pas de « en service » inventé en 2030).
        $undated = Line::factory()->create(['name' => 'Ligne de Charlie à Delta', 'code' => null, 'network' => 'principal', 'is_lgv' => false, 'gauge_mm_default' => 1435]);
        DB::table('line_reference_geometries')->insert(['line_id' => $undated->id, 'length_m' => 14600, 'sources' => '[]', 'geom' => DB::raw($geom),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_reference_parts')->insert(['line_id' => $undated->id, 'source' => 'archeoferroviaire', 'source_trace_ids' => '[]', 'length_m' => 14600,
            'geom' => DB::raw($geom), 'created_at' => now(), 'updated_at' => now()]);

        DB::statement('DROP TABLE IF EXISTS tmp_chunks');
        $this->artisan('vff:build-era-segments', ['--line' => (string) $line->id, '--no-classify' => true, '--no-enrich' => true])->assertSuccessful();
        DB::statement('DROP TABLE IF EXISTS tmp_chunks');
        $this->artisan('vff:build-era-segments', ['--line' => (string) $undated->id, '--no-classify' => true, '--no-enrich' => true])->assertSuccessful();

        $this->assertEqualsWithDelta(7.3, $this->km($line, 2020)['in_service france'] ?? 0, 0.3);
        $this->assertEqualsWithDelta(7.3, $this->km($line, 2020)['in_service etranger'] ?? 0, 0.3);
        $now = $this->km($line, 2030);
        $this->assertArrayNotHasKey('in_service france', $now, 'tracé disparu en France : plus en service');
        $this->assertGreaterThan(6.5, $now['in_service etranger'] ?? 0, 'à l\'étranger : en service selon la chronologie');
        $this->assertSame([], $this->km($undated, 2030));
    }
}
