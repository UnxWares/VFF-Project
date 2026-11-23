<?php

namespace Tests\Feature\Structures;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineProfile;
use App\Services\Media\CommonsClient;
use App\Services\Profiles\GeoplateformeClient;
use App\Services\Structures\BdTopoConstructions;
use App\Services\Structures\StructureImporter;
use App\Services\Structures\WikidataStructures;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Import des ouvrages d'art de bout en bout, sur des caches factices (aucun appel réseau) :
 *
 *   ligne A (en service, profil calé PK 100+000) : tunnel BD TOPO de 600 m, nommé par Wikidata ;
 *     pont de 30 m sans nom (écarté, trop court) ;
 *   ligne B (déposée, 1900 seulement, profil calé PK 50+000) : tunnel de la BD TOPO sans voie (gardé),
 *     pont routier nommé qui la croise (écarté : il ne la suit pas), pont nommé qui la suit, reconnu
 *     seulement par un élément Wikidata de classe générique (écarté : pont routier).
 */
class StructureImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private Line $a;

    private Line $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/vff-structures-'.getmypid();
        File::ensureDirectoryExists($this->dir.'/ign/bdtopo-tuiles');
        File::ensureDirectoryExists($this->dir.'/ouvrages/constructions');

        $now = Era::factory()->current()->create(['year' => 2030]);
        $old = Era::factory()->create(['year' => 1900]);
        $this->a = Line::factory()->create(['slug' => 'ligne-a']);
        $this->b = Line::factory()->create(['slug' => 'ligne-b']);

        // Ligne A d'ouest en est sur 4 km le long de 45,05 N ; ligne B 1 km plus au nord, déposée.
        // Tracé actuel coupé en morceaux dans le tunnel : celui du milieu n'atteint aucune tête.
        foreach ([[0, 1000], [1000, 1200], [1200, 1400], [1400, 1600], [1600, 4000]] as [$from, $to]) {
            $this->segment($this->a, $now, [[$from, 0], [$to, 0]]);
        }
        $this->segment($this->a, $old, [[0, 0], [4000, 0]]);
        $this->segment($this->b, $old, [[0, 1000], [4000, 1000]]);
        $this->profile($this->a, 0, 100000);
        $this->profile($this->b, 1000, 50000);

        // Tuile BD TOPO (0,2 degré) : voies de A, au sol, en tunnel (1 000 à 1 600 m) et sur un petit pont.
        $tracks = [
            ['ground', [[0, 0], [1000, 0]]],
            ['tunnel', [[1000, 0], [1300, 0]]],
            ['tunnel', [[1300, 0], [1600, 0]]],
            ['ground', [[1600, 0], [3000, 0]]],
            ['bridge', [[3000, 0], [3030, 0]]],
            ['ground', [[3030, 0], [4000, 0]]],
        ];
        [$tx, $ty] = GeoplateformeClient::tileOf(...self::p(0, 0));
        file_put_contents($this->dir."/ign/bdtopo-tuiles/{$tx}_{$ty}.json.gz", gzencode(json_encode(array_map(
            fn (array $t): array => [$t[0], array_map(fn (array $c): array => [...self::p(...$c), 300.0], $t[1])],
            $tracks,
        ))));
        // Constructions (1 degré) : tunnel de B sans voie, pont routier nommé qui croise B.
        file_put_contents($this->dir.'/ouvrages/constructions/3_45.json.gz', gzencode(json_encode([
            ['Tunnel', null, [self::p(2000, 1000), self::p(2400, 1000)]],
            ['Pont', 'Pont de la Route', [self::p(500, 970), self::p(500, 1030)]],
            ['Pont', 'Pont Neuf', [self::p(3000, 1000), self::p(3120, 1000)]],
        ])));
        file_put_contents($this->dir.'/ouvrages/wikidata.json', json_encode([
            'classes' => ['Q42' => ['Q1311958'], 'Q7' => ['Q12280']],
            'rows' => [
                self::wikidataRow('Q42', 'tunnel de la Forêt', self::p(1010, 30), ['lengths' => '598|http://www.wikidata.org/entity/Q11573',
                    'inception' => '1880-01-01T00:00:00Z|9', 'article' => 'https://fr.wikipedia.org/wiki/Tunnel_de_la_For%C3%AAt']),
                // Pont de classe générique sur l'emprise de B : il ne confirme pas un pont nommé sans voie.
                self::wikidataRow('Q7', 'pont Neuf', self::p(3060, 1000), ['inception' => '1967-01-01T00:00:00Z|9']),
            ],
        ]));
        file_put_contents($this->dir.'/ouvrages/commons.json', '{}');
        // Infobox déjà lue : la mise en service manque à Wikidata, la construction n'est pas remplacée.
        file_put_contents($this->dir.'/ouvrages/wikipedia.json', json_encode(['Tunnel de la Forêt' => [
            'revid' => 123, 'built' => ['date' => '1879-01-01', 'precision' => 'year'],
            'opened' => ['date' => '1882-05-14', 'precision' => 'day'], 'closed' => null,
        ]]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /**
     * Ligne de la réponse SPARQL de WikidataStructures::details.
     *
     * @param  array{0: float, 1: float}  $point
     * @param  array<string, string>  $values
     * @return array<string, array{value: string}>
     */
    private static function wikidataRow(string $id, string $label, array $point, array $values): array
    {
        return array_map(fn (string $v): array => ['value' => $v], $values + [
            'item' => 'http://www.wikidata.org/entity/'.$id, 'labelFr' => $label, 'coords' => sprintf('Point(%.7F %.7F)', ...$point),
        ]);
    }

    /** Point à `m` mètres à l'est de (3,02 E ; 45,05 N), `n` mètres au nord. */
    private static function p(float $m, float $n): array
    {
        return [round(3.02 + $m / (111195.0 * cos(deg2rad(45.05))), 7), round(45.05 + $n / 111195.0, 7)];
    }

    /** @param  list<array{0: float, 1: float}>  $points */
    private function segment(Line $line, Era $era, array $points): void
    {
        $wkt = 'LINESTRING('.implode(',', array_map(fn (array $p): string => implode(' ', self::p(...$p)), $points)).')';
        DB::table('line_segments')->insert(['line_id' => $line->id, 'era_id' => $era->id, 'status' => 'in_service',
            'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function profile(Line $line, float $north, int $pk): void
    {
        $profile = LineProfile::create([
            'line_id' => $line->id, 'source' => 'bdtopo-rge-alti', 'resource' => 'ign_rge_alti_wld', 'step_m' => 100,
            'length_m' => 4000, 'trace_length_m' => 4000, 'covered_m' => 4000, 'off_path_m' => 0, 'pk_anchors' => 2,
            'branches' => [], 'structures' => [], 'altitude_sources' => [], 'gaps' => [], 'stations' => [],
            'min_altitude_m' => 0, 'max_altitude_m' => 0, 'ascent_m' => 0, 'descent_m' => 0, 'computed_at' => now(),
        ]);
        for ($seq = 0; $seq <= 40; $seq++) {
            [$lng, $lat] = self::p($seq * 100, $north);
            DB::table('line_profile_points')->insert(['line_profile_id' => $profile->id, 'line_id' => $line->id, 'seq' => $seq,
                'distance_m' => $seq * 100, 'pk_m' => $pk + $seq * 100, 'piece' => 0, 'trace_source' => 'osm',
                'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat))]);
        }
    }

    private function importer(): StructureImporter
    {
        return new StructureImporter(
            new GeoplateformeClient($this->dir.'/ign', 0),
            new BdTopoConstructions($this->dir.'/ouvrages', 0),
            app(WikidataStructures::class),
            app(CommonsClient::class),
            app(WikimediaClient::class),
            $this->dir.'/ouvrages',
        );
    }

    public function test_dry_run_writes_nothing(): void
    {
        $stats = $this->importer()->run(true, true, false, fn () => null);

        $this->assertSame(2, $stats['structures']);
        $this->assertSame(0, DB::table('structures')->count());
    }

    public function test_import_names_dates_and_places_the_structures_on_their_lines(): void
    {
        $stats = $this->importer()->run(false, true, false, fn () => null);

        $this->assertSame(['tunnel' => 2], $stats['by_kind']);
        $this->assertSame(1, $stats['wikidata_matched']);

        $forest = DB::table('structures')->where('wikidata_id', 'Q42')->first();
        $this->assertSame('tunnel de la Forêt', $forest->name);
        $this->assertEqualsWithDelta(600, $forest->length_m, 2, 'longueur mesurée sur les deux tronçons assemblés');
        $this->assertSame('bdtopo', $forest->length_source);
        $this->assertSame('1880-01-01', $forest->built_on, 'la date de Wikidata reste');
        $this->assertSame('1882-05-14', $forest->opened_on);
        $this->assertSame('day', $forest->opened_precision);
        $sources = array_column((array) json_decode($forest->sources, true), null, 'source');
        $this->assertSame(['opened_on'], $sources['wikipedia']['fields']);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?oldid=123', $sources['wikipedia']['url']);
        $link = DB::table('line_structures')->where('structure_id', $forest->id)->first();
        $this->assertSame($this->a->id, $link->line_id);
        $this->assertEqualsWithDelta(101000, $link->pk_start_m, 3);
        $this->assertEqualsWithDelta(101600, $link->pk_end_m, 3);
        $this->assertSame([1900, 2030], json_decode($link->era_years, true));

        $old = DB::table('structures')->whereNull('wikidata_id')->first();
        $this->assertSame('tunnel', $old->kind);
        $oldLink = DB::table('line_structures')->where('structure_id', $old->id)->first();
        $this->assertSame($this->b->id, $oldLink->line_id, 'tunnel d\'une ligne déposée gardé');
        $this->assertEqualsWithDelta(52000, $oldLink->pk_start_m, 3);
        $this->assertEqualsWithDelta(52400, $oldLink->pk_end_m, 3);
        $this->assertSame([1900], json_decode($oldLink->era_years, true));

        $this->assertSame(0, DB::table('structures')->where('name', 'Pont de la Route')->count(), 'un pont qui croise la ligne n\'est pas à elle');
        $this->assertSame(0, DB::table('structures')->whereIn('name', ['Pont Neuf', 'pont Neuf'])->count(), 'pont routier nommé, sans voie ni classe ferroviaire');

        // Second passage : mêmes ouvrages, mis à jour sur place.
        $ids = DB::table('structures')->orderBy('id')->pluck('id')->all();
        $again = $this->importer()->run(false, true, false, fn () => null);
        $this->assertSame(0, $again['removed']);
        $this->assertSame($ids, DB::table('structures')->orderBy('id')->pluck('id')->all());
    }

    public function test_a_trace_cut_by_the_border_takes_the_wikidata_length(): void
    {
        $cache = json_decode((string) file_get_contents($this->dir.'/ouvrages/wikidata.json'), true);
        $cache['rows'][0]['lengths']['value'] = '13657|http://www.wikidata.org/entity/Q11573';
        file_put_contents($this->dir.'/ouvrages/wikidata.json', json_encode($cache));

        $this->importer()->run(false, true, false, fn () => null);

        $forest = DB::table('structures')->where('wikidata_id', 'Q42')->first();
        $this->assertSame(13657, $forest->length_m);
        $this->assertSame('wikidata', $forest->length_source);
        $sources = array_column((array) json_decode($forest->sources, true), 'fields', 'source');
        $this->assertSame(['geometry'], $sources['bdtopo'], 'la longueur n\'est plus celle de la BD TOPO');
        $this->assertContains('length_m', $sources['wikidata']);
    }
}
