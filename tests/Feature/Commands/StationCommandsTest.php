<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\StationTerminusOverrides;
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

    /**
     * Dates des événements retenus, gardées avec leur précision pour vff:build-station-eras (La Fouillouse :
     * fermeture le 26/09/1857, gare ouverte le 24/11/1857, même année) : la plus ancienne ouverture, la moins
     * précise à date égale ; la fin du service qui peut tomber le plus tard (1857 à l'année après le 26/09).
     */
    public function test_service_dates_are_kept_with_their_precision(): void
    {
        $closed = $this->rfnLine('300000', 'Ligne de Charlie à Delta');
        $this->track($closed, $this->current, [[47.0, 3.0], [47.1, 3.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $station = $this->station('charlie', 'Charlie', 47.05, 3.0001);
        $this->source('osm', 'n9', 'Charlie', 47.05, 3.0001, ['station_id' => $station->id]);
        $event = fn (string $type, string $date, string $precision) => DB::table('line_events')->insert(['line_id' => $closed->id, 'type' => $type,
            'event_date' => $date, 'date_precision' => $precision, 'description' => $type, 'created_at' => now(), 'updated_at' => now()]);
        $event('opening', '1827-01-01', 'year');
        $event('opening_freight', '1827-01-01', 'day');
        $event('closure', '1857-09-26', 'day');
        $dates = fn () => array_values((array) DB::table('station_lines')->where('station_id', $station->id)
            ->first(['first_year', 'first_event_date', 'first_event_precision', 'last_year', 'last_event_date', 'last_event_precision']));

        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertSame([1827, '1827-01-01', 'year', 1857, '1857-09-26', 'day'], $dates());

        // Une fermeture connue à l'année peut tomber après le 26/09 : c'est elle qui compte.
        $event('closure_passenger', '1857-01-01', 'year');
        $this->artisan('vff:attach-stations', ['--years-only' => true])->assertSuccessful();
        $this->assertSame([1827, '1827-01-01', 'year', 1857, '1857-01-01', 'year'], $dates());

        // Ligne de nouveau en service à la gare : ni fin, ni date de fin.
        $this->track($closed, $this->current, [[47.0, 3.0], [47.1, 3.0]]);
        $this->artisan('vff:attach-stations', ['--years-only' => true])->assertSuccessful();
        $this->assertSame([1827, '1827-01-01', 'year', null, null, null], $dates());
    }

    /**
     * Pau - Canfranc (664000) : fermée d'Oloron à Canfranc en 1970, rouverte d'Oloron à Bedous en 2016.
     * La réouverture ne prolonge que la desserte des gares de la portion rouverte : Accous, au-delà de
     * Bedous, reste desservie jusqu'en 1970 (avant le 19/11/2026 : « desservie depuis 1928 », sans fin).
     */
    public function test_reopening_of_another_portion_does_not_extend_service(): void
    {
        $line = $this->rfnLine('664000', 'Ligne de Pau à Canfranc');
        $this->track($line, $this->current, [[47.0, 3.0], [47.1, 3.0]], ['status' => 'abandoned', 'traffic_type' => 'unknown']);
        $stations = [];
        foreach (['Bedous' => [47.045, 5_000], 'Accous' => [47.072, 8_000]] as $name => [$lat, $pk]) {
            $stations[$name] = $this->station(strtolower($name), $name, $lat, 3.0);
            $this->source('sncf', "8766{$pk}", $name, $lat, 3.0, ['station_id' => $stations[$name]->id,
                'properties' => ['lines' => [['code_ligne' => '664000', 'pk_m' => $pk]]]]);
        }
        $event = fn (string $type, string $date, ?array $pk) => DB::table('line_events')->insert(['line_id' => $line->id, 'type' => $type,
            'event_date' => $date, 'description' => $type, 'section_pk_start_m' => $pk[0] ?? null, 'section_pk_end_m' => $pk[1] ?? null,
            'section_resolution' => $pk === null ? 'whole_line' : 'pk', 'created_at' => now(), 'updated_at' => now()]);
        $event('opening', '1928-07-18', null);
        $event('closure', '1970-03-27', [0, 11_100]);
        $event('reopening_passenger', '2016-06-26', [0, 6_000]);
        $years = fn (string $name) => array_values((array) DB::table('station_lines')->where('station_id', $stations[$name]->id)->first(['first_year', 'last_year']));

        $this->artisan('vff:attach-stations')->assertSuccessful();

        $this->assertSame([1928, null], $years('Bedous'), 'portion rouverte : desservie aujourd\'hui');
        $this->assertSame([1928, 1970], $years('Accous'), 'portion restée fermée');
    }

    /**
     * Ligne secondaire sans liste officielle (Lorient - Gourin, Caussade - Caylus) : son tracé s'arrête sur
     * la place de la gare, à 100-400 m. La gare de même nom qu'une extrémité est rattachée (terminus_name),
     * jamais une gare ambiguë, jamais en double, jamais sur une ligne du RFN (liste SNCF).
     */
    public function test_named_terminus_of_a_secondary_line_is_attached(): void
    {
        $secondary = $this->rfnLine('', 'Ligne de Kilo à Lima', ['code' => null, 'network' => 'secondaire']);
        // Tracé de 47,000 à 47,100 ; Kilo à 300 m au sud de son début, Lima-Ville et Lima-Port à 200 m de sa fin.
        $this->track($secondary, $this->current, [[47.0, 4.0], [47.1, 4.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $kilo = $this->station('kilo', 'Kilo', 46.9973, 4.0);
        $this->station('lima-ville', 'Lima-Ville', 47.1018, 4.0);
        $this->station('lima-port', 'Lima-Port', 47.1015, 4.0010);
        // Gare intermédiaire à 300 m : pas une extrémité, pas rattachée.
        $this->station('mike', 'Mike', 47.05, 4.0040);
        // Extrémité nommée, mais au milieu du tracé, loin de ses bouts (Le Touquet-Aéroport sur « Ligne
        // d'Etaples au Touquet ») : pas le terminus.
        $other = $this->rfnLine('', 'Ligne de Oscar à Papa', ['code' => null, 'network' => 'secondaire']);
        $this->track($other, $this->current, [[47.2, 4.0], [47.3, 4.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $this->station('papa', 'Papa', 47.25, 4.0030);
        // Ligne du RFN (liste SNCF) dont Kilo serait aussi une extrémité : la règle ne s'y applique pas.
        $rfn = $this->rfnLine('700000', 'Ligne de Kilo à November');
        $this->track($rfn, $this->current, [[47.0, 4.0], [47.0, 4.1]]);
        DB::table('sncf_gares')->insert(['code_ligne' => '700000', 'libelle' => 'NOVEMBER', 'code_uic' => '87700000', 'pk_m' => 7_000]);
        DB::table('line_events')->insert(['line_id' => $secondary->id, 'type' => 'opening', 'event_date' => '1903-06-01',
            'description' => 'Ouverture', 'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now()]);
        $links = fn (Line $line) => DB::table('station_lines as sl')->join('stations as s', 's.id', '=', 'sl.station_id')
            ->where('sl.line_id', $line->id)->orderBy('s.name')->get(['s.name', 'sl.method', 'sl.distance_m', 'sl.first_year'])
            ->map(fn ($r) => [$r->name, $r->method, $r->first_year])->all();

        $this->artisan('vff:attach-stations')->expectsOutputToContain('terminus nommés : 1')->assertSuccessful();

        $this->assertSame([['Kilo', 'terminus_name', 1903]], $links($secondary));
        $this->assertEqualsWithDelta(300, DB::table('station_lines')->where('station_id', $kilo->id)->value('distance_m'), 10);
        $this->assertNotContains('terminus_name', DB::table('station_lines')->where('line_id', $rfn->id)->pluck('method')->all());
        $this->assertSame([], $links($other));

        // --termini-only : ajout sans rien retirer, et jamais deux fois.
        DB::table('station_lines')->where('line_id', $secondary->id)->delete();
        $this->artisan('vff:attach-stations', ['--termini-only' => true])->expectsOutputToContain('terminus nommés : 1')->assertSuccessful();
        $this->artisan('vff:attach-stations', ['--termini-only' => true])->expectsOutputToContain('terminus nommés : 0')->assertSuccessful();
        $this->assertSame([['Kilo', 'terminus_name', 1903]], $links($secondary));
    }

    /**
     * Terminus relus à la main (database/data/station_terminus_overrides.php) : « attach » rattache la gare
     * nommée par la source même loin du bout du tracé (gare propre d'une compagnie, à 900 m), « none »
     * empêche la règle des terminus nommés ; décision invalide signalée et ignorée ; --years-only les
     * applique, --dry-run n'écrit rien, un second passage n'ajoute rien.
     */
    public function test_reviewed_termini_are_applied_from_the_decisions_file(): void
    {
        $secondary = ['code' => null, 'network' => 'secondaire'];
        $quebec = $this->rfnLine('', 'Ligne de Quebec à Romeo', $secondary);
        $this->track($quebec, $this->current, [[47.0, 5.0], [47.1, 5.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        // Gare propre de la compagnie à 900 m du début du tracé : hors de portée de la règle (500 m).
        $quebecEst = $this->station('quebec-est', 'Quebec-Est', 46.9919, 5.0);
        $sierra = $this->rfnLine('', 'Ligne de Sierra à Tango', $secondary);
        $this->track($sierra, $this->current, [[47.2, 5.0], [47.3, 5.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        // Gare de même nom à 200 m du bout : la règle la rattacherait, la relecture dit « rien ».
        $this->station('sierra', 'Sierra', 47.1982, 5.0);
        $source = 'https://fr.wikipedia.org/w/index.php?title=Quebec&oldid=1';
        $this->app->instance(StationTerminusOverrides::class, new StationTerminusOverrides([
            ['line' => $quebec->slug, 'terminus' => 'Quebec', 'decision' => 'attach', 'station' => 'quebec-est', 'source_url' => $source, 'reason' => 'tête de ligne'],
            ['line' => $sierra->slug, 'terminus' => 'Sierra', 'decision' => 'none', 'source_url' => null, 'reason' => 'terminus sur une place'],
            ['line' => $quebec->slug, 'terminus' => 'Romeo', 'decision' => 'attach', 'station' => 'quebec-est', 'source_url' => null, 'reason' => 'sans source'],
            ['line' => 'ligne-absente', 'terminus' => 'Uniform', 'decision' => 'none', 'source_url' => null, 'reason' => 'ligne d\'une autre base'],
        ]));
        $links = fn () => DB::table('station_lines')->whereIn('line_id', [$quebec->id, $sierra->id])->orderBy('line_id')
            ->get(['station_id', 'line_id', 'method', 'source_ref'])->map(fn ($r) => (array) $r)->all();

        $this->artisan('vff:attach-stations', ['--years-only' => true, '--dry-run' => true])
            ->expectsOutputToContain('terminus relus : 1 ajoutés, 1 sans gare, 1 lignes absentes de la base')
            ->expectsOutputToContain('attach : station (slug) et source_url')
            ->assertSuccessful();
        $this->assertSame([], $links(), '--dry-run : rien d\'écrit');

        $this->artisan('vff:attach-stations', ['--years-only' => true])->assertSuccessful();
        $expected = [['station_id' => $quebecEst->id, 'line_id' => $quebec->id, 'method' => 'terminus_reviewed', 'source_ref' => 'relu:'.$source]];
        $this->assertSame($expected, $links());
        $this->assertEqualsWithDelta(900, DB::table('station_lines')->where('line_id', $quebec->id)->value('distance_m'), 10);
        $this->assertNotNull(DB::table('station_lines')->where('line_id', $quebec->id)->value('pk_m'), 'PK projeté sur le tracé');

        $this->artisan('vff:attach-stations', ['--years-only' => true])->expectsOutputToContain('terminus relus : 0 ajoutés')->assertSuccessful();
        // Rattachement complet et --termini-only : même résultat, la règle ne touche pas Sierra.
        $this->artisan('vff:attach-stations', ['--termini-only' => true])->assertSuccessful();
        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertSame($expected, $links());
    }

    /**
     * Une décision relue l'emporte sur ce qui est déjà en base, même en --years-only (relecture du 19/11/2026
     * au soir) : « none » retire le terminus nommé de son extrémité (Sainte-Foy-l'Argentière sur la ligne
     * inachevée de Pontcharra) et le terminus relu qu'aucune décision « attach » ne porte plus (Lyon-Est sur
     * le tramway OTL de Lyon à La Balme). L'autre extrémité, les autres méthodes et les lignes sans décision
     * ne bougent pas ; --dry-run n'écrit rien ; un second passage ne retire plus rien.
     */
    public function test_reviewed_decisions_retract_terminus_links_already_in_base(): void
    {
        $secondary = ['code' => null, 'network' => 'secondaire'];
        $victor = $this->rfnLine('', 'Ligne de Victor à Whiskey', $secondary);
        $this->track($victor, $this->current, [[47.0, 6.0], [47.1, 6.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        // Gares de même nom à 170 m des deux bouts du tracé : la règle des terminus nommés les rattache.
        $this->station('victor', 'Victor', 46.9985, 6.0);
        $whiskey = $this->station('whiskey', 'Whiskey', 47.1015, 6.0);
        $xray = $this->rfnLine('', 'Ligne de Xray à Yankee', $secondary);
        $this->track($xray, $this->current, [[47.2, 6.0], [47.3, 6.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $xrayEst = $this->station('xray-est', 'Xray-Est', 47.1919, 6.0);
        // Gare au bord du tracé, rattachée par proximité : jamais touchée par les décisions.
        $zulu = $this->station('zulu', 'Zulu', 47.25, 6.0001);
        $source = 'https://fr.wikipedia.org/w/index.php?title=Xray&oldid=1';
        $decide = fn (array $entries) => $this->app->instance(StationTerminusOverrides::class, new StationTerminusOverrides($entries));
        $decide([['line' => $xray->slug, 'terminus' => 'Xray', 'decision' => 'attach', 'station' => 'xray-est', 'source_url' => $source, 'reason' => 'gare de la compagnie']]);
        $links = fn () => DB::table('station_lines as sl')->join('stations as s', 's.id', '=', 'sl.station_id')
            ->whereIn('sl.line_id', [$victor->id, $xray->id])->orderBy('s.name')->pluck('sl.method', 's.name')->all();
        $this->artisan('vff:attach-stations')->assertSuccessful();
        $before = ['Victor' => 'terminus_name', 'Whiskey' => 'terminus_name', 'Xray-Est' => 'terminus_reviewed', 'Zulu' => 'segment'];
        $this->assertSame($before, $links());

        // Nouvelle lecture : Victor n'est pas le terminus, et la gare de Xray non plus.
        $decide([
            ['line' => $victor->slug, 'terminus' => 'Victor', 'decision' => 'none', 'source_url' => null, 'reason' => 'ligne inachevée'],
            ['line' => $xray->slug, 'terminus' => 'Xray', 'decision' => 'none', 'source_url' => $source, 'reason' => 'départ d\'un quai'],
        ]);
        $this->artisan('vff:attach-stations', ['--years-only' => true, '--dry-run' => true])
            ->expectsOutputToContain('terminus relus : 0 ajoutés, 2 retirés, 2 sans gare')->assertSuccessful();
        $this->assertSame($before, $links(), '--dry-run : rien d\'écrit');

        $this->artisan('vff:attach-stations', ['--years-only' => true])->expectsOutputToContain('2 retirés')->assertSuccessful();
        $this->assertSame(['Whiskey' => 'terminus_name', 'Zulu' => 'segment'], $links());
        $this->assertSame(0, DB::table('station_lines')->where('station_id', $xrayEst->id)->count());
        $this->assertSame(1, DB::table('station_lines')->where('station_id', $whiskey->id)->count());
        $this->assertSame(1, DB::table('station_lines')->where('station_id', $zulu->id)->count());

        $this->artisan('vff:attach-stations', ['--years-only' => true])
            ->expectsOutputToContain('terminus relus : 0 ajoutés, 2 sans gare')->assertSuccessful();
        // Rattachement complet : même résultat.
        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertSame(['Whiskey' => 'terminus_name', 'Zulu' => 'segment'], $links());
    }

    /**
     * Revue des PR #19 et #20 (19/11/2026) : Chambéry-Challes-les-Eaux, rattachée à 378 m du tracé du tramway
     * de Chambéry à Chignin, était « desservie depuis 1856 » (date de la gare) alors que la ligne a ses dates
     * (1897 à 1932). Pour un terminus relu ou nommé, un événement de toute la ligne ou d'une section qui part
     * ou arrive à l'extrémité rattachée couvre la gare, quelle que soit la distance au tracé.
     */
    public function test_reviewed_terminus_far_from_the_trace_takes_the_years_of_the_line(): void
    {
        $line = $this->rfnLine('', 'Ligne de Victor à Whiskey', ['code' => null, 'network' => 'secondaire']);
        $this->track($line, $this->current, [[47.0, 6.0], [47.1, 6.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        // Gare de la grande ligne, à 400 m au sud du début du tracé du tramway, ouverte en 1856.
        $victor = $this->station('victor-ville', 'Victor-Ville', 46.9964, 6.0);
        DB::table('station_events')->insert(['station_id' => $victor->id, 'type' => 'opening', 'event_date' => '1856-01-01',
            'date_precision' => 'year', 'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->app->instance(StationTerminusOverrides::class, new StationTerminusOverrides([
            ['line' => $line->slug, 'terminus' => 'Victor', 'decision' => 'attach', 'station' => 'victor-ville',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Victor&oldid=1', 'reason' => 'tête de ligne'],
        ]));
        $event = fn (string $type, string $date, array $section) => DB::table('line_events')->insert($section + ['line_id' => $line->id,
            'type' => $type, 'event_date' => $date, 'description' => $type, 'created_at' => now(), 'updated_at' => now()]);
        // Section nommée non résolue qui part de l'extrémité rattachée, sans géométrie.
        $event('opening', '1897-01-01', ['section_resolution' => 'unresolved', 'section_from' => 'Victor', 'section_to' => 'Whiskey']);
        // Section d'une autre portion, loin de la gare : ne la concerne pas.
        $event('closure', '1929-01-01', ['section_resolution' => 'unresolved', 'section_from' => 'Uniform', 'section_to' => 'Whiskey']);
        // Fermeture de toute la ligne, avec la géométrie du tracé (à 400 m de la gare).
        $event('closure', '1932-12-31', ['section_resolution' => 'whole_line',
            'section_geom' => DB::raw('ST_SetSRID(ST_MakeLine(ST_MakePoint(6.0, 47.0), ST_MakePoint(6.0, 47.1)), 4326)')]);

        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertSame([1897, 1932, 'line_events'], $this->serviceYears($victor->id));

        // Le recalcul des ères borne par les dates de la gare sans reprendre 1856.
        $this->artisan('vff:build-station-eras')->assertSuccessful();
        $this->assertSame([1897, 1932, 'line_events'], $this->serviceYears($victor->id), 'après vff:build-station-eras');

        $this->artisan('vff:attach-stations', ['--years-only' => true])->assertSuccessful();
        $this->assertSame([1897, 1932, 'line_events'], $this->serviceYears($victor->id), 'après --years-only');
    }

    /** @return list<mixed> années de desserte et leur source, pour l'unique rattachement de la gare */
    private function serviceYears(int $stationId): array
    {
        return array_values((array) DB::table('station_lines')->where('station_id', $stationId)->first(['first_year', 'last_year', 'years_source']));
    }

    /**
     * Revue des PR #19 et #20 : une décision « attach » dont la gare n'existe plus (slug changé après une fusion
     * de gares) est signalée et ne bloque pas la règle des terminus nommés ; avant, le rattachement complet
     * vidait station_lines et le terminus disparaissait avec un simple avertissement.
     */
    public function test_invalid_reviewed_decision_leaves_the_named_terminus_rule_in_place(): void
    {
        $line = $this->rfnLine('', 'Ligne de Victor à Whiskey', ['code' => null, 'network' => 'secondaire']);
        $this->track($line, $this->current, [[47.0, 6.0], [47.1, 6.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $victor = $this->station('victor', 'Victor', 46.9985, 6.0);
        $this->app->instance(StationTerminusOverrides::class, new StationTerminusOverrides([
            ['line' => $line->slug, 'terminus' => 'Victor', 'decision' => 'attach', 'station' => 'victor-ancienne',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Victor&oldid=1', 'reason' => 'gare fusionnée depuis'],
        ]));
        $method = fn () => DB::table('station_lines')->where('station_id', $victor->id)->where('line_id', $line->id)->value('method');

        foreach ([[], ['--termini-only' => true], ['--years-only' => true]] as $options) {
            $this->artisan('vff:attach-stations', $options)
                ->expectsOutputToContain('gare introuvable ; la règle des terminus nommés s\'applique à cette extrémité')
                ->assertSuccessful();
            $this->assertSame('terminus_name', $method(), 'options : '.json_encode($options));
        }
    }

    public function test_dry_run_reports_the_changes_without_writing(): void
    {
        $closed = $this->rfnLine('300000', 'Ligne de Charlie à Delta');
        $this->track($closed, $this->current, [[47.0, 3.0], [47.1, 3.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $station = $this->station('charlie', 'Charlie', 47.05, 3.0001);
        $this->source('osm', 'n9', 'Charlie', 47.05, 3.0001, ['station_id' => $station->id]);
        $event = fn (string $type, string $date) => DB::table('line_events')->insert(['line_id' => $closed->id, 'type' => $type,
            'event_date' => $date, 'description' => $type, 'created_at' => now(), 'updated_at' => now()]);
        $event('opening', '1880-01-01');
        $event('closure', '1950-06-01');
        $this->artisan('vff:attach-stations')->assertSuccessful();
        // Sources nouvelles depuis le dernier calcul : ouverture plus ancienne, réouverture de la ligne.
        $event('opening', '1875-05-01');
        $event('reopening', '1995-01-01');
        $before = $this->snapshot(['station_lines']);
        $csv = storage_path('app/reports/annees-desserte.csv');

        $this->artisan('vff:attach-stations', ['--years-only' => true, '--dry-run' => true, '--report' => $csv])
            ->expectsOutputToContain('Passage à blanc')->assertSuccessful();

        $this->assertSame($before, $this->snapshot(['station_lines']), '--dry-run a écrit');
        $rows = array_map(fn ($l) => str_getcsv($l, escape: ''), file($csv, FILE_IGNORE_NEW_LINES) ?: []);
        $this->assertCount(2, $rows);
        $row = array_combine($rows[0], $rows[1]);
        $this->assertSame(['Charlie', '300000', 'gardé', '1880', '1875', 'avancée', '1950', '', 'retirée'],
            [$row['gare'], $row['ligne_code'], $row['rattachement'], $row['debut_avant'], $row['debut_apres'], $row['debut'],
                $row['fin_avant'], $row['fin_apres'], $row['fin']]);

        // Rattachement complet à blanc : la gare déplacée loin de la voie perdrait sa ligne, rien n'est écrit.
        DB::table('stations')->where('id', $station->id)->update(['geom' => DB::raw('ST_SetSRID(ST_MakePoint(3.05, 47.05), 4326)')]);
        DB::table('station_sources')->where('station_id', $station->id)->update(['geom' => DB::raw('ST_SetSRID(ST_MakePoint(3.05, 47.05), 4326)')]);
        $this->artisan('vff:attach-stations', ['--dry-run' => true, '--report' => $csv])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['station_lines']));
        $this->assertStringContainsString(',retiré,', (string) file_get_contents($csv));

        // En vrai : les années changent.
        $this->artisan('vff:attach-stations', ['--years-only' => true])->assertSuccessful();
        $this->assertSame([1875, null], array_values((array) DB::table('station_lines')->where('station_id', $station->id)->first(['first_year', 'last_year'])));
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
