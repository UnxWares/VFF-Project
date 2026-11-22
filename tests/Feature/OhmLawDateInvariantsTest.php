<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Station;
use App\Services\DataInvariants;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dates d'ouverture OpenHistoricalMap qui sont celles de la loi d'utilité publique ou de la première
 * section recopiée (Lens - Frévent : 08/08/1890 pour une mise en service le 01/12/1895).
 */
class OhmLawDateInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private const BEFORE = 'stations.ohm_opened_before_lines';

    private const OFF_SECTION = 'stations.ohm_opened_off_section';

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create(['code' => '900400', 'name' => 'Ligne de Lens à Frévent']);
        $this->opening($this->line, '1895-12-01');
    }

    private function opening(Line $line, string $date, string $resolution = 'trace'): void
    {
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'opening', 'event_date' => $date, 'date_precision' => 'day',
            'description' => 'Ouverture', 'confidence' => 'high', 'section_resolution' => $resolution, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function station(string $name, string $opened, string $source = 'ohm', string $precision = 'day', string $method = 'sncf_pk', string $pkSource = 'sncf', ?Line $line = null): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic(47.50, 2.0 + mt_rand(1, 9_000) / 10_000), 'status' => 'active', 'source_status' => 'active',
            'provenance' => ['status' => 'sncf', 'opened_at' => $source], 'opened_at' => $opened, 'opened_precision' => $precision,
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => ($line ?? $this->line)->id, 'method' => $method, 'pk_m' => 4_000,
            'pk_source' => $pkSource, 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    /** @phpstan-impure */
    private function violations(string $key): int
    {
        return collect((new DataInvariants([self::BEFORE, self::OFF_SECTION]))->run())->firstWhere('key', $key)['count'];
    }

    public function test_an_ohm_opening_far_before_the_line_is_reported(): void
    {
        $this->station('Bully', '1890-08-08');
        $this->assertSame(1, $this->violations(self::BEFORE));
    }

    public function test_the_gap_must_exceed_six_months(): void
    {
        $station = $this->station('Rue Faidherbe', '1895-06-30');
        $this->assertSame(0, $this->violations(self::BEFORE));
        DB::table('stations')->where('id', $station->id)->update(['opened_at' => '1895-05-30']);
        $this->assertSame(1, $this->violations(self::BEFORE));
    }

    public function test_only_day_precise_ohm_dates_on_resolved_official_attachments_count(): void
    {
        // Autre origine, date à l'année, section non résolue, rattachement par nom ou PK projeté : jamais signalé.
        $this->station('Wikidata', '1890-08-08', 'wikidata');
        $this->station('Annuelle', '1890-01-01', 'ohm', 'year');
        $this->station('Terminus', '1890-08-08', 'ohm', 'day', 'terminus_name', 'name');
        $this->station('Projetee', '1890-08-08', 'ohm', 'day', 'segment', 'projected_raw');
        $this->assertSame(0, $this->violations(self::BEFORE));

        $other = Line::factory()->create(['code' => null, 'name' => 'Ligne non résolue']);
        $this->opening($other, '1895-12-01', 'whole_line');
        $this->station('Entiere', '1890-08-08', 'ohm', 'day', 'sncf_pk', 'sncf', $other);
        $this->assertSame(0, $this->violations(self::BEFORE));
    }

    public function test_an_ohm_opening_absolved_by_an_older_line_is_not_reported(): void
    {
        $station = $this->station('Bifurcation', '1890-08-08');
        $older = Line::factory()->create(['code' => null, 'name' => 'Ligne plus ancienne']);
        $this->opening($older, '1889-01-01');
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $older->id, 'method' => 'sncf_pk', 'pk_m' => 0,
            'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(0, $this->violations(self::BEFORE));
    }

    public function test_a_date_copied_from_the_first_section_onto_a_later_section_is_reported(): void
    {
        // Ligne en deux sections : la gare de la section tardive porte la date de la première.
        $this->opening($this->line, '1905-03-01');
        DB::table('line_events')->where('line_id', $this->line->id)->where('event_date', '1895-12-01')
            ->update(['section_pk_start_m' => 0, 'section_pk_end_m' => 10_000]);
        DB::table('line_events')->where('line_id', $this->line->id)->where('event_date', '1905-03-01')
            ->update(['section_pk_start_m' => 20_000, 'section_pk_end_m' => 30_000]);
        $late = $this->station('Tardive', '1895-12-01');
        DB::table('station_lines')->where('station_id', $late->id)->update(['pk_m' => 25_000]);
        $this->station('Premiere', '1895-12-01');
        $this->assertSame(1, $this->violations(self::OFF_SECTION));
        $this->assertStringContainsString('Tardive', (new DataInvariants([self::OFF_SECTION]))->run()[0]['examples'][0]);
    }

    public function test_stations_of_the_same_section_sharing_its_date_are_not_reported(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $this->station($name, '1895-12-01');
        }
        $this->assertSame(0, $this->violations(self::OFF_SECTION));
    }

    public function test_the_window_is_six_months_before_and_two_years_after_the_section(): void
    {
        $station = $this->station('Halte', '1897-11-30');
        $this->assertSame(0, $this->violations(self::OFF_SECTION));
        DB::table('stations')->where('id', $station->id)->update(['opened_at' => '1897-12-02']);
        $this->assertSame(1, $this->violations(self::OFF_SECTION));
        DB::table('stations')->where('id', $station->id)->update(['opened_at' => '1895-06-02']);
        $this->assertSame(0, $this->violations(self::OFF_SECTION));
        DB::table('stations')->where('id', $station->id)->update(['opened_at' => '1895-05-30']);
        $this->assertSame(1, $this->violations(self::OFF_SECTION));
    }

    public function test_a_late_halt_with_its_own_source_is_not_reported(): void
    {
        $this->station('Halte sourcee', '1920-05-01', 'correction');
        $this->station('Halte wikidata', '1920-05-01', 'wikidata');
        $this->assertSame(0, $this->violations(self::OFF_SECTION));
        $this->station('Halte OHM', '1920-05-01');
        $this->assertSame(1, $this->violations(self::OFF_SECTION));
    }

    public function test_unresolved_sections_and_unofficial_attachments_are_ignored(): void
    {
        $this->station('Terminus', '1920-05-01', 'ohm', 'day', 'terminus_name', 'name');
        $this->station('Projetee', '1920-05-01', 'ohm', 'day', 'segment', 'projected_raw');
        $this->station('Annuelle', '1920-01-01', 'ohm', 'year');
        $other = Line::factory()->create(['code' => null, 'name' => 'Ligne non résolue']);
        $this->opening($other, '1895-12-01', 'unresolved');
        $this->station('Non resolue', '1920-05-01', 'ohm', 'day', 'sncf_pk', 'sncf', $other);
        $this->assertSame(0, $this->violations(self::OFF_SECTION));
    }

    public function test_a_section_covering_the_station_within_the_window_on_any_line_absolves_it(): void
    {
        $station = $this->station('Bifurcation', '1920-05-01');
        $branch = Line::factory()->create(['code' => null, 'name' => 'Embranchement']);
        $this->opening($branch, '1919-01-01');
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $branch->id, 'method' => 'sncf_pk', 'pk_m' => 0,
            'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(0, $this->violations(self::OFF_SECTION));
    }
}
