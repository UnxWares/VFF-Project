<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\StationServiceDates;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Dates de début et de fin de desserte d'une gare par une ligne (App\Services\StationServiceDates),
 * mêmes valeurs sur la fiche gare, la fiche ligne, en REST, en GraphQL et par les outils MCP
 * get_station et get_line. Cas témoin réel : Saint-Calais sur 507000, desservie du 31/03/1879
 * (événement d'ouverture) au 15/05/1938 (fermeture aux voyageurs).
 */
class StationServiceDatesTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    /** @var array<string, int> identifiants des événements de ligne */
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();
        $past = Era::factory()->create(['year' => 1930]);
        Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->line = Line::factory()->create(['slug' => 'ligne-temoin-900320', 'code' => '900320', 'name' => 'Ligne témoin des dates de desserte']);
        LineSegment::factory()->create([
            'line_id' => $this->line->id, 'era_id' => $past->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
            'geom' => LineString::make([Point::makeGeodetic(47.50, 2.00), Point::makeGeodetic(47.50, 2.20)]),
        ]);
        $this->events = [
            // Même date, autre portion, créé en premier : jamais pris pour l'ouverture des gares témoins.
            'opening_far' => $this->event('opening', '1879-03-31', 'day', [18_000, 20_000]),
            'opening' => $this->event('opening', '1879-03-31', 'day', [0, 20_000]),
            'closure' => $this->event('closure_passenger', '1938-05-15', 'day', [0, 20_000]),
            'opening_month' => $this->event('opening', '1869-04-01', 'month', [0, 20_000]),
        ];
    }

    /** @param  array{0: int, 1: int}  $pk */
    private function event(string $type, string $date, string $precision, array $pk): int
    {
        return (int) DB::table('line_events')->insertGetId(['line_id' => $this->line->id, 'type' => $type, 'event_date' => $date,
            'date_precision' => $precision, 'description' => 'Événement témoin', 'confidence' => 'high', 'section_resolution' => 'resolved',
            'section_from' => 'A', 'section_to' => 'B', 'section_pk_start_m' => $pk[0], 'section_pk_end_m' => $pk[1],
            'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $station  attributs de la gare
     * @param  array<string, mixed>  $link  colonnes de station_lines
     */
    private function station(string $slug, int $pk, array $station, array $link): Station
    {
        // Sans date d'ouverture donnée, la gare n'en a pas : la fabrique en tire une au hasard entre 1876 et 1976,
        // et une année 1879 (celle de la ligne) annulait à bon droit le début de desserte, au hasard des tirages.
        $s = Station::factory()->create($station + [
            'slug' => $slug, 'name' => ucfirst($slug), 'type' => 'gare', 'geom' => Point::makeGeodetic(47.50, 2.00 + $pk / 100_000),
            'status' => 'declassified', 'provenance' => [], 'opened_at' => null, 'opened_precision' => null,
        ]);
        DB::table('station_lines')->insert($link + ['station_id' => $s->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => $pk,
            'pk_source' => 'sncf', 'distance_m' => 0, 'years_source' => 'line_events', 'created_at' => now(), 'updated_at' => now()]);

        return $s;
    }

    /** Gares témoins, et leurs bornes attendues (null : seules les années sont sûres). */
    private function witnesses(): array
    {
        $dated = ['first_year' => 1879, 'last_year' => 1938, 'first_event_date' => '1879-03-31', 'first_event_precision' => 'day',
            'last_event_date' => '1938-05-15', 'last_event_precision' => 'day'];
        $this->station('saint-calais', 5_000, ['opened_at' => '1873-02-20', 'opened_precision' => 'day'], $dated);
        // Gare ouverte la même année, après la ligne : sa propre date borne la desserte.
        $this->station('ouverte-apres', 8_000, ['opened_at' => '1879-11-02', 'opened_precision' => 'day'], $dated);
        // Même année, à l'année seulement : rien ne dit qu'elle était ouverte le 31/03.
        $this->station('ouverte-meme-annee', 9_000, ['opened_at' => '1879-01-01', 'opened_precision' => 'year'], $dated);
        // Date déduite des lignes : ne contredit rien.
        $this->station('ouverte-deduite', 10_000, ['opened_at' => '1879-01-01', 'opened_precision' => 'year',
            'provenance' => ['opened_at' => 'inferred:line_event']], $dated);
        // Fermée en 1935, avant la ligne : last_year borné à 1935, la date de 1938 ne vaut plus.
        $this->station('fermee-avant', 11_000, ['closed_at' => '1935-06-01', 'closed_precision' => 'day'], ['last_year' => 1935] + $dated);
        // Fin en intervalle (borne haute) : jamais une date au jour.
        $this->station('borne-haute', 12_000, ['closed_at' => '1938-12-31', 'closed_precision' => 'range', 'provenance' => ['closed_after' => 1930]], $dated);
        $this->station('au-mois', 13_000, [], ['first_year' => 1869, 'last_year' => null, 'first_event_date' => '1869-04-01',
            'first_event_precision' => 'month']);

        $start = ['date' => '1879-03-31', 'precision' => 'day', 'event_id' => $this->events['opening']];
        $end = ['date' => '1938-05-15', 'precision' => 'day', 'event_id' => $this->events['closure']];

        return [
            'saint-calais' => [$start, $end],
            'ouverte-apres' => [null, $end],
            'ouverte-meme-annee' => [null, $end],
            'ouverte-deduite' => [$start, $end],
            'fermee-avant' => [$start, null],
            'borne-haute' => [$start, null],
            'au-mois' => [['date' => '1869-04-01', 'precision' => 'month', 'event_id' => $this->events['opening_month']], null],
        ];
    }

    private function mcp(string $tool, array $arguments): array
    {
        $response = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]])
            ->assertOk();

        return json_decode((string) $response->json('result.content.0.text'), true);
    }

    /** Borne de l'API REST : avec le lien de l'événement. */
    private static function rest(?array $bound): ?array
    {
        return $bound === null ? null : $bound + ['links' => ['event' => route('api.events.show', $bound['event_id'])]];
    }

    public function test_the_station_sheet_gives_the_service_dates_and_their_event_everywhere(): void
    {
        foreach ($this->witnesses() as $slug => [$start, $end]) {
            $this->get('/stations/'.$slug)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('lines.0.service_start', $start)->where('lines.0.service_end', $end)->etc());
            $this->getJson('/api/v1/stations/'.$slug)->assertOk()
                ->assertJsonPath('data.lines.0.service_start', self::rest($start))
                ->assertJsonPath('data.lines.0.service_end', self::rest($end));
            $graphql = $this->postJson('/graphql', ['query' => "{ station(slug: \"{$slug}\") { sheet { lines {
                service_start { date precision event_id } service_end { date precision event_id } } } } }"])
                ->assertOk()->assertJsonMissingPath('errors');
            $this->assertSame([$start, $end], [$graphql->json('data.station.sheet.lines.0.service_start'),
                $graphql->json('data.station.sheet.lines.0.service_end')], "GraphQL {$slug}");
            $line = $this->mcp('get_station', ['station' => $slug])['lines'][0];
            $this->assertSame([$start['date'] ?? null, $start['event_id'] ?? null, $end['date'] ?? null, $end['event_id'] ?? null],
                [$line['service_start']['date'] ?? null, $line['service_start']['event_id'] ?? null,
                    $line['service_end']['date'] ?? null, $line['service_end']['event_id'] ?? null], "MCP {$slug}");
        }
        // Le lien mène bien à l'événement.
        $this->getJson(route('api.events.show', $this->events['opening']))->assertOk()->assertJsonPath('data.date', '1879-03-31');
    }

    public function test_mcp_writes_each_date_at_its_own_precision(): void
    {
        $this->witnesses();
        $fr = $this->mcp('get_station', ['station' => 'saint-calais'])['lines'][0];
        $this->assertSame('31 mars 1879', $fr['service_start']['when']);
        $this->assertSame('15 mai 1938', $fr['service_end']['when']);
        $this->assertSame('31 March 1879', $this->mcp('get_station', ['station' => 'saint-calais', 'lang' => 'en'])['lines'][0]['service_start']['when']);
        // Au mois : jamais un jour.
        $month = $this->mcp('get_station', ['station' => 'au-mois', 'lang' => 'de'])['lines'][0]['service_start'];
        $this->assertSame(['date' => '1869-04-01', 'precision' => 'month', 'when' => 'April 1869', 'event_id' => $this->events['opening_month']], $month);
    }

    public function test_the_line_sheet_gives_the_same_dates_for_each_station(): void
    {
        $expected = $this->witnesses();
        $pick = fn (array $stations): array => collect($stations)
            ->mapWithKeys(fn (array $s): array => [$s['slug'] => [$s['service_start'], $s['service_end']]])->all();
        $rest = array_map(fn (array $b): array => [self::rest($b[0]), self::rest($b[1])], $expected);

        $this->get('/lines/'.$this->line->slug)->assertOk()->assertInertia(function (Assert $page) use ($expected): void {
            foreach (array_keys($expected) as $i => $slug) {
                [$start, $end] = $expected[$slug];
                $page->where("stations.{$i}.slug", $slug)->where("stations.{$i}.service_start", $start)->where("stations.{$i}.service_end", $end);
            }
            $page->etc();
        });
        $this->assertEquals($rest, $pick($this->getJson('/api/v1/lines/'.$this->line->slug)->assertOk()->json('data.stations')));
        $graphql = $this->postJson('/graphql', ['query' => '{ line(slug: "ligne-temoin-900320") { sheet { stations { slug
            service_start { date precision event_id } service_end { date precision event_id } } } } }'])
            ->assertOk()->assertJsonMissingPath('errors');
        $this->assertEquals($expected, $pick($graphql->json('data.line.sheet.stations')));

        /** @var list<array<string, mixed>> $stations */
        $stations = $this->mcp('get_line', ['line' => $this->line->slug, 'include_events' => false])['line']['stations'];
        $mcp = [];
        foreach ($stations as $s) {
            $mcp[$s['slug']] = [
                $s['service_start'] === null ? null : array_diff_key($s['service_start'], ['when' => true]),
                $s['service_end'] === null ? null : array_diff_key($s['service_end'], ['when' => true]),
            ];
        }
        $this->assertEquals($expected, $mcp);
        $this->assertSame('31 mars 1879', $stations[0]['service_start']['when']);
    }

    public function test_a_line_that_never_served_the_station_has_no_service_date(): void
    {
        $this->station('la-fouillouse', 5_000, ['opened_at' => '1879-11-24', 'opened_precision' => 'day'], ['first_year' => 1879, 'last_year' => 1879,
            'first_event_date' => '1879-03-31', 'first_event_precision' => 'day', 'last_event_date' => '1879-09-26', 'last_event_precision' => 'day',
            'years_source' => 'never_served']);
        $this->getJson('/api/v1/stations/la-fouillouse')->assertOk()
            ->assertJsonPath('data.lines.0.never_served', 'line_closed')
            ->assertJsonPath('data.lines.0.service_start', null)->assertJsonPath('data.lines.0.service_end', null);
    }

    public function test_dates_are_compared_only_at_a_precision_both_share(): void
    {
        $this->assertTrue(StationServiceDates::notAfter('1879-03-31', 'day', '1879-03-31', 'day'));
        $this->assertFalse(StationServiceDates::notAfter('1879-04-01', 'day', '1879-03-31', 'day'));
        $this->assertTrue(StationServiceDates::notAfter('1879-02-01', 'month', '1879-03-31', 'day'));
        $this->assertFalse(StationServiceDates::notAfter('1879-03-01', 'month', '1879-03-31', 'day'));
        $this->assertFalse(StationServiceDates::notAfter('1879-01-01', 'year', '1879-12-31', 'day'));
        $this->assertFalse(StationServiceDates::notAfter('1870-01-01', 'range', '1879-12-31', 'day'));
    }
}
