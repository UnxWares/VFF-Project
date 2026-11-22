<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Garde-fou de vff:import-station-closures --from-cache (incident du 21/11/2026 : cache des articles et cache
 * Wikidata vides, 1 729 événements effacés et 4 réécrits) : cache vide ou très incomplet, la commande refuse
 * d'écrire, avec un code de sortie non nul et sans toucher aux événements. --cache-only de
 * vff:import-station-sources remplit le cache Wikidata sans écrire dans la base.
 */
class ImportStationClosuresCacheGuardTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::preventStrayRequests();
    }

    private function closedStationWithWikidata(string $slug, string $qid, ?string $frwiki = null): int
    {
        $id = $this->station($slug, ucfirst($slug), 48.0, 2.0, ['status' => 'declassified', 'source_status' => 'declassified'])->id;
        DB::table('station_sources')->insert([
            'source' => 'wikidata', 'source_ref' => $qid, 'station_id' => $id, 'name' => 'x', 'lifecycle' => 'disused',
            'properties' => json_encode($frwiki ? ['frwiki' => $frwiki] : []), 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.0, 48.0), 4326)'),
        ]);

        return $id;
    }

    private function wikipediaEvent(int $stationId): void
    {
        DB::table('station_events')->insert(['station_id' => $stationId, 'type' => 'closure', 'event_date' => '1980-01-01', 'date_precision' => 'year',
            'source' => 'wikipedia', 'source_ref' => 'gare:Alpha#infobox', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_an_empty_article_cache_refuses_to_run_and_keeps_the_events(): void
    {
        $id = $this->closedStationWithWikidata('alpha', 'Q1', 'Gare d\'Alpha');
        $this->wikipediaEvent($id);
        $before = $this->snapshot(['station_events', 'stations']);

        $this->artisan('vff:import-station-closures', ['--from-cache' => true, '--source' => ['wikipedia-stations']])
            ->expectsOutputToContain('Cache incomplet pour articles de gare : 1 éléments absents sur 1 attendus')
            ->expectsOutputToContain('--from-cache refusé')
            ->assertFailed();

        $this->assertSame($before, $this->snapshot(['station_events', 'stations']));
        $this->assertSame(1, DB::table('station_events')->where('source', 'wikipedia')->count());
    }

    public function test_an_empty_wikidata_cache_refuses_to_run_and_keeps_the_events(): void
    {
        $id = $this->closedStationWithWikidata('alpha', 'Q1');
        DB::table('station_events')->insert(['station_id' => $id, 'type' => 'closure', 'event_date' => '1980-01-01', 'date_precision' => 'year',
            'source' => 'wikidata:qualifiers', 'source_ref' => 'Q1#P3999', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('vff:import-station-closures', ['--from-cache' => true, '--source' => ['wikidata']])
            ->expectsOutputToContain('Cache incomplet pour wikidata')
            ->assertFailed();

        $this->assertSame(1, DB::table('station_events')->where('source', 'wikidata:qualifiers')->count());
    }

    public function test_a_dry_run_still_works_with_an_empty_cache_but_warns(): void
    {
        $this->closedStationWithWikidata('alpha', 'Q1', 'Gare d\'Alpha');
        $before = $this->snapshot(['station_events', 'stations']);

        $this->artisan('vff:import-station-closures', ['--from-cache' => true, '--dry-run' => true, '--source' => ['wikipedia-stations']])
            ->expectsOutputToContain('une exécution réelle serait refusée')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot(['station_events', 'stations']));
    }

    public function test_a_complete_cache_is_accepted(): void
    {
        $id = $this->closedStationWithWikidata('alpha', 'Q1', 'Gare d\'Alpha');
        $dir = storage_path('app/sources/stations/wikipedia');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.sha1('Gare d\'Alpha').'.json', json_encode(['title' => 'Gare d\'Alpha', 'revid' => 5, 'content' => "{{Infobox Gare\n | fermeture = 1972\n}}\nLa gare est fermée.", 'requested' => 'Gare d\'Alpha']));

        $this->artisan('vff:import-station-closures', ['--from-cache' => true, '--source' => ['wikipedia-stations']])->assertSuccessful();

        $this->assertSame(1, DB::table('station_events')->where('station_id', $id)->where('source', 'wikipedia')->count());
    }

    public function test_without_from_cache_the_guard_does_not_apply(): void
    {
        $this->closedStationWithWikidata('alpha', 'Q1');

        $this->artisan('vff:import-station-closures', ['--source' => ['wikidata']])->assertSuccessful();
    }

    public function test_cache_only_fills_the_wikidata_cache_without_writing_to_the_database(): void
    {
        Sleep::fake();
        Http::fake([
            'www.wikidata.org/*' => Http::sequence()
                ->push(['query' => ['search' => [['title' => 'Q1']]]]) // première classe : un élément
                ->whenEmpty(Http::response(['query' => ['search' => []], 'entities' => ['Q1' => ['claims' => []]]])),
        ]);
        $before = $this->snapshot(['station_sources', 'station_events', 'stations']);

        $this->artisan('vff:import-station-sources', ['--source' => ['wikidata'], '--cache-only' => true])
            ->expectsOutputToContain('cache Wikidata')
            ->assertSuccessful();

        $this->assertNotEmpty(glob(storage_path('app/sources/stations/wikidata/entities-*.json')));
        $this->assertFileExists(storage_path('app/sources/stations/wikidata/.nobackup'));
        $this->assertSame($before, $this->snapshot(['station_sources', 'station_events', 'stations']));
    }

    public function test_cache_only_requires_the_wikidata_source_and_no_from_cache(): void
    {
        $this->artisan('vff:import-station-sources', ['--cache-only' => true])->assertFailed();
        $this->artisan('vff:import-station-sources', ['--source' => ['wikidata'], '--cache-only' => true, '--from-cache' => true])->assertFailed();
    }
}
