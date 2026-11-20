<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\BuildLineGeometry;
use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommandSandbox;
use Tests\TestCase;
use Throwable;

/**
 * Commandes de géométrie et de statut SNCF : géométrie fusionnée et PK,
 * slugs canoniques, tracés SNCF (import, comblement, ré-attribution,
 * reclassement, audit), dates tirées des noms de tracés.
 */
class GeometryCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Era $past;

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->past = $this->era(1930);
        $this->current = $this->era(2030, current: true);
    }

    private function sncf(string $code, string $libelle, string $wkt): void
    {
        DB::insert('INSERT INTO sncf_line_geometries (code_ligne, libelle, geom, created_at, updated_at) VALUES (?, ?, ST_GeomFromText(?, 4326), NOW(), NOW())', [$code, $libelle, $wkt]);
    }

    /** @return list<array{string, string}> [statut, trafic] des tronçons de la ligne à l'ère, du nord au sud */
    private function states(Line $line, Era $era): array
    {
        return DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $era->id)
            ->orderByRaw('ST_YMax(geom) DESC')->get(['status', 'traffic_type'])
            ->map(fn ($s) => [$s->status, $s->traffic_type])->all();
    }

    // ─── vff:canonicalize-station-slugs ─────────────────────────────────

    public function test_osm_slugs_become_readable_unique_slugs_with_permanent_redirects(): void
    {
        $this->station('gare-dalpha', "Gare d'Alpha", 48.0, 2.0);
        $a = $this->station('osm-11-gare-d-alpha', "Gare d'Alpha", 48.1, 2.1, ['department_code' => '91']);
        $b = $this->station('osm-12-gare-d-alpha', "Gare d'Alpha", 48.2, 2.2, ['department_code' => '91']);
        $c = $this->station('osm-13-saint-emilion', 'Saint-Émilion', 44.9, -0.15);
        $d = $this->station('osm-14', '!!!', 45.0, 1.0);

        $this->artisan('vff:canonicalize-station-slugs')
            ->expectsOutputToContain('4 gares canonicalisées (+ redirections 301)')
            ->assertSuccessful();

        $slugs = DB::table('stations')->orderBy('id')->pluck('slug', 'id')->all();
        $this->assertSame('gare-dalpha-91', $slugs[$a->id]);
        $this->assertSame('gare-dalpha-91-2', $slugs[$b->id]);
        $this->assertSame('saint-emilion', $slugs[$c->id]);
        $this->assertSame('gare-'.$d->id, $slugs[$d->id]);
        $this->assertSame(
            ['osm-11-gare-d-alpha' => $a->id, 'osm-12-gare-d-alpha' => $b->id, 'osm-13-saint-emilion' => $c->id, 'osm-14' => $d->id],
            DB::table('station_redirects')->orderBy('old_slug')->pluck('station_id', 'old_slug')->all(),
        );

        // L'ancien lien répond par une redirection permanente vers le slug canonique.
        $this->get('/stations/osm-13-saint-emilion')->assertStatus(301)->assertRedirect('/stations/saint-emilion');

        // Idempotent : plus rien à canonicaliser.
        $snapshot = $this->snapshot(['stations', 'station_redirects']);
        $this->artisan('vff:canonicalize-station-slugs')->expectsOutputToContain('0 gares canonicalisées')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['stations', 'station_redirects']));
    }

    public function test_canonicalize_dry_run_lists_the_renames(): void
    {
        $this->station('osm-11-gare-de-beta', 'Gare de Beta', 48.1, 2.1);

        $this->artisan('vff:canonicalize-station-slugs', ['--dry-run' => true])
            ->expectsOutputToContain('osm-11-gare-de-beta  →  gare-de-beta')
            ->expectsOutputToContain('[DRY-RUN] ✓ 1 gares canonicalisées')
            ->assertSuccessful();

        $this->assertSame('osm-11-gare-de-beta', DB::table('stations')->value('slug'));
    }

    // ─── vff:build-line-geometry / vff:line-pk-of-point ─────────────────

    public function test_line_geometry_merges_active_track_and_measures_route_length(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        // Deux tronçons bout à bout (48.0 → 48.1 → 48.2 sur le méridien 2°E), un tronçon fermé ignoré.
        $this->track($line, $this->current, [[48.0, 2.0], [48.1, 2.0]]);
        $this->track($line, $this->current, [[48.1, 2.0], [48.2, 2.0]], ['status' => 'single_track']);
        $this->track($line, $this->current, [[47.0, 2.0], [47.5, 2.0]], ['status' => 'abandoned', 'traffic_type' => 'unknown']);
        // Ère passée sans tronçon actif : pas de géométrie.
        $this->track($line, $this->past, [[48.0, 2.0], [48.2, 2.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);

        $this->artisan('vff:build-line-geometry')->expectsOutputToContain('1 géométries construites (0 ignorées)')->assertSuccessful();
        $this->artisan('vff:build-line-geometry', ['--era' => 1930])->expectsOutputToContain('0 lignes avec segments actifs')->assertSuccessful();

        $rows = DB::table('line_geometries')->get();
        $this->assertCount(1, $rows);
        $g = $rows[0];
        $expected = (int) round(DB::selectOne("SELECT ST_Length(ST_GeomFromText('LINESTRING(2 48, 2 48.2)', 4326)::geography) AS m")->m);
        $this->assertSame([$line->id, $this->current->id, 2, 1], [$g->line_id, $g->era_id, $g->segment_count, $g->part_count]);
        $this->assertEqualsWithDelta(22_239, $g->length_m, 30);
        $this->assertEqualsWithDelta($expected, $g->length_m, 2);
        $this->assertSame($g->length_m, $g->route_length_m);
        $this->assertSame('MULTILINESTRING', DB::selectOne('SELECT GeometryType(geom) AS t FROM line_geometries')->t);

        // Rejouer remplace la géométrie de l'ère, sans doublon.
        $this->artisan('vff:build-line-geometry')->assertSuccessful();
        $this->assertSame(1, DB::table('line_geometries')->count());

        // PK par projection : au milieu ≈ 11,1 km, au départ 0.
        $this->assertEqualsWithDelta($expected / 2, BuildLineGeometry::pkOfPoint($line->id, 2.0005, 48.1), 60);
        $this->assertEqualsWithDelta(0, BuildLineGeometry::pkOfPoint($line->id, 2.0, 48.0), 1);
        $this->assertNull(BuildLineGeometry::pkOfPoint($line->id, 2.0, 48.0, $this->past->id));

        $this->artisan('vff:line-pk-of-point', ['--line' => '100000', '--lon' => 2.0, '--lat' => 48.1])
            ->expectsOutputToContain('Ligne [100000] Ligne de Alpha à Bravo, point (2.00000, 48.10000) → PK 11,1')
            ->assertSuccessful();
        $this->artisan('vff:line-pk-of-point', ['--line' => $line->slug, '--lon' => 2.0, '--lat' => 48.1, '--era' => 1930])
            ->expectsOutputToContain('Aucune géométrie pour la ligne 100000')
            ->assertFailed();
    }

    public function test_parallel_tracks_count_once_in_the_route_length(): void
    {
        $line = $this->rfnLine('200000', 'Ligne de Charlie à Delta');
        // Voie principale + voie parallèle à 10 m, plus courte, et une antenne disjointe.
        $this->track($line, $this->current, [[48.0, 2.0], [48.1, 2.0]]);
        $this->track($line, $this->current, [[48.02, 2.00013], [48.08, 2.00013]]);
        $this->track($line, $this->current, [[48.5, 2.5], [48.55, 2.5]]);

        $this->artisan('vff:build-line-geometry')->assertSuccessful();

        $g = DB::table('line_geometries')->first();
        $this->assertSame([3, 3], [$g->segment_count, $g->part_count]);
        $main = 11_120;
        $branch = 5_560;
        $this->assertEqualsWithDelta($main + 6_672 + $branch, $g->length_m, 40);
        $this->assertEqualsWithDelta($main + $branch, $g->route_length_m, 40);
    }

    public function test_line_pk_of_point_accepts_the_greenwich_meridian(): void
    {
        // Le méridien 0 traverse la France (Normandie, Gironde) : --lon=0 est valide.
        $line = $this->rfnLine('300000', 'Ligne de Caen à Lisieux');
        $this->track($line, $this->current, [[49.0, -0.1], [49.0, 0.1]]);
        $this->artisan('vff:build-line-geometry')->assertSuccessful();

        $this->artisan('vff:line-pk-of-point', ['--line' => '300000', '--lon' => '0', '--lat' => '49'])
            ->expectsOutputToContain('→ PK 7,3')
            ->assertSuccessful();
    }

    /** @return iterable<string, array{array<string, mixed>, string, int}> */
    public static function invalidPk(): iterable
    {
        yield 'sans ligne' => [['--lon' => 2, '--lat' => 48], 'Options --line, --lon et --lat requises.', 2];
        yield 'sans latitude' => [['--line' => '100000', '--lon' => 2], 'Options --line, --lon et --lat requises.', 2];
        yield 'ligne inconnue' => [['--line' => 'nulle-part', '--lon' => 2, '--lat' => 48], 'Ligne introuvable : nulle-part', 1];
    }

    #[DataProvider('invalidPk')]
    public function test_line_pk_of_point_rejects_invalid_input(array $args, string $error, int $code): void
    {
        $this->rfnLine('100000', 'Ligne de Alpha à Bravo');

        $this->artisan('vff:line-pk-of-point', $args)->expectsOutputToContain($error)->assertExitCode($code);
    }

    // ─── vff:dates-from-trace-names ─────────────────────────────────────

    public function test_trace_names_give_dated_events_on_the_trace_geometry(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $trace = fn (string $name, string $layer = 'disparue', ?int $lineId = null) => DB::table('source_traces')->insertGetId([
            'source' => 'archeoferroviaire', 'source_ref' => 'a-'.md5($name.$layer), 'layer' => $layer, 'name' => $name,
            'line_id' => $lineId ?? $line->id, 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_GeomFromText('LINESTRING(2 48, 2 48.1)', 4326)"),
        ]);
        $initial = $trace("Tracé initial (jusqu'en 1876)");
        $military = $trace('Marcq / Saint-Juvin (ligne militaire 14/18)');
        $maginot = $trace('Ligne Hagondange Vigy Mil 30-40 VN');
        $trace('Ligne exploitée (depuis 1990)', 'exploitee'); // couche des lignes exploitées : ignorée
        $trace('Tracé sans date', 'disparue');
        DB::table('source_traces')->where('id', $trace('Déviation (1905-1937)'))->update(['line_id' => null]); // non rattaché : ignoré

        $this->artisan('vff:dates-from-trace-names')
            ->expectsOutputToContain('✓ 2 ouvertures et 3 fermetures tirées des noms de 3 tracés.')
            ->assertSuccessful();

        $events = DB::table('line_events')->orderBy('fingerprint')
            ->get(['line_id', 'type', 'event_date', 'date_precision', 'confidence', 'source', 'section_resolution', 'source_trace_id', 'fingerprint', 'description']);
        $this->assertSame([
            ["archeo-name:{$initial}:closure", 'closure', '1876-01-01', 'medium'],
            ["archeo-name:{$military}:closure", 'closure', '1918-01-01', 'medium'],
            ["archeo-name:{$military}:opening", 'opening', '1914-01-01', 'medium'],
            ["archeo-name:{$maginot}:closure", 'closure', '1940-01-01', 'low'],
            ["archeo-name:{$maginot}:opening", 'opening', '1930-01-01', 'low'],
        ], $events->sortBy('fingerprint', SORT_NATURAL)->map(fn ($e) => [$e->fingerprint, $e->type, $e->event_date, $e->confidence])->values()->all());
        foreach ($events as $e) {
            $this->assertSame([$line->id, 'year', 'archeoferroviaire', 'trace'], [$e->line_id, $e->date_precision, $e->source, $e->section_resolution]);
            $this->assertStringContainsString("d'après le nom du tracé archeoferroviaire", $e->description);
            $this->assertStringNotContainsString('—', $e->description);
        }
        // La portion de l'événement est la géométrie du tracé.
        $this->assertSame(5, (int) DB::selectOne('SELECT COUNT(*) n FROM line_events e JOIN source_traces t ON t.id = e.source_trace_id WHERE ST_Equals(e.section_geom, t.geom)')->n);

        // Rejouable sans doublon.
        $snapshot = $this->snapshot(['line_events']);
        $this->artisan('vff:dates-from-trace-names')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['line_events']));
    }

    // ─── Tracés et statuts SNCF ─────────────────────────────────────────

    public function test_reclassify_status_kills_track_that_sncf_says_closed_but_never_resurrects(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($line, $this->current, [[48.9, 2.0], [49.0, 2.0]]);                 // tronçon déposé selon SNCF
        $this->track($line, $this->current, [[48.5, 2.0], [48.6, 2.0]]);                 // neutralisé ET exploité à côté : intouché
        $this->track($line, $this->current, [[48.0, 2.0], [48.1, 2.0]], ['status' => 'abandoned', 'traffic_type' => 'unknown']); // SNCF « Exploitée » : pas de résurrection
        $this->track($line, $this->past, [[48.9, 2.0], [49.0, 2.0]]);                    // autre ère : intouchée
        $this->sncf('100000', 'Fermée et déposée (Plus utilisable)', 'LINESTRING(2 48.9, 2 49)');
        $this->sncf('100000', 'Neutralisée', 'LINESTRING(2 48.5, 2 48.6)');
        $this->sncf('100000', 'Exploitée', 'LINESTRING(2.0001 48.5, 2.0001 48.6)');
        $this->sncf('100000', 'Exploitée', 'LINESTRING(2 48, 2 48.1)');
        $this->sncf('999000', 'Fermée', 'LINESTRING(2 48.9, 2 49)'); // autre code : ignoré

        $this->artisan('vff:reclassify-status', ['--dry-run' => true])
            ->expectsOutputToContain('→ dismantled: 1')->expectsOutputToContain('total : 1')->assertSuccessful();
        $this->assertSame([['in_service', 'mixed'], ['in_service', 'mixed'], ['abandoned', 'unknown']], $this->states($line, $this->current));

        $this->artisan('vff:reclassify-status')->expectsOutputToContain('1 segments reclassés')->assertSuccessful();
        $this->assertSame([['dismantled', 'unknown'], ['in_service', 'mixed'], ['abandoned', 'unknown']], $this->states($line, $this->current));
        $this->assertSame([['in_service', 'mixed']], $this->states($line, $this->past));

        $this->artisan('vff:reclassify-status')->expectsOutputToContain('0 segments reclassés')->assertSuccessful();
    }

    public function test_reclassify_status_without_dead_sncf_label_does_nothing(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($line, $this->current, [[48.0, 2.0], [48.1, 2.0]]);
        $this->sncf('100000', 'Exploitée', 'LINESTRING(2 48, 2 48.1)');

        $this->artisan('vff:reclassify-status')->expectsOutputToContain('Aucun libellé mort.')->assertSuccessful();
        $this->assertSame([['in_service', 'mixed']], $this->states($line, $this->current));
    }

    public function test_fill_missing_geometry_adds_sncf_track_only_to_lines_without_track(): void
    {
        $with = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($with, $this->current, [[48.0, 2.0], [48.1, 2.0]]);
        $without = $this->rfnLine('200000', 'Ligne de Charlie à Delta');
        $this->sncf('100000', 'Exploitée', 'LINESTRING(2 48, 2 48.1)');
        $this->sncf('200000', 'Neutralisée', 'LINESTRING(3 47, 3 47.1)');
        $this->sncf('200000', 'Fermée et déposée (Plus utilisable)', 'LINESTRING(3 47.1, 3 47.2)');

        $this->artisan('vff:fill-missing-geometry', ['--dry-run' => true])
            ->expectsOutputToContain('1 lignes sans tracé à combler')->expectsOutputToContain('[200000] Ligne de Charlie à Delta')->assertSuccessful();
        $this->assertSame(1, DB::table('line_segments')->count());

        $this->artisan('vff:fill-missing-geometry')->expectsOutputToContain('✓ 2 segments SNCF insérés pour 1 lignes')->assertSuccessful();
        $this->assertSame([['dismantled', 'unknown'], ['abandoned', 'unknown']], $this->states($without, $this->current));
        $this->assertSame([], $this->states($without, $this->past));
        $this->assertSame([['in_service', 'mixed']], $this->states($with, $this->current));
        $this->assertEqualsWithDelta(11_120, DB::table('line_segments')->where('line_id', $without->id)->min('length_meters'), 20);

        // Rejouer : la ligne a désormais un tracé, rien à combler.
        $this->artisan('vff:fill-missing-geometry')->expectsOutputToContain('0 lignes sans tracé')->assertSuccessful();
        $this->assertSame(3, DB::table('line_segments')->count());
    }

    public function test_import_segments_from_sncf_replaces_the_era_track(): void
    {
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($line, $this->current, [[40.0, 2.0], [40.1, 2.0]]); // ancien tracé de l'ère : remplacé
        $this->track($line, $this->past, [[40.0, 2.0], [40.1, 2.0]]);    // autre ère : conservée
        $this->sncf('100000', 'Exploitée', 'LINESTRING(2 48, 2 48.1)');
        $this->sncf('100000', 'Transférée en voie de service', 'LINESTRING(2 48.1, 2 48.2)');
        $this->sncf('555000', 'Exploitée', 'LINESTRING(5 45, 5 45.1)'); // code sans ligne VFF : ignoré

        $this->artisan('vff:import-segments-sncf')->expectsOutputToContain('✓ 2 segments insérés')->assertSuccessful();

        $this->assertSame([['service_track', 'unknown'], ['in_service', 'unknown']], $this->states($line, $this->current));
        $this->assertCount(1, $this->states($line, $this->past));
        $this->artisan('vff:import-segments-sncf')->assertSuccessful();
        $this->assertSame(3, DB::table('line_segments')->count());
    }

    public function test_reattribute_segments_moves_track_to_the_line_sncf_draws_there(): void
    {
        $wrong = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $right = $this->rfnLine('200000', 'Ligne de Charlie à Delta');
        $seg = $this->track($wrong, $this->current, [[48.5, 2.0], [48.6, 2.0]]);   // SNCF : ligne 200000 ici
        $ok = $this->track($wrong, $this->current, [[48.0, 2.0], [48.1, 2.0]]);    // bien attribué
        $this->sncf('100000', 'Exploitée', 'LINESTRING(2 48, 2 48.1)');
        $this->sncf('200000', 'Exploitée', 'LINESTRING(2.0002 48.5, 2.0002 48.6)');

        $this->artisan('vff:reattribute-segments')->expectsOutputToContain('✓ 1 segments réattribués')->assertSuccessful();

        $this->assertSame($right->id, DB::table('line_segments')->where('id', $seg->id)->value('line_id'));
        $this->assertSame($wrong->id, DB::table('line_segments')->where('id', $ok->id)->value('line_id'));
        $this->artisan('vff:reattribute-segments')->expectsOutputToContain('✓ 0 segments réattribués')->assertSuccessful();
    }

    public function test_audit_and_fix_lines_creates_names_reattributes_and_applies_sncf_status(): void
    {
        Http::fake(['ressources.data.sncf.com/*' => Http::response(['total_count' => 3, 'results' => [
            ['code_ligne' => '200000', 'lib_ligne' => 'Ligne de Charlie à Delta'],
            ['code_ligne' => '300000', 'lib_ligne' => 'Ligne de Echo à Foxtrot'],
            ['code_ligne' => '400000', 'lib_ligne' => '400000'], // libellé = code : ignoré
        ]])]);
        $alpha = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $anonymous = $this->rfnLine('200000', 'Ligne 200000');
        $moved = $this->track($alpha, $this->current, [[48.5, 2.0], [48.6, 2.0]]);
        $closed = $this->track($alpha, $this->current, [[48.0, 2.0], [48.1, 2.0]]);
        $this->sncf('100000', 'Fermée et déposée (Plus utilisable)', 'LINESTRING(2 48, 2 48.1)');
        $this->sncf('200000', 'Exploitée', 'LINESTRING(2.0002 48.5, 2.0002 48.6)');
        $this->sncf('300000', 'Neutralisée', 'LINESTRING(4 45, 4 45.1)');

        $this->artisan('vff:audit-and-fix-lines')
            ->expectsOutputToContain('1 codes_ligne absents de VFF')
            ->expectsOutputToContain('→ 1 segments ré-attribués')
            ->assertSuccessful();

        // Ligne manquante créée avec le libellé SNCF, ligne anonyme renommée.
        $created = DB::table('lines')->where('code', '300000')->first();
        $this->assertSame(['rfn-300000', 'Ligne de Echo à Foxtrot', null], [$created->slug, $created->name, $created->type]);
        $this->assertSame('Ligne de Charlie à Delta', DB::table('lines')->where('id', $anonymous->id)->value('name'));
        // Tronçon ré-attribué, et statut selon l'enum (« et déposée » → dismantled), sans trafic.
        $this->assertSame($anonymous->id, DB::table('line_segments')->where('id', $moved->id)->value('line_id'));
        $this->assertSame(['dismantled', 'unknown'], [
            DB::table('line_segments')->where('id', $closed->id)->value('status'),
            DB::table('line_segments')->where('id', $closed->id)->value('traffic_type'),
        ]);

        // Rejouable : plus rien à créer, renommer ni déplacer.
        $snapshot = $this->snapshot(['lines', 'line_segments']);
        $this->artisan('vff:audit-and-fix-lines')->expectsOutputToContain('0 codes_ligne absents')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['lines', 'line_segments']));
    }

    public function test_audit_and_fix_lines_needs_the_sncf_reference(): void
    {
        $this->artisan('vff:audit-and-fix-lines')
            ->expectsOutputToContain("sncf_line_geometries est vide. Lance d'abord vff:import-sncf-line-geometries.")
            ->assertFailed();
    }

    // ─── Ère inconnue ───────────────────────────────────────────────────

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function eraCommands(): iterable
    {
        foreach ([
            'vff:audit-and-fix-lines', 'vff:audit-line-completeness', 'vff:build-line-geometry', 'vff:classify-lines',
            'vff:enrich-signalling', 'vff:fill-missing-geometry', 'vff:import-segments-sncf', 'vff:import-sncf-signalling',
            'vff:import-sncf-station-status', 'vff:import-sncf-vmax', 'vff:reattribute-segments', 'vff:reclassify-status',
            'vff:import-gtfs-voyageurs', 'vff:build-shared-track', 'vff:derive-traffic',
        ] as $name) {
            yield $name => [$name, ['--era' => 1855]];
        }
        yield 'vff:set-status' => ['vff:set-status', ['code' => '100000', 'status' => 'abandoned', '--all' => true, '--era' => 1855]];
    }

    /** Ère inexistante : échec (code ≠ 0 ou exception), jamais d'écriture ni d'appel réseau. */
    #[DataProvider('eraCommands')]
    public function test_unknown_era_fails_without_writing(string $name, array $args): void
    {
        Http::fake();
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($line, $this->current, [[48.0, 2.0], [48.1, 2.0]]);
        $this->sncf('100000', 'Fermée', 'LINESTRING(2 48, 2 48.1)');
        $before = $this->snapshot();

        try {
            $code = Artisan::call($name, $args);
        } catch (Throwable $e) {
            $code = 'exception '.class_basename($e);
        }

        $this->assertNotSame(0, $code, "{$name} --era=1855 a réussi");
        $this->assertSame($before, $this->snapshot());
        Http::assertNothingSent();
    }
}
