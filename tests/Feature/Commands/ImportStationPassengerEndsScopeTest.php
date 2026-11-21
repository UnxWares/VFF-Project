<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-station-passenger-ends : choix de la gare (--station), articles absents du cache,
 * bilan détaillé (-v), fin du fret écrite à part de la fin des voyageurs. Jamais de réseau.
 */
class ImportStationPassengerEndsScopeTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::fake(['*' => Http::response([], 500)]);
    }

    private function cachePage(string $title, string $infobox): void
    {
        $dir = storage_path('app/sources/stations/wikipedia');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.sha1($title).'.json', json_encode([
            'title' => $title, 'revid' => 1, 'content' => "{{Infobox Gare\n | nom = x\n{$infobox}\n}}\nLa gare est fermée.", 'requested' => $title,
        ]));
    }

    private function closedStation(string $slug, string $article, string $qid): int
    {
        $id = $this->station($slug, ucfirst($slug), 48.0, 2.0, ['status' => 'no_commercial', 'source_status' => 'no_commercial', 'wikidata_id' => $qid])->id;
        DB::table('station_sources')->insert([
            'source' => 'wikidata', 'source_ref' => $qid, 'station_id' => $id, 'name' => 'x', 'lifecycle' => 'disused',
            'properties' => json_encode(['frwiki' => $article]), 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.0, 48.0), 4326)'),
        ]);

        return $id;
    }

    /** @return list<string> */
    private function events(int $stationId): array
    {
        return DB::table('station_events')->where('station_id', $stationId)->orderBy('event_date')
            ->get(['type', 'event_date'])->map(fn ($e) => "{$e->type} {$e->event_date}")->all();
    }

    public function test_an_unknown_station_fails_without_writing(): void
    {
        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true, '--station' => 'nulle-part'])
            ->expectsOutputToContain('Gare introuvable : nulle-part')
            ->assertFailed();

        $this->assertSame(0, DB::table('station_events')->count());
    }

    public function test_station_option_takes_a_slug_or_an_id_and_leaves_the_others_alone(): void
    {
        $a = $this->closedStation('alpha', 'Gare de Alpha', 'Q101');
        $b = $this->closedStation('bravo', 'Gare de Bravo', 'Q102');
        $this->cachePage('Gare de Alpha', ' | fermeture = 5 mai 1938 (voyageurs)');
        $this->cachePage('Gare de Bravo', ' | fermeture = 6 mai 1939 (voyageurs)');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true, '--station' => 'alpha'])->assertSuccessful();
        $this->assertSame(['closure_passenger 1938-05-05'], $this->events($a));
        $this->assertSame([], $this->events($b));

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true, '--station' => (string) $b])->assertSuccessful();
        $this->assertSame(['closure_passenger 1939-05-06'], $this->events($b));
        $this->assertSame(['closure_passenger 1938-05-05'], $this->events($a));
    }

    public function test_articles_missing_from_the_cache_are_reported_and_nothing_is_fetched(): void
    {
        $id = $this->closedStation('charlie', 'Gare de Charlie', 'Q103');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])
            ->expectsOutputToContain('absents du cache')
            ->assertSuccessful();

        $this->assertSame([], $this->events($id));
        Http::assertNothingSent();
    }

    public function test_a_freight_end_alone_is_written_and_a_verbose_run_lists_each_date(): void
    {
        $id = $this->closedStation('delta', 'Gare de Delta', 'Q104');
        $this->cachePage('Gare de Delta', ' | fermeture = 2 novembre 2009 (marchandises)');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true, '-v' => true])
            ->expectsOutputToContain('closure_freight')
            ->assertSuccessful();

        $this->assertSame(['closure_freight 2009-11-02'], $this->events($id));
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $id = $this->closedStation('echo-gare', 'Gare de Echo', 'Q105');
        $this->cachePage('Gare de Echo', ' | fermeture = 5 mai 1938 (voyageurs)<br />30 septembre 1990 (marchandises)');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])->assertSuccessful();
        $first = $this->events($id);
        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])->assertSuccessful();

        $this->assertSame(['closure_passenger 1938-05-05', 'closure_freight 1990-09-30'], $first);
        $this->assertSame($first, $this->events($id));
    }
}
