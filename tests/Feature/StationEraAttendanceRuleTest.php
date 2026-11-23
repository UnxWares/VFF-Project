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
use Tests\TestCase;

/**
 * Règle facultative G7 de vff:build-station-eras (--rules=g7) : une gare à code UIC absente de la série de
 * fréquentation SNCF 2015-2024 n'avait plus de voyageurs en 2015 ; sans date de fermeture, elle n'est plus
 * « active incertaine » à l'ère 2020 mais fermée (ou fret seul), et sa fermeture est datée « avant 2015 ».
 */
class StationEraAttendanceRuleTest extends TestCase
{
    use RefreshDatabase;

    private const YEARS = [2000, 2010, 2020];

    /** @var array<int, Era> */
    private array $eras = [];

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::YEARS as $year) {
            $this->eras[$year] = Era::factory()->create(['year' => $year, 'label' => "Années {$year}"]);
        }
        $this->eras[2030] = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        // Une ligne est-ouest en service, mixte, à toutes les ères : les gares ne sont jamais plafonnées par la ligne.
        $this->line = Line::factory()->create(['code' => '570000', 'name' => 'Ligne de Paris à Bordeaux']);
        foreach ($this->eras as $era) {
            LineSegment::factory()->create([
                'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => 'in_service', 'is_heritage' => false, 'traffic_type' => 'mixed',
                'geom' => LineString::make([Point::makeGeodetic(45.0, 0.00), Point::makeGeodetic(45.0, 0.60)]),
            ]);
        }
    }

    /** Gare d'une ligne en service, fermée selon ses sources, sans aucune date de fermeture (ouverture 1900 seule). */
    private function station(string $name, float $lng, string $sourceStatus, ?string $uic): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic(45.0, $lng), 'uic_code' => $uic,
            'status' => $sourceStatus, 'source_status' => $sourceStatus, 'position_source' => 'sncf', 'provenance' => ['status' => 'sncf'],
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk',
            'pk_m' => (int) ($lng * 1000), 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('station_events')->insert(['station_id' => $station->id, 'type' => 'opening', 'event_date' => '1900-05-01',
            'date_precision' => 'day', 'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    /** Une ligne de la série de fréquentation SNCF (une année suffit : on teste la présence de la gare dans le jeu). */
    private function attendance(Station $station, int $year = 2016, int $passengers = 120): void
    {
        DB::table('station_attendances')->insert(['station_id' => $station->id, 'year' => $year, 'passengers' => $passengers, 'footfall' => $passengers,
            'series' => '2015-2024', 'uic_code' => (string) $station->uic_code, 'source_name' => $station->name, 'match_method' => 'uic', 'source' => 'sncf',
            'dataset' => 'frequentation-gares-archive', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<int, ?string> */
    private function frise(Station $station): array
    {
        $rows = DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')
            ->where('se.station_id', $station->id)->pluck('se.status', 'e.year')->all();

        return array_map(fn (int $year) => $rows[$year] ?? null, array_combine([...self::YEARS, 2030], [...self::YEARS, 2030]));
    }

    private function build(?string $rules = null): void
    {
        $this->artisan('vff:build-station-eras', array_filter(['--today' => '2026-11-23', '--rules' => $rules]))->assertSuccessful();
    }

    /** Une gare témoin ouverte et fréquentée, pour que la série soit importée. */
    private function importedSeries(): void
    {
        $this->attendance($this->station('Témoin ouvert', 0.05, 'active', '87000001'));
    }

    public function test_without_the_rule_an_undated_closure_keeps_the_station_active_but_uncertain_in_2020(): void
    {
        $this->importedSeries();
        $station = $this->station('Vars', 0.20, 'declassified', '87583001');

        $this->build();

        $this->assertSame([2000 => 'active', 2010 => 'active', 2020 => 'active', 2030 => 'declassified'], $this->frise($station));
        $this->assertSame('range', $station->fresh()->closed_precision);
        $this->assertSame('2026-11-23', $station->fresh()->closed_at?->format('Y-m-d'));
    }

    public function test_g7_closes_the_station_from_2020_when_absent_from_the_attendance_series(): void
    {
        $this->importedSeries();
        $station = $this->station('Vars', 0.20, 'declassified', '87583001');

        $this->build('g7');

        // Fermée à l'ère 2020 (état au 31/12/2020, aucun voyageur depuis 2015), puis retirée : la règle des gares fermées.
        $this->assertSame([2000 => 'active', 2010 => 'active', 2020 => 'declassified', 2030 => null], $this->frise($station));
        $fresh = $station->fresh();
        $this->assertSame('2014-12-31', $fresh->closed_at?->format('Y-m-d'));
        $this->assertSame('range', $fresh->closed_precision);
        $this->assertSame('vff:eras', $fresh->provenance['closed_at'] ?? null);
        $this->assertSame('g7', $fresh->provenance['closed_rule'] ?? null);
        $this->assertSame('Absente des fréquentations SNCF depuis 2015 : plus de voyageurs avant cette date',
            DB::table('station_eras')->where('station_id', $station->id)->where('era_id', $this->eras[2020]->id)->value('note'));

        // Rejouable : sans la règle, tout revient.
        $this->build();
        $this->assertSame('active', $this->frise($station)[2020]);
        $this->assertSame('2026-11-23', $station->fresh()->closed_at?->format('Y-m-d'));
        $this->assertArrayNotHasKey('closed_rule', $station->fresh()->provenance);
    }

    public function test_g7_leaves_a_station_present_in_the_attendance_series(): void
    {
        $this->importedSeries();
        $station = $this->station('Fermée en 2016', 0.20, 'declassified', '87583002');
        $this->attendance($station, 2015, 4000);
        $this->attendance($station, 2016, 300);

        $this->build('g7');

        $this->assertSame('active', $this->frise($station)[2020]);
        $this->assertSame('2026-11-23', $station->fresh()->closed_at?->format('Y-m-d'));
        $this->assertArrayNotHasKey('closed_rule', $station->fresh()->provenance);
    }

    public function test_g7_needs_a_uic_code_to_conclude(): void
    {
        $this->importedSeries();
        $station = $this->station('Sans code UIC', 0.20, 'declassified', null);

        $this->build('g7');

        $this->assertSame('active', $this->frise($station)[2020]);
    }

    public function test_g7_does_nothing_when_the_series_is_not_imported(): void
    {
        $station = $this->station('Vars', 0.20, 'declassified', '87583001');

        $this->build('g7');

        $this->assertSame('active', $this->frise($station)[2020]);
        $this->assertArrayNotHasKey('closed_rule', $station->fresh()->provenance);
    }

    public function test_g7_leaves_a_station_still_open_today(): void
    {
        $this->importedSeries();
        $station = $this->station('Ouverte sans fréquentation', 0.20, 'active', '87583003');

        $this->build('g7');

        $this->assertSame([2000 => 'active', 2010 => 'active', 2020 => 'active', 2030 => 'active'], $this->frise($station));
    }

    public function test_g7_keeps_the_freight_status_of_today_for_a_freight_only_station(): void
    {
        $this->importedSeries();
        $station = $this->station('Charmant', 0.30, 'no_commercial', '87583658');

        $this->build('g7');

        $this->assertSame('no_commercial', $this->frise($station)[2020]);
        $this->assertSame('no_commercial', $this->frise($station)[2030]);
    }
}
