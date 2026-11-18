<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Commandes OpenStreetMap appuyées sur OsmImporter : vff:import-osm (lignes
 * numérotées RFN), vff:import-osm-stations, vff:backfill-names,
 * vff:enrich-tunnels. Overpass simulé, reprises sans attente (Sleep::fake).
 */
class OsmCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const OVERPASS = 'overpass-api.de/*';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
        Sleep::fake();
    }

    /** @param  array<string, string>  $tags  @param  list<array{0: float, 1: float}>  $points [lat, lon] */
    private static function way(int $id, array $tags, array $points): array
    {
        return ['type' => 'way', 'id' => $id, 'tags' => $tags, 'geometry' => array_map(fn ($p) => ['lat' => $p[0], 'lon' => $p[1]], $points)];
    }

    /** @return array{elements: list<array<string, mixed>>} */
    private function railways(): array
    {
        return ['elements' => [
            self::way(1, ['railway' => 'rail', 'ref' => '420 000', 'name' => 'Ligne de Paris-Montparnasse à Brest', 'maxspeed' => '160',
                'electrified' => 'contact_line', 'voltage' => '25000', 'operator' => 'SNCF Réseau', 'railway:traffic_mode' => 'mixed'],
                [[48.39, -4.48], [48.40, -4.40]]),
            self::way(2, ['railway' => 'construction', 'ref' => '420000'], [[48.40, -4.40], [48.41, -4.30]]),
            self::way(3, ['railway' => 'rail', 'ref' => 'D 812', 'name' => 'Faux ref (route)'], [[48.0, -3.0], [48.1, -3.0]]),
            self::way(4, ['railway' => 'narrow_gauge', 'name' => 'Chemin de fer touristique de la Vallée', 'operator' => 'Train touristique de la Vallée',
                'gauge' => '1000', 'maxspeed' => '9999', 'voltage' => '99999999'], [[47.0, -2.0], [47.1, -2.0]]),
            self::way(5, ['railway' => 'rail', 'ref' => '999 999'], [[47.5, -1.0]]),
            ['type' => 'node', 'id' => 7, 'lat' => 48.0, 'lon' => -2.0],
        ]];
    }

    public function test_numbered_ways_are_attached_to_their_line(): void
    {
        $brest = $this->rfnLine('420000', '420000');
        Http::fake([self::OVERPASS => Http::response($this->railways())]);

        $this->artisan('vff:import-osm', ['--region' => 'FR-BRE'])
            ->expectsOutputToContain('→ 6 éléments reçus.')
            ->expectsOutputToContain('✓ 2 tronçons créés.')
            ->assertSuccessful();

        $this->assertSame('Ligne de Paris-Montparnasse à Brest', $brest->fresh()->name, 'nom anonyme remplacé');
        $segments = DB::table('line_segments')->where('line_id', $brest->id)->orderBy('osm_way_id')->get();
        $this->assertSame([[1, 'in_service', true, 25000, 160, 1435, 'mixed', false], [2, 'construction', false, null, null, 1435, null, false]],
            $segments->map(fn ($s) => [(int) $s->osm_way_id, $s->status, (bool) $s->electrified, $s->voltage_v, $s->max_speed_kmh, $s->gauge_mm,
                $s->osm_traffic_mode, (bool) $s->is_heritage])->all());
        $this->assertSame(1, DB::table('lines')->count(), 'ni le faux ref « D 812 », ni le way sans ref, ni le way dégénéré');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r['data'], '["ISO3166-2"="FR-BRE"][admin_level=4]')
            && str_contains($r['data'], '["service"!~"."]'));

        // Ré-import : mêmes tronçons (clé osm_way_id), aucun doublon.
        $before = $this->snapshot(['lines', 'line_segments']);
        $this->artisan('vff:import-osm', ['--region' => 'FR-BRE'])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['lines', 'line_segments']));
    }

    public function test_unnumbered_ways_and_strict_mode(): void
    {
        Http::fake([self::OVERPASS => Http::response($this->railways())]);

        $this->artisan('vff:import-osm', ['--include-unref' => true])->expectsOutputToContain('✓ 3 tronçons créés.')->assertSuccessful();

        $heritage = DB::table('lines')->where('name', 'Chemin de fer touristique de la Vallée')->first();
        $this->assertSame('chemin-de-fer-touristique-de-la-vallee-4', $heritage->slug);
        $segment = DB::table('line_segments')->where('line_id', $heritage->id)->first();
        $this->assertSame([true, 1000, null, null, 'in_service'], [(bool) $segment->is_heritage, $segment->gauge_mm, $segment->max_speed_kmh,
            $segment->voltage_v, $segment->status], 'opérateur touristique, voie métrique, valeurs aberrantes écartées');
        $this->assertSame('rfn-420000', DB::table('lines')->where('code', '420 000')->value('slug'), 'ligne créée depuis le ref');

        // Mode strict : seulement les lignes déjà connues.
        DB::table('line_segments')->delete();
        DB::table('lines')->delete();
        $this->rfnLine('420000', 'Ligne de Paris-Montparnasse à Brest');
        $this->artisan('vff:import-osm', ['--strict-existing' => true, '--include-unref' => true])
            ->expectsOutputToContain('✓ 2 tronçons créés.')->assertSuccessful();
        $this->assertSame(1, DB::table('lines')->count());
    }

    public function test_overpass_failures_are_retried_then_reported(): void
    {
        Http::fake([self::OVERPASS => Http::sequence()->push('', 504)->push('', 429)->push($this->railways())]);
        $this->rfnLine('420000', '420000');

        $this->artisan('vff:import-osm')->expectsOutputToContain('✓ 2 tronçons créés.')->assertSuccessful();
        Sleep::assertSequence([Sleep::for(10)->seconds(), Sleep::for(20)->seconds()]);
    }

    public function test_client_errors_are_not_retried_and_unknown_eras_refused(): void
    {
        Http::fake([self::OVERPASS => Http::response('requête invalide', 400)]);

        $this->artisan('vff:import-osm')->expectsOutputToContain('Overpass a échoué après 1 tentative(s) : HTTP 400')->assertFailed();
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
        $this->artisan('vff:import-osm', ['--era' => 1855])->expectsOutputToContain('Ère introuvable')->assertFailed();
        Http::assertSentCount(1);
    }

    public function test_stations_are_imported_and_keep_their_canonical_slug(): void
    {
        Http::fake([self::OVERPASS => Http::response(['elements' => [
            ['type' => 'node', 'id' => 11, 'lat' => 48.388, 'lon' => -4.479, 'tags' => ['railway' => 'station', 'name' => 'Brest', 'addr:city' => 'Brest']],
            ['type' => 'node', 'id' => 12, 'lat' => 48.40, 'lon' => -4.40, 'tags' => ['railway' => 'halt', 'name' => 'Kerhuon']],
            ['type' => 'node', 'id' => 13, 'lat' => 48.41, 'lon' => -4.35, 'tags' => ['railway' => 'stop', 'name' => 'La Forest']],
            ['type' => 'node', 'id' => 14, 'lat' => 48.42, 'lon' => -4.30, 'tags' => ['railway' => 'service_station', 'name' => 'Embranchement X']],
            ['type' => 'way', 'id' => 15],
            ['type' => 'node', 'id' => 16, 'tags' => ['railway' => 'station', 'name' => 'Sans position']],
        ]])]);

        $this->artisan('vff:import-osm-stations', ['--region' => 'FR-BRE'])
            ->expectsOutputToContain('✓ 6 nodes reçus | 4 créées | 0 mises à jour')->assertSuccessful();

        $this->assertSame([[11, 'osm-11-brest', 'gare', 'Brest'], [12, 'osm-12-kerhuon', 'halte', null], [13, 'osm-13-la-forest', 'point_arret', null],
            [14, 'osm-14-embranchement-x', 'embranchement', null]],
            DB::table('stations')->orderBy('osm_node_id')->get()->map(fn ($s) => [(int) $s->osm_node_id, $s->slug, $s->type, $s->city])->all());

        $this->artisan('vff:canonicalize-station-slugs')->assertSuccessful();
        $this->assertSame('brest', DB::table('stations')->where('osm_node_id', 11)->value('slug'));

        $this->artisan('vff:import-osm-stations', ['--region' => 'FR-BRE'])
            ->expectsOutputToContain('0 créées | 4 mises à jour')->assertSuccessful();
        $this->assertSame('brest', DB::table('stations')->where('osm_node_id', 11)->value('slug'), 'le ré-import garde le slug canonique');
        $this->get('/stations/brest')->assertOk();
        $this->get('/stations/osm-11-brest')->assertRedirect('/stations/brest');
    }

    public function test_station_import_failure(): void
    {
        Http::fake([self::OVERPASS => Http::response('', 503)]);

        $this->artisan('vff:import-osm-stations')->expectsOutputToContain('Overpass returned HTTP 503')->assertFailed();
        $this->assertSame(0, DB::table('stations')->count());
    }

    public function test_backfill_names_only_replaces_anonymous_names(): void
    {
        $anonymous = $this->rfnLine('830000', '830000');
        $named = $this->rfnLine('420000', 'Ligne de Paris à Brest');
        Http::fake([self::OVERPASS => Http::response(['elements' => [
            ['type' => 'way', 'id' => 1, 'tags' => ['ref' => '830 000', 'name' => 'Ligne de Marseille à Vintimille']],
            ['type' => 'way', 'id' => 2, 'tags' => ['ref' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest']],
            ['type' => 'way', 'id' => 3, 'tags' => ['ref' => 'D 12', 'name' => 'Ligne fantôme']],
            ['type' => 'way', 'id' => 4, 'tags' => ['ref' => '830000', 'name' => 'Viaduc']],
        ]])]);

        $this->artisan('vff:backfill-names')->expectsOutputToContain('✓ 1 lignes renommées.')->assertSuccessful();

        $this->assertSame('Ligne de Marseille à Vintimille', $anonymous->fresh()->name);
        $this->assertSame('Ligne de Paris à Brest', $named->fresh()->name, 'un vrai nom n\'est jamais remplacé');
    }

    public function test_backfill_names_failure(): void
    {
        Http::fake([self::OVERPASS => Http::response('', 500)]);

        $this->artisan('vff:backfill-names')->expectsOutputToContain('Overpass returned HTTP 500')->assertFailed();
    }

    public function test_unnumbered_tunnels_are_attached_to_the_nearest_line(): void
    {
        $era = Era::where('year', 2030)->first();
        $line = $this->rfnLine('420000', 'Ligne de Paris à Brest');
        $this->track($line, $era, [[48.39, -4.48], [48.40, -4.40]]);
        Http::fake([self::OVERPASS => Http::response(['elements' => [
            self::way(21, ['railway' => 'rail', 'tunnel' => 'yes'], [[48.3955, -4.44], [48.3960, -4.43], [48.3965, -4.42]]),
            self::way(22, ['railway' => 'rail'], [[48.50, -4.44], [48.51, -4.43]]),
        ]])]);

        $this->artisan('vff:enrich-tunnels', ['--distance' => 200])
            ->expectsOutputToContain('✓ 2 ways reçus | 1 attachés | 1 skippés (trop loin)')->assertSuccessful();

        $this->assertSame([(int) $line->id, 'in_service'], [(int) DB::table('line_segments')->where('osm_way_id', 21)->value('line_id'),
            DB::table('line_segments')->where('osm_way_id', 21)->value('status')]);
        $this->assertFalse(DB::table('line_segments')->where('osm_way_id', 22)->exists());

        $this->artisan('vff:enrich-tunnels', ['--era' => 1855])->expectsOutputToContain('Ère introuvable')->assertFailed();
    }

    // ─── vff:import-osm-greenways ────────────────────────────────────────

    /** @return array{elements: list<array<string, mixed>>} */
    private function greenways(): array
    {
        return ['elements' => [
            self::way(31, ['railway' => 'abandoned', 'name' => 'Ancienne ligne'], [[48.0, -2.0], [48.0, -1.9]]),
            self::way(32, ['highway' => 'cycleway', 'name' => 'Voie verte de la Vallée', 'start_date' => '2012-06-30'], [[48.0003, -2.0], [48.0003, -1.9]]),
            self::way(33, ['highway' => 'cycleway', 'start_date' => '2099'], [[48.5, -2.0], [48.5, -1.9]]),
            self::way(34, ['highway' => 'path', 'bicycle' => 'designated'], [[48.0004, -1.95], [48.0004, -1.92]]),
            self::way(35, ['highway' => 'cycleway'], [[48.0, -2.0]]),
            ['type' => 'relation', 'id' => 90, 'tags' => ['route' => 'bicycle', 'name' => 'V3', 'ref' => 'V3', 'network' => 'ncn', 'start_date' => '2013'],
                'members' => [['type' => 'way', 'ref' => 32, 'role' => ''], ['type' => 'node', 'ref' => 1]]],
        ]];
    }

    public function test_greenways_near_the_railway_are_kept_with_their_cycle_routes(): void
    {
        Http::fake([self::OVERPASS => Http::response($this->greenways())]);

        $this->artisan('vff:import-osm-greenways', ['--region' => ['fr-bre'], '--pause' => 0])
            ->expectsOutputToContain('→ FR-BRE')
            ->expectsOutputToContain('4 ways lus, 3 gardés (proches d\'une voie ferrée)')
            ->assertSuccessful();

        $rows = DB::table('present_osm_ways')->orderBy('id')->get()->keyBy('id');
        $this->assertEquals([31, 32, 34], $rows->keys()->all(), 'voies vertes le long du way ferroviaire du même lot gardées ; celle à 55 km écartée');
        $this->assertSame(['railway', 'greenway', 'greenway'], [$rows[31]->kind, $rows[32]->kind, $rows[34]->kind]);
        $this->assertSame([2012, 'FR-BRE', 'cycleway'], [$rows[32]->start_year, $rows[32]->region, $rows[32]->highway]);
        $this->assertEquals([['id' => 90, 'name' => 'V3', 'ref' => 'V3', 'network' => 'ncn', 'start_date' => '2013']], json_decode($rows[32]->routes, true));
        $this->assertNull($rows[34]->routes);
        $this->assertTrue(Storage::disk('local')->exists('sources/osm/greenways-FR-BRE-'.now()->format('Y-m-d').'.json'));
        Http::assertSent(fn (Request $r) => str_contains($r['data'], 'area["ISO3166-2"="FR-BRE"]') && str_contains($r['data'], 'rel(bw.w)'));

        // Copie du jour réutilisée ; un way disparu d'OSM est supprimé.
        DB::table('present_osm_ways')->insert(['id' => 99, 'region' => 'FR-BRE', 'kind' => 'railway', 'tags' => '{}', 'length_m' => 10,
            'seen_at' => now()->subDay(), 'geom' => DB::raw("ST_GeomFromText('LINESTRING(-2 48, -2.001 48)', 4326)")]);
        $this->artisan('vff:import-osm-greenways', ['--region' => ['FR-BRE'], '--pause' => 0])
            ->expectsOutputToContain('[copie locale]')
            ->expectsOutputToContain('1 ways absents de la dernière lecture supprimés.')
            ->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_greenways_rotate_servers_and_report_failed_regions(): void
    {
        Http::fake([
            'overpass-api.de/*' => Http::response('', 504),
            'maps.mail.ru/*' => Http::response($this->greenways()),
        ]);

        $this->artisan('vff:import-osm-greenways', ['--region' => ['FR-BRE'], '--pause' => 0])->assertSuccessful();
        Sleep::assertSequence([Sleep::for(30)->seconds()]);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'maps.mail.ru'));
    }

    public function test_greenways_failures_keep_existing_ways(): void
    {
        Http::fake([self::OVERPASS => Http::response('', 400)]);
        DB::table('present_osm_ways')->insert(['id' => 99, 'region' => 'FR-BRE', 'kind' => 'railway', 'tags' => '{}', 'length_m' => 10,
            'seen_at' => now()->subDay(), 'geom' => DB::raw("ST_GeomFromText('LINESTRING(-2 48, -2.001 48)', 4326)")]);

        $this->artisan('vff:import-osm-greenways', ['--region' => ['FR-BRE'], '--pause' => 0, '--endpoint' => ['https://overpass-api.de/api/interpreter']])
            ->expectsOutputToContain('FR-BRE : requête refusée : HTTP 400 sur overpass-api.de')
            ->expectsOutputToContain('Régions en échec (relancer : --region=FR-BRE)')
            ->assertFailed();
        $this->artisan('vff:import-osm-greenways', ['--region' => ['FR-NOR'], '--from-cache' => true])
            ->expectsOutputToContain('FR-NOR : aucune copie locale')
            ->assertFailed();

        $this->assertTrue(DB::table('present_osm_ways')->where('id', 99)->exists(), 'région en échec : rien de supprimé');
        Http::assertSentCount(1);
    }
}
