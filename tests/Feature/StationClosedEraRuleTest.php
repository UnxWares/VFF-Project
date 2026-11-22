<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\LineStations;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Règle de Baptiste du 22/11/2026 (vff:build-station-eras, keepClosedOnlyAtClosingEra) : une gare
 * fermée « ne disparaît jamais de la section gares, ne disparaît jamais de la liste des gares de la
 * ligne mais sur la carte d'époques elle est marquée comme fermée dans la décennie où elle ferme puis
 * est enlevée sur les décennies suivantes même si toujours debout ». Réseau d'essai : une ligne
 * est-ouest par gare, des ères 1950 à 1990 et l'ère courante (2030).
 */
class StationClosedEraRuleTest extends TestCase
{
    use RefreshDatabase;

    private const YEARS = [1950, 1960, 1970, 1980, 1990];

    /** @var array<int, Era> */
    private array $eras = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::YEARS as $year) {
            $this->eras[$year] = Era::factory()->create(['year' => $year, 'label' => "Années {$year}"]);
        }
        $this->eras[2030] = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
    }

    /**
     * Ligne à la latitude donnée, avec un tronçon à chaque ère : statut de la voie par ère (défaut :
     * en service, mixte).
     *
     * @param  array<int, string>  $statusByEra
     */
    private function line(string $code, float $lat, array $statusByEra = []): Line
    {
        $line = Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}"]);
        foreach ($this->eras as $year => $era) {
            $status = $statusByEra[$year] ?? 'in_service';
            LineSegment::factory()->create([
                'line_id' => $line->id, 'era_id' => $era->id, 'status' => $status, 'is_heritage' => false,
                'traffic_type' => $status === 'in_service' ? 'mixed' : 'unknown',
                'geom' => LineString::make([Point::makeGeodetic($lat, 2.00), Point::makeGeodetic($lat, 2.20)]),
            ]);
        }

        return $line;
    }

    /**
     * Gare sur sa ligne, avec ses événements datés (Wikipédia).
     *
     * @param  list<array{0: string, 1: string}>  $events
     */
    private function station(string $name, float $lat, string $sourceStatus, Line $line, array $events): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic($lat, 2.10),
            'status' => $sourceStatus === 'gone' ? 'declassified' : $sourceStatus, 'source_status' => $sourceStatus,
            'position_source' => 'sncf', 'provenance' => ['status' => 'sncf'], 'opened_at' => null,
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk',
            'pk_m' => 1000, 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($events as [$type, $date]) {
            DB::table('station_events')->insert(['station_id' => $station->id, 'type' => $type, 'event_date' => $date,
                'date_precision' => 'day', 'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $station;
    }

    /** @return array<int, ?string> statut de station_eras à chaque ère (null : absente de la carte) */
    private function frise(Station $station): array
    {
        $rows = DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')
            ->where('se.station_id', $station->id)->pluck('se.status', 'e.year')->all();

        return array_map(fn (int $year) => $rows[$year] ?? null, array_combine([...self::YEARS, 2030], [...self::YEARS, 2030]));
    }

    private function build(): void
    {
        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-22'])
            ->expectsOutputToContain('Gares fermées montrées à la seule ère de leur fermeture')->assertSuccessful();
    }

    public function test_total_closure_is_shown_closed_at_its_closing_era_only(): void
    {
        $station = $this->station('Fermée en 1955', 47.00, 'declassified', $this->line('950000', 47.00),
            [['opening', '1900-05-01'], ['closure', '1955-06-01']]);

        $this->build();

        $this->assertSame([1950 => 'active', 1960 => 'declassified', 1970 => null, 1980 => null, 1990 => null, 2030 => null],
            $this->frise($station));
        // Statut affiché (fiche, section des gares) : toujours « fermée ».
        $this->assertSame('declassified', $station->fresh()->status->value);
        $this->assertSame('1955-06-01', (string) $station->fresh()->closed_at?->format('Y-m-d'));
    }

    public function test_passenger_end_then_freight_end_follows_the_same_rule(): void
    {
        $station = $this->station('Voyageurs 1955, fret 1975', 47.10, 'declassified', $this->line('950100', 47.10),
            [['opening', '1900-05-01'], ['closure_passenger', '1955-06-01'], ['closure_freight', '1975-09-30']]);
        $freightOnly = $this->station('Fret seul aujourd\'hui', 47.15, 'no_commercial', $this->line('950150', 47.15),
            [['opening', '1900-05-01'], ['closure_passenger', '1955-06-01']]);

        $this->build();

        $this->assertSame([1950 => 'active', 1960 => 'no_commercial', 1970 => 'no_commercial', 1980 => 'declassified', 1990 => null, 2030 => null],
            $this->frise($station));
        // Fret seul : montrée tant qu'elle a du fret (l'ère courante suit les sources du jour, StationTimeline::currentStatus).
        $this->assertSame([1950 => 'active', 1960 => 'no_commercial', 1970 => 'no_commercial', 1980 => 'no_commercial', 1990 => 'no_commercial'],
            array_slice($this->frise($freightOnly), 0, 5, true));
    }

    /** Debout, disparue (sources « gone ») ou démolie : même frise, la variante de b47b5c3 est retirée. */
    public function test_standing_gone_and_demolished_stations_leave_the_map_the_same_way(): void
    {
        $events = [['opening', '1900-05-01'], ['closure_passenger', '1955-06-01'], ['closure_freight', '1965-09-30']];
        $standing = $this->station('Debout', 47.20, 'declassified', $this->line('950200', 47.20), $events);
        $gone = $this->station('Disparue', 47.25, 'gone', $this->line('950250', 47.25), $events);
        $demolished = $this->station('Démolie', 47.30, 'gone', $this->line('950300', 47.30),
            [['opening', '1900-05-01'], ['demolition', '1965-03-01']]);

        $this->build();

        $expected = [1950 => 'active', 1960 => 'no_commercial', 1970 => 'declassified', 1980 => null, 1990 => null, 2030 => null];
        $this->assertSame($expected, $this->frise($standing));
        $this->assertSame($expected, $this->frise($gone));
        $this->assertSame([1950 => 'active', 1960 => 'active', 1970 => 'declassified', 1980 => null, 1990 => null, 2030 => null],
            $this->frise($demolished));
    }

    public function test_reopened_station_comes_back_at_its_reopening_era(): void
    {
        $station = $this->station('Rouverte en 1985', 47.40, 'active', $this->line('950400', 47.40),
            [['opening', '1900-05-01'], ['closure', '1955-06-01'], ['reopening', '1985-09-01']]);

        $this->build();

        $this->assertSame([1950 => 'active', 1960 => 'declassified', 1970 => null, 1980 => null, 1990 => 'active', 2030 => 'active'],
            $this->frise($station));
    }

    /**
     * Gare sans fermeture propre sur une ligne qui ferme (gare fermée si toutes ses lignes le sont) : fermée à la
     * première ère où la ligne est fermée, puis absente ; sur la carte, seulement si une voie reste à moins de
     * 300 m (ligne déposée : jamais, la fiche de la ligne la garde).
     */
    public function test_station_on_a_closed_or_dismantled_line(): void
    {
        $abandoned = $this->line('950500', 47.50, [1970 => 'abandoned', 1980 => 'abandoned', 1990 => 'abandoned', 2030 => 'abandoned']);
        $onAbandoned = $this->station('Ligne fermée, voie en place', 47.50, 'declassified', $abandoned, [['opening', '1900-05-01']]);
        $dismantled = $this->line('950600', 47.60, [1970 => 'dismantled', 1980 => 'dismantled', 1990 => 'dismantled', 2030 => 'dismantled']);
        $onDismantled = $this->station('Ligne déposée', 47.60, 'declassified', $dismantled, [['opening', '1900-05-01']]);

        $this->build();

        $expected = [1950 => 'active', 1960 => 'active', 1970 => 'declassified', 1980 => null, 1990 => null, 2030 => null];
        $this->assertSame($expected, $this->frise($onAbandoned));
        $this->assertSame($expected, $this->frise($onDismantled));

        $names = function (int $year): array {
            /** @var list<array{properties: array{name: string, service: string}}> $features */
            $features = $this->getJson("/api/v1/eras/{$year}/stations.geojson")->assertOk()->json('features');

            return array_column(array_column($features, 'properties'), 'service', 'name');
        };
        $this->assertSame('closed', $names(1970)['Ligne fermée, voie en place'] ?? null);
        $this->assertArrayNotHasKey('Ligne déposée', $names(1970));
        $this->assertArrayNotHasKey('Ligne fermée, voie en place', $names(1980));
        $this->assertArrayNotHasKey('Ligne fermée, voie en place', $names(2030));

        // Arrêts de la ligne : à l'ère de la fermeture, plus après.
        $stops = DB::table('line_stops as ls')->join('eras as e', 'e.id', '=', 'ls.era_id')
            ->where('ls.station_id', $onAbandoned->id)->orderBy('e.year')->pluck('e.year')->all();
        $this->assertNotContains(1980, $stops);
        $this->assertNotContains(2030, $stops);
    }

    /** Section des gares, fiche de gare et liste des gares de la ligne : toutes les gares, avec leurs dates. */
    public function test_closed_stations_stay_in_the_station_section_and_the_line_list(): void
    {
        $line = $this->line('950700', 47.70);
        $closed = $this->station('Fermée depuis longtemps', 47.70, 'declassified', $line, [['opening', '1900-05-01'], ['closure', '1955-06-01']]);
        $open = $this->station('Ouverte', 47.70, 'active', $line, [['opening', '1900-05-01']]);
        DB::table('station_lines')->where('station_id', $open->id)->update(['pk_m' => 2000]);

        $this->build();

        $this->get('/stations?q=Fermée')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Stations/Index')->where('stations.total', 1)->where('stations.data.0.name', 'Fermée depuis longtemps')->etc());
        $this->get('/stations?status=declassified')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stations.total', 1)->etc());
        $this->assertContains('Fermée depuis longtemps', $this->getJson('/api/v1/stations?per_page=50')->assertOk()->json('data.*.name'));
        $this->getJson('/api/v1/stations/'.$closed->slug)->assertOk();
        $this->get('/stations/'.$closed->slug)->assertOk();

        $list = app(LineStations::class)->forLine($line->id);
        $this->assertSame(['Fermée depuis longtemps', 'Ouverte'], array_column($list['stations'], 'name'));
        $row = $list['stations'][0];
        $this->assertSame('1955-06-01', substr((string) $row['closed_at'], 0, 10));
        $this->assertSame('declassified', $row['eras'][1960]['status']);
        $this->assertNull($row['eras'][1970]['status']);
    }
}
