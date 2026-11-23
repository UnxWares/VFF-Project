<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-station-names : noms successifs lus dans les articles Wikipédia en cache (jamais de réseau avec
 * --from-cache) et dans Wikidata, écrits dans alt_names sans toucher aux entrées des autres sources.
 */
class ImportStationNamesTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::fake(['*' => Http::response([], 500)]);
    }

    private function linked(string $slug, string $name, string $article, string $text, array $attributes = []): int
    {
        $id = $this->station($slug, $name, 48.0, 2.0, $attributes)->id;
        DB::table('station_sources')->insert([
            'source' => 'wikidata', 'source_ref' => 'Q'.abs(crc32($slug)), 'station_id' => $id, 'name' => $name, 'lifecycle' => 'unknown',
            'properties' => json_encode(['frwiki' => $article]), 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.0, 48.0), 4326)'),
        ]);
        $dir = storage_path('app/sources/stations/wikipedia');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.sha1($article).'.json', json_encode(['title' => $article, 'revid' => 77, 'content' => $text, 'requested' => $article]));

        return $id;
    }

    private function alt(int $id): array
    {
        return json_decode((string) DB::table('stations')->where('id', $id)->value('alt_names'), true) ?? [];
    }

    public function test_a_rename_gives_dated_names_with_a_permalink_and_keeps_other_sources(): void
    {
        $id = $this->linked('masse', 'Masséna', 'Gare du boulevard Masséna', "Elle est d'abord baptisée « Orléans-Ceinture » avant d'être renommée « Masséna » en 1970.",
            ['opened_at' => '1867-01-01', 'alt_names' => [['name' => 'Masséna RER', 'source' => 'osm']]]);

        $this->artisan('vff:import-station-names', ['--from-cache' => true])->assertSuccessful();

        $alt = $this->alt($id);
        $this->assertSame('osm', $alt[0]['source']);
        $this->assertSame(['Orléans-Ceinture', 1867, 1970, 'wikipedia_history'], [$alt[1]['name'], $alt[1]['from'], $alt[1]['to'], $alt[1]['source']]);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Gare_du_boulevard_Masséna&oldid=77', urldecode($alt[1]['source_url']));
    }

    public function test_a_second_run_changes_nothing_and_dry_run_writes_nothing(): void
    {
        $id = $this->linked('zola', 'Zola-Gare', 'Gare de Zola', 'En 1950, la gare est renommée Zola en cohérence avec la station.');
        $this->artisan('vff:import-station-names', ['--from-cache' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertSame([], $this->alt($id));

        $this->artisan('vff:import-station-names', ['--from-cache' => true])->assertSuccessful();
        $first = DB::table('stations')->where('id', $id)->value('alt_names');
        $this->artisan('vff:import-station-names', ['--from-cache' => true])->assertSuccessful();

        $this->assertSame($first, DB::table('stations')->where('id', $id)->value('alt_names'));
        $this->assertSame([['Zola-Gare', 1950, true]], array_map(fn ($a) => [$a['name'], $a['from'], $a['renamed']], $this->alt($id)));
    }

    public function test_the_name_of_a_shop_in_a_closed_station_gives_way_to_the_railway_name(): void
    {
        $id = $this->station('boulevard-ornano', 'La Recyclerie', 48.9, 2.3, ['status' => 'declassified', 'provenance' => ['name' => 'osm']])->id;

        $this->artisan('vff:import-station-names', ['--from-cache' => true])->assertSuccessful();

        $row = DB::table('stations')->where('id', $id)->first();
        $this->assertSame('Boulevard Ornano', $row->name);
        $this->assertSame('wikidata', json_decode($row->provenance, true)['name']);
        $this->assertSame(['La Recyclerie', 'osm_place'], [$this->alt($id)[0]['name'], $this->alt($id)[0]['source']]);

        $this->artisan('vff:import-station-names', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame('Boulevard Ornano', DB::table('stations')->where('id', $id)->value('name'));
        $this->assertCount(1, $this->alt($id));
    }
}
