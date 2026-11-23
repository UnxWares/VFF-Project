<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\DataInvariants;
use App\Services\InvariantExceptions;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cas légitimes des invariants (chantier « invariants-gares-nuit », 23/11/2026) : fichier d'exceptions
 * database/data/invariant_exceptions.php (App\Services\InvariantExceptions) et gare terminus nommé d'une section
 * ouverte à sa date (stations.opened_before_lines).
 */
class InvariantExceptionsTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $past = Era::factory()->create(['year' => 1950]);
        Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->line = Line::factory()->create(['code' => '900400', 'name' => 'Ligne témoin des exceptions']);
        LineSegment::factory()->create([
            'line_id' => $this->line->id, 'era_id' => $past->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
            'geom' => LineString::make([Point::makeGeodetic(47.50, 2.00), Point::makeGeodetic(47.50, 2.20)]),
        ]);
        $this->opening('1869-06-01', 'trace', null, null);
    }

    private function opening(string $date, string $resolution, ?string $from, ?string $to): void
    {
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'opening', 'event_date' => $date, 'date_precision' => 'day',
            'description' => 'Ouverture', 'confidence' => 'high', 'section_resolution' => $resolution, 'section_from' => $from, 'section_to' => $to,
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function station(string $name, string $opened = '1869-05-01'): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic(47.50, 2.05), 'status' => 'active', 'source_status' => 'active',
            'position_source' => 'sncf', 'provenance' => ['status' => 'sncf'], 'opened_at' => $opened, 'opened_precision' => 'day',
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => 4_000,
            'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    private function violations(string $key, ?InvariantExceptions $exceptions = null): int
    {
        return collect((new DataInvariants([$key], $exceptions ?? new InvariantExceptions([])))->run())->firstWhere('key', $key)['count'];
    }

    public function test_the_versioned_exceptions_are_valid_and_unique(): void
    {
        $exceptions = new InvariantExceptions;
        $this->assertNotSame([], $exceptions->all());
        $this->assertSame([], $exceptions->errors());
    }

    public function test_invalid_entries_are_reported(): void
    {
        $valid = ['invariant' => 'stations.opened_before_lines', 'slug' => 'une-gare', 'reason' => 'Raison.', 'source_url' => 'https://exemple.test/a', 'reviewed_at' => '2026-11-23'];
        $this->assertSame([], InvariantExceptions::validate($valid));
        $this->assertContains('reason manquant', InvariantExceptions::validate(['reason' => ' '] + $valid));
        $this->assertContains('invariant inconnu « inconnu.x »', InvariantExceptions::validate(['invariant' => 'inconnu.x'] + $valid));
        $this->assertContains('slug mal formé « Une Gare »', InvariantExceptions::validate(['slug' => 'Une Gare'] + $valid));
        $this->assertContains('source_url : lien https attendu', InvariantExceptions::validate(['source_url' => 'http://exemple.test'] + $valid));
        $this->assertContains('reviewed_at : date AAAA-MM-JJ attendue', InvariantExceptions::validate(['reviewed_at' => '23/11/2026'] + $valid));
        $this->assertContains('reason : pas de tiret long', InvariantExceptions::validate(['reason' => "a \u{2014} b"] + $valid));
        $errors = (new InvariantExceptions([$valid, $valid]))->errors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('doublon', $errors[0]);
        $this->assertSame(['une-gare'], (new InvariantExceptions([$valid]))->slugs('stations.opened_before_lines'));
        $this->assertSame([], (new InvariantExceptions([$valid]))->slugs('stations.ohm_opened_off_section'));
    }

    public function test_an_exception_removes_only_its_station_and_its_invariant(): void
    {
        $station = $this->station('Inaugurée');
        $this->assertSame(1, $this->violations('stations.opened_before_lines'));
        $entry = fn (string $invariant, string $slug) => ['invariant' => $invariant, 'slug' => $slug, 'reason' => 'Raison.', 'source_url' => 'https://exemple.test/a', 'reviewed_at' => '2026-11-23'];

        $this->assertSame(0, $this->violations('stations.opened_before_lines', new InvariantExceptions([$entry('stations.opened_before_lines', $station->slug)])));
        // Autre gare ou autre invariant : la violation reste.
        $this->assertSame(1, $this->violations('stations.opened_before_lines', new InvariantExceptions([$entry('stations.opened_before_lines', 'autre-gare')])));
        $this->assertSame(1, $this->violations('stations.opened_before_lines', new InvariantExceptions([$entry('stations.ohm_opened_off_section', $station->slug)])));
    }

    public function test_an_exception_removes_a_line_from_the_km_drop(): void
    {
        $this->assertSame(1, $this->violations('lines.km_drop_without_event'));
        $entry = ['invariant' => 'lines.km_drop_without_event', 'slug' => $this->line->slug, 'reason' => 'Raison.', 'source_url' => 'https://exemple.test/a', 'reviewed_at' => '2026-11-23'];
        $this->assertSame(0, $this->violations('lines.km_drop_without_event', new InvariantExceptions([$entry])));
    }

    public function test_a_station_named_as_the_end_of_a_section_opened_on_its_date_is_not_reported(): void
    {
        // Section Dormans - Épernay du 01/05/1869 : sa géométrie résolue ne couvre pas la gare, mais elle la nomme.
        $this->station('Terminus nommé');
        $this->assertSame(1, $this->violations('stations.opened_before_lines'));
        $this->opening('1869-05-01', 'places', 'Ailleurs', 'Terminus nommé');
        $this->assertSame(0, $this->violations('stations.opened_before_lines'));
    }

    public function test_the_section_name_ignores_accents_hyphens_abbreviations_parentheses_and_suffixes(): void
    {
        $cases = [
            ['Rédange (Moselle)', 'Rédange'],
            ['Prades-Molitg-les-Bains', 'Prades'],
            ['St-Chély-d\'Apcher', 'Saint-Chély-d\'Apcher'],
        ];
        foreach ($cases as [$stationName, $sectionName]) {
            $this->station($stationName);
            $this->assertSame(1, $this->violations('stations.opened_before_lines'), $stationName);
            $this->opening('1869-05-01', 'places', $sectionName, 'Ailleurs');
            $this->assertSame(0, $this->violations('stations.opened_before_lines'), $stationName);
            DB::table('stations')->where('name', $stationName)->delete();
            DB::table('line_events')->where('section_to', 'Ailleurs')->delete();
        }
    }

    public function test_the_name_is_enough_whatever_the_resolution_of_the_section(): void
    {
        foreach (['unresolved', 'pk', 'trace', 'places'] as $resolution) {
            DB::table('stations')->delete();
            DB::table('line_events')->where('section_from', 'Terminus nommé')->orWhere('section_to', 'Terminus nommé')->delete();
            $this->station('Terminus nommé');
            $this->assertSame(1, $this->violations('stations.opened_before_lines'), $resolution);
            $this->opening('1869-05-01', $resolution, 'Terminus nommé', 'Ailleurs');
            $this->assertSame(0, $this->violations('stations.opened_before_lines'), $resolution);
            DB::table('line_events')->where('section_to', 'Ailleurs')->delete();
        }
    }

    public function test_a_named_section_that_opens_too_late_does_not_absolve(): void
    {
        $this->station('Terminus nommé');
        // Section nommée mais ouverte le 05/05/1869, quatre jours après la gare : ne la décrit pas.
        $this->opening('1869-05-05', 'places', 'Ailleurs', 'Terminus nommé');
        $this->assertSame(1, $this->violations('stations.opened_before_lines'));
        // Autre nom à la bonne date : la violation reste.
        $this->opening('1869-05-01', 'places', 'Ailleurs', 'Un autre nom');
        $this->assertSame(1, $this->violations('stations.opened_before_lines'));
        // Le nom est un début de mot seulement (Pra ne désigne pas Prades).
        DB::table('stations')->update(['name' => 'Prades-Molitg']);
        $this->opening('1869-05-01', 'places', 'Pra', 'Ailleurs');
        $this->assertSame(1, $this->violations('stations.opened_before_lines'));
    }
}
