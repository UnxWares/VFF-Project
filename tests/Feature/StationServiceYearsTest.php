<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\DataInvariants;
use App\Services\LineStations;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Ligne desservie de … à … » de la fiche gare (station_lines.first_year / last_year) : une seule
 * source de vérité avec les dates de la gare (fiche ligne, carte). Contrôle qualité du 2026-10-20 :
 * Briouze et Lonlay-le-Tesson « 434000 desservie de 1869 à 2006 » (fermées en 1970 et 1991),
 * Mâcon-Loché-TGV, Exideuil-sur-Vienne. BuildStationEras::reconcileServiceYears borne la période,
 * invariant stations.line_service_outside_station_life sur toutes les gares.
 */
class StationServiceYearsTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private const ONLY = ['stations.line_service_outside_station_life', 'stations.opened_before_lines'];

    protected function setUp(): void
    {
        parent::setUp();
        $past = Era::factory()->create(['year' => 1950]);
        $current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->line = Line::factory()->create(['code' => '900300', 'name' => 'Ligne témoin des années']);
        foreach ([$past, $current] as $era) {
            LineSegment::factory()->create([
                'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
                'geom' => LineString::make([Point::makeGeodetic(47.50, 2.00), Point::makeGeodetic(47.50, 2.20)]),
            ]);
        }
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'opening', 'event_date' => '1869-06-01', 'date_precision' => 'day',
            'description' => 'Ouverture', 'confidence' => 'high', 'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param  array<string, string>  $events  type => date */
    private function station(string $name, float $lon, int $pk, array $events, int $first, ?int $last): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic(47.50, $lon), 'status' => 'active', 'source_status' => 'active',
            'position_source' => 'sncf', 'provenance' => ['status' => 'sncf'],
            // Date d'ouverture retenue par vff:consolidate-stations (sources de la gare).
            'opened_at' => $events['opening'] ?? null, 'opened_precision' => isset($events['opening']) ? 'day' : null,
        ]);
        DB::table('station_sources')->insert(['source' => 'sncf', 'source_ref' => "{$name}-sncf", 'station_id' => $station->id, 'name' => $name,
            'lifecycle' => 'active', 'passenger' => true, 'freight' => false, 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lon}, 47.50), 4326)")]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => $pk,
            'pk_source' => 'sncf', 'distance_m' => 0, 'first_year' => $first, 'last_year' => $last, 'years_source' => 'line_events',
            'created_at' => now(), 'updated_at' => now()]);
        foreach ($events as $type => $date) {
            DB::table('station_events')->insert(['station_id' => $station->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'day',
                'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $station;
    }

    /** @return array{0: ?int, 1: ?int, 2: ?string} */
    private function years(Station $station): array
    {
        $r = DB::table('station_lines')->where('station_id', $station->id)->first(['first_year', 'last_year', 'years_source']);

        return [$r->first_year, $r->last_year, $r->years_source];
    }

    private function violations(string $key): int
    {
        return collect((new DataInvariants(self::ONLY))->run())->firstWhere('key', $key)['count'];
    }

    public function test_service_years_are_bounded_by_the_station_life_on_every_page(): void
    {
        // Briouze : « desservie de 1869 à 2006 » (déclassement), gare fermée le 31/05/1970.
        $closed = $this->station('Briouze', 2.05, 4_000, ['opening' => '1872-07-01', 'closure' => '1970-05-31'], 1869, 2006);
        // Lille-Europe : ouverte en 1994 sur le tracé d'une ligne dont la desserte finit en 1956.
        $later = $this->station('Lille-Europe', 2.10, 8_000, ['opening' => '1994-06-01'], 1869, 1956);
        // Gare sans contradiction.
        $fine = $this->station('Témoin', 2.15, 12_000, ['opening' => '1869-06-01'], 1869, null);
        // Avant reconstruction : Briouze et Lille-Europe « desservies » avant leur ouverture.
        $this->assertSame(2, $this->violations('stations.line_service_outside_station_life'));

        $this->artisan('vff:build-station-eras', ['--today' => '2026-10-20'])->assertSuccessful()
            ->expectsOutputToContain('jamais desservies');

        $this->assertSame([1872, 1970, 'line_events'], $this->years($closed));
        $this->assertSame([1994, 1956, 'never_served'], $this->years($later));
        $this->assertSame([1869, null, 'line_events'], $this->years($fine));
        $this->assertSame(0, $this->violations('stations.line_service_outside_station_life'));
        // Carte : aucun arrêt hors de la période de desserte.
        $stopYears = fn (Station $s) => DB::table('line_stops')->join('eras', 'eras.id', '=', 'line_stops.era_id')
            ->where('station_id', $s->id)->orderBy('year')->pluck('year')->all();
        $this->assertNotContains(2030, $stopYears($closed));
        $this->assertSame([], $stopYears($later));
        // Arrêts restants renumérotés : en 2030, Témoin seule, position 0, pas une « extrémité » (une seule gare).
        $this->assertSame([[$fine->id, 0, false]], DB::table('line_stops')->join('eras', 'eras.id', '=', 'line_stops.era_id')
            ->where('eras.is_current', true)->get(['station_id', 'position', 'is_terminal'])
            ->map(fn ($r) => [(int) $r->station_id, (int) $r->position, (bool) $r->is_terminal])->all());

        // Fiche ligne : la gare jamais desservie n'est pas une gare de la ligne.
        $this->assertSame(['Briouze', 'Témoin'], array_column((new LineStations)->forLine($this->line->id)['stations'], 'name'));
        // Fiche gare : « jamais desservie par cette ligne », pas « desservie de 1994 à 1956 ».
        $this->get('/stations/'.$later->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.never_served', 'line_closed')->where('lines.0.first_year', 1994)->where('lines.0.last_year', 1956)->etc());
        $this->get('/stations/'.$closed->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.never_served', null)->where('lines.0.last_year', 1970)->etc());

        // Une période réécrite à tort (pipeline partiel) est signalée.
        DB::table('station_lines')->where('station_id', $closed->id)->update(['last_year' => 2006]);
        $this->assertSame(1, $this->violations('stations.line_service_outside_station_life'));
    }

    public function test_a_station_opened_before_all_its_lines_is_reported(): void
    {
        // Mâcon-Loché-TGV « ouverte le 19/09/1981 » (Wikidata), la LGV le 27/09/1981.
        $tgv = $this->station('Mâcon-Loché-TGV', 2.05, 4_000, [], 1869, null);
        DB::table('stations')->where('id', $tgv->id)->update(['opened_at' => '1869-05-20', 'opened_precision' => 'day']);
        $this->assertSame(1, $this->violations('stations.opened_before_lines'));

        DB::table('stations')->where('id', $tgv->id)->update(['opened_at' => '1869-06-01']);
        $this->assertSame(0, $this->violations('stations.opened_before_lines'));
        // Date déduite des lignes : pas une contradiction de sources.
        DB::table('stations')->where('id', $tgv->id)->update(['opened_at' => '1869-05-20', 'provenance' => json_encode(['opened_at' => 'inferred:lines'])]);
        $this->assertSame(0, $this->violations('stations.opened_before_lines'));
    }
}
