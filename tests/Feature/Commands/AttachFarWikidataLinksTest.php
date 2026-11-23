<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * « Ligne desservie » (P81) de Wikidata : une gare à plus de 2 km de tous les tracés de la ligne n'y est pas rattachée
 * (Paris-Tolbiac à 105 km de la 590000, Derval à 31 km de la 464000), une gare proche l'est.
 */
class AttachFarWikidataLinksTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private function wikidataStation(string $slug, string $qid, float $lat, float $lng): void
    {
        $station = $this->station($slug, ucfirst($slug), $lat, $lng, ['wikidata_id' => $qid]);
        DB::table('station_sources')->insert([
            'source' => 'wikidata', 'source_ref' => $qid, 'name' => ucfirst($slug), 'name_norm' => $slug, 'station_id' => $station->id,
            'kind' => 'gare', 'lifecycle' => 'active', 'properties' => json_encode(['lines' => ['Q9']]),
            'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat)),
        ]);
    }

    public function test_a_wikidata_link_to_a_line_far_from_the_station_is_dropped(): void
    {
        $this->sandboxStorage();
        $era = Era::factory()->create(['year' => 2030, 'label' => 'Années 2030', 'is_current' => true]);
        $line = $this->rfnLine('590000', 'Ligne de Alpha à Bravo');
        $this->track($line, $era, [[48.00, 2.0], [48.30, 2.0]]);
        DB::table('wikidata_lines')->insert(['qid' => 'Q9', 'category' => 'line', 'line_id' => $line->id, 'fetched_at' => now()]);
        $this->wikidataStation('proche', 'Q1', 48.10, 2.01);
        $this->wikidataStation('tolbiac', 'Q2', 49.00, 3.50);

        $this->artisan('vff:attach-stations')->expectsOutputToContain('wikidata_p81 retirés')->assertSuccessful();

        $this->assertSame(['proche'], DB::table('station_lines as sl')->join('stations as s', 's.id', '=', 'sl.station_id')
            ->where('sl.line_id', $line->id)->pluck('s.slug')->all());
    }
}
