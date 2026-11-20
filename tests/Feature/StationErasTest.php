<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * vff:build-station-eras : le statut d'une gare à une ère est plafonné par
 * l'état des lignes qui la desservent à cette ère (line_segments).
 */
class StationErasTest extends TestCase
{
    use RefreshDatabase;

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
    }

    /** Ligne est-ouest à la latitude donnée, avec un tronçon de l'ère courante. */
    private function line(string $code, float $lat, string $status, string $traffic): Line
    {
        $line = Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}"]);
        LineSegment::factory()->create([
            'line_id' => $line->id,
            'era_id' => $this->current->id,
            'geom' => LineString::make([Point::makeGeodetic($lat, 2.00), Point::makeGeodetic($lat, 2.20)]),
            'status' => $status,
            'traffic_type' => $traffic,
            'is_heritage' => false,
        ]);

        return $line;
    }

    /** Gare sur la ligne, statut « source » SNCF (drapeaux voyageurs / fret). */
    private function station(string $name, float $lat, string $sourceStatus, Line $line): Station
    {
        $station = Station::factory()->create([
            'name' => $name,
            'geom' => Point::makeGeodetic($lat, 2.10),
            'status' => $sourceStatus === 'gone' ? 'declassified' : $sourceStatus,
            'source_status' => $sourceStatus,
            'position_source' => 'sncf',
            'provenance' => ['status' => 'sncf'],
            'opened_at' => null,
        ]);
        DB::table('station_lines')->insert([
            'station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk',
            'pk_m' => 1000, 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $station;
    }

    private function eraStatus(Station $station): ?string
    {
        return DB::table('station_eras')->where('station_id', $station->id)->where('era_id', $this->current->id)->value('status');
    }

    public function test_freight_flag_on_closed_line_gives_closed_station(): void
    {
        $closed = $this->line('900000', 48.00, 'abandoned', 'unknown');
        $station = $this->station('Gare fret ligne fermée', 48.00, 'no_commercial', $closed);

        $this->artisan('vff:build-station-eras')->assertSuccessful();

        $this->assertSame('declassified', $this->eraStatus($station));
        $this->assertSame('declassified', $station->fresh()->status->value);
        // Le statut « source » reste intact : le calcul est rejouable après le moteur décennal.
        $this->assertSame('no_commercial', $station->fresh()->source_status);
    }

    public function test_passenger_flag_on_dismantled_line_gives_closed_station(): void
    {
        $gone = $this->line('900100', 48.10, 'dismantled', 'unknown');
        $station = $this->station('Gare voyageurs ligne déposée', 48.10, 'active', $gone);

        $this->artisan('vff:build-station-eras')->assertSuccessful();

        $this->assertSame('declassified', $this->eraStatus($station));
    }

    public function test_open_lines_keep_or_cap_station_status(): void
    {
        $mixed = $this->line('900200', 48.20, 'in_service', 'mixed');
        $freightOnly = $this->line('900300', 48.30, 'in_service', 'freight');

        $passenger = $this->station('Gare voyageurs', 48.20, 'active', $mixed);
        $freightStation = $this->station('Gare fret seul', 48.20, 'no_commercial', $mixed);
        $onFreightLine = $this->station('Gare voyageurs sur ligne fret', 48.30, 'active', $freightOnly);

        $this->artisan('vff:build-station-eras')->assertSuccessful();

        // « S'il y a du voyageur, il y a du fret » : une gare voyageurs reste « active ».
        $this->assertSame('active', $this->eraStatus($passenger));
        // Fret seul : plus de voyageurs mais du fret sur une ligne en service.
        $this->assertSame('no_commercial', $this->eraStatus($freightStation));
        // Ligne ouverte au seul fret : la gare ne peut pas être plus active.
        $this->assertSame('no_commercial', $this->eraStatus($onFreightLine));
    }

    public function test_gone_station_is_absent_from_current_era(): void
    {
        $closed = $this->line('900400', 48.40, 'abandoned', 'unknown');
        $station = $this->station('Gare disparue', 48.40, 'gone', $closed);

        $this->artisan('vff:build-station-eras')->assertSuccessful();

        $this->assertNull($this->eraStatus($station));
    }

    /**
     * Cabessut (Cahors - Capdenac, fermée le 09/06/2011) : date de fermeture retenue, mais encore « non
     * confirmée » ouverte en 2020 sur la frise (rapport fiche-ligne du 19/11/2026). L'ère postérieure à la date
     * devient fermée ; une ère antérieure ou confirmée ne change pas.
     */
    public function test_unconfirmed_eras_after_the_retained_closure_date_are_closed(): void
    {
        $years = [2000, 2020];
        foreach ($years as $year) {
            Era::factory()->create(['year' => $year, 'label' => "Années {$year}"]);
        }
        $line = $this->line('724000', 48.55, 'abandoned', 'unknown');
        $station = $this->station('Cabessut', 48.55, 'declassified', $line);
        DB::table('station_events')->insert(['station_id' => $station->id, 'type' => 'opening', 'event_date' => '1886-01-01',
            'date_precision' => 'year', 'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'closure', 'event_date' => '2011-06-09', 'date_precision' => 'day',
            'description' => 'Fermeture', 'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now()]);
        $era = fn (int $year) => (array) DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')
            ->where('se.station_id', $station->id)->where('e.year', $year)->first(['se.status', 'se.uncertain']);

        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-19'])
            ->expectsOutputToContain('1 couples gare × ère non confirmés deviennent fermés')->assertSuccessful();

        $this->assertSame('2011-06-09', DB::table('stations')->where('id', $station->id)->value('closed_at'));
        $this->assertSame(['status' => 'declassified', 'uncertain' => false], $era(2020));
        $this->assertSame('active', $era(2000)['status'] ?? null);
    }

    /**
     * Gex : fermée aux voyageurs le 31/05/1980 avec la ligne de Collonges à Divonne. Le tramway de Ferney à
     * Gex, rattaché par son nom (terminus_name) et sans fermeture datée, n'efface pas cette date déduite.
     */
    public function test_a_line_attached_by_name_does_not_block_the_inferred_passenger_closure(): void
    {
        $main = $this->line('895000', 48.50, 'abandoned', 'unknown');
        $station = $this->station('Gex', 48.50, 'declassified', $main);
        $tram = $this->line('895100', 48.50, 'dismantled', 'unknown');
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $tram->id, 'method' => 'terminus_name', 'pk_m' => 0,
            'distance_m' => 200, 'source_ref' => 'name:gex', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_events')->insert(['line_id' => $main->id, 'type' => 'closure_passenger', 'event_date' => '1980-05-31', 'date_precision' => 'day',
            'description' => 'Fermeture aux voyageurs', 'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now()]);
        $inferred = fn (): mixed => $this->passengerClosureDate($station->id);

        $this->artisan('vff:build-station-eras')->assertSuccessful();
        $this->assertSame('1980-05-31', $inferred());

        // Même chose pour un terminus relu à la main (terminus_reviewed, database/data/station_terminus_overrides.php).
        DB::table('station_lines')->where('line_id', $tram->id)->update(['method' => 'terminus_reviewed']);
        $this->artisan('vff:build-station-eras')->assertSuccessful();
        $this->assertSame('1980-05-31', $inferred());

        // Une ligne rattachée autrement (liste, proximité) et sans fermeture datée empêche toujours la déduction.
        DB::table('station_lines')->where('line_id', $tram->id)->update(['method' => 'segment']);
        $this->artisan('vff:build-station-eras')->assertSuccessful();
        $this->assertNull($inferred());
    }

    /**
     * Ernée (438000) : fin du fret en mars 1987 selon Wikipédia, fermeture totale le 01/03/1987 selon OHM.
     * La date déduite pour la gare disparue est toujours la même, « 03/1987 » : à date égale, la précision la
     * moins fine (le 1er du mois d'OHM reprend le mois), puis le premier enregistré. Avant, l'ordre physique
     * des lignes la faisait changer d'un passage à l'autre.
     */
    public function test_same_day_line_closures_give_the_same_inferred_date_on_every_run(): void
    {
        $closed = $this->line('438000', 48.45, 'dismantled', 'unknown');
        $station = $this->station('Ernée', 48.45, 'gone', $closed);
        $event = fn (string $type, string $precision) => DB::table('line_events')->insertGetId(['line_id' => $closed->id, 'type' => $type,
            'event_date' => '1987-03-01', 'date_precision' => $precision, 'description' => $type, 'section_resolution' => 'whole_line',
            'created_at' => now(), 'updated_at' => now()]);
        $event('closure', 'day');
        $month = $event('closure_freight', 'month');
        $event('closure_passenger', 'month');
        $inferred = fn () => (array) DB::table('station_events')->where('station_id', $station->id)->where('type', 'closure')
            ->first(['event_date', 'date_precision', 'source_ref']);

        $this->artisan('vff:build-station-eras')->assertSuccessful();
        $this->assertSame(['event_date' => '1987-03-01', 'date_precision' => 'month', 'source_ref' => "line_event:{$month}"], $inferred());

        $this->artisan('vff:build-station-eras')->assertSuccessful();
        $this->assertSame("line_event:{$month}", $inferred()['source_ref']);
    }

    public function test_station_whose_line_has_disappeared_is_closed_whatever_wikidata_says(): void
    {
        // Ligne en service en 1990, absente de la carte actuelle (fermée puis déposée) ; la halte
        // est « en service » selon Wikidata seule (ex. Croisette, ligne de Templeuve à Don-Sainghin).
        $old = Era::factory()->create(['year' => 1990]);
        $line = Line::factory()->create(['code' => '900700', 'name' => 'Ligne disparue']);
        LineSegment::factory()->create([
            'line_id' => $line->id, 'era_id' => $old->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
            'geom' => LineString::make([Point::makeGeodetic(48.70, 2.00), Point::makeGeodetic(48.70, 2.20)]),
        ]);
        // Une ligne en service ailleurs : l'ère courante a des tronçons.
        $this->line('900800', 48.80, 'in_service', 'mixed');
        $station = $this->station('Halte Wikidata', 48.70, 'active', $line);
        $station->update(['provenance' => ['status' => 'wikidata'], 'position_source' => 'wikidata']);

        $this->artisan('vff:build-station-eras')->assertSuccessful();

        $this->assertSame('declassified', $this->eraStatus($station));
    }

    public function test_station_on_a_vanished_portion_of_a_partly_open_line_is_closed(): void
    {
        // Ligne dont il ne reste qu'un bout en service loin de la gare (halte de Has, ligne de
        // Templeuve à Don-Sainghin) : son tracé de référence passe à la gare, sans tronçon actuel.
        $line = Line::factory()->create(['code' => '901000', 'name' => 'Ligne en partie disparue']);
        LineSegment::factory()->create([
            'line_id' => $line->id, 'era_id' => $this->current->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
            'geom' => LineString::make([Point::makeGeodetic(49.10, 2.50), Point::makeGeodetic(49.10, 2.60)]),
        ]);
        DB::statement(<<<'SQL'
            INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, 40000, '{"archeoferroviaire": 40000}', ST_Multi(ST_SetSRID(ST_MakeLine(ST_MakePoint(2.00, 49.10), ST_MakePoint(2.60, 49.10)), 4326)), NOW(), NOW())
        SQL, [$line->id]);
        $station = $this->station('Halte disparue', 49.10, 'active', $line);
        $station->update(['geom' => Point::makeGeodetic(49.10, 2.10), 'provenance' => ['status' => 'wikidata'], 'position_source' => 'wikidata']);

        $this->artisan('vff:build-station-eras')->assertSuccessful();

        $this->assertSame('declassified', $this->eraStatus($station));
    }

    public function test_tourist_railway_outside_vff_gives_tourist_station(): void
    {
        $this->line('900900', 48.95, 'in_service', 'mixed');
        // Voie OSM touristique en service, rattachée à aucune ligne VFF (ex. petit train de Saint-Trojan).
        DB::statement(<<<'SQL'
            INSERT INTO source_traces (source, source_ref, layer, name, properties, length_m, seen_at, created_at, updated_at, geom)
            VALUES ('osm', '990001', 'narrow_gauge', 'Le petit train', '{"usage": "tourism", "name": "Le p’tit train de Saint-Trojan"}', 1000, NOW(), NOW(), NOW(),
                    ST_Multi(ST_SetSRID(ST_MakeLine(ST_MakePoint(2.00, 48.90), ST_MakePoint(2.20, 48.90)), 4326)))
        SQL);
        $station = Station::factory()->create([
            'name' => 'Gatseau', 'geom' => Point::makeGeodetic(48.90, 2.10), 'status' => 'active', 'source_status' => 'active',
            'position_source' => 'osm', 'provenance' => ['status' => 'osm'], 'opened_at' => null,
        ]);

        $this->artisan('vff:build-station-eras')->assertSuccessful();

        $row = DB::table('station_eras')->where('station_id', $station->id)->where('era_id', $this->current->id)->first();
        $this->assertSame('tourist', $row->status);
        // Ère courante : statut attesté aujourd'hui par OSM (StationLineCoverage::attestedToday, 2026-10-20) : certain.
        $this->assertFalse((bool) $row->uncertain);
    }

    public function test_undated_closure_is_uncertain_after_the_last_proof(): void
    {
        // Halte fermée sur une ligne toujours exploitée (Courtonne-la-Meurdrac) : ouverte en 1855,
        // fermeture inconnue. Avant la règle, « ouverte aux voyageurs » jusqu'à l'ère précédente.
        $line = $this->line('901100', 47.50, 'in_service', 'mixed');
        foreach ([1860, 1870] as $y) {
            $era = Era::factory()->create(['year' => $y]);
            LineSegment::factory()->create([
                'line_id' => $line->id, 'era_id' => $era->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
                'geom' => LineString::make([Point::makeGeodetic(47.50, 2.00), Point::makeGeodetic(47.50, 2.20)]),
            ]);
        }
        $undated = $this->station('Halte sans date', 47.50, 'declassified', $line);
        $dated = $this->station('Halte datée', 47.50, 'declassified', $line);
        $event = fn (Station $s, string $type, string $date, string $source) => DB::table('station_events')->insert([
            'station_id' => $s->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'year', 'source' => $source,
            'is_inferred' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $event($undated, 'opening', '1855-01-01', 'wikidata');
        $event($dated, 'opening', '1855-01-01', 'wikidata');
        $event($dated, 'closure', '1865-01-01', 'wikipedia');

        $this->artisan('vff:build-station-eras')->assertSuccessful();

        $row = fn (Station $s, int $y) => DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')
            ->where('se.station_id', $s->id)->where('e.year', $y)->first(['se.status', 'se.uncertain', 'se.note']);
        // Ouverture en 1855 : affirmée à l'ère suivante seulement.
        $this->assertSame(['active', false], [$row($undated, 1860)->status, (bool) $row($undated, 1860)->uncertain]);
        $this->assertTrue((bool) $row($undated, 1870)->uncertain);
        $this->assertStringContainsString('Fermeture non datée', (string) $row($undated, 1870)->note);
        // Fermeture datée : ouverte (affirmée) avant, fermée après.
        $this->assertSame(['active', false], [$row($dated, 1860)->status, (bool) $row($dated, 1860)->uncertain]);
        $this->assertSame('declassified', $row($dated, 1870)->status);

        // Fiche (en français) : la frise montre « incertaine » et la note.
        $this->withHeader('Accept-Language', 'fr')->get('/stations/'.$undated->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Stations/Show')
            ->where('eras.1.year', 1870)
            ->where('eras.1.status', 'uncertain')
            ->where('eras.1.note', fn ($note) => str_contains((string) $note, 'Fermeture non datée'))
            ->where('eras.1.undated', true)
            ->where('eras.0.undated', false)
            ->etc());
    }

    public function test_station_list_and_geojson_follow_station_eras(): void
    {
        $closed = $this->line('900500', 48.50, 'abandoned', 'unknown');
        $open = $this->line('900600', 48.60, 'in_service', 'mixed');
        $this->station('Gare fermée', 48.50, 'no_commercial', $closed);
        $this->station('Gare ouverte', 48.60, 'active', $open);
        $this->artisan('vff:build-station-eras')->assertSuccessful();

        $this->get('/stations?era=2030&status=declassified')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stations/Index')
                ->where('stations.total', 1)
                ->where('stations.data.0.name', 'Gare fermée')
                ->where('counts.active', 1)
                ->etc()
            );

        $features = collect($this->getJson('/api/v1/eras/2030/stations.geojson')->assertOk()->json('features'));
        $this->assertSame('closed', $features->firstWhere('properties.name', 'Gare fermée')['properties']['service']);
        $this->assertSame('passenger', $features->firstWhere('properties.name', 'Gare ouverte')['properties']['service']);
    }

    /**
     * Date de fermeture aux voyageurs déduite pour une gare (relue en base à chaque appel).
     *
     * @phpstan-impure
     */
    private function passengerClosureDate(int $stationId): mixed
    {
        return DB::table('station_events')->where('station_id', $stationId)->where('type', 'closure_passenger')->value('event_date');
    }
}
