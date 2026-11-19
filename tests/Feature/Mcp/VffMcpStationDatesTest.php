<?php

namespace Tests\Feature\Mcp;

use App\Models\Era;
use App\Models\Station;
use App\Support\EventText;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `get_station` n'écrit jamais une date d'intervalle ou une borne comme un jour précis (revue de la
 * PR #18, point 5) : une fermeture de précision `range` est rédigée avec ses bornes, comme sur la
 * fiche de la gare, ou « date inconnue » quand la borne haute n'est que l'année du calcul ; les dates
 * « avant », « après » et « années 1950 » de la chronologie gardent leur sens.
 */
class VffMcpStationDatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function station(string $slug, array $attributes): Station
    {
        return Station::factory()->create(['slug' => $slug, 'name' => ucfirst($slug), 'city' => ucfirst($slug),
            'geom' => Point::makeGeodetic(48.2, 6.5), 'status' => 'declassified'] + $attributes);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function sheet(array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'get_station', 'arguments' => $arguments]])
            ->assertOk()->json('result');
        $this->assertFalse($result['isError'], $result['content'][0]['text']);

        return json_decode($result['content'][0]['text'], true);
    }

    public function test_a_closure_known_by_an_interval_is_written_with_its_bounds(): void
    {
        // Comme la gare « Madame » : fermée entre 1980 et 1989, sans date.
        $this->station('madame', ['closed_at' => '1989-12-31', 'closed_precision' => 'range', 'provenance' => ['closed_after' => 1979]]);

        $closed = $this->sheet(['station' => 'madame'])['station']['closed'];
        $this->assertSame(['date' => null, 'precision' => 'range', 'from_year' => 1980, 'to_year' => 1989, 'when' => 'entre 1980 et 1989'], $closed);
        $this->assertSame('between 1980 and 1989', $this->sheet(['station' => 'madame', 'lang' => 'en'])['station']['closed']['when']);
        $this->assertSame('zwischen 1980 und 1989', $this->sheet(['station' => 'madame', 'lang' => 'de'])['station']['closed']['when']);
    }

    public function test_an_interval_ending_this_year_is_an_unknown_date(): void
    {
        // Comme Jarménil : fermée aujourd'hui, aucune source ne date la fermeture ; la borne haute n'est
        // que la date du calcul, jamais écrite comme un jour.
        $today = now()->toDateString();
        $this->station('jarmenil', ['closed_at' => $today, 'closed_precision' => 'range', 'provenance' => ['closed_after' => 1870]]);

        $closed = $this->sheet(['station' => 'jarmenil'])['station']['closed'];
        $this->assertNull($closed['date']);
        $this->assertSame(1871, $closed['from_year']);
        $this->assertNull($closed['to_year']);
        $this->assertSame('date inconnue, après 1870 (aucune source ne la date)', $closed['when']);
        $this->assertStringNotContainsString((string) now()->year, $closed['when']);
    }

    public function test_an_interval_without_lower_bound_is_an_upper_bound(): void
    {
        $this->station('sans-borne', ['closed_at' => '1950-12-31', 'closed_precision' => 'range', 'provenance' => []]);

        $closed = $this->sheet(['station' => 'sans-borne'])['station']['closed'];
        $this->assertSame(['date' => null, 'precision' => 'range', 'from_year' => null, 'to_year' => 1950, 'when' => 'au plus tard en 1950'], $closed);
    }

    public function test_bounded_dates_of_the_timeline_keep_their_meaning(): void
    {
        $station = $this->station('bornes', ['closed_at' => '1938-05-15', 'closed_precision' => 'day']);
        foreach ([['opening', '1950-01-01', 'decade'], ['closure_passenger', '1960-01-01', 'before'], ['closure', '1970-01-01', 'after']] as [$type, $date, $precision]) {
            DB::table('station_events')->insert(['station_id' => $station->id, 'type' => $type, 'event_date' => $date,
                'date_precision' => $precision, 'source' => 'wikidata', 'created_at' => now(), 'updated_at' => now()]);
        }

        $data = $this->sheet(['station' => 'bornes']);
        $this->assertSame(['années 1950', 'avant 1960', 'après 1970'], array_column($data['events'], 'when'));
        $this->assertSame('15 mai 1938', $data['station']['closed']['when'], 'une date au jour reste écrite au jour');
        $this->assertSame(['the 1950s', 'before 1960', 'after 1970'], array_column($this->sheet(['station' => 'bornes', 'lang' => 'en'])['events'], 'when'));
    }

    /** @return array<string, array{0: string, 1: ?string, 2: string, 3: ?int, 4: ?int, 5: string}> */
    public static function dates(): array
    {
        return [
            'jour' => ['1875-04-01', 'day', 'fr', null, null, '1er avril 1875'],
            'mois' => ['1875-04-01', 'month', 'fr', null, null, 'avril 1875'],
            'année' => ['1875-01-01', 'year', 'fr', null, null, '1875'],
            'décennie allemande' => ['1950-01-01', 'decade', 'de', null, null, '1950er-Jahre'],
            'intervalle sans borne basse' => ['1989-12-31', 'range', 'fr', null, 2026, 'au plus tard en 1989'],
            'intervalle d’un an' => ['1989-12-31', 'range', 'fr', 1988, 2026, '1989'],
            'intervalle jusqu’à l’année du calcul' => ['2026-11-19', 'range', 'en', null, 2026, 'date unknown (no source gives it)'],
            'précision inconnue' => ['1875-04-12', 'inconnue', 'fr', null, null, '1875'],
            'précision absente' => ['1875-04-12', null, 'fr', null, null, '1875'],
        ];
    }

    #[DataProvider('dates')]
    public function test_a_date_is_never_written_finer_than_its_precision(string $iso, ?string $precision, string $locale, ?int $after, ?int $currentYear, string $expected): void
    {
        $this->assertSame($expected, EventText::isoDate($iso, $precision, $locale, $after, $currentYear));
    }
}
