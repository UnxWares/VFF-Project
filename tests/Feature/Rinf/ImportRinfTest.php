<?php

namespace Tests\Feature\Rinf;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * vff:import-rinf sur l'extrait réel de la ligne 579000 (Beillant - Angoulême) lu le 24/11/2026 au point SPARQL de
 * l'ERA (tests/Fixtures/rinf/line-579000, RINF © ERA, voir tests/Fixtures/SOURCES.md) : passe à blanc sans écriture,
 * import hors ligne, PK des sections, rattachement des gares, puis le schéma des voies servi sur ces données réelles.
 */
class ImportRinfTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-579000', 'code' => '579000', 'name' => 'Ligne de Beillant à Angoulême']);
        Station::factory()->create(['slug' => 'cognac', 'name' => 'Cognac', 'type' => 'gare', 'geom' => Point::makeGeodetic(45.6846, -0.3266)]);
        Station::factory()->create(['slug' => 'saint-michel-sur-charente', 'name' => 'Saint-Michel-sur-Charente', 'type' => 'halte', 'geom' => Point::makeGeodetic(45.6447, 0.1101)]);
        // Homonyme lointain : jamais rattaché.
        Station::factory()->create(['slug' => 'jarnac-ailleurs', 'name' => 'Jarnac', 'type' => 'gare', 'geom' => Point::makeGeodetic(48.0, 2.0)]);
    }

    private function import(string ...$options): int
    {
        return $this->artisan('vff:import-rinf', ['--offline' => true, '--line' => '579000', '--cache-dir' => base_path('tests/Fixtures/rinf')]
            + array_fill_keys($options, true))->run();
    }

    public function test_dry_run_measures_without_writing(): void
    {
        $this->artisan('vff:import-rinf', ['--offline' => true, '--dry-run' => true, '--line' => '579000', '--cache-dir' => base_path('tests/Fixtures/rinf')])
            ->expectsOutputToContain('Points d\'exploitation : 17')
            ->expectsOutputToContain('Sections de ligne : 17 (liaisons : 2 ; avec les deux PK : 15 ; 65 km)')
            ->expectsOutputToContain('Aiguillages et signaux : 0')
            ->expectsOutputToContain('Passe à blanc')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('rinf_sections')->count());
        $this->assertSame(0, DB::table('rinf_operational_points')->count());
    }

    public function test_offline_import_writes_sections_tracks_points_and_platforms_with_provenance(): void
    {
        $this->assertSame(0, $this->import());

        $this->assertSame(17, DB::table('rinf_sections')->count());
        $cognac = DB::table('rinf_sections')->where('uri', 'http://data.europa.eu/949/sectionOfLine/579000-1_FR0000003513_FR9900004367')->first();
        $this->assertSame([17277, 17917, 640, 'regular', '579000', '579000-1', $this->line->id],
            [$cognac->pk_start_m, $cognac->pk_end_m, $cognac->length_m, $cognac->nature, $cognac->line_code, $cognac->national_line, $cognac->line_id]);
        $this->assertStringContainsString('graph/0087', $cognac->dataset);
        $this->assertNotNull($cognac->fetched_at);
        // PK négatif (« 0+-31 ») lu tel quel.
        $this->assertSame(-31, DB::table('rinf_sections')->where('op_start_uopid', 'FR0000003470')->where('op_end_uopid', 'FR0000001119')->value('pk_start_m'));

        $op = DB::table('rinf_operational_points')->where('uopid', 'FR0000003513')->first();
        $this->assertSame(['Cognac', 10, 'station', 'name_distance'], [$op->name, $op->op_type, $op->kind, $op->station_match]);
        $this->assertSame(Station::where('slug', 'cognac')->value('id'), $op->station_id);
        $this->assertLessThan(100, $op->station_distance_m);
        $this->assertSame('name_distance', DB::table('rinf_operational_points')->where('uopid', 'FR9900001730')->value('station_match'));
        $this->assertNull(DB::table('rinf_operational_points')->where('uopid', 'FR0000002507')->value('station_id'));

        $vu = DB::table('rinf_tracks')->where('section_uri', $cognac->uri)->first();
        $this->assertSame(['Voie UNIQUE de Beillant à St-Michel-sur-Charente', 'B', 140, false], [$vu->ref, $vu->direction, $vu->max_speed_kmh, $vu->electrified]);
        $this->assertSame(530, DB::table('rinf_tracks')->where('op_uopid', 'FR0000003513')->where('ref', '4 - COGNAC')->value('length_m'));
        $quai = DB::table('rinf_platforms')->where('ref', 'Quai VE')->where('op_uopid', 'FR0000003513')->first();
        $this->assertSame([145, 300, 'FR0000003513'], [$quai->length_m, $quai->height_mm, $quai->op_uopid]);
    }

    public function test_a_second_line_import_replaces_only_that_line(): void
    {
        $this->import();
        DB::table('rinf_sections')->insert(['uri' => 'http://data.europa.eu/949/sectionOfLine/autre', 'national_line' => '500000-1', 'line_code' => '500000',
            'op_start_uopid' => 'FRX', 'op_end_uopid' => 'FRY', 'pk_start_m' => 0, 'pk_end_m' => 10, 'nature' => 'regular', 'dataset' => 'x']);
        $this->import();

        $this->assertSame(17, DB::table('rinf_sections')->where('line_code', '579000')->count());
        $this->assertSame(1, DB::table('rinf_sections')->where('line_code', '500000')->count());
    }

    public function test_offline_without_cache_fails_without_network(): void
    {
        $this->artisan('vff:import-rinf', ['--offline' => true, '--line' => '123456', '--cache-dir' => base_path('tests/Fixtures/rinf')])
            ->expectsOutputToContain('absente du cache')
            ->assertFailed();
        $this->artisan('vff:import-rinf', ['--offline' => true, '--line' => 'abc', '--cache-dir' => base_path('tests/Fixtures/rinf')])->assertExitCode(2);
    }

    public function test_the_layout_of_the_real_extract(): void
    {
        $this->import();
        $json = $this->getJson('/api/v1/lines/rfn-579000/track-layout')->assertOk()->json();

        $this->assertSame('rinf', $json['sources'][0]['key']);
        $this->assertSame(16, count($json['operational_points']));
        $cognac = collect((array) $json['operational_points'])->firstWhere('uopid', 'FR0000003513');
        $this->assertSame(['station', 17277, 'cognac', 'exact'], [$cognac['kind'], $cognac['pk_m'], $cognac['station_slug'], $cognac['precision']]);
        // Saint-Michel est typé gare de fret au RINF : halte d'après VFF.
        $this->assertSame('halt', collect((array) $json['operational_points'])->firstWhere('uopid', 'FR9900001730')['kind']);

        $tracks = collect((array) $json['tracks']);
        $main = $tracks->where('kind', 'running')->whereIn('ref', ['V1', 'V2', 'VU']);
        // Voie unique et voies 1 et 2 (en ligne comme à la traversée des gares) : rangées 0 et 1, jamais ailleurs ;
        // la troisième voie de Sireuil (« Voie E de Sireuil », en ligne) passe à côté.
        $this->assertEqualsCanonicalizing([0, 1], $main->pluck('lane')->unique()->values()->all());
        $this->assertSame(2, $tracks->where('rinf_ref', 'Voie E de Sireuil')->count());
        $this->assertNotContains($tracks->firstWhere('rinf_ref', 'Voie E de Sireuil')['lane'], [0, 1]);
        $this->assertSame([0], $main->where('ref', 'VU')->pluck('lane')->unique()->values()->all());
        $this->assertSame([1], $main->where('ref', 'V2')->pluck('lane')->unique()->values()->all());
        $this->assertSame('up', $main->firstWhere('rinf_ref', 'Voie V1')['direction']);
        // Voies de service de Cognac : impaires au-dessus, paires au-dessous, sans chevauchement dans une rangée.
        $sidings = $tracks->where('op_id', 'rinf:op:FR0000003513')->where('kind', 'siding');
        $this->assertTrue($sidings->where('ref', '3')->every(fn (array $t): bool => $t['lane'] < 0));
        $this->assertTrue($sidings->where('ref', '4')->every(fn (array $t): bool => $t['lane'] > 1));
        foreach ($tracks->whereNotIn('kind', ['running', 'crossover'])->groupBy('lane') as $lane => $group) {
            $sorted = $group->sortBy('pk_from_m')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                $this->assertGreaterThanOrEqual($sorted[$i - 1]['pk_to_m'], $sorted[$i]['pk_from_m'], "rangée {$lane} sans chevauchement");
            }
        }
        $this->assertSame(64902, $json['coverage']['rinf_m']);
        $this->assertSame(['pk_from_m' => -235, 'pk_to_m' => -31, 'kmh' => 110, 'tracks' => ['V1', 'V2'], 'source' => 'rinf'], $json['strip']['speeds'][0]);
    }
}
