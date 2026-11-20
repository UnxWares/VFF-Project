<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Commandes d'audit et d'orchestration : vff:rebuild-pipeline,
 * vff:audit-line-completeness, vff:resolve-event-sections,
 * vff:audit-current-network, vff:audit-coverage, vff:enrich-signalling.
 * Sources SNCF simulées, rapports écrits dans le bac à sable.
 */
class AuditAndPipelineCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
        Sleep::fake();
    }

    // ─── vff:rebuild-pipeline ────────────────────────────────────────────

    public function test_pipeline_runs_the_classification(): void
    {
        $this->artisan('vff:rebuild-pipeline')
            ->expectsOutputToContain('▶ vff:classify-lines')
            ->expectsOutputToContain('✓ Pipeline terminé (idempotent, relançable sans risque).')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_pipeline_stops_at_the_first_failing_step(): void
    {
        Http::fake(['ressources.data.sncf.com/*' => Http::response('indisponible', 503)]);

        $this->artisan('vff:rebuild-pipeline', ['--with-signalling' => true, '--with-graph' => true])
            ->expectsOutputToContain('▶ vff:import-sncf-signalling')
            ->expectsOutputToContain('✗ Échec à l\'étape vff:import-sncf-signalling : pipeline interrompu.')
            ->doesntExpectOutputToContain('▶ vff:build-network-graph')
            ->assertFailed();
    }

    public function test_pipeline_on_an_unknown_era_fails(): void
    {
        $this->artisan('vff:rebuild-pipeline', ['--era' => 1855])
            ->expectsOutputToContain('Ère 1855 introuvable : pipeline non lancé.')
            ->doesntExpectOutputToContain('▶ vff:classify-lines')
            ->assertFailed();
    }

    // ─── vff:audit-line-completeness ─────────────────────────────────────

    public function test_line_completeness_lists_deficient_and_missing_lines(): void
    {
        Http::fake(['ressources.data.sncf.com/*' => Http::response(['results' => [
            ['code_ligne' => '100000', 'pk_debut_r' => '000+000', 'pk_fin_r' => '010+000'],
            ['code_ligne' => '100000', 'pk_debut_r' => '010+000', 'pk_fin_r' => '020-500'],
            ['code_ligne' => '300000', 'pk_debut_r' => '000+000', 'pk_fin_r' => '005+000'],
            ['code_ligne' => '400000', 'pk_debut_r' => 'inconnu', 'pk_fin_r' => '005+000'],
        ]])]);
        $era = DB::table('eras')->where('year', 2030)->first();
        $short = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        // ~2,3 km tracés sur 19,5 km SNCF (PK « 020-500 » = 19 500 m) : 12 %.
        $this->track($short, Era::find($era->id), [[47.0, 2.0], [47.0, 2.03]]);
        $full = $this->rfnLine('300000', 'Ligne de Charlie à Delta');
        $this->track($full, Era::find($era->id), [[47.1, 2.0], [47.1, 2.07]]);
        $orphan = $this->rfnLine('200000', 'Ligne de Echo à Foxtrot');
        $this->track($orphan, Era::find($era->id), [[47.2, 2.0], [47.2, 2.01]]);

        $this->artisan('vff:audit-line-completeness', ['--threshold' => 30])
            ->expectsOutputToContain('2 lignes SNCF référencées')
            ->expectsOutputToContain('1 lignes en déficit (< 30% de la longueur SNCF) :')
            ->expectsOutputToContain('SNCF   19.5 km')
            ->expectsOutputToContain('1 lignes VFF absentes du référentiel SNCF : 200000')
            ->assertSuccessful();
    }

    public function test_line_completeness_refuses_to_audit_without_the_reference(): void
    {
        Http::fake(['ressources.data.sncf.com/*' => Http::response('', 503)]);

        $this->artisan('vff:audit-line-completeness')
            ->expectsOutputToContain('SNCF HTTP 503 : audit impossible.')
            ->assertFailed();
        $this->artisan('vff:audit-line-completeness', ['--era' => 1855])
            ->expectsOutputToContain('Ère introuvable')
            ->assertFailed();
    }

    // ─── vff:resolve-event-sections ──────────────────────────────────────

    private function lineWithReferenceTrace(): int
    {
        $line = $this->rfnLine('901000', 'Ligne de Alphaville à Gammaville');
        DB::statement("INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, 30395, '[\"test\"]', ST_GeomFromText('MULTILINESTRING((2.0 47.0, 2.2 47.0, 2.4 47.0))', 4326), now(), now())", [$line->id]);
        foreach ([['Alphaville', 2.0, 0], ['Betaville', 2.2, 15197], ['Gammaville', 2.4, 30395]] as [$name, $lon, $pk]) {
            DB::table('sncf_gares')->insert(['code_ligne' => '901000', 'libelle' => $name, 'commune' => $name, 'pk_m' => $pk,
                'lon' => $lon, 'lat' => 47.0, 'voyageurs' => true, 'fret' => true]);
        }

        return $line->id;
    }

    /** @param  array<string, mixed>  $attributes */
    private function event(int $lineId, array $attributes): int
    {
        return DB::table('line_events')->insertGetId($attributes + ['line_id' => $lineId, 'type' => 'closure_passenger',
            'event_date' => '1950-01-01', 'description' => 'Fermeture', 'source' => 'wikipedia', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_event_sections_dry_run_writes_nothing(): void
    {
        Http::fake(['data.geopf.fr/*' => Http::response(['features' => []]), 'geo.api.gouv.fr/*' => Http::response([])]);
        $lineId = $this->lineWithReferenceTrace();
        $id = $this->event($lineId, ['section_from' => 'Alphaville', 'section_to' => 'Betaville']);

        $this->artisan('vff:resolve-event-sections', ['--line' => '901000', '--dry-run' => true])
            ->expectsOutputToContain('Lignes à traiter : 1 (portée : pending)')
            ->expectsOutputToContain('À blanc : rien n\'a été enregistré.')
            ->assertSuccessful();

        $this->assertNull(DB::table('line_events')->where('id', $id)->value('section_resolution'));
    }

    public function test_event_sections_are_resolved_and_reported(): void
    {
        Http::fake(['data.geopf.fr/*' => Http::response(['features' => []]), 'geo.api.gouv.fr/*' => Http::response([])]);
        $lineId = $this->lineWithReferenceTrace();
        $places = $this->event($lineId, ['section_from' => 'Alphaville', 'section_to' => 'Betaville']);
        $whole = $this->event($lineId, ['type' => 'opening', 'event_date' => '1880-01-01']);
        $other = $this->event($lineId, ['source' => 'lignes-oubliees', 'section_pk_start_m' => 0, 'section_pk_end_m' => 15197]);
        $report = $this->sandbox.'/rapport/sections.csv';

        $this->artisan('vff:resolve-event-sections', ['--source' => 'wikipedia', '--report' => $report])
            ->expectsOutputToContain('Lignes à traiter : 1 (portée : pending)')
            ->assertSuccessful();

        $this->assertSame('places', DB::table('line_events')->where('id', $places)->value('section_resolution'));
        $this->assertSame('whole_line', DB::table('line_events')->where('id', $whole)->value('section_resolution'));
        $this->assertNull(DB::table('line_events')->where('id', $other)->value('section_resolution'), 'autre source : non traitée');

        $rows = array_map('str_getcsv', array_filter(explode("\n", File::get($report))));
        $this->assertSame(['id', 'line_id', 'source', 'type', 'event_date', 'section_from', 'section_to', 'pk_start_m', 'pk_end_m',
            'before', 'after', 'length_m', 'note_before', 'note_after', 'quote'], $rows[0]);
        $this->assertCount(3, $rows, 'en-tête + les deux événements dont la résolution change');
        $this->assertSame([(string) $places, 'places'], [$rows[1][0], $rows[1][10]]);

        // Deuxième passage : plus rien en attente.
        $this->artisan('vff:resolve-event-sections', ['--source' => 'wikipedia'])
            ->expectsOutputToContain('Lignes à traiter : 0 (portée : pending)')
            ->assertSuccessful();
        // Partition : la ligne n'est pas dans la part 1/2 si son id est pair, et inversement.
        $this->artisan('vff:resolve-event-sections', ['--all' => true, '--shard' => (($lineId + 1) % 2).'/2'])
            ->expectsOutputToContain('Lignes à traiter : 0 (portée : all)')
            ->assertSuccessful();
    }

    // ─── vff:audit-current-network ───────────────────────────────────────

    public function test_current_network_coverage_by_source(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        DB::statement("INSERT INTO sncf_line_geometries (code_ligne, libelle, geom, created_at, updated_at)
            VALUES ('100000', 'Exploitée', ST_GeomFromText('LINESTRING(2.0 47.0, 2.1 47.0)', 4326), now(), now())");
        // BD TOPO rattachée, décalée de ~11 m ; OSM rattachée mais seulement sur la moitié.
        foreach ([['bdtopo', 'LINESTRING(2.0 47.0001, 2.1 47.0001)'], ['osm', 'LINESTRING(2.0 47.0, 2.05 47.0)']] as $i => [$src, $wkt]) {
            DB::statement("INSERT INTO source_traces (source, source_ref, layer, line_id, properties, seen_at, geom)
                VALUES (?, ?, 'Voie ferrée principale', ?, '{}', now(), ST_Multi(ST_GeomFromText(?, 4326)))", [$src, "t{$i}", $line->id, $wkt]);
        }

        $this->artisan('vff:audit-current-network', ['--line' => ['100000']])
            ->expectsOutputToContain('Écart forme SNCF « Exploitée » → tracé bdtopo rattaché : médiane 11.1 m')
            ->expectsOutputToContain('Détail 100000 - Ligne de Alpha à Bravo')
            ->assertSuccessful();

        $rows = DB::table('rfn_trace_coverage')->orderBy('source')->get()->keyBy('source');
        $this->assertSame(['bdtopo', 'osm'], $rows->keys()->all());
        $this->assertEqualsWithDelta(7599, $rows['bdtopo']->length_m, 30);
        $this->assertEquals($rows['bdtopo']->length_m, $rows['bdtopo']->covered_m);
        $this->assertEqualsWithDelta($rows['osm']->length_m / 2, $rows['osm']->covered_m, 200);
        $this->assertEqualsWithDelta(11.1, (float) $rows['bdtopo']->offset_median_m, 0.2);
    }

    public function test_current_network_refuses_unknown_sources(): void
    {
        $this->artisan('vff:audit-current-network', ['--source' => ["osm' OR 1=1 --"]])
            ->expectsOutputToContain('Source inconnue')
            ->assertFailed();
        $this->assertSame(0, DB::table('rfn_trace_coverage')->count());
    }

    // ─── vff:audit-coverage ──────────────────────────────────────────────

    public function test_coverage_audit_writes_its_reports_in_storage(): void
    {
        $bare = $this->rfnLine('100000', 'Ligne sans tracé');
        $traced = $this->rfnLine('200000', 'Ligne tracée');
        DB::statement("INSERT INTO source_traces (source, source_ref, layer, line_id, name, length_m, seen_at, geom) VALUES
            ('osm', 'w1', 'rail', ?, 'Ligne tracée', 7600, now(), ST_Multi(ST_GeomFromText('LINESTRING(2.0 47.0, 2.1 47.0)', 4326))),
            ('archeoferroviaire', 'a1', 'projet', NULL, 'Projet de ligne Brest Le Conquet', 5000, now(), ST_Multi(ST_GeomFromText('LINESTRING(-4.4 48.4, -4.7 48.35)', 4326)))",
            [$traced->id]);

        $this->artisan('vff:audit-coverage', ['--skip-uncovered' => true])
            ->expectsOutputToContain('→ 1. Lignes sans tracé précis : 1 (dont 1 sans AUCUN tracé)')
            ->expectsOutputToContain('Fichiers produits :')
            ->assertSuccessful();

        $dir = storage_path('app/reports/audit-coverage');
        $this->assertStringStartsWith($this->sandbox, $dir, 'rapports dans le bac à sable');
        $sansTrace = File::get($dir.'/lignes-sans-trace.csv');
        $this->assertStringContainsString('Ligne sans tracé', $sansTrace);
        $this->assertStringNotContainsString('Ligne tracée', $sansTrace);
        $this->assertStringContainsString('projet jamais réalisé (volontairement non créé)', File::get($dir.'/traces-archeo-non-rattaches.csv'));
        unset($bare);
    }

    // ─── vff:enrich-signalling ───────────────────────────────────────────

    public function test_signalling_follows_the_french_rules(): void
    {
        $era = Era::where('year', 2030)->first();
        $curated = $this->rfnLine('752000', 'LGV Sud-Est', ['slug' => 'rfn-752000']);
        $this->track($curated, $era, [[47.0, 2.0], [47.0, 2.1]], ['max_speed_kmh' => 300, 'electrified' => true]);
        $lgv = $this->rfnLine('999000', 'LGV inconnue');
        $this->track($lgv, $era, [[47.1, 2.0], [47.1, 2.1]], ['max_speed_kmh' => 300, 'electrified' => true]);
        $classic220 = $this->rfnLine('570000', 'Ligne classique rapide');
        $this->track($classic220, $era, [[47.2, 2.0], [47.2, 2.1]], ['max_speed_kmh' => 220, 'electrified' => true]);
        $secondary = $this->rfnLine('580000', 'Ligne non électrifiée');
        $this->track($secondary, $era, [[47.3, 2.0], [47.3, 2.1]], ['max_speed_kmh' => 100]);
        $slow = $this->rfnLine('590000', 'Ligne lente');
        $this->track($slow, $era, [[47.4, 2.0], [47.4, 2.1]], ['max_speed_kmh' => 40]);

        $this->artisan('vff:enrich-signalling')->expectsOutputToContain('Total LGV : 1 segments curés')->assertSuccessful();

        $sig = fn ($line) => DB::table('line_segments')->where('line_id', $line->id)->first(['signalling', 'train_protection']);
        $this->assertSame(['TVM-300', 'TVM-300'], array_values((array) $sig($curated)));
        $this->assertSame(['TVM-430', 'TVM-430'], array_values((array) $sig($lgv)), 'LGV : la TVM est aussi la protection, jamais de KVB');
        $this->assertSame(['BAL', 'KVB'], array_values((array) $sig($classic220)), '220 km/h : ligne classique');
        $this->assertSame(['BAPR', 'KVB'], array_values((array) $sig($secondary)));
        $this->assertSame(['BMVU', null], array_values((array) $sig($slow)));

        $ranges = DB::table('line_signalling')->where('line_id', $classic220->id)->get();
        $this->assertCount(1, $ranges);
        $this->assertSame(['BAL', '["KVB"]', true], [$ranges[0]->signalling, $ranges[0]->train_protection, (bool) $ranges[0]->kvb_equipped]);
        $this->assertSame(0, $ranges[0]->pk_start_m);
        $this->assertEqualsWithDelta(7597, $ranges[0]->pk_end_m, 30);
        $this->assertFalse((bool) DB::table('line_signalling')->where('line_id', $lgv->id)->value('kvb_equipped'));

        $before = $this->snapshot(['line_segments', 'line_signalling'], ['id']);
        $this->artisan('vff:enrich-signalling')->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_segments', 'line_signalling'], ['id']), 'idempotent');
    }

    public function test_signalling_on_an_unknown_era_fails_without_writing(): void
    {
        $this->artisan('vff:enrich-signalling', ['--era' => 1855])->expectsOutputToContain('Ère introuvable')->assertFailed();
    }
}
