<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-station-closures : fermetures lues dans les articles Wikipédia
 * (gare et ligne, cache disque) et les notes OSM → station_events, rattachées
 * aux seules gares fermées aujourd'hui, rejouable.
 */
class ImportStationClosuresTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::fake(['*' => Http::response([], 500)]); // --from-cache : jamais de réseau
    }

    private function cachePage(string $title, string $content, int $revid = 123456): void
    {
        $dir = storage_path('app/sources/stations/wikipedia');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.sha1($title).'.json', json_encode(['title' => $title, 'revid' => $revid, 'content' => $content, 'requested' => $title]));
    }

    private function sourceRow(int $stationId, string $source, string $ref, array $props): void
    {
        DB::table('station_sources')->insert([
            'source' => $source, 'source_ref' => $ref, 'station_id' => $stationId, 'name' => 'x', 'lifecycle' => 'disused',
            'properties' => json_encode($props), 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.0, 48.0), 4326)'),
        ]);
    }

    public function test_closures_from_station_and_line_articles_and_osm(): void
    {
        $closed = fn (string $slug, string $name, string $status) => $this->station($slug, $name, 48.0, 2.0,
            ['status' => $status === 'gone' ? 'declassified' : $status, 'source_status' => $status])->id;
        $quittebeuf = $closed('quittebeuf', 'Quittebeuf', 'declassified');
        $tourgeville = $closed('tourgeville', 'Tourgéville', 'gone');
        $open = $closed('ouverte', 'Ouverte', 'active');
        $fenouillet = $closed('fenouillet', 'Fenouillet-St-Alban', 'declassified');

        $morsalines = $closed('morsalines', 'Morsalines', 'declassified');
        DB::table('station_events')->insert(['station_id' => $morsalines, 'type' => 'opening', 'event_date' => '1886-01-01', 'date_precision' => 'year',
            'source' => 'wikidata', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->sourceRow($morsalines, 'wikidata', 'Q3', ['frwiki' => 'Gare de Morsalines']);
        // Infobox erronée : fermeture antérieure à l'ouverture, écartée.
        $this->cachePage('Gare de Morsalines', "{{Infobox Gare\n | fermeture = {{date|8 février 1848}}\n}}\nLa '''gare de Morsalines''' est une gare fermée.");
        $this->sourceRow($quittebeuf, 'wikidata', 'Q1', ['frwiki' => 'Gare de Quittebeuf']);
        $this->sourceRow($open, 'wikidata', 'Q2', ['frwiki' => 'Gare ouverte']);
        $this->sourceRow($fenouillet, 'osm', 'n42', ['description' => 'Gare non desservie depuis 2016 mais toujours répertoriée par la SNCF.']);
        $this->cachePage('Gare de Quittebeuf', "{{Infobox Gare\n | fermeture = {{date|27 septembre 1969}} <small>(voyageurs)</small>"
            ."<br>{{date|30 novembre 1990}} <small>(marchandises)</small>\n}}\nLa '''gare de Quittebeuf''' est une gare fermée.");
        $this->cachePage('Gare ouverte', "{{Infobox Gare\n | fermeture = 1972\n}}\nLa '''gare''' est ouverte.");

        $line = $this->rfnLine('379000', 'Ligne de Mézidon à Trouville - Deauville', ['wikipedia_title' => 'Ligne de Mézidon à Trouville - Deauville']);
        foreach ([$tourgeville, $open] as $sid) {
            DB::table('station_lines')->insert(['station_id' => $sid, 'line_id' => $line->id, 'method' => 'sncf_pk', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->cachePage('Ligne de Mézidon à Trouville - Deauville', "== Histoire ==\nEn 1960, les haltes de Tourgéville et d'Ouverte "
            .'sont fermées.', 777);

        $this->artisan('vff:import-station-closures', ['--from-cache' => true])->assertSuccessful();

        $events = fn (int $sid) => DB::table('station_events')->where('station_id', $sid)->orderBy('event_date')
            ->get(['type', 'event_date', 'source', 'source_url'])->map(fn ($e) => "{$e->type} {$e->event_date} {$e->source}")->all();
        $this->assertSame(['closure_passenger 1969-09-27 wikipedia', 'closure_freight 1990-11-30 wikipedia'], $events($quittebeuf));
        $this->assertSame(['closure 1960-01-01 wikipedia'], $events($tourgeville));
        $this->assertSame(['closure_passenger 2016-01-01 osm:note'], $events($fenouillet));
        $this->assertSame(['opening 1886-01-01 wikidata'], $events($morsalines));
        // Gare en service aujourd'hui : aucune fermeture (elle la fermerait à tort).
        $this->assertSame([], $events($open));
        // Permalien de la révision lue (CC BY-SA), date retenue sur la fiche.
        $this->assertStringContainsString('oldid=777', (string) DB::table('station_events')->where('station_id', $tourgeville)->value('source_url'));
        $this->assertSame('1960-01-01', substr((string) DB::table('stations')->where('id', $tourgeville)->value('closed_at'), 0, 10));

        // Rejouable : mêmes événements.
        $before = $this->snapshot(['station_events'], ['id']);
        $this->artisan('vff:import-station-closures', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['station_events'], ['id']));
    }
}
