<?php

namespace Tests\Feature;

use App\Console\Commands\SourceRightsReport;
use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Support\SourceRights;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:source-rights : contrôle des provenances (--check) et mesure de ce que la carte
 * perdrait sans les sources incertaines (--impact). Lecture seule : aucune ligne modifiée.
 *
 * Lignes est-ouest de 0,1° (≈ 7,6 km à 47° N).
 */
class SourceRightsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Era $era;

    private float $lat = 47.0;

    private string $json;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->current()->create(['year' => 2030]);
        $this->json = tempnam(sys_get_temp_dir(), 'vff-droits-').'.json';
    }

    protected function tearDown(): void
    {
        // parent::tearDown() d'abord : une erreur de nettoyage ne doit pas laisser la transaction ouverte.
        try {
            parent::tearDown();
        } finally {
            // Le fichier JSON et le fichier vide que tempnam() a créé à côté.
            foreach ([$this->json, substr($this->json, 0, -strlen('.json'))] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function line(string $name): Line
    {
        $this->lat += 0.05;

        return Line::factory()->create(['name' => $name, 'code' => null, 'network' => null, 'description' => null]);
    }

    private function wkt(float $lon1 = 2.30, float $lon2 = 2.40): string
    {
        return sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $lon1, $this->lat, $lon2, $this->lat);
    }

    private function part(Line $line, string $source, ?string $matched, float $lon1 = 2.30, float $lon2 = 2.40): void
    {
        DB::table('line_reference_parts')->insert(['line_id' => $line->id, 'source' => $source, 'source_trace_ids' => '[]',
            'matched_source' => $matched, 'length_m' => (int) round(($lon2 - $lon1) / 0.1 * 7586),
            'geom' => DB::raw('ST_Multi('.$this->wkt($lon1, $lon2).')'), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function segment(Line $line, string $source, float $lon1 = 2.30, float $lon2 = 2.40): void
    {
        DB::table('line_segments')->insert(['line_id' => $line->id, 'era_id' => $this->era->id, 'status' => 'in_service',
            'traffic_type' => 'mixed', 'source' => $source, 'length_meters' => (int) round(($lon2 - $lon1) / 0.1 * 7586),
            'geom' => DB::raw($this->wkt($lon1, $lon2)), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function event(Line $line, string $source, ?string $url = null): void
    {
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'opening', 'event_date' => '1900-01-01',
            'description' => 'test', 'source' => $source, 'source_url' => $url, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function station(string $name, array $sources): void
    {
        $station = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($this->lat, 2.35), 'description' => null]);
        foreach ($sources as $i => $source) {
            DB::table('station_sources')->insert(['source' => $source, 'source_ref' => $name.$i, 'station_id' => $station->id, 'name' => $name,
                'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(2.35, %F), 4326)', $this->lat))]);
        }
    }

    /** @return array<string, mixed> */
    private function impact(): array
    {
        $this->artisan('vff:source-rights', ['--impact' => true, '--json' => $this->json])->assertSuccessful();

        return json_decode((string) file_get_contents($this->json), true);
    }

    public function test_check_passes_when_every_provenance_is_declared(): void
    {
        $line = $this->line('Ligne déclarée');
        $this->event($line, 'wikipedia');
        $this->event($line, 'manual', 'https://www.lignes-oubliees.com/index.php?page=x');

        $this->artisan('vff:source-rights', ['--check' => true])
            ->expectsOutputToContain('toutes les provenances présentes en base sont déclarées')
            ->assertSuccessful();
    }

    public function test_check_fails_on_an_undeclared_provenance(): void
    {
        $this->event($this->line('Ligne d\'un blog'), 'blog-inconnu');

        $this->artisan('vff:source-rights', ['--check' => true])
            ->expectsOutputToContain('line_events : provenance « blog-inconnu » absente du registre')
            ->assertFailed();
    }

    public function test_check_reads_every_table_of_the_registry(): void
    {
        $rights = new SourceRights;
        $declared = array_unique([...array_keys($rights->sources()), ...array_keys($rights->composites())]);

        $this->assertSame([], array_values(array_diff($declared, array_keys(SourceRightsReport::PROVENANCE_QUERIES))),
            'table du registre que vff:source-rights --check ne relit pas : ajoutez sa requête à PROVENANCE_QUERIES');
        foreach (SourceRightsReport::PROVENANCE_QUERIES as $table => $sql) {
            DB::select($sql); // chaque requête s'exécute sur le schéma réel
        }
    }

    public function test_check_fails_on_an_undeclared_signalling_source(): void
    {
        // Table oubliée jusqu'à la revue de la PR #18 : une valeur nouvelle passait le contrôle sans être vue.
        DB::table('line_signalling_events')->insert([
            'line_id' => $this->line('Ligne signalée')->id, 'system' => 'BAL', 'role' => 'block', 'event_date' => '1975-01-01',
            'date_precision' => 'year', 'scope' => 'unspecified', 'confidence' => 'high', 'origin' => 'text', 'source' => 'forum-inconnu',
            'source_title' => 'Fil de discussion', 'source_revision' => 1, 'source_url' => 'https://forum.example.org/1',
            'source_excerpt' => 'Le BAL arrive.', 'fingerprint' => sha1('forum'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('vff:source-rights', ['--check' => true])
            ->expectsOutputToContain('line_signalling_events : provenance « forum-inconnu » absente du registre')
            ->assertFailed();
    }

    public function test_impact_counts_direct_and_cited_uncertain_events(): void
    {
        $line = $this->line('Ligne datée');
        $this->event($line, 'lignes-oubliees', 'https://www.lignes-oubliees.com/index.php?page=x');
        $this->event($line, 'archeoferroviaire');
        $this->event($line, 'manual', 'https://www.lignes-oubliees.com/index.php?page=y');
        $this->event($line, 'manual', 'https://fr.wikipedia.org/w/index.php?title=X&oldid=1');
        $this->event($line, 'wikipedia');
        $only = $this->line('Ligne datée par lignes-oubliees seulement');
        $this->event($only, 'lignes-oubliees');

        $out = $this->impact();

        $this->assertSame(6, $out['evenements']['total']);
        $this->assertSame(4, $out['evenements']['concernes']);
        $this->assertSame(['lignes-oubliees' => 2, 'archeoferroviaire' => 1, 'manual' => 1], $out['evenements']['par_source']);
        $this->assertSame(1, $out['evenements']['lignes_sans_autre_date']);
    }

    public function test_impact_separates_defined_and_drawn_trace(): void
    {
        // Ligne A : définie par archeo, recalée sur OSM (dessin sûr) ; ligne B : dessin archeo seul ;
        // ligne C : BD TOPO + un bout archeo non recalé.
        $a = $this->line('A');
        $this->part($a, 'archeoferroviaire', 'osm');
        $this->segment($a, 'archeoferroviaire');
        $b = $this->line('B');
        $this->part($b, 'archeoferroviaire', null);
        $this->segment($b, 'archeoferroviaire');
        $c = $this->line('C');
        $this->part($c, 'bdtopo', null, 2.30, 2.35);
        $this->part($c, 'archeoferroviaire', 'connector', 2.35, 2.40);
        $this->segment($c, 'bdtopo', 2.30, 2.35);
        $this->segment($c, 'archeoferroviaire', 2.35, 2.40);

        $out = $this->impact();

        $this->assertEqualsWithDelta(22.8, $out['trace_reference']['km_total'], 0.1);
        $this->assertEqualsWithDelta(19.0, $out['trace_reference']['km_definis'], 0.1);
        $this->assertEqualsWithDelta(11.4, $out['trace_reference']['km_dessines'], 0.1);
        $this->assertSame(2, $out['trace_reference']['lignes_definies_seulement_par_elles']);
        $this->assertSame(1, $out['trace_reference']['lignes_dessinees_seulement_par_elles']);
        $this->assertSame([['ere' => 2030, 'km_en_service' => 23, 'km_definis' => 19, 'km_dessines' => 11]], $out['eres']);
    }

    public function test_impact_counts_stations_known_only_from_uncertain_sources(): void
    {
        $this->line('Ligne des gares');
        $this->station('Gare seulement lignes-oubliees', ['lignes-oubliees']);
        $this->station('Gare recoupée', ['lignes-oubliees', 'sncf']);
        $this->station('Gare OSM', ['osm']);

        $this->assertSame(1, $this->impact()['gares_seulement_par_elles']);
    }

    public function test_impact_writes_nothing_to_the_tables(): void
    {
        $line = $this->line('Ligne intacte');
        $this->part($line, 'archeoferroviaire', null);
        $this->segment($line, 'archeoferroviaire');
        $this->event($line, 'lignes-oubliees');
        $before = DB::selectOne('SELECT (SELECT COUNT(*) FROM line_events) e, (SELECT COUNT(*) FROM line_segments) s,
            (SELECT COUNT(*) FROM line_reference_parts) p, (SELECT MAX(updated_at) FROM line_segments) u');

        $this->impact();

        $this->assertEquals($before, DB::selectOne('SELECT (SELECT COUNT(*) FROM line_events) e, (SELECT COUNT(*) FROM line_segments) s,
            (SELECT COUNT(*) FROM line_reference_parts) p, (SELECT MAX(updated_at) FROM line_segments) u'));
    }
}
