<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-sncf-vmax : provenance de la vitesse (max_speed_source) et dernier recours OSM
 * (tag maxspeed) pour les tronçons sans vitesse SNCF (code absent du jeu : 995000 Corse,
 * 552000 ligne de Sceaux, 657000 Lamothe - Arcachon… ; 2026-10-20).
 */
class SncfVmaxOsmFallbackTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Era $current;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era(2020);
        $this->current = $this->era(2030, current: true);
        $this->sandboxStorage();
        $this->cacheDir = $this->sandbox.'/app/sources/sncf-vmax';
        Http::preventStrayRequests();
        @mkdir($this->cacheDir, 0775, true);
        file_put_contents("{$this->cacheDir}/vitesse-maximale-nominale-sur-ligne.json", json_encode(['records' => [
            ['code_ligne' => '655000', 'v_max' => '160', 'geo_shape' => ['geometry' => ['type' => 'LineString', 'coordinates' => [[4.00, 47.00], [4.00, 47.10]]]]],
        ]]));
    }

    private function osm(float $lng, string $maxspeed, string $layer = 'rail', float $lat0 = 47.00, float $lat1 = 47.10): void
    {
        DB::table('source_traces')->insert([
            'source' => 'osm', 'source_ref' => 'way/'.random_int(1, 1_000_000), 'layer' => $layer,
            'properties' => json_encode(['maxspeed' => $maxspeed]), 'seen_at' => now(),
            'geom' => DB::raw(sprintf("ST_Multi(ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326))", $lng, $lat0, $lng, $lat1)),
        ]);
    }

    private function runVmax(): void
    {
        $this->artisan('vff:import-sncf-vmax', ['--cache-only' => true, '--cache-dir' => $this->cacheDir])->assertSuccessful();
    }

    private function speed(object $seg): array
    {
        $r = DB::table('line_segments')->where('id', $seg->id)->first(['max_speed_kmh', 'max_speed_source']);

        return [$r->max_speed_kmh, $r->max_speed_source];
    }

    public function test_sncf_speed_is_tagged_and_never_replaced_by_osm(): void
    {
        $seg = $this->track($this->rfnLine('655000', 'Ligne de Bordeaux à Irun'), $this->current, [[47.00, 4.0], [47.10, 4.0]], ['max_speed_kmh' => null]);
        $this->osm(4.0, '100');

        $this->runVmax();

        $this->assertSame([160, 'sncf'], $this->speed($seg));
    }

    public function test_line_absent_from_the_sncf_dataset_takes_the_osm_maxspeed_of_its_track(): void
    {
        $seg = $this->track($this->rfnLine('657000', 'Ligne de Lamothe à Arcachon'), $this->current, [[47.00, 5.0], [47.10, 5.0]], ['max_speed_kmh' => null]);
        $this->osm(5.0, '140', lat1: 47.08);
        $this->osm(5.0, '60', lat0: 47.08);
        // Voie désaffectée et tag non numérique : ignorés.
        $this->osm(5.0, '200', 'disused');
        $this->osm(5.00001, '100 mph');

        $this->runVmax();
        $this->assertSame([140, 'osm'], $this->speed($seg), 'valeur qui recouvre le plus de longueur');

        // Rejouable : une vitesse OSM est reprise, sans doublon ni dérive.
        $this->runVmax();
        $this->assertSame([140, 'osm'], $this->speed($seg));
    }

    public function test_osm_needs_half_of_the_segment_and_never_makes_a_classic_line_high_speed(): void
    {
        $short = $this->track($this->rfnLine('354000', 'Ligne de Motteville à Montérolier-Buchy'), $this->current, [[47.00, 6.0], [47.10, 6.0]], ['max_speed_kmh' => null]);
        $this->osm(6.0, '120', lat1: 47.03);
        // Voie LGV voisine à 300 km/h sur une ligne classique : jamais reprise.
        $classic = $this->track($this->rfnLine('262000', 'Ligne de Douai à Blanc-Misseron'), $this->current, [[47.00, 7.0], [47.10, 7.0]], ['max_speed_kmh' => null]);
        $this->osm(7.0, '300');
        $lgv = $this->track($this->rfnLine('999000', 'LGV Test'), $this->current, [[47.00, 8.0], [47.10, 8.0]], ['max_speed_kmh' => null]);
        $this->osm(8.0, '300');

        $this->runVmax();

        $this->assertSame([null, null], $this->speed($short), '3 km de maxspeed sur 11 km : pas assez');
        $this->assertSame([null, null], $this->speed($classic));
        $this->assertSame([300, 'osm'], $this->speed($lgv));
    }

    public function test_osm_fallback_follows_the_past_era_exception(): void
    {
        $line = $this->rfnLine('657000', 'Ligne de Lamothe à Arcachon');
        $past = $this->track($line, Era::where('year', 2020)->first(), [[47.00, 5.0], [47.10, 5.0]], ['max_speed_kmh' => null]);
        $this->osm(5.0, '140');
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'electrification', 'event_date' => '2023-01-01',
            'description' => 'Électrification', 'source' => 'manual', 'section_resolution' => 'whole_line']);

        $this->artisan('vff:import-sncf-vmax', ['--cache-only' => true, '--previous' => true, '--cache-dir' => $this->cacheDir])->assertSuccessful();

        $this->assertSame([null, null], $this->speed($past), 'électrifiée en 2023 : pas de vitesse 2020 tirée de l\'état actuel');
    }
}
