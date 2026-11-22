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
            'description' => 'Ouverture', 'confidence' => 'high', 'section_resolution' => 'trace', 'created_at' => now(), 'updated_at' => now()]);
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

    /**
     * Les deux années égales (revue des PR #19 et #20) : La Fouillouse sur 4014 (Saint-Étienne - Andrézieux),
     * section fermée le 26/09/1857, gare ouverte le 24/11/1857, « desservie de 1857 à 1857 » sur la fiche gare.
     * Les dates tranchent au jour, ou au mois quand c'est la précision commune ; à l'année près, rien n'est
     * sûr et la période reste telle quelle.
     */
    public function test_same_year_dates_show_a_line_that_never_served_the_station(): void
    {
        $link = function (Station $station, array $dates): void {
            DB::table('station_lines')->where('station_id', $station->id)->update($dates);
        };
        // La Fouillouse : fermeture au jour, ouverture de la gare au jour, deux mois plus tard.
        $fouillouse = $this->station('La Fouillouse', 2.02, 2_000, ['opening' => '1857-11-24'], 1857, 1857);
        $link($fouillouse, ['last_event_date' => '1857-09-26', 'last_event_precision' => 'day']);
        // Même jour : rien ne dit que la ligne fermait avant le premier train de la gare.
        $sameDay = $this->station('Même jour', 2.04, 3_000, ['opening' => '1857-09-26'], 1857, 1857);
        $link($sameDay, ['last_event_date' => '1857-09-26', 'last_event_precision' => 'day']);
        // Fermeture connue au mois (septembre), gare ouverte en novembre : précision commune au mois.
        $month = $this->station('Septembre', 2.06, 4_000, ['opening' => '1857-11-24'], 1857, 1857);
        $link($month, ['last_event_date' => '1857-09-01', 'last_event_precision' => 'month']);
        // Même mois à la précision commune : indécidable.
        $sameMonth = $this->station('Même mois', 2.08, 5_000, ['opening' => '1857-09-28'], 1857, 1857);
        $link($sameMonth, ['last_event_date' => '1857-09-01', 'last_event_precision' => 'month']);
        // Fermeture connue à l'année seulement : « 1857-1857 » reste, rien n'est présenté comme sûr.
        $year = $this->station('À l\'année', 2.10, 6_000, ['opening' => '1857-11-24'], 1857, 1857);
        $link($year, ['last_event_date' => '1857-01-01', 'last_event_precision' => 'year']);
        // Ouverture de la gare déduite des lignes : pas une date de la gare, elle ne tranche rien.
        $inferred = $this->station('Déduite', 2.12, 7_000, ['opening' => '1857-11-24'], 1857, 1857);
        $link($inferred, ['last_event_date' => '1857-09-26', 'last_event_precision' => 'day']);
        DB::table('stations')->where('id', $inferred->id)->update(['provenance' => json_encode(['opened_at' => 'inferred:lines'])]);
        // L'inverse : gare fermée le 10/03/1857, ligne ouverte le 01/10/1857.
        $closedFirst = $this->station('Fermée avant', 2.14, 8_000, ['opening' => '1840-05-01', 'closure' => '1857-03-10'], 1857, 1857);
        $link($closedFirst, ['first_event_date' => '1857-10-01', 'first_event_precision' => 'day']);
        DB::table('stations')->where('id', $closedFirst->id)->update(['closed_at' => '1857-03-10', 'closed_precision' => 'day']);
        // Fermée puis rouverte après l'ouverture de la ligne : desservie.
        $reopened = $this->station('Rouverte', 2.16, 9_000, ['opening' => '1840-05-01', 'closure' => '1857-03-10', 'reopening' => '1858-01-01'], 1857, 1857);
        $link($reopened, ['first_event_date' => '1857-10-01', 'first_event_precision' => 'day']);
        DB::table('stations')->where('id', $reopened->id)->update(['closed_at' => '1857-03-10', 'closed_precision' => 'day']);

        $this->artisan('vff:build-station-eras', ['--today' => '2026-10-20'])->assertSuccessful();

        $source = fn (Station $s) => DB::table('station_lines')->where('station_id', $s->id)->value('years_source');
        $this->assertSame('never_served', $source($fouillouse));
        $this->assertSame('never_served', $source($month));
        $this->assertSame('never_served', $source($closedFirst));
        foreach ([$sameDay, $sameMonth, $year, $inferred, $reopened] as $served) {
            $this->assertSame('line_events', $source($served), $served->name);
        }
        $this->assertSame([1857, 1857, 'line_events'], $this->years($year));
        // Carte et fiche ligne : aucune trace de la gare jamais desservie.
        $this->assertSame(0, DB::table('line_stops')->where('station_id', $fouillouse->id)->count());
        $this->assertNotContains('La Fouillouse', array_column((new LineStations)->forLine($this->line->id)['stations'], 'name'));
        // Fiche gare : « jamais desservie par cette ligne », qui a fermé avant l'ouverture de la gare.
        $this->get('/stations/'.$fouillouse->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.never_served', 'line_closed')->etc());
        $this->get('/stations/'.$closedFirst->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.never_served', 'station_closed')->etc());
        $this->get('/stations/'.$year->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.never_served', null)->where('lines.0.first_year', 1857)->where('lines.0.last_year', 1857)->etc());
        $this->assertSame(0, $this->violations('stations.line_service_outside_station_life'));
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

    /** Ligne annexe, rattachée à la gare par $method / $pkSource, avec une ouverture au jour résolue comme $resolution. */
    private function secondLine(Station $station, string $date, string $resolution, string $method = 'sncf_pk', string $pkSource = 'sncf'): void
    {
        $line = Line::factory()->create(['code' => null, 'name' => 'Ligne annexe '.$station->id.$method]);
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'opening', 'event_date' => $date, 'date_precision' => 'day',
            'description' => 'Ouverture', 'confidence' => 'high', 'section_resolution' => $resolution, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => $method, 'pk_m' => 0,
            'pk_source' => $pkSource, 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_opened_before_lines_only_compares_with_resolved_sections(): void
    {
        // La ligne témoin ouvre le 01/06/1869 (section « trace ») ; gare du 20/05/1869 : cas réel gardé.
        $station = $this->station('Résolue', 2.05, 4_000, [], 1869, null);
        DB::table('stations')->where('id', $station->id)->update(['opened_at' => '1869-05-20', 'opened_precision' => 'day']);
        $this->assertSame(1, $this->violations('stations.opened_before_lines'));
        // Même événement mais section non résolue ou « toute la ligne » : on ne sait pas qu'il vise la gare.
        foreach (['whole_line', 'unresolved', 'pending'] as $resolution) {
            DB::table('line_events')->where('line_id', $this->line->id)->update(['section_resolution' => $resolution]);
            $this->assertSame(0, $this->violations('stations.opened_before_lines'), $resolution);
        }
        DB::table('line_events')->where('line_id', $this->line->id)->update(['section_resolution' => 'places']);
        $this->assertSame(1, $this->violations('stations.opened_before_lines'));
    }

    public function test_opened_before_lines_ignores_terminus_name_and_projected_raw_attachments(): void
    {
        // Dormans : gare ancienne, terminus d'une ligne secondaire datée plus tard (rattachée par le nom ou un PK projeté).
        $station = $this->station('Terminus', 2.05, 4_000, [], 1869, null);
        DB::table('stations')->where('id', $station->id)->update(['opened_at' => '1869-05-20', 'opened_precision' => 'day']);
        DB::table('station_lines')->where('station_id', $station->id)->delete();
        $this->secondLine($station, '1903-03-20', 'trace', 'terminus_name', 'name');
        $this->assertSame(0, $this->violations('stations.opened_before_lines'));
        DB::table('station_lines')->where('station_id', $station->id)->delete();
        $this->secondLine($station, '1903-03-20', 'trace', 'segment', 'projected_raw');
        $this->assertSame(0, $this->violations('stations.opened_before_lines'));
        // Rattachement officiel : la contradiction compte.
        DB::table('station_lines')->where('station_id', $station->id)->delete();
        $this->secondLine($station, '1903-03-20', 'trace', 'sncf_pk', 'sncf');
        $this->assertSame(1, $this->violations('stations.opened_before_lines'));
        // Une ligne officielle plus ancienne que la gare absout, quel que soit le rattachement annexe.
        $this->secondLine($station, '1869-01-01', 'trace', 'sncf_pk', 'sncf');
        $this->assertSame(0, $this->violations('stations.opened_before_lines'));
    }

    public function test_opened_before_lines_tolerates_a_few_days(): void
    {
        // Ligne du 01/06/1869 : la veille, 3 jours avant (inauguration) tolérés ; 4 jours avant signalé.
        $station = $this->station('Inaugurée', 2.05, 4_000, [], 1869, null);
        foreach (['1869-05-31' => 0, '1869-05-29' => 0, '1869-05-28' => 1] as $opened => $expected) {
            DB::table('stations')->where('id', $station->id)->update(['opened_at' => $opened, 'opened_precision' => 'day']);
            $this->assertSame($expected, $this->violations('stations.opened_before_lines'), $opened);
        }
        // Date de gare à l'année ou au mois : jamais comparée.
        DB::table('stations')->where('id', $station->id)->update(['opened_at' => '1869-01-01', 'opened_precision' => 'month']);
        $this->assertSame(0, $this->violations('stations.opened_before_lines'));
    }
}
