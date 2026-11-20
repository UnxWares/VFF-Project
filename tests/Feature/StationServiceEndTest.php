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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fin de desserte d'une gare par une ligne (App\Enums\ServiceEnd) et origine des années, mêmes
 * valeurs sur la fiche gare, la fiche ligne, en REST, en GraphQL et par l'outil MCP get_station.
 * Cas témoins du recalcul du 19/11/2026 (rapport soir/gares-recalcul.md) :
 *   - Jarménil (062000) : fermée à une date inconnue, « entre 1871 et 2026 » : last_year 2026 n'est
 *     que l'année du calcul, fin non datée ;
 *   - Frenelle-la-Grande - Puzieux (040000) : fermée « entre 1980 et 1989 » : au plus tard en 1989 ;
 *   - Briouze : fermée le 31/05/1970, desservie jusqu'en 1970 (année sûre) ;
 *   - Morlaix (447000) : aucun événement daté de la ligne, années tirées des dates de la gare.
 */
class StationServiceEndTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $past = Era::factory()->create(['year' => 1950]);
        $current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->line = Line::factory()->create(['slug' => 'ligne-temoin-900310', 'code' => '900310', 'name' => 'Ligne témoin des fins de desserte']);
        foreach ([$past, $current] as $era) {
            LineSegment::factory()->create([
                'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
                'geom' => LineString::make([Point::makeGeodetic(47.50, 2.00), Point::makeGeodetic(47.50, 2.20)]),
            ]);
        }
    }

    /**
     * @param  array{0: ?string, 1: ?string, 2: ?int}  $closed  closed_at, closed_precision, closed_after
     * @param  array{0: ?int, 1: ?int, 2: string}  $years  first_year, last_year, years_source
     */
    private function station(string $slug, int $pk, array $closed, array $years): Station
    {
        [$closedAt, $precision, $after] = $closed;
        $station = Station::factory()->create([
            'slug' => $slug, 'name' => ucfirst($slug), 'type' => 'gare', 'geom' => Point::makeGeodetic(47.50, 2.00 + $pk / 100_000),
            'status' => $closedAt === null ? 'active' : 'declassified', 'opened_at' => $years[0] !== null ? "{$years[0]}-01-01" : null,
            'opened_precision' => $years[0] !== null ? 'year' : null, 'closed_at' => $closedAt, 'closed_precision' => $precision,
            'provenance' => $after !== null ? ['closed_after' => $after] : [],
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => $pk,
            'pk_source' => 'sncf', 'distance_m' => 0, 'first_year' => $years[0], 'last_year' => $years[1], 'years_source' => $years[2],
            'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    /** @return array<string, Station> */
    private function witnesses(): array
    {
        $today = now()->toDateString();

        return [
            'jarmenil' => $this->station('jarmenil', 2_000, [$today, 'range', 1870], [1869, now()->year, 'line_events']),
            'frenelle' => $this->station('frenelle', 6_000, ['1989-12-31', 'range', 1979], [1872, 1989, 'line_events']),
            'briouze' => $this->station('briouze', 10_000, ['1970-05-31', 'day', null], [1872, 1970, 'line_events']),
            'morlaix' => $this->station('morlaix', 14_000, [null, null, null], [1865, null, 'station']),
        ];
    }

    public function test_the_station_sheet_says_when_the_end_of_service_is_only_a_bound(): void
    {
        $expected = ['jarmenil' => ['undated', 'line_events'], 'frenelle' => ['upper_bound', 'line_events'],
            'briouze' => ['year', 'line_events'], 'morlaix' => [null, 'station']];
        foreach ($this->witnesses() as $slug => $station) {
            [$kind, $source] = $expected[$slug];
            $this->get('/stations/'.$slug)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('lines.0.last_year_kind', $kind)->where('lines.0.years_source', $source)->etc());
            $this->getJson('/api/v1/stations/'.$slug)->assertOk()
                ->assertJsonPath('data.lines.0.last_year_kind', $kind)->assertJsonPath('data.lines.0.years_source', $source);
            $this->postJson('/graphql', ['query' => "{ station(slug: \"{$slug}\") { sheet { lines { last_year last_year_kind years_source } } } }"])
                ->assertOk()->assertJsonMissingPath('errors')
                ->assertJsonPath('data.station.sheet.lines.0.last_year_kind', $kind)
                ->assertJsonPath('data.station.sheet.lines.0.years_source', $source);
            $mcp = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
                ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                    'params' => ['name' => 'get_station', 'arguments' => ['station' => $slug]]])->assertOk();
            $line = json_decode((string) $mcp->json('result.content.0.text'), true)['lines'][0];
            $this->assertSame([$kind, $source], [$line['last_year_kind'], $line['years_source']], "MCP {$slug}");
        }
    }

    public function test_the_line_sheet_gives_the_same_end_of_service_for_each_station(): void
    {
        $this->witnesses();
        $expected = [['jarmenil', 'undated', 'line_events'], ['frenelle', 'upper_bound', 'line_events'],
            ['briouze', 'year', 'line_events'], ['morlaix', null, 'station']];
        $pick = fn (array $stations): array => array_map(fn (array $s): array => [$s['slug'], $s['served_until_kind'], $s['years_source']], $stations);

        $this->get('/lines/'.$this->line->slug)->assertOk()->assertInertia(function (Assert $page) use ($expected): void {
            foreach ($expected as $i => [$slug, $kind, $source]) {
                $page->where("stations.{$i}.slug", $slug)->where("stations.{$i}.served_until_kind", $kind)->where("stations.{$i}.years_source", $source);
            }
            $page->etc();
        });
        $rest = $this->getJson('/api/v1/lines/'.$this->line->slug)->assertOk();
        $this->assertSame($expected, $pick($rest->json('data.stations')));
        $graphql = $this->postJson('/graphql', ['query' => '{ line(slug: "ligne-temoin-900310") { sheet { stations { slug served_until_kind years_source } } } }'])
            ->assertOk()->assertJsonMissingPath('errors');
        $this->assertSame($expected, $pick($graphql->json('data.line.sheet.stations')));
    }
}
