<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Services\PlaceNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Importeurs de sources géographiques brutes (source_traces, communes) :
 * carte archeoferroviaire (GeoJSONX, tracés seulement), IGN BD TOPO (WFS),
 * OpenStreetMap (Overpass), communes (geo.api.gouv.fr), desserte GTFS.
 * Réponses simulées, copies brutes dans le bac à sable, nouvels essais sans
 * attente réelle (Sleep::fake).
 */
class SourceImportCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
        Sleep::fake();
    }

    /** @return array<string, int> source_ref => longueur (m), pour une source */
    private function traces(string $source): array
    {
        return DB::table('source_traces')->where('source', $source)->orderBy('source_ref')->pluck('length_m', 'source_ref')->all();
    }

    // ─── vff:import-archeo-map (GeoJSONX « Ma carte ») ──────────────────

    private const RADIX = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/ !#$%&\'()*-.:<=>?@[]^_`{|}~';

    /** Nombre entier → base 91 zigzag (inverse de GeoJsonXDecoder::decodeNumber, decimals = 0). */
    private function gjxNumber(int $n): string
    {
        $z = $n >= 0 ? 2 * $n : -2 * $n - 1;
        $out = '';
        do {
            $out = self::RADIX[$z % 91].$out;
            $z = intdiv($z, 91);
        } while ($z > 0);

        return $out;
    }

    /** Points [lat, lng] → suite « x,y;dx,dy… » en Web Mercator (m). */
    private function gjxLine(array $points): string
    {
        $out = [];
        [$px, $py] = [0, 0];
        foreach ($points as [$lat, $lng]) {
            $x = (int) round($lng * M_PI / 180 * 6378137.0);
            $y = (int) round(log(tan(M_PI / 4 + $lat * M_PI / 360)) * 6378137.0);
            $out[] = $this->gjxNumber($x - $px).','.$this->gjxNumber($y - $py);
            [$px, $py] = [$x, $y];
        }

        return implode(';', $out);
    }

    /** @param  list<array<int, mixed>>  $principal */
    private function maCarte(array $principal): string
    {
        $path = $this->sandbox.'/macarte-test.json';
        File::put($path, json_encode(['layers' => [
            ['name' => 'Cartes IGN', 'type' => 'Geoportail'],
            ['name' => 'Réseau principal', 'type' => 'Vector', 'data' => ['decimals' => 0, 'hashProperties' => ['name', 'description', 'desc'], 'features' => $principal]],
            ['name' => 'Réseaux secondaires', 'type' => 'Vector', 'data' => ['decimals' => 0, 'hashProperties' => ['name'], 'features' => [
                [[4, [$this->gjxLine([[47.0, 3.0], [47.05, 3.0]]), $this->gjxLine([[47.05, 3.0], [47.1, 3.0]])]], [0, 'Tramway de Charlie']],
            ]]],
            ['name' => 'Lignes exploitées', 'type' => 'Vector', 'data' => ['decimals' => 0, 'features' => [[[1, $this->gjxLine([[46.0, 4.0], [46.1, 4.0]])]]]]],
            ['name' => 'Calque inconnu', 'type' => 'Vector', 'data' => ['decimals' => 0, 'features' => [[[1, $this->gjxLine([[45.0, 5.0], [45.1, 5.0]])]]]]],
        ]]));

        return $path;
    }

    public function test_archeo_map_imports_traces_only_and_is_replayable(): void
    {
        $file = $this->maCarte([
            [[1, $this->gjxLine([[48.0, 2.0], [48.05, 2.0], [48.1, 2.0]])], [0, 'Ligne de Alpha à Bravo', 1, 'Texte de la fiche (droits réservés)', 2, 'Autre texte']],
            ['C,C'],                                     // point : ignoré
            [[1, $this->gjxNumber(10).','.$this->gjxNumber(10)]], // « ligne » d'un seul point : ignorée
        ]);

        $this->artisan('vff:import-archeo-map', ['--file' => $file])
            ->expectsOutputToContain('Géométries dégénérées ignorées : 2')
            ->expectsOutputToContain('✓ 3 tracés archeoferroviaire en base')
            ->assertSuccessful();

        $rows = DB::table('source_traces')->where('source', 'archeoferroviaire')->orderBy('layer')->get(['layer', 'name', 'properties', 'length_m']);
        $this->assertSame(['exploitee', 'principal', 'secondaire'], $rows->pluck('layer')->all());
        $principal = $rows[1];
        $this->assertSame('Ligne de Alpha à Bravo', $principal->name);
        // Tracés seulement : les textes des fiches ne sont jamais stockés.
        $this->assertSame(['name' => 'Ligne de Alpha à Bravo'], json_decode($principal->properties, true));
        $this->assertEqualsWithDelta(11_120, $principal->length_m, 30);
        $this->assertEqualsWithDelta(11_120, $rows[2]->length_m, 30); // MultiLineString de 2 parties
        $this->assertSame(['{}'], [$rows[0]->properties]);
        // Aucun texte, date ni photo importé d'archeoferroviaire : ni événement, ni média, ni ligne.
        $this->assertSame([0, 0, 0], [DB::table('line_events')->count(), DB::table('media')->count(), DB::table('lines')->count()]);

        // Rejouable : mêmes tracés, mêmes identifiants (empreinte calque + nom + géométrie).
        $snapshot = $this->snapshot(['source_traces'], except: ['seen_at']);
        $this->artisan('vff:import-archeo-map', ['--file' => $file])->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['source_traces'], except: ['seen_at']));
    }

    public function test_archeo_map_reports_then_prunes_traces_no_longer_served(): void
    {
        $old = DB::table('source_traces')->insertGetId([
            'source' => 'archeoferroviaire', 'source_ref' => 'ancien', 'layer' => 'principal', 'name' => 'Tracé retiré',
            'seen_at' => now()->subDay(), 'geom' => DB::raw("ST_Multi(ST_GeomFromText('LINESTRING(1 47, 1 47.1)', 4326))"),
        ]);
        $file = $this->maCarte([[[1, $this->gjxLine([[48.0, 2.0], [48.1, 2.0]])], [0, 'Ligne de Alpha à Bravo']]]);

        $this->artisan('vff:import-archeo-map', ['--file' => $file])
            ->expectsOutputToContain('1 tracés absents de la carte (relancer avec --prune pour les supprimer).')->assertSuccessful();
        $this->assertTrue(DB::table('source_traces')->where('id', $old)->exists());

        $this->artisan('vff:import-archeo-map', ['--file' => $file, '--prune' => true])
            ->expectsOutputToContain('1 tracés absents de la carte supprimés.')->assertSuccessful();
        $this->assertFalse(DB::table('source_traces')->where('id', $old)->exists());
    }

    public function test_archeo_map_download_keeps_a_raw_copy(): void
    {
        $body = File::get($this->maCarte([[[1, $this->gjxLine([[48.0, 2.0], [48.1, 2.0]])], [0, 'Ligne de Alpha à Bravo']]]));
        Http::fake(['macarte.ign.fr/*' => Http::response($body)]);

        $this->artisan('vff:import-archeo-map')->expectsOutputToContain('✓ 3 tracés archeoferroviaire en base')->assertSuccessful();

        $this->assertSame($body, Storage::disk('local')->get('sources/archeoferroviaire/macarte-'.now()->format('Y-m-d').'.json'));
        $this->assertStringStartsWith($this->sandbox, Storage::disk('local')->path('sources'));
    }

    // ─── vff:import-communes ────────────────────────────────────────────

    public function test_communes_are_upserted_from_the_official_gazetteer(): void
    {
        Http::fake([
            'geo.api.gouv.fr/communes?*' => Http::response([
                ['nom' => 'Saint-Étienne', 'code' => '42218', 'codeDepartement' => '42', 'population' => 170_049, 'centre' => ['type' => 'Point', 'coordinates' => [4.3872, 45.4397]]],
                ['nom' => 'Sans centre', 'code' => '99999'],
            ]),
            'geo.api.gouv.fr/communes_associees_deleguees*' => Http::response([
                ['nom' => 'Terrenoire', 'code' => '42330', 'type' => 'commune-associee', 'chefLieu' => '42218', 'codeDepartement' => '42', 'centre' => ['type' => 'Point', 'coordinates' => [4.43, 45.43]]],
            ]),
        ]);

        $this->artisan('vff:import-communes')->expectsOutputToContain('✓ 2 communes en base (2 actuelles, 1 associées/déléguées).')->assertSuccessful();
        $this->artisan('vff:import-communes')->assertSuccessful();

        $rows = DB::table('communes')->orderBy('code_insee')->get(['code_insee', 'type', 'nom', 'nom_norm', 'departement', 'population', 'chef_lieu']);
        $this->assertCount(2, $rows);
        $this->assertSame(['42218', 'commune-actuelle', 'Saint-Étienne', PlaceNames::normalize('Saint-Étienne'), '42', 170_049, null], array_values((array) $rows[0]));
        $this->assertSame(['42330', 'commune-associee', 'Terrenoire'], array_slice(array_values((array) $rows[1]), 0, 3));
        $this->assertSame('42218', $rows[1]->chef_lieu);
        $this->assertTrue(Storage::disk('local')->exists('sources/geo-api/communes-'.now()->format('Y-m-d').'.json'));
    }

    public function test_communes_import_stops_on_an_http_error_without_writing(): void
    {
        Http::fake(['geo.api.gouv.fr/*' => Http::response('Service Unavailable', 503)]);

        try {
            $this->artisan('vff:import-communes')->run();
            $this->fail('Une erreur HTTP doit interrompre l\'import.');
        } catch (RequestException $e) {
            $this->assertSame(503, $e->response->status());
        }
        $this->assertSame(0, DB::table('communes')->count());
    }

    // ─── vff:import-gtfs-voyageurs ──────────────────────────────────────

    private function gtfs(): string
    {
        $dir = $this->sandbox.'/gtfs';
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/routes.txt", "\xEF\xBB\xBFroute_id,route_short_name,route_type\nR-TER,TER,2\nR-CAR,Car TER,3\n");
        File::put("{$dir}/trips.txt", "route_id,service_id,trip_id\nR-TER,S1,T1\nR-CAR,S1,T2\n");
        File::put("{$dir}/stop_times.txt", "trip_id,arrival_time,departure_time,stop_id,stop_sequence\nT1,08:00:00,08:00:00,StopPoint:OCETrain TER-87000001,1\nT1,08:20:00,08:20:00,StopPoint:OCETrain TER-87000752,2\nT2,09:00:00,09:00:00,StopPoint:OCECar TER-87000300,1\n");
        File::put("{$dir}/stops.txt", "stop_id,stop_name,stop_lat,stop_lon\nStopPoint:OCETrain TER-87000001,Alpha,48.0,2.0\nStopPoint:OCETrain TER-87000752,Lgv,47.0,4.0\nStopPoint:OCECar TER-87000300,Charlie,46.0,3.0\n");

        return $dir;
    }

    public function test_gtfs_rail_service_proves_passengers_without_ever_downgrading(): void
    {
        $era = Era::where('is_current', true)->first();
        $classic = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $freight = $this->track($classic, $era, [[48.0, 2.0], [48.1, 2.0]], ['traffic_type' => 'freight']);
        $closed = $this->track($classic, $era, [[48.1, 2.0], [48.2, 2.0]], ['status' => 'abandoned', 'traffic_type' => 'unknown']);
        $lgv = $this->rfnLine('752000', 'LGV Sud-Est');
        $lgvTrack = $this->track($lgv, $era, [[47.0, 4.0], [47.1, 4.0]], ['traffic_type' => 'unknown']);
        $busOnly = $this->rfnLine('300000', 'Ligne de Charlie à Delta');
        $bus = $this->track($busOnly, $era, [[46.0, 3.0], [46.1, 3.0]], ['traffic_type' => 'freight']);
        DB::table('sncf_gares')->insert([
            ['code_uic' => '87000001', 'code_ligne' => '100000', 'voyageurs' => true],
            ['code_uic' => '87000752', 'code_ligne' => '752000', 'voyageurs' => true],
            ['code_uic' => '87000300', 'code_ligne' => '300000', 'voyageurs' => true],
        ]);

        $this->artisan('vff:import-gtfs-voyageurs', ['--dir' => $this->gtfs()])
            ->expectsOutputToContain('routes rail (type=2) : 1 · bus exclus (type=3) : 1')
            ->expectsOutputToContain('UIC voyageurs (rail) : 2')
            ->assertSuccessful();

        $traffic = fn ($s) => DB::table('line_segments')->where('id', $s->id)->value('traffic_type');
        $this->assertSame('mixed', $traffic($freight));
        $this->assertSame('unknown', $traffic($closed));     // tronçon fermé : jamais typé
        $this->assertSame('passenger', $traffic($lgvTrack)); // LGV reconnue par son nom (is_lgv encore false)
        $this->assertSame('freight', $traffic($bus));        // car de substitution : pas une preuve ferroviaire
        $this->assertEqualsCanonicalizing([$classic->id, $lgv->id], DB::table('line_sources')->where('source_type', 'gtfs-voyageurs')->pluck('line_id')->all());

        // fetched_at = date de la relecture : elle change à chaque import, pas le contenu.
        $snapshot = $this->snapshot(['line_segments', 'line_sources'], ['fetched_at']);
        $this->travel(2)->seconds(); // la relecture tombe dans une autre seconde (le test était instable sans ça)
        $this->artisan('vff:import-gtfs-voyageurs', ['--dir' => $this->gtfs()])->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['line_segments', 'line_sources'], ['fetched_at']));
    }

    public function test_gtfs_import_requires_the_four_files(): void
    {
        $dir = $this->gtfs();
        File::delete("{$dir}/stop_times.txt");

        $this->artisan('vff:import-gtfs-voyageurs', ['--dir' => $dir])
            ->expectsOutputToContain("Fichier GTFS manquant : {$dir}/stop_times.txt")->assertFailed();
    }

    // ─── vff:import-bdtopo-rail (WFS Géoplateforme) ─────────────────────

    /** @return array<string, mixed> */
    private function wfsFeature(string $cleabs, string $nature, string $toponyme, float $lng): array
    {
        return ['type' => 'Feature', 'properties' => ['cleabs' => $cleabs, 'nature' => $nature, 'cpx_toponyme' => $toponyme, 'etat_de_l_objet' => 'En service', 'nombre_de_voies' => 'Double'],
            'geometry' => ['type' => 'LineString', 'coordinates' => [[$lng, 48.0, -1000.0], [$lng, 48.1, -1000.0]]]];
    }

    private function fakeWfs(int $failures = 0): void
    {
        $calls = 0;
        Http::fake(function (Request $request) use (&$calls, $failures) {
            if (($request['RESULTTYPE'] ?? null) === 'hits') {
                return Http::response('<wfs:FeatureCollection numberMatched="7" numberReturned="0"/>');
            }
            if ($calls++ < $failures) {
                return Http::response('Bad Gateway', 502);
            }

            return Http::response((int) $request['STARTINDEX'] === 0
                ? ['numberMatched' => 3, 'features' => [$this->wfsFeature('TRONFERR0001', 'Voie ferrée principale', 'Ligne de Alpha à Bravo', 2.0), $this->wfsFeature('TRONFERR0002', 'Voie de service', '', 2.1)]]
                : ['numberMatched' => 3, 'features' => [$this->wfsFeature('TRONFERR0003', 'LGV', 'LGV Sud-Est', 2.2), ['properties' => ['nature' => 'Voie ferrée principale'], 'geometry' => null]]]);
        });
    }

    public function test_bdtopo_import_pages_through_the_wfs_and_drops_z(): void
    {
        $this->fakeWfs();

        $this->artisan('vff:import-bdtopo-rail', ['--page-size' => 2])
            ->expectsOutputToContain('→ 3 tronçons BD TOPO à importer (hors métro/tramway).')
            ->expectsOutputToContain('Exclus (transport urbain, non importés) : Métro : 7, Tramway : 7')
            ->assertSuccessful();

        $rows = DB::table('source_traces')->where('source', 'bdtopo')->orderBy('source_ref')->get(['source_ref', 'layer', 'name']);
        $this->assertSame([['TRONFERR0001', 'Voie ferrée principale', 'Ligne de Alpha à Bravo'], ['TRONFERR0002', 'Voie de service', null], ['TRONFERR0003', 'LGV', 'LGV Sud-Est']],
            $rows->map(fn ($r) => [$r->source_ref, $r->layer, $r->name])->all());
        $this->assertSame(2, (int) DB::selectOne("SELECT MAX(ST_NDims(geom)) d FROM source_traces WHERE source = 'bdtopo'")->d);
        $this->assertSame('Double', DB::table('source_traces')->where('source_ref', 'TRONFERR0001')->value(DB::raw("properties->>'nombre_de_voies'")));
        Http::assertSent(fn (Request $r) => ($r['TYPENAMES'] ?? null) === 'BDTOPO_V3:troncon_de_voie_ferree' && (int) ($r['STARTINDEX'] ?? -1) === 2
            && str_contains((string) $r['CQL_FILTER'], "nature NOT IN ('Métro','Tramway')"));

        $snapshot = $this->snapshot(['source_traces'], except: ['seen_at']);
        $this->artisan('vff:import-bdtopo-rail', ['--page-size' => 2])->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['source_traces'], except: ['seen_at']));
    }

    public function test_bdtopo_retries_a_busy_wfs_with_backoff(): void
    {
        $this->fakeWfs(failures: 2);

        $this->artisan('vff:import-bdtopo-rail', ['--page-size' => 2])
            ->expectsOutputToContain('WFS indisponible (STARTINDEX=0, tentative 1) : nouvel essai dans 5s')
            ->expectsOutputToContain('WFS indisponible (STARTINDEX=0, tentative 2) : nouvel essai dans 10s')
            ->assertSuccessful();

        Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(10)->seconds()]);
        $this->assertCount(3, $this->traces('bdtopo'));
    }

    public function test_bdtopo_prune_only_after_a_complete_import_and_bbox_validation(): void
    {
        $this->fakeWfs();
        DB::table('source_traces')->insert(['source' => 'bdtopo', 'source_ref' => 'TRONFERR9999', 'layer' => 'Voie ferrée principale', 'seen_at' => now()->subWeek(),
            'geom' => DB::raw("ST_Multi(ST_GeomFromText('LINESTRING(1 47, 1 47.1)', 4326))")]);

        $this->artisan('vff:import-bdtopo-rail', ['--page-size' => 2, '--prune' => true, '--bbox' => '1.9,47.9,2.3,48.2'])
            ->expectsOutputToContain('--prune ignoré : import partiel')->assertSuccessful();
        $this->assertArrayHasKey('TRONFERR9999', $this->traces('bdtopo'));
        Http::assertSent(fn (Request $r) => str_contains((string) ($r['CQL_FILTER'] ?? ''), "BBOX(geometrie,1.900000,47.900000,2.300000,48.200000,'urn:ogc:def:crs:OGC:1.3:CRS84')"));

        $this->artisan('vff:import-bdtopo-rail', ['--page-size' => 2, '--prune' => true])
            ->expectsOutputToContain('1 tronçons bdtopo absents du WFS supprimés.')->assertSuccessful();
        $this->assertArrayNotHasKey('TRONFERR9999', $this->traces('bdtopo'));

        $this->artisan('vff:import-bdtopo-rail', ['--bbox' => '1.9,47.9,abc'])
            ->expectsOutputToContain('--bbox attend lon_min,lat_min,lon_max,lat_max')->assertFailed();
    }

    // ─── vff:import-osm-railways (Overpass) ─────────────────────────────

    /** @return array<string, mixed> */
    private function overpass(): array
    {
        $geom = fn (float $lng, int $n = 2) => array_map(fn ($i) => ['lat' => 48.0 + $i / 10, 'lon' => $lng], range(0, $n - 1));

        return ['version' => 0.6, 'elements' => [
            ['type' => 'way', 'id' => 11, 'tags' => ['railway' => 'rail', 'name' => 'Ligne de Alpha à Bravo', 'usage' => 'main'], 'geometry' => $geom(2.0)],
            ['type' => 'way', 'id' => 12, 'tags' => ['abandoned:railway' => 'rail', 'old_name' => 'Tacot de Charlie'], 'geometry' => $geom(2.1)],
            ['type' => 'way', 'id' => 13, 'tags' => ['railway' => 'platform'], 'geometry' => $geom(2.2)],
            ['type' => 'way', 'id' => 14, 'tags' => ['railway' => 'rail'], 'geometry' => $geom(2.3, 1)],
            ['type' => 'node', 'id' => 15, 'lat' => 48.0, 'lon' => 2.0],
        ]];
    }

    public function test_osm_railways_keep_tracks_and_lifecycle_ways_and_reuse_the_daily_copy(): void
    {
        Http::fake(['overpass-api.de/*' => Http::response($this->overpass())]);

        $this->artisan('vff:import-osm-railways', ['--region' => ['fr-bre']])
            ->expectsOutputToContain('2 ways importés, 2 ignorés (pas une voie ou < 2 nœuds)')
            ->assertSuccessful();

        $rows = DB::table('source_traces')->where('source', 'osm')->orderBy('source_ref')->get(['source_ref', 'layer', 'name']);
        $this->assertSame([['11', 'rail', 'Ligne de Alpha à Bravo'], ['12', 'abandoned:railway', 'Tacot de Charlie']], $rows->map(fn ($r) => [$r->source_ref, $r->layer, $r->name])->all());
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains((string) $r['data'], 'area["ISO3166-2"="FR-BRE"]'));

        // Même jour : la copie locale sert, aucune nouvelle requête.
        $this->artisan('vff:import-osm-railways', ['--region' => ['FR-BRE']])->expectsOutputToContain('[copie locale du jour]')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertTrue(Storage::disk('local')->exists('sources/osm/railways-FR-BRE-'.now()->format('Y-m-d').'.json'));
    }

    public function test_osm_railways_reject_truncated_answers_and_report_failed_regions(): void
    {
        $truncated = json_encode(['elements' => [], 'remark' => 'runtime error: Query timed out in "query" at line 3 after 601 seconds.']);
        Http::fake([
            'overpass-a.example/*' => Http::response($truncated),
            'overpass-b.example/*' => Http::response('rate limited', 429),
        ]);

        $this->artisan('vff:import-osm-railways', ['--region' => ['FR-NOR'], '--attempts' => 3, '--endpoint' => ['https://overpass-a.example/api/interpreter', 'https://overpass-b.example/api/interpreter']])
            ->expectsOutputToContain('tentative 1 échouée (runtime error: Query timed out')
            ->expectsOutputToContain('tentative 2 échouée (HTTP 429 sur overpass-b.example) : nouvel essai dans 60s')
            ->expectsOutputToContain('Overpass a échoué après 3 tentatives')
            ->expectsOutputToContain('Régions en échec (relancer : --region=FR-NOR)')
            ->assertFailed();

        Sleep::assertSequence([Sleep::for(30)->seconds(), Sleep::for(60)->seconds()]);
        $this->assertSame(0, DB::table('source_traces')->count());
        $this->assertFalse(Storage::disk('local')->exists('sources/osm/railways-FR-NOR-'.now()->format('Y-m-d').'.json'));
    }

    public function test_osm_railways_refused_query_is_not_retried(): void
    {
        Http::fake(['*' => Http::response('bad query', 400)]);

        $this->artisan('vff:import-osm-railways', ['--region' => ['FR-PDL'], '--attempts' => 5])
            ->expectsOutputToContain('requête refusée : HTTP 400')->assertFailed();
        Http::assertSentCount(1);
    }
}
