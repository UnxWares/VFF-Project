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
 * Règles facultatives G3 et G6 de vff:build-station-eras (--rules=g3,g6, désactivées par défaut) : ouverture
 * déduite bornée par la carte, fermeture tirée de la fin des voyageurs de la ligne rattachée par PK. Réseau
 * d'essai : une ligne est-ouest par gare, ères 1950 à 1990 et ère courante (2030).
 */
class StationEraAuditRulesTest extends TestCase
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
     * Ligne à la latitude donnée, un tronçon par ère : [statut, trafic] par ère (défaut : en service, mixte).
     *
     * @param  array<int, array{0: string, 1: string}>  $byEra
     */
    private function line(string $code, float $lat, array $byEra = []): Line
    {
        $line = Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}"]);
        foreach ($this->eras as $year => $era) {
            [$status, $traffic] = $byEra[$year] ?? ['in_service', 'mixed'];
            LineSegment::factory()->create([
                'line_id' => $line->id, 'era_id' => $era->id, 'status' => $status, 'is_heritage' => false, 'traffic_type' => $traffic,
                'geom' => LineString::make([Point::makeGeodetic($lat, 2.00), Point::makeGeodetic($lat, 2.20)]),
            ]);
        }

        return $line;
    }

    /** Événement de ligne ; section par PK (en mètres) ou toute la ligne. */
    private function lineEvent(Line $line, string $type, string $date, ?array $pk = null): void
    {
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'day',
            'description' => $type, 'confidence' => 'high', 'section_resolution' => $pk === null ? 'whole_line' : 'pk',
            'section_pk_start_m' => $pk[0] ?? null, 'section_pk_end_m' => $pk[1] ?? null, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param  list<array{0: string, 1: string}>  $events  événements propres de la gare (Wikipédia) */
    private function station(string $name, float $lat, string $sourceStatus, Line $line, array $events = []): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic($lat, 2.10),
            'status' => $sourceStatus, 'source_status' => $sourceStatus,
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

    /** @return array<int, ?string> statut à chaque ère (null : absente de la carte) */
    private function frise(Station $station): array
    {
        $rows = DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')
            ->where('se.station_id', $station->id)->pluck('se.status', 'e.year')->all();

        return array_map(fn (int $year) => $rows[$year] ?? null, array_combine([...self::YEARS, 2030], [...self::YEARS, 2030]));
    }

    private function build(?string $rules = null): void
    {
        $this->artisan('vff:build-station-eras', array_filter(['--today' => '2026-11-22', '--rules' => $rules]))->assertSuccessful();
    }

    /** Ligne datée de 1955, voie hors service près de la gare jusqu'en 1960 : la carte la dessert dès 1970. */
    private function lateLine(string $code, float $lat): Line
    {
        $line = $this->line($code, $lat, [1950 => ['abandoned', 'unknown'], 1960 => ['abandoned', 'unknown']]);
        $this->lineEvent($line, 'opening', '1955-05-01');

        return $line;
    }

    public function test_g3_bounds_an_inferred_opening_by_the_first_served_era(): void
    {
        $station = $this->station('Ouverture déduite', 47.00, 'active', $this->lateLine('960000', 47.00));

        $this->build('g3');

        $this->assertSame([1950 => null, 1960 => null, 1970 => 'active', 1980 => 'active', 1990 => 'active', 2030 => 'active'], $this->frise($station));
        $fresh = $station->fresh();
        $this->assertSame('1970-12-31', $fresh->opened_at?->format('Y-m-d'));
        $this->assertSame('range', $fresh->opened_precision);
        $this->assertSame(1954, $fresh->provenance['opened_after'] ?? null);
        $this->assertSame('g3', $fresh->provenance['opened_rule'] ?? null);
        $this->assertSame('inferred:line_event', $fresh->provenance['opened_at'] ?? null);

        // Rejouable, et sans la règle la date déduite revient telle quelle.
        $this->build();
        $fresh = $station->fresh();
        $this->assertSame('1955-05-01', $fresh->opened_at?->format('Y-m-d'));
        $this->assertSame('day', $fresh->opened_precision);
        $this->assertArrayNotHasKey('opened_after', $fresh->provenance);
        $this->assertArrayNotHasKey('opened_rule', $fresh->provenance);
        $this->assertSame('declassified', $this->frise($station)[1960]);
    }

    public function test_g3_is_off_by_default(): void
    {
        $station = $this->station('Ouverture déduite', 47.00, 'active', $this->lateLine('960000', 47.00));

        $this->build();

        $this->assertSame('declassified', $this->frise($station)[1960]);
        $this->assertSame('1955-05-01', $station->fresh()->opened_at?->format('Y-m-d'));
    }

    public function test_g3_never_touches_a_sourced_opening_or_a_reopened_station(): void
    {
        $sourced = $this->station('Ouverture sourcée', 47.10, 'active', $this->lateLine('960100', 47.10), [['opening', '1955-05-01']]);
        $reopened = $this->station('Rouverte', 47.20, 'active', $this->lateLine('960200', 47.20), [['reopening', '1985-09-01']]);

        $this->build('g3');

        $this->assertNotSame('range', $sourced->fresh()->opened_precision);
        $this->assertArrayNotHasKey('opened_rule', $sourced->fresh()->provenance ?? []);
        $this->assertNotSame('range', $reopened->fresh()->opened_precision);
        $this->assertArrayNotHasKey('opened_rule', $reopened->fresh()->provenance ?? []);
        $this->assertSame('declassified', $this->frise($reopened)[1960]);
    }

    /** Ligne ouverte en 1900, fermée aux voyageurs en 1965 (section par PK ou toute la ligne), fret ensuite. */
    private function passengerEndLine(string $code, float $lat, bool $byPk): Line
    {
        $freight = ['in_service', 'freight'];
        $line = $this->line($code, $lat, [1970 => $freight, 1980 => $freight, 1990 => $freight, 2030 => $freight]);
        $this->lineEvent($line, 'opening', '1900-05-01');
        $this->lineEvent($line, 'closure_passenger', '1965-09-26', $byPk ? [0, 5000] : null);

        return $line;
    }

    public function test_g6_dates_an_undated_closure_from_the_passenger_end_attached_by_pk(): void
    {
        $station = $this->station('Halte fermée sans date', 47.30, 'declassified', $this->passengerEndLine('960300', 47.30, true));

        $this->build();
        $before = $station->fresh();
        $this->assertSame('range', $before->closed_precision);
        $this->assertSame('vff:eras', $before->provenance['closed_at'] ?? null);

        $this->build('g6');

        $fresh = $station->fresh();
        $this->assertSame('1965-09-26', $fresh->closed_at?->format('Y-m-d'));
        $this->assertSame('day', $fresh->closed_precision);
        $this->assertSame('inferred:line_event', $fresh->provenance['closed_at'] ?? null);
        $this->assertSame('g6', $fresh->provenance['closed_rule'] ?? null);
        // Fret seul non confirmé après la fin des voyageurs : fermée à l'ère 1970, puis retirée de la carte.
        $this->assertSame([1950 => 'active', 1960 => 'active', 1970 => 'declassified', 1980 => null, 1990 => null, 2030 => null],
            $this->frise($station));

        // Sans la règle, la date redevient l'intervalle des ères.
        $this->build();
        $this->assertSame('range', $station->fresh()->closed_precision);
        $this->assertArrayNotHasKey('closed_rule', $station->fresh()->provenance);
    }

    public function test_g6_ignores_a_passenger_end_without_pk(): void
    {
        $station = $this->station('Halte, fin de toute la ligne', 47.40, 'declassified', $this->passengerEndLine('960400', 47.40, false));

        $this->build('g6');

        $this->assertSame('range', $station->fresh()->closed_precision);
        $this->assertArrayNotHasKey('closed_rule', $station->fresh()->provenance);
    }

    public function test_g6_ignores_a_line_reopened_after_the_passenger_end(): void
    {
        $line = $this->passengerEndLine('960500', 47.50, true);
        $this->lineEvent($line, 'reopening_passenger', '1975-06-01', [0, 5000]);
        $station = $this->station('Halte, ligne rouverte', 47.50, 'declassified', $line);

        $this->build('g6');

        $this->assertArrayNotHasKey('closed_rule', $station->fresh()->provenance ?? []);
    }

    public function test_g6_ignores_a_passenger_end_followed_by_a_freight_end(): void
    {
        $line = $this->passengerEndLine('960600', 47.60, true);
        // Fin du fret donnée pour toute la ligne : quel que soit son rattachement, le fret a continué après 1965.
        $this->lineEvent($line, 'closure_freight', '1980-05-31');
        $station = $this->station('Gare, fret jusqu\'en 1980', 47.60, 'declassified', $line);

        $this->build('g6');

        $this->assertArrayNotHasKey('closed_rule', $station->fresh()->provenance ?? []);
    }

    public function test_rules_from_configuration_and_unknown_rule(): void
    {
        config(['stations.era_rules' => ['g6']]);
        $station = $this->station('Halte fermée sans date', 47.30, 'declassified', $this->passengerEndLine('960300', 47.30, true));

        $this->build();
        $this->assertSame('g6', $station->fresh()->provenance['closed_rule'] ?? null);
        // --rules=none passe avant la configuration.
        $this->build('none');
        $this->assertArrayNotHasKey('closed_rule', $station->fresh()->provenance);

        $this->artisan('vff:build-station-eras', ['--rules' => 'g3,g9'])->expectsOutputToContain('Règle inconnue : g9')->assertFailed();
    }
}
