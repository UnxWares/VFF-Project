<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-osm-railways --geojsonseq : import par fichiers GeoJSON
 * séquentiels (osmium export), sans Overpass ; ways retenus / ignorés,
 * fichier illisible refusé avant toute écriture.
 */
class OsmRailwaysFilesTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::fake();
    }

    private function feature(array $props, string $type, array $coords): string
    {
        return "\x1e".json_encode(['type' => 'Feature', 'properties' => $props, 'geometry' => ['type' => $type, 'coordinates' => $coords]]);
    }

    public function test_geojsonseq_files_are_imported_without_overpass(): void
    {
        $file = $this->sandbox.'/rail.geojsonseq';
        File::put($file, implode("\n", [
            $this->feature(['@type' => 'way', '@id' => 101, 'railway' => 'rail', 'name' => 'Ligne de Alpha à Bravo', 'service' => 'siding'], 'LineString', [[2.0, 48.0], [2.0, 48.1]]),
            '',
            $this->feature(['@id' => 102, 'disused:railway' => 'rail', 'old_name' => 'Tacot'], 'LineString', [[2.1, 48.0], [2.1, 48.1]]),
            $this->feature(['@type' => 'node', '@id' => 103, 'railway' => 'station'], 'Point', [2.0, 48.0]),
            $this->feature(['@type' => 'way', '@id' => 104, 'railway' => 'platform'], 'LineString', [[2.2, 48.0], [2.2, 48.1]]),
            $this->feature(['@type' => 'way', '@id' => 105, 'railway' => 'rail'], 'LineString', [[2.3, 48.0]]),
            $this->feature(['@type' => 'way', 'railway' => 'rail'], 'LineString', [[2.4, 48.0], [2.4, 48.1]]),
            $this->feature(['@type' => 'way', '@id' => 107, 'railway' => 'rail'], 'Polygon', [[[2.5, 48.0], [2.5, 48.1], [2.6, 48.1], [2.5, 48.0]]]),
            '   ',
        ]));
        DB::insert("INSERT INTO source_traces (source, source_ref, layer, length_m, seen_at, geom)
            VALUES ('osm', '999', 'rail', 10, '2020-01-01', ST_Multi(ST_GeomFromText('LINESTRING(1 46, 1.1 46)', 4326)))");

        $this->artisan('vff:import-osm-railways', ['--geojsonseq' => [$file], '--prune' => true])
            ->expectsOutputToContain('→ rail.geojsonseq : 2 ways importés, 5 ignorés')
            ->expectsOutputToContain('Import par fichiers : --prune non applicable.')
            ->assertSuccessful();

        $rows = DB::table('source_traces')->where('source', 'osm')->orderBy('source_ref')->get(['source_ref', 'layer', 'name', 'length_m'])->all();
        $this->assertSame([['101', 'rail', 'Ligne de Alpha à Bravo'], ['102', 'disused:railway', 'Tacot'], ['999', 'rail', null]],
            array_map(fn ($r) => [$r->source_ref, $r->layer, $r->name], $rows), 'pas de purge en import par fichiers');
        $this->assertEqualsWithDelta(11120, $rows[0]->length_m, 30);
        $this->assertSame('siding', json_decode(DB::table('source_traces')->where('source_ref', '101')->value('properties'), true)['service']);
        Http::assertNothingSent();
    }

    public function test_unreadable_file_is_refused_before_any_import(): void
    {
        $ok = $this->sandbox.'/ok.geojsonseq';
        File::put($ok, $this->feature(['@id' => 1, 'railway' => 'rail'], 'LineString', [[2.0, 48.0], [2.0, 48.1]]));

        $this->artisan('vff:import-osm-railways', ['--geojsonseq' => [$ok, $this->sandbox.'/absent.geojsonseq']])
            ->expectsOutputToContain('Fichier illisible : '.$this->sandbox.'/absent.geojsonseq')
            ->assertFailed();

        $this->assertSame(0, DB::table('source_traces')->count());
    }
}
