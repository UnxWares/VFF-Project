<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Support\StationNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Chaîne des gares : dédoublonnage des sources (vff:consolidate-stations),
 * rattachement aux lignes avec PK et période de desserte
 * (vff:attach-stations), recalage sur la voie (vff:snap-stations).
 */
class StationCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Era $current;

    private Line $alpha;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->current = $this->era(2030, current: true);
        $this->alpha = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        // Voie nord-sud sur le méridien 2°E, de 48.00 à 48.10 (≈ 11,1 km).
        $this->track($this->alpha, $this->current, [[48.00, 2.0], [48.10, 2.0]]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function source(string $source, string $ref, string $name, float $lat, float $lng, array $attributes = []): int
    {
        $props = $attributes['properties'] ?? null;
        unset($attributes['properties']);

        return DB::table('station_sources')->insertGetId($attributes + [
            'source' => $source, 'source_ref' => $ref, 'name' => $name, 'name_norm' => StationNames::normalize($name),
            'kind' => 'gare', 'lifecycle' => 'active', 'properties' => $props === null ? null : json_encode($props),
            'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat)),
        ]);
    }

    /** @return array{0: float, 1: float} [lat, lng] arrondis à 1e-5 */
    private function position(int $stationId): array
    {
        $p = DB::selectOne('SELECT ST_Y(geom) lat, ST_X(geom) lng FROM stations WHERE id = ?', [$stationId]);

        return [round($p->lat, 5), round($p->lng, 5)];
    }

    // ─── vff:consolidate-stations ───────────────────────────────────────

    public function test_sources_of_the_same_station_are_merged_and_sncf_wins(): void
    {
        $sncf = $this->source('sncf', '87000001', 'Alpha', 48.0450, 2.0001, ['uic_code' => '87000001', 'passenger' => true, 'freight' => true]);
        $osm = $this->source('osm', 'n123', "Gare d'Alpha", 48.0452, 2.0003);
        $wikidata = $this->source('wikidata', 'Q1', "gare d'Alpha", 48.0460, 2.0010, ['uic_code' => '87000001', 'wikidata_id' => 'Q1']);
        $other = $this->source('osm', 'n456', 'Bravo', 48.0950, 2.0002);

        // Un passage à blanc d'abord : l'instance réutilisée ne doit rien garder du passage précédent.
        $this->artisan('vff:consolidate-stations', ['--dry-run' => true])->expectsOutputToContain('2 gares après dédoublonnage')->assertSuccessful();
        $this->assertSame(0, DB::table('stations')->count());
        $this->artisan('vff:consolidate-stations')
            ->expectsOutputToContain('4 objets sources')
            ->expectsOutputToContain('2 gares après dédoublonnage')
            ->assertSuccessful();

        $stations = DB::table('stations')->orderBy('name')->get();
        $this->assertSame(['Alpha', 'Bravo'], $stations->pluck('name')->all());
        $alpha = $stations[0];
        $this->assertSame(['87000001', 'Q1', 123, 3, 'sncf'], [$alpha->uic_code, $alpha->wikidata_id, $alpha->osm_node_id, $alpha->sources_count, $alpha->position_source]);
        $this->assertSame([48.045, 2.0001], $this->position($alpha->id));
        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertSame([$alpha->id, $alpha->id, $alpha->id], [$links[$sncf], $links[$osm], $links[$wikidata]]);
        $this->assertSame($stations[1]->id, $links[$other]);

        // Rejouable : mêmes gares (mêmes identifiants), mêmes rattachements.
        $snapshot = $this->snapshot(['stations', 'station_sources']);
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['stations', 'station_sources']));
    }

    public function test_consolidation_keeps_the_closures_read_by_import_station_closures(): void
    {
        $this->source('osm', 'n123', 'Alpha', 48.0450, 2.0001, ['lifecycle' => 'disused', 'opened_on' => '1880-05-01', 'opened_precision' => 'day']);
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $sid = (int) DB::table('stations')->value('id');
        $closure = fn (string $source) => ['station_id' => $sid, 'type' => 'closure', 'event_date' => '1960-01-01', 'date_precision' => 'year',
            'source' => $source, 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()];
        DB::table('station_events')->insert([$closure('wikipedia'), $closure('wikidata:qualifiers'), $closure('osm:note')]);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        // Les événements des sources de station_sources sont recréés (ouverture OSM),
        // ceux de vff:import-station-closures sont conservés.
        $this->assertEqualsCanonicalizing(['osm', 'osm:note', 'wikidata:qualifiers', 'wikipedia'],
            DB::table('station_events')->where('station_id', $sid)->pluck('source')->all());
    }

    public function test_a_station_whose_sources_disappear_is_removed(): void
    {
        $this->source('osm', 'n123', 'Alpha', 48.0450, 2.0001);
        $gone = $this->source('osm', 'n456', 'Bravo', 48.0950, 2.0002);
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $this->assertSame(2, DB::table('stations')->count());

        DB::table('station_sources')->where('id', $gone)->delete();
        $this->artisan('vff:consolidate-stations')->expectsOutputToContain('1 gares après dédoublonnage')->assertSuccessful();

        $this->assertSame(['Alpha'], DB::table('stations')->pluck('name')->all());
    }

    // ─── vff:attach-stations ────────────────────────────────────────────

    public function test_stations_are_attached_by_sncf_pk_and_by_proximity_with_projected_pk(): void
    {
        $byPk = $this->station('alpha', 'Alpha', 48.0450, 2.0000);
        $near = $this->station('beta', 'Beta', 48.0900, 2.0004);     // ≈ 30 m de la voie
        $loose = $this->station('gamma', 'Gamma', 48.0200, 2.0016);  // ≈ 120 m : seulement pour une position imprécise
        $far = $this->station('delta', 'Delta', 48.0500, 2.0150);    // ≈ 1,1 km : jamais
        $this->source('sncf', '87000001', 'Alpha', 48.0450, 2.0000, ['station_id' => $byPk->id, 'properties' => ['lines' => [['code_ligne' => '100000', 'pk_m' => 5_000]]]]);
        $this->source('osm', 'n2', 'Beta', 48.0900, 2.0004, ['station_id' => $near->id]);
        $this->source('wikidata', 'Q3', 'Gamma', 48.0200, 2.0016, ['station_id' => $loose->id]);
        DB::table('stations')->where('id', $loose->id)->update(['position_source' => 'wikidata']);
        DB::table('stations')->where('id', $near->id)->update(['position_source' => 'osm']);
        DB::table('line_events')->insert([
            ['line_id' => $this->alpha->id, 'type' => 'opening', 'event_date' => '1855-05-01', 'description' => 'Ouverture', 'created_at' => now(), 'updated_at' => now()],
            ['line_id' => $this->alpha->id, 'type' => 'closure', 'event_date' => '1990-01-01', 'description' => 'Fermeture', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('vff:attach-stations')->expectsOutputToContain('sncf_pk : 1')->assertSuccessful();

        $links = DB::table('station_lines')->orderBy('station_id')->get()->keyBy('station_id');
        $this->assertSame(['sncf_pk', 5_000, 'sncf'], [$links[$byPk->id]->method, $links[$byPk->id]->pk_m, $links[$byPk->id]->pk_source]);
        $this->assertSame('segment', $links[$near->id]->method);
        $this->assertEqualsWithDelta(30, $links[$near->id]->distance_m, 3);
        // PK interpolé depuis la gare de PK connu : 5 000 + (48.09 - 48.045) × 111,2 km.
        $this->assertSame('projected', $links[$near->id]->pk_source);
        $this->assertEqualsWithDelta(10_004, $links[$near->id]->pk_m, 15);
        $this->assertSame('segment', $links[$loose->id]->method);
        $this->assertArrayNotHasKey($far->id, $links->all());
        // Desserte : ouverture de la ligne ; la fermeture de 1990 est effacée car la voie est en service aujourd'hui.
        $this->assertSame([1855, null], [$links[$byPk->id]->first_year, $links[$byPk->id]->last_year]);

        // Rejouable (table recalculée) : même contenu.
        $snapshot = $this->snapshot(['station_lines'], except: ['id']);
        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['station_lines'], except: ['id']));
    }

    public function test_station_of_a_line_schema_is_attached_with_the_schema_pk(): void
    {
        // Gare d'un schéma Wikipédia posée par vff:import-station-sources --source=pk, à 1,1 km de la voie
        // dessinée : rattachée par sa liste (PK recalé dans le repère officiel), pas par proximité.
        $kappa = $this->station('kappa', 'Kappa', 48.0630, 2.0150);
        $this->source('wikipedia_schema', 'L1:kappa', 'Kappa', 48.0630, 2.0150, ['station_id' => $kappa->id, 'lifecycle' => 'unknown',
            'properties' => ['line_id' => $this->alpha->id, 'pk_m' => 7_000, 'geocoding' => 'pk', 'precision_m' => 450]]);

        $this->artisan('vff:attach-stations')->assertSuccessful();

        $link = DB::table('station_lines')->where('station_id', $kappa->id)->first();
        $this->assertSame([$this->alpha->id, 'source_pk', 7_000, 'wikipedia_schema'], [$link->line_id, $link->method, $link->pk_m, $link->pk_source]);
    }

    public function test_pk_is_projected_across_a_small_gap_between_two_pieces_of_the_trace(): void
    {
        // Ligne sans tracé SNCF : tracé de référence en deux morceaux bout à bout, séparés de 55 m (deux sources).
        $beta = $this->rfnLine('200000', 'Ligne de Gamma à Delta');
        DB::table('line_reference_geometries')->insert(['line_id' => $beta->id, 'length_m' => 11100, 'sources' => '[]',
            'geom' => DB::raw("ST_GeomFromText('MULTILINESTRING((2.1 48.0, 2.1 48.05), (2.1 48.0505, 2.1 48.1))', 4326)"),
            'created_at' => now(), 'updated_at' => now()]);
        $gamma = $this->station('gamma-b', 'Gamma', 48.01, 2.1);
        $delta = $this->station('delta-b', 'Delta', 48.08, 2.1);
        $this->source('sncf', '87000009', 'Gamma', 48.01, 2.1, ['station_id' => $gamma->id, 'properties' => ['lines' => [['code_ligne' => '200000', 'pk_m' => 1_000]]]]);

        $this->artisan('vff:attach-stations')->assertSuccessful();

        $link = DB::table('station_lines')->where(['station_id' => $delta->id, 'line_id' => $beta->id])->first();
        // 1 000 + (48,08 - 48,01) × 111,2 km, et non l'abscisse depuis le début du 2e morceau (3,3 km).
        $this->assertSame('projected', $link->pk_source);
        $this->assertEqualsWithDelta(8_785, $link->pk_m, 60);
    }

    public function test_closed_line_gives_the_last_service_year_unless_reopened(): void
    {
        $closed = $this->rfnLine('300000', 'Ligne de Charlie à Delta');
        $this->track($closed, $this->current, [[47.0, 3.0], [47.1, 3.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $station = $this->station('charlie', 'Charlie', 47.05, 3.0001);
        $this->source('osm', 'n9', 'Charlie', 47.05, 3.0001, ['station_id' => $station->id]);
        DB::table('line_events')->insert([
            ['line_id' => $closed->id, 'type' => 'opening', 'event_date' => '1880-01-01', 'description' => 'Ouverture', 'created_at' => now(), 'updated_at' => now()],
            ['line_id' => $closed->id, 'type' => 'closure_passenger', 'event_date' => '1938-10-02', 'description' => 'Voyageurs', 'created_at' => now(), 'updated_at' => now()],
            ['line_id' => $closed->id, 'type' => 'closure', 'event_date' => '1950-06-01', 'description' => 'Fermeture', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('vff:attach-stations')->assertSuccessful();
        // Fermeture aux seuls voyageurs : pas la fin de la desserte.
        $this->assertSame([1880, 1950], array_values((array) DB::table('station_lines')->where('station_id', $station->id)->first(['first_year', 'last_year'])));

        DB::table('line_events')->insert(['line_id' => $closed->id, 'type' => 'reopening', 'event_date' => '1995-01-01', 'description' => 'Réouverture', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertNull(DB::table('station_lines')->where('station_id', $station->id)->value('last_year'));
    }

    /**
     * Fin de desserte (contrôle qualité du 2026-10-20, Briouze et Lonlay-l'Abbaye « desservies de 1869 à
     * 2006 » sur la fiche gare, fermées en 1970 sur la fiche ligne) : la fin du service voyageurs ou la
     * fermeture totale, pas un déclassement ni une dépose postérieurs ; à défaut, le plus ancien des deux.
     */
    public function test_service_ends_with_the_closure_not_a_later_declassification(): void
    {
        $closed = $this->rfnLine('300000', 'Ligne de Charlie à Delta');
        $this->track($closed, $this->current, [[47.0, 3.0], [47.1, 3.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $station = $this->station('charlie', 'Charlie', 47.05, 3.0001);
        $this->source('osm', 'n9', 'Charlie', 47.05, 3.0001, ['station_id' => $station->id]);
        $event = fn (string $type, string $date) => DB::table('line_events')->insert(['line_id' => $closed->id, 'type' => $type,
            'event_date' => $date, 'description' => $type, 'created_at' => now(), 'updated_at' => now()]);
        $event('opening', '1869-06-01');
        // Déclassement d'un projet antérieur, avant l'ouverture : ignoré.
        $event('declassification', '1865-07-07');
        $event('declassification', '2006-03-01');
        $event('dismantling', '1990-01-01');
        $years = fn () => array_values((array) DB::table('station_lines')->where('station_id', $station->id)->first(['first_year', 'last_year']));

        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertSame([1869, 1990], $years(), 'sans fermeture : le plus ancien déclassement ou dépose');

        $event('closure_passenger', '1970-05-31');
        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertSame([1869, 1970], $years(), 'fin des voyageurs, pas le déclassement de 2006');

        // --years-only : recalcul des seules années, sans toucher aux lignes exclues.
        DB::table('station_lines')->where('station_id', $station->id)->update(['last_year' => 2006]);
        $this->artisan('vff:attach-stations', ['--years-only' => true, '--except' => ['300000']])->assertSuccessful();
        $this->assertSame([1869, 2006], $years());
        $this->artisan('vff:attach-stations', ['--years-only' => true])->assertSuccessful();
        $this->assertSame([1869, 1970], $years());
    }

    public function test_junction_station_is_attached_to_the_branch_it_terminates(): void
    {
        // liste-des-gares ne cite Briouze que sous la ligne principale (100000) ; la branche 434000
        // « Ligne de Briouze à La Ferté-Macé » en part : avant, ses seules gares étaient ses haltes.
        $branch = $this->rfnLine('434000', 'Ligne de Briouze à La Ferté-Macé');
        $this->track($branch, $this->current, [[48.05, 2.0], [48.05, 2.1]]);
        // Branche sans nom de terminus connu, partant de Kilo : extrémité de son tracé SNCF.
        $spur = $this->rfnLine('500000', 'Ligne de Golf à Hotel');
        $this->track($spur, $this->current, [[48.03, 2.0], [48.03, 1.9]]);
        DB::table('sncf_line_geometries')->insert(['code_ligne' => '500000', 'libelle' => 'Golf - Hotel', 'pk_debut_m' => 0, 'pk_fin_m' => 7_400,
            'rg_troncon' => 1, 'idgaia' => 'g-500000', 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw('ST_SetSRID(ST_MakeLine(ST_MakePoint(2.0, 48.03), ST_MakePoint(1.9, 48.03)), 4326)')]);
        // Ligne qui passe sous la gare Zulu sans s'y arrêter (pont) : jamais rattachée.
        $bridge = $this->rfnLine('600000', 'Ligne de Xray à Yankee');
        $this->track($bridge, $this->current, [[48.08, 1.95], [48.08, 2.05]]);
        foreach (['434000', '500000', '600000', '100000'] as $code) {
            DB::table('sncf_gares')->insert(['code_ligne' => $code, 'libelle' => "Halte {$code}", 'code_uic' => "8790{$code}", 'pk_m' => 3_000]);
        }
        $stations = [];
        foreach (['Briouze' => [48.05, 5_000], 'Kilo' => [48.03, 3_000], 'Zulu' => [48.08, 9_000]] as $name => [$lat, $pk]) {
            $stations[$name] = $this->station(strtolower($name), $name, $lat, 2.0, ['position_source' => 'sncf']);
            $this->source('sncf', "8700{$pk}", $name, $lat, 2.0, ['station_id' => $stations[$name]->id,
                'properties' => ['lines' => [['code_ligne' => '100000', 'pk_m' => $pk]]]]);
        }

        $this->artisan('vff:attach-stations')->assertSuccessful();

        $links = fn (Station $s) => DB::table('station_lines as sl')->join('lines as l', 'l.id', '=', 'sl.line_id')
            ->where('sl.station_id', $s->id)->orderBy('l.code')->pluck('sl.method', 'l.code')->all();
        $this->assertSame(['100000' => 'sncf_pk', '434000' => 'junction'], $links($stations['Briouze']));
        $this->assertSame(['100000' => 'sncf_pk', '500000' => 'junction'], $links($stations['Kilo']));
        $this->assertSame(['100000' => 'sncf_pk'], $links($stations['Zulu']));
        // PK projeté sur la branche (origine) pour l'ordre des arrêts.
        $this->assertNotNull(DB::table('station_lines')->where('station_id', $stations['Briouze']->id)->where('line_id', $branch->id)->value('pk_m'));
    }

    // ─── vff:snap-stations ──────────────────────────────────────────────

    public function test_stations_off_their_track_are_moved_onto_it(): void
    {
        $offset = $this->station('alpha', 'Alpha', 48.0500, 2.0015, ['position_source' => 'sncf']);   // ≈ 110 m à l'est : recalée
        $onTrack = $this->station('beta', 'Beta', 48.0200, 2.0001, ['position_source' => 'sncf']);    // ≈ 7 m : intouchée
        $wrongSource = $this->station('gamma', 'Gamma', 48.0800, 2.0100, ['position_source' => 'sncf']); // ≈ 745 m, OSM sur les quais
        $loose = $this->station('delta', 'Delta', 48.0300, 2.0015, ['position_source' => 'wikidata']);  // rattachement lâche : intouchée
        foreach ([[$offset, 'sncf_pk', 0], [$onTrack, 'sncf_pk', 0], [$wrongSource, 'sncf_pk', 0], [$loose, 'trace', 150]] as [$s, $method, $d]) {
            DB::table('station_lines')->insert(['station_id' => $s->id, 'line_id' => $this->alpha->id, 'method' => $method, 'distance_m' => $d, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->source('sncf', '87000003', 'Gamma', 48.0800, 2.0100, ['station_id' => $wrongSource->id]);
        $this->source('osm', 'n3', 'Gamma', 48.0800, 2.0002, ['station_id' => $wrongSource->id]);

        $this->artisan('vff:snap-stations', ['--dry-run' => true])
            ->expectsOutputToContain('Source de position corrigée : 1 gare(s) ; recalées sur la voie : 1 gare(s) (simulation)')
            ->assertSuccessful();
        $this->assertSame([48.05, 2.0015], $this->position($offset->id));

        $this->artisan('vff:snap-stations')->expectsOutputToContain('Gares hors voie (> 40 m de toute voie) : 3')->assertSuccessful();

        $this->assertSame([48.05, 2.0], $this->position($offset->id));
        $this->assertSame([48.02, 2.0001], $this->position($onTrack->id));
        $this->assertSame([48.08, 2.0002], $this->position($wrongSource->id));
        $this->assertSame('osm', DB::table('stations')->where('id', $wrongSource->id)->value('position_source'));
        $this->assertSame([48.03, 2.0015], $this->position($loose->id));

        $prov = json_decode(DB::table('stations')->where('id', $offset->id)->value('provenance'), true);
        $this->assertSame('recalage:sncf', $prov['geom']);
        $this->assertSame(['voie', [2.0015, 48.05], $this->alpha->id], [$prov['geom_recalage']['mode'], $prov['geom_recalage']['snapped_from'], $prov['geom_recalage']['line_id']]);
        $this->assertEqualsWithDelta(112, $prov['geom_recalage']['snap_m'], 3);

        // Rejouable : plus rien à recaler.
        $this->artisan('vff:snap-stations')
            ->expectsOutputToContain('Source de position corrigée : 0 gare(s) ; recalées sur la voie : 0 gare(s)')
            ->assertSuccessful();
    }

    public function test_sncf_point_contradicted_by_two_agreeing_sources_takes_the_osm_position(): void
    {
        // Millau (gares-relecture, 2026-10-20) : point SNCF posé 1,4 km plus loin le long de la voie ;
        // OSM et Gares & Connexions concordent : position OSM. Une seule source contre le point SNCF : intouchée.
        $millau = $this->station('millau', 'Millau', 48.0500, 2.0001, ['position_source' => 'sncf', 'source_status' => 'active']);
        $alone = $this->station('lone', 'Seule', 48.0300, 2.0001, ['position_source' => 'sncf', 'source_status' => 'active']);
        foreach ([$millau, $alone] as $s) {
            DB::table('station_lines')->insert(['station_id' => $s->id, 'line_id' => $this->alpha->id, 'method' => 'sncf_pk', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->source('osm', 'n10936292223', 'Millau', 48.0630, 2.0001, ['station_id' => $millau->id]);
        $this->source('sncf_voyageurs', '87613000', 'Millau', 48.0632, 2.0002, ['station_id' => $millau->id]);
        $this->source('osm', 'n9', 'Seule', 48.0400, 2.0001, ['station_id' => $alone->id]);

        $this->artisan('vff:snap-stations')->expectsOutputToContain('Point SNCF démenti par OSM et une autre source (> 100 m) : 1 gare(s)')->assertSuccessful();

        $this->assertSame([48.063, 2.0001], $this->position($millau->id));
        $this->assertSame('osm', DB::table('stations')->where('id', $millau->id)->value('position_source'));
        $this->assertSame([48.03, 2.0001], $this->position($alone->id));
        $prov = json_decode(DB::table('stations')->where('id', $millau->id)->value('provenance'), true);
        $this->assertSame(['concordance', 'sncf_voyageurs'], [$prov['geom_recalage']['mode'], $prov['geom_recalage']['agree']]);
    }
}
