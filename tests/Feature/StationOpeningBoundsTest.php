<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\GeoExporter;
use App\Services\StationCorrections;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Ouverture encadrée par une correction sourcée (database/data/gares_corrections.php, add opening precision range) :
 * une halte créée après sa ligne, sans date connue, ne prend ni la date de la ligne ni une date inventée.
 *
 * Cas d'essai modelés sur Compans (ligne de 1861, halte entre 1891 et 1907) et Renescure (pas avant 1889, desservie
 * aujourd'hui), plus une gare dont la correction datée remplace l'année plus ancienne de Wikidata (Breitenbach).
 */
class StationOpeningBoundsTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const TODAY = '2026-11-23';

    private Line $line;

    /** @var array<int, Era> */
    private array $eras = [];

    private Station $between;

    private Station $notBefore;

    private Station $replaced;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1850, 1860, 1870, 1880, 1890, 1900, 1910, 1920] as $year) {
            $this->eras[$year] = Era::factory()->create(['year' => $year, 'label' => "Années {$year}"]);
        }
        $this->eras[2030] = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);

        $this->line = Line::factory()->create(['code' => '961000', 'name' => 'Ligne témoin des bornes', 'slug' => 'ligne-bornes']);
        foreach ($this->eras as $year => $era) {
            if ($year < 1860) {
                continue;
            }
            LineSegment::factory()->create([
                'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => 'in_service', 'is_heritage' => false, 'traffic_type' => 'mixed',
                'geom' => LineString::make([Point::makeGeodetic(48.0, 2.00), Point::makeGeodetic(48.0, 2.30)]),
            ]);
        }
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'opening', 'event_date' => '1861-08-31', 'date_precision' => 'day',
            'description' => 'opening', 'confidence' => 'high', 'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now()]);

        $this->between = $this->station('Halte encadrée', '87000101', 2.05, 1000);
        $this->notBefore = $this->station('Halte sans borne haute', '87000102', 2.15, 2000);
        $this->replaced = $this->station('Gare corrigée', '87000103', 2.25, 3000);
        DB::table('station_events')->insert(['station_id' => $this->replaced->id, 'type' => 'opening', 'event_date' => '1862-01-01',
            'date_precision' => 'year', 'source' => 'wikidata', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);

        $source = ['source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare&oldid=1', 'quote' => 'citation', 'reason' => 'essai'];
        $this->app->instance(StationCorrections::class, new StationCorrections([
            ['station' => ['name' => 'Halte encadrée', 'uic' => '87000101'], 'action' => 'add', 'type' => 'opening',
                'precision' => 'range', 'after' => 1890, 'date' => '1907-12-31'] + $source,
            ['station' => ['name' => 'Halte sans borne haute', 'uic' => '87000102'], 'action' => 'add', 'type' => 'opening',
                'precision' => 'range', 'after' => 1888, 'date' => null] + $source,
            ['station' => ['name' => 'Gare corrigée', 'uic' => '87000103'], 'action' => 'add', 'type' => 'opening',
                'date' => '1893-11-01', 'precision' => 'day'] + $source,
        ]));
        $this->artisan('vff:build-station-eras', ['--today' => self::TODAY, '--rules' => 'none'])->assertSuccessful();
    }

    private function station(string $name, string $uic, float $lng, int $pk): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'uic_code' => $uic, 'geom' => Point::makeGeodetic(48.0, $lng), 'status' => 'active', 'source_status' => 'active',
            'position_source' => 'sncf', 'provenance' => ['status' => 'sncf'], 'opened_at' => null, 'opened_precision' => null,
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk',
            'pk_m' => $pk, 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    /** @return array<int, array{status: string, uncertain: bool, note: ?string}> année → état de la gare */
    private function eras(Station $station): array
    {
        $out = [];
        foreach (DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')->where('se.station_id', $station->id)
            ->orderBy('e.year')->get(['e.year', 'se.status', 'se.uncertain', 'se.note']) as $r) {
            $out[(int) $r->year] = ['status' => (string) $r->status, 'uncertain' => (bool) $r->uncertain, 'note' => $r->note];
        }

        return $out;
    }

    public function test_the_sheet_keeps_the_interval_and_never_the_date_of_the_line(): void
    {
        $between = $this->between->fresh();
        $this->assertSame(['range', '1907-12-31', 1890, 'correction'],
            [$between->opened_precision, $between->opened_at?->format('Y-m-d'), $between->provenance['opened_after'] ?? null, $between->provenance['opened_at'] ?? null]);
        // Sans borne haute : aujourd'hui (la halte est desservie), la borne basse est gardée.
        $notBefore = $this->notBefore->fresh();
        $this->assertSame(['range', self::TODAY, 1888], [$notBefore->opened_precision, $notBefore->opened_at?->format('Y-m-d'), $notBefore->provenance['opened_after'] ?? null]);
        // La date de la ligne (31/08/1861) n'est plus déduite pour ces haltes.
        $this->assertFalse(DB::table('station_events')->whereIn('station_id', [$this->between->id, $this->notBefore->id])->where('is_inferred', true)
            ->where('type', 'opening')->exists());
        // La ligne ne dessert la halte qu'à partir de la borne basse, jamais de la borne haute.
        $this->assertSame(1891, (int) DB::table('station_lines')->where('station_id', $this->between->id)->value('first_year'));
    }

    public function test_the_eras_are_uncertain_between_the_bounds(): void
    {
        $eras = $this->eras($this->between);
        $this->assertSame([1900, 1910, 1920, 2030], array_keys($eras), 'absente jusqu\'à la borne basse (1890)');
        $this->assertTrue($eras[1900]['uncertain']);
        $this->assertStringContainsString('Date d\'ouverture inconnue (entre 1891 et 1907)', (string) $eras[1900]['note']);
        $this->assertFalse($eras[1910]['uncertain']);
        $this->assertFalse($eras[2030]['uncertain']);

        $open = $this->eras($this->notBefore);
        $this->assertSame([1890, 1900, 1910, 1920, 2030], array_keys($open));
        foreach ([1890, 1900, 1910, 1920] as $year) {
            $this->assertTrue($open[$year]['uncertain'], "ère {$year}");
            $this->assertStringContainsString('Date d\'ouverture inconnue (pas avant 1889)', (string) $open[$year]['note']);
        }
        $this->assertFalse($open[2030]['uncertain']);
    }

    public function test_a_corrected_opening_replaces_an_older_date_of_another_source(): void
    {
        $eras = $this->eras($this->replaced);
        $this->assertArrayNotHasKey(1880, $eras, 'l\'année 1862 de Wikidata ne doit plus ouvrir la gare');
        $this->assertSame([1900, 1910, 1920, 2030], array_keys($eras));
        $this->assertFalse($eras[1900]['uncertain']);
        $this->assertSame(['day', '1893-11-01'], [$this->replaced->fresh()->opened_precision, $this->replaced->fresh()->opened_at?->format('Y-m-d')]);
    }

    public function test_the_api_the_chronology_and_the_map_serve_the_interval(): void
    {
        $sheet = $this->getJson("/api/v1/stations/{$this->between->slug}")->assertOk()
            ->assertJsonPath('data.opened_at', '1907-12-31')
            ->assertJsonPath('data.opened_precision', 'range')
            ->assertJsonPath('data.opened_after', 1890)
            ->json('data.chronology');
        $openings = array_values(array_filter((array) $sheet, fn (array $e): bool => $e['type'] === 'opening'));
        $this->assertCount(1, $openings, 'les deux bornes forment une seule entrée');
        $this->assertSame(['range', 1890, '1907-12-31', 'correction'], [$openings[0]['precision'], $openings[0]['after'], $openings[0]['date'], $openings[0]['source']]);

        $this->graphQL("{ station(slug: \"{$this->between->slug}\") { opened_precision opened_after } }")->assertJsonMissingPath('errors')
            ->assertJsonPath('data.station.opened_precision', 'range')
            ->assertJsonPath('data.station.opened_after', 1890);

        $features = collect(app(GeoExporter::class)->stationsAsGeoJson(1900)['features'])->keyBy('properties.slug');
        $this->assertSame(['range', 1890, 'uncertain'], [$features[$this->between->slug]['properties']['opened_precision'],
            $features[$this->between->slug]['properties']['opened_after'], $features[$this->between->slug]['properties']['status']]);
        $this->assertSame(['day', null], [$features[$this->replaced->slug]['properties']['opened_precision'], $features[$this->replaced->slug]['properties']['opened_after']]);

        foreach (['fr' => 'entre 1891 et 1907', 'en' => 'between 1891 and 1907', 'de' => 'zwischen 1891 und 1907'] as $lang => $when) {
            $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
                ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => (object) ['name' => 'get_station',
                    'arguments' => (object) ['station' => $this->between->slug, 'lang' => $lang]]])->assertOk()->json('result');
            $opened = (json_decode((string) $result['content'][0]['text'], true) ?? [])['station']['opened'] ?? [];
            $this->assertSame([$when, 1891, 1907], [$opened['when'] ?? null, $opened['from_year'] ?? null, $opened['to_year'] ?? null]);
        }
    }

    public function test_an_interval_must_be_an_opening_with_ordered_bounds(): void
    {
        $base = ['station' => ['name' => 'X', 'uic' => '87000000'], 'action' => 'add', 'type' => 'opening', 'precision' => 'range',
            'source_url' => 'https://example.org/x', 'reason' => 'essai'];
        $this->assertSame([], StationCorrections::validate($base + ['after' => 1890, 'date' => '1907-12-31']));
        $this->assertSame([], StationCorrections::validate($base + ['after' => 1890, 'date' => null]));
        $this->assertSame([], StationCorrections::validate($base + ['date' => '1907-12-31']));
        $this->assertNotSame([], StationCorrections::validate($base + ['after' => 1907, 'date' => '1907-12-31']));
        $this->assertNotSame([], StationCorrections::validate($base + ['after' => null, 'date' => null]));
        $this->assertNotSame([], StationCorrections::validate(['type' => 'closure', 'after' => 1890, 'date' => '1907-12-31'] + $base));
        $this->assertSame([['date' => '1890-12-31', 'precision' => 'after'], ['date' => '1907-12-31', 'precision' => 'before']],
            StationCorrections::addedEvents($base + ['after' => 1890, 'date' => '1907-12-31']));
    }
}
