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
 * vff:build-station-eras, ouverture déduite d'une gare sans date propre (inferEvents, étape a) : l'ouverture
 * de la SECTION qui couvre la gare passe avant l'ouverture « toute la ligne », même quand celle-ci porte le
 * tracé de référence entier pour géométrie (section_resolution whole_line, posé par vff:resolve-event-sections).
 * Cas réel : Chaville - Vélizy, sur le tronçon Meudon-Val-Fleury - Viroflay-Rive-Gauche du 31/05/1902 de la
 * ligne des Invalides à Versailles-Rive-Gauche, datée de 1840 (Wikidata, ligne entière).
 */
class StationOpeningFromSectionTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->line = Line::factory()->create(['code' => '977000', 'name' => 'Ligne des Invalides à Versailles-Rive-Gauche']);
        LineSegment::factory()->create([
            'line_id' => $this->line->id, 'era_id' => $current->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
            'geom' => LineString::make([Point::makeGeodetic(48.80, 2.00), Point::makeGeodetic(48.80, 2.20)]),
        ]);
    }

    private function station(string $name, float $lng): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic(48.80, $lng), 'status' => 'active', 'source_status' => 'active',
            'position_source' => 'sncf', 'provenance' => ['status' => 'sncf'], 'opened_at' => null,
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk',
            'pk_m' => (int) round(($lng - 2.00) * 73000), 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    /** Ouverture de la ligne : portion de $fromLng à $toLng (null : sans géométrie), « toute la ligne » ou localisée. */
    private function opening(string $date, ?float $fromLng, ?float $toLng, string $resolution, string $source = 'wikipedia', ?int $lineId = null, bool $named = false): void
    {
        $id = DB::table('line_events')->insertGetId(['line_id' => $lineId ?? $this->line->id, 'type' => 'opening', 'event_date' => $date,
            'date_precision' => 'day', 'description' => 'Ouverture', 'source' => $source, 'section_resolution' => $resolution,
            'section_from' => $resolution === 'whole_line' ? null : 'A', 'section_to' => ($resolution === 'whole_line' || $source === 'ohm' && ! $named) ? null : 'B',
            'created_at' => now(), 'updated_at' => now()]);
        if ($fromLng !== null && $toLng !== null) {
            DB::update('UPDATE line_events SET section_geom = ST_Multi(ST_SetSRID(ST_MakeLine(ST_MakePoint(?, 48.80), ST_MakePoint(?, 48.80)), 4326)) WHERE id = ?',
                [$fromLng, $toLng, $id]);
        }
    }

    private function inferredOpening(Station $station): ?string
    {
        return DB::table('station_events')->where('station_id', $station->id)->where('type', 'opening')->where('is_inferred', true)->value('event_date');
    }

    public function test_the_section_opening_beats_a_whole_line_opening_that_carries_the_reference_trace(): void
    {
        $station = $this->station('Chaville-Vélizy', 2.10);
        $this->opening('1840-09-10', 2.00, 2.20, 'whole_line');
        $this->opening('1902-05-31', 2.05, 2.20, 'places');

        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-23'])->assertSuccessful();

        $this->assertSame('1902-05-31', $this->inferredOpening($station));
    }

    public function test_the_section_opening_beats_a_whole_line_opening_without_geometry(): void
    {
        $station = $this->station('Chaville-Vélizy', 2.10);
        $this->opening('1840-09-10', null, null, 'whole_line');
        $this->opening('1902-05-31', 2.05, 2.20, 'places');

        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-23'])->assertSuccessful();

        $this->assertSame('1902-05-31', $this->inferredOpening($station));
    }

    public function test_a_station_outside_every_section_keeps_the_whole_line_opening(): void
    {
        // Section de 2,15 à 2,20 : à 3,7 km de la gare, bien au-delà des 300 m de EVENT_COVERS_STATION.
        $station = $this->station('Meudon', 2.10);
        $this->opening('1840-09-10', 2.00, 2.20, 'whole_line');
        $this->opening('1902-05-31', 2.15, 2.20, 'places');

        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-23'])->assertSuccessful();

        $this->assertSame('1840-09-10', $this->inferredOpening($station));
    }

    public function test_the_earliest_of_two_sections_covering_the_station_is_kept(): void
    {
        // Gare à la jonction de deux sections : la première ouverte la dessert (Corte, terminus de 1888 avant 1894).
        $station = $this->station('Corte', 2.10);
        $this->opening('1840-09-10', 2.00, 2.20, 'whole_line');
        $this->opening('1888-02-01', 2.00, 2.10, 'places');
        $this->opening('1894-12-03', 2.10, 2.20, 'places');

        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-23'])->assertSuccessful();

        $this->assertSame('1888-02-01', $this->inferredOpening($station));
    }

    public function test_an_ohm_trace_does_not_outrank_the_whole_line_opening(): void
    {
        // Gauriaguet : le way OHM de la LGV (start_date 2017) passe à côté de la vieille gare.
        $station = $this->station('Gauriaguet', 2.10);
        $this->opening('1874-01-01', 2.00, 2.20, 'whole_line', 'wikidata');
        $this->opening('2017-07-02', 2.05, 2.15, 'trace', 'ohm');

        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-23'])->assertSuccessful();

        $this->assertSame('1874-01-01', $this->inferredOpening($station));
    }

    public function test_an_ohm_trace_with_both_ends_named_stays_a_section(): void
    {
        // Thionville - Anzeling : le tracé OHM aux deux bouts nommés date bien la gare, avant l'ouverture de la ligne entière.
        $station = $this->station('Anzeling', 2.10);
        $this->opening('1908-04-01', null, null, 'whole_line', 'wikipedia');
        $this->opening('1863-06-11', 2.05, 2.15, 'trace', 'ohm', null, true);

        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-23'])->assertSuccessful();

        $this->assertSame('1863-06-11', $this->inferredOpening($station));
    }

    public function test_a_text_section_still_outranks_an_ohm_trace(): void
    {
        $station = $this->station('Chaville-Vélizy', 2.10);
        $this->opening('1840-09-10', 2.00, 2.20, 'whole_line', 'wikidata');
        $this->opening('1931-01-01', 2.05, 2.15, 'trace', 'ohm');
        $this->opening('1902-05-31', 2.05, 2.20, 'places');

        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-23'])->assertSuccessful();

        $this->assertSame('1902-05-31', $this->inferredOpening($station));
    }

    public function test_a_line_attached_only_by_segment_does_not_date_a_station_that_has_its_own_line(): void
    {
        // Beaujeu : la voie métrique voisine (rattachement par segment) ne date pas la gare de sa ligne à PK sourcé.
        $station = $this->station('Beaujeu', 2.10);
        $this->opening('1869-01-01', 2.00, 2.20, 'whole_line', 'wikidata');
        $neighbour = Line::factory()->create(['code' => '776000', 'name' => 'Ligne voisine']);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $neighbour->id, 'method' => 'segment',
            'pk_m' => 1000, 'pk_source' => 'projected', 'distance_m' => 40, 'created_at' => now(), 'updated_at' => now()]);
        $this->opening('1901-01-01', 2.05, 2.15, 'places', 'manual', $neighbour->id);

        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-23'])->assertSuccessful();

        $this->assertSame('1869-01-01', $this->inferredOpening($station));
    }
}
