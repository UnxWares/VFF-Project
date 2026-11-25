<?php

namespace Tests\Feature\Rinf;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\Rinf\RinfDataset;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    /**
     * A point described by two graphs (27 points on 24/11/2026: Nîmes-Pont du Gard, 810000 in the graph of SNCF Réseau
     * and 834000 in the graph of Occitanie): a line import replaces only the tracks and platforms of its own graph and
     * keeps the point of SNCF Réseau, like the import of the whole of France.
     */
    public function test_a_line_import_keeps_what_another_graph_describes_at_a_shared_point(): void
    {
        $other = ['dataset' => 'http://data.europa.eu/949/graph/60Z7 ('.RinfDataset::ENDPOINT.')', 'fetched_at' => '2026-11-24 20:00:00'];
        DB::table('rinf_tracks')->insert(['uri' => 'http://data.europa.eu/949/track/autre-graphe', 'parent' => 'op', 'op_uopid' => 'FR0000003513',
            'rinf_class' => 'running', 'ref' => 'Voie 9 de Cognac'] + $other);
        DB::table('rinf_platforms')->insert(['uri' => 'http://data.europa.eu/949/platformEdge/autre-graphe', 'track_uri' => 'http://data.europa.eu/949/track/autre-graphe',
            'op_uopid' => 'FR0000003513', 'ref' => 'Quai 9', 'length_m' => 200] + $other);

        $this->assertSame(0, $this->import());

        $this->assertSame('Voie 9 de Cognac', DB::table('rinf_tracks')->where('uri', 'http://data.europa.eu/949/track/autre-graphe')->value('ref'));
        $this->assertSame('Quai 9', DB::table('rinf_platforms')->where('uri', 'http://data.europa.eu/949/platformEdge/autre-graphe')->value('ref'));
        $this->assertSame(530, DB::table('rinf_tracks')->where('op_uopid', 'FR0000003513')->where('ref', '4 - COGNAC')->value('length_m'));

        // The same line read from a cache where Cognac comes from another graph: the point of SNCF Réseau stays.
        $dir = sys_get_temp_dir().'/vff-rinf-test-'.bin2hex(random_bytes(6));
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        File::copyDirectory(base_path('tests/Fixtures/rinf/line-579000'), $dir.'/line-579000');
        $ops = json_decode((string) file_get_contents($dir.'/line-579000/operational-points-000.json'), true);
        foreach ($ops['results']['bindings'] as &$row) {
            if ($row['uopid']['value'] === 'FR0000003513') {
                $row['g']['value'] = 'http://data.europa.eu/949/graph/60Z7';
                $row['name']['value'] = 'Cognac (Occitanie)';
            }
        }
        unset($row);
        file_put_contents($dir.'/line-579000/operational-points-000.json', json_encode($ops));
        $this->assertSame(0, $this->artisan('vff:import-rinf', ['--offline' => true, '--line' => '579000', '--cache-dir' => $dir])->run());

        $cognac = DB::table('rinf_operational_points')->where('uopid', 'FR0000003513')->first(['name', 'dataset']);
        $this->assertSame('Cognac', $cognac->name);
        $this->assertStringContainsString('/graph/0087 ', $cognac->dataset);
    }

    public function test_offline_without_cache_fails_without_network(): void
    {
        $this->artisan('vff:import-rinf', ['--offline' => true, '--line' => '123456', '--cache-dir' => base_path('tests/Fixtures/rinf')])
            ->expectsOutputToContain('absente du cache')
            ->assertFailed();
        $this->artisan('vff:import-rinf', ['--offline' => true, '--line' => 'abc', '--cache-dir' => base_path('tests/Fixtures/rinf')])->assertExitCode(2);
        // A code of five digits read the sections of 579000 by prefix, then deleted nothing and failed on the track URIs.
        $this->artisan('vff:import-rinf', ['--offline' => true, '--line' => '57900', '--cache-dir' => base_path('tests/Fixtures/rinf')])->assertExitCode(2);
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
