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
 * vff:import-station-sources : gares de chaque source, telles que la source les
 * décrit (SNCF, OSM, OHM…), dans station_sources, sans fusion. Réponses
 * rejouées depuis le cache (--from-cache) ou simulées ; une source lue en
 * partie ne supprime rien.
 */
class StationSourcesCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        Http::fake();
    }

    /** @param  array<int, array<string, mixed>>|array<string, mixed>  $data */
    private function cache(string $rel, array $data): void
    {
        $path = storage_path('app/sources/stations/'.$rel);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, array<string, mixed>> */
    private function sources(string $source): array
    {
        return DB::table('station_sources')->where('source', $source)->orderBy('source_ref')->get()
            ->mapWithKeys(fn ($r) => [$r->source_ref => (array) $r])->all();
    }

    public function test_sncf_stations_are_grouped_by_uic_code(): void
    {
        DB::table('sncf_gares')->insert([
            ['code_uic' => '87474007', 'code_ligne' => '420000', 'libelle' => 'BREST', 'commune' => 'Brest', 'department' => 'FINISTERE',
                'pk_m' => 622000, 'rg_troncon' => 1, 'voyageurs' => true, 'fret' => true, 'lon' => -4.479, 'lat' => 48.388],
            ['code_uic' => '87474007', 'code_ligne' => '470000', 'libelle' => 'BREST', 'commune' => 'Brest', 'department' => 'FINISTERE',
                'pk_m' => 0, 'rg_troncon' => 1, 'voyageurs' => false, 'fret' => true, 'lon' => -4.481, 'lat' => 48.388],
            ['code_uic' => '87999999', 'code_ligne' => '470000', 'libelle' => "L'ISLE-ADAM", 'commune' => "L'Isle-Adam", 'department' => 'VAL-D-OISE',
                'pk_m' => 5000, 'rg_troncon' => 1, 'voyageurs' => false, 'fret' => false, 'lon' => 2.22, 'lat' => 49.11],
        ]);
        $this->cache('sncf/gares-de-voyageurs.json', [
            ['nom' => 'Arcachon', 'codes_uic' => '87581538', 'position_geographique' => ['lon' => -1.165, 'lat' => 44.659], 'segment_drg' => 'b'],
            ['nom' => 'Sans position', 'codes_uic' => '87000001'],
        ]);

        $this->artisan('vff:import-station-sources', ['--source' => ['sncf'], '--from-cache' => true])
            ->expectsOutputToContain('✓ sncf : 2 objets enregistrés')
            ->expectsOutputToContain('✓ sncf_voyageurs : 1 objets enregistrés')
            ->assertSuccessful();

        $sncf = $this->sources('sncf');
        $this->assertSame(['Brest', 'active', true, true, '87474007', 'brest'], [$sncf['87474007']['name'], $sncf['87474007']['lifecycle'],
            (bool) $sncf['87474007']['passenger'], (bool) $sncf['87474007']['freight'], $sncf['87474007']['uic_code'], $sncf['87474007']['name_norm']]);
        $props = json_decode($sncf['87474007']['properties'], true);
        $this->assertSame(['420000', '470000'], array_column($props['lines'], 'code_ligne'));
        $this->assertEqualsWithDelta(74, $props['position_spread_m'], 2, 'deux positions à ~150 m : 74 m du point moyen');
        $this->assertSame(["L'Isle-Adam", 'disused'], [$sncf['87999999']['name'], $sncf['87999999']['lifecycle']], 'ni voyageurs ni fret');
        $this->assertSame(['Arcachon'], array_column($this->sources('sncf_voyageurs'), 'name'));
    }

    /** @return array{elements: list<array<string, mixed>>} */
    private function osmElements(): array
    {
        $n = fn (int $id, array $tags, float $lat = 48.0, float $lon = -2.0) => ['type' => 'node', 'id' => $id, 'lat' => $lat, 'lon' => $lon, 'tags' => $tags];

        return ['elements' => [
            $n(1, ['railway' => 'station', 'name' => 'Rennes', 'uic_ref' => '8747100;8747101', 'wikidata' => 'Q123', 'start_date' => '1857-04-28']),
            $n(2, ['disused:railway' => 'halt', 'name' => 'Halte du Moulin', 'end_date' => '1938-05']),
            $n(3, ['historic' => 'railway_station', 'old_name' => 'Gare de Mauron']),
            ['type' => 'way', 'id' => 4, 'center' => ['lat' => 48.1, 'lon' => -2.1], 'tags' => ['building' => 'train_station', 'name' => 'Ancienne gare']],
            $n(5, ['railway' => 'station', 'station' => 'subway', 'name' => 'Métro République']),
            $n(6, ['railway' => 'halt', 'tram' => 'yes', 'name' => 'Arrêt de tram']),
            $n(7, ['railway' => 'station']),
            $n(8, ['railway' => 'station', 'name' => 'Gare désaffectée', 'disused' => 'yes']),
        ]];
    }

    public function test_osm_stations_keep_their_lifecycle_dates_and_identifiers(): void
    {
        $this->cache('osm/FR-BRE.json', $this->osmElements());

        $this->artisan('vff:import-station-sources', ['--source' => ['osm'], '--region' => ['fr-bre'], '--from-cache' => true])
            ->expectsOutputToContain('FR-BRE : 8 objets, 5 retenus')
            ->assertSuccessful();

        $osm = $this->sources('osm');
        $this->assertSame(['n1', 'n2', 'n3', 'n8', 'w4'], array_keys($osm), 'métro, tram et objet sans nom écartés');
        $this->assertSame(['Rennes', 'gare', 'active', '8747100', 'Q123', '1857-04-28', 'day'],
            [$osm['n1']['name'], $osm['n1']['kind'], $osm['n1']['lifecycle'], $osm['n1']['uic_code'], $osm['n1']['wikidata_id'],
                $osm['n1']['opened_on'], $osm['n1']['opened_precision']]);
        $this->assertSame(['halte', 'disused', '1938-05-01', 'month', 'all'],
            [$osm['n2']['kind'], $osm['n2']['lifecycle'], $osm['n2']['closed_on'], $osm['n2']['closed_precision'], $osm['n2']['closed_scope']]);
        $this->assertSame(['Gare de Mauron', 'historic'], [$osm['n3']['name'], $osm['n3']['lifecycle']]);
        $this->assertSame('unknown', $osm['w4']['lifecycle'], 'bâtiment de gare : état inconnu');
        $this->assertSame('disused', $osm['n8']['lifecycle'], 'disused=yes sur une gare « active »');

        // Rejouable à l'identique.
        $before = $this->snapshot(['station_sources'], ['id', 'seen_at']);
        $this->artisan('vff:import-station-sources', ['--source' => ['osm'], '--region' => ['FR-BRE'], '--from-cache' => true])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['station_sources'], ['id', 'seen_at']));
    }

    public function test_osm_import_of_some_regions_never_deletes_the_others(): void
    {
        DB::statement("INSERT INTO station_sources (source, source_ref, name, lifecycle, seen_at, created_at, updated_at, geom)
            VALUES ('osm', 'n999', 'Gare de Normandie', 'active', now() - interval '1 day', now(), now(), ST_SetSRID(ST_MakePoint(0.1, 49.4), 4326))");
        $this->cache('osm/FR-BRE.json', $this->osmElements());

        $this->artisan('vff:import-station-sources', ['--source' => ['osm'], '--region' => ['FR-BRE'], '--from-cache' => true])->assertSuccessful();

        $this->assertTrue(DB::table('station_sources')->where('source_ref', 'n999')->exists());
    }

    public function test_ohm_tiles_missing_do_not_delete_known_stations(): void
    {
        DB::statement("INSERT INTO station_sources (source, source_ref, name, lifecycle, seen_at, created_at, updated_at, geom)
            VALUES ('ohm', 'n500', 'Gare du sud-est', 'historic', now() - interval '1 day', now(), now(), ST_SetSRID(ST_MakePoint(5.0, 44.0), 4326))");
        $this->cache('ohm/nw.json', ['elements' => [
            ['type' => 'node', 'id' => 600, 'lat' => 48.0, 'lon' => -2.0, 'tags' => ['railway' => 'station', 'name' => 'Gare datée', 'start_date' => '1880', 'end_date' => '1950']],
            ['type' => 'node', 'id' => 601, 'lat' => 48.1, 'lon' => -2.1, 'tags' => ['railway' => 'station', 'name' => 'Gare sans fin']],
        ]]);

        $this->artisan('vff:import-station-sources', ['--source' => ['ohm'], '--from-cache' => true])
            ->expectsOutputToContain('absent du cache : ohm/ne.json')
            ->expectsOutputToContain('tuile ne : échec')
            ->assertSuccessful();

        $ohm = $this->sources('ohm');
        $this->assertArrayHasKey('n500', $ohm, 'tuile sud-est non lue : sa gare reste');
        $this->assertSame(['historic', '1880-01-01', '1950-01-01'], [$ohm['n600']['lifecycle'], $ohm['n600']['opened_on'], $ohm['n600']['closed_on']]);
        $this->assertSame('unknown', $ohm['n601']['lifecycle'], 'OHM sans end_date : existence non confirmée');
    }

    public function test_overpass_failures_are_retried_without_waiting_and_skip_the_region(): void
    {
        Http::fake(['overpass-api.de/*' => Http::response('', 504)]);

        $this->artisan('vff:import-station-sources', ['--source' => ['osm'], '--region' => ['FR-BRE']])
            ->expectsOutputToContain('FR-BRE : échec, région ignorée')
            ->assertSuccessful();

        Http::assertSentCount(7);
        Sleep::assertSleptTimes(6);
        $this->assertFalse(File::exists(storage_path('app/sources/stations/osm/FR-BRE.json')), 'aucune réponse en erreur mise en cache');
    }

    public function test_unknown_source_fails(): void
    {
        $this->artisan('vff:import-station-sources', ['--source' => ['minitel']])
            ->expectsOutputToContain('Source inconnue : minitel')
            ->assertFailed();
    }
}
