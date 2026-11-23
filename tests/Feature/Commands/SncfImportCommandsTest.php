<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Services\LevelCrossings\LevelCrossingTiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Importeurs SNCF Réseau Open Data (ressources.data.sncf.com, data.sncf.com),
 * sur des réponses simulées (tests/Fixtures/Sncf) : valeurs écrites, PK SNCF
 * (« 017+250 », « 001-250 »), rejouabilité, et surtout : une source en panne
 * ou vide ne vide JAMAIS le référentiel déjà importé.
 */
class SncfImportCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const API = 'ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/';

    private Era $current;

    /** @var array<string, array{0: mixed, 1: int}> jeu de données SNCF => [corps, statut HTTP] */
    private array $datasets = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->current = $this->era(2030, current: true);
    }

    /**
     * Réponses par jeu de données, modifiables d'un appel à l'autre (Http::fake
     * cumule les règles : la première enregistrée l'emporterait toujours).
     */
    private function serve(string $dataset, mixed $body, int $status = 200): void
    {
        if ($this->datasets === []) {
            Http::fake(function (Request $request) {
                foreach ($this->datasets as $name => [$body, $status]) {
                    if (str_contains($request->url(), "/datasets/{$name}/")) {
                        return Http::response($body, $status);
                    }
                }

                return Http::response('jeu de données non simulé', 404);
            });
        }
        $this->datasets[$dataset] = [$body, $status];
    }

    private function sncfGeometry(string $code, string $libelle, string $wkt): void
    {
        DB::insert('INSERT INTO sncf_line_geometries (code_ligne, libelle, geom) VALUES (?, ?, ST_GeomFromText(?, 4326))', [$code, $libelle, $wkt]);
    }

    // ─── vff:import-sncf-line-geometries ────────────────────────────────

    public function test_line_geometries_import_keeps_linestrings_and_parses_pk(): void
    {
        Http::fake([self::API.'formes-des-lignes-du-rfn/*' => Http::response($this->jsonFixture('Sncf/formes-des-lignes-du-rfn.json'))]);
        $this->sncfGeometry('555000', 'Exploitée', 'LINESTRING(5 45, 5 45.1)'); // ancien référentiel : remplacé

        $this->artisan('vff:import-sncf-line-geometries')
            ->expectsOutputToContain('2 tronçons insérés, 2 ignorés (sans code ou non LineString), 1 doublons idgaia')
            ->expectsOutputToContain('Total: 2 géométries, 1 codes_ligne distincts')
            ->assertSuccessful();

        $rows = DB::table('sncf_line_geometries')->orderBy('idgaia')->get(['code_ligne', 'libelle', 'mnemo', 'pk_debut_m', 'pk_fin_m', 'rg_troncon', 'idgaia']);
        $this->assertSame([
            ['100000', 'Exploitée', 'EXPLOI', 0, 17_250, 1, 'gaia-1'],
            // « 021-100 » : 21 km MOINS 100 m (convention SNCF partagée par les autres importeurs).
            ['100000', 'Fermée et déposée (Plus utilisable)', 'DEPOSE', 17_250, 20_900, 2, 'gaia-2'],
        ], $rows->map(fn ($r) => array_values((array) $r))->all());
        $this->assertSame(3, (int) DB::selectOne('SELECT ST_NPoints(geom) n FROM sncf_line_geometries WHERE idgaia = ?', ['gaia-1'])->n);

        // Rejouable : même contenu.
        $snapshot = $this->snapshot(['sncf_line_geometries'], except: ['id']);
        $this->artisan('vff:import-sncf-line-geometries')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['sncf_line_geometries'], except: ['id']));
    }

    public function test_line_geometries_import_never_empties_the_reference_on_failure(): void
    {
        $this->sncfGeometry('555000', 'Exploitée', 'LINESTRING(5 45, 5 45.1)');
        $before = $this->snapshot(['sncf_line_geometries']);

        Http::fake([self::API.'*' => Http::response('Bad Gateway', 502)]);
        $this->artisan('vff:import-sncf-line-geometries')->expectsOutputToContain('SNCF HTTP 502 (offset 0) : référentiel conservé.')->assertFailed();
        $this->assertSame($before, $this->snapshot(['sncf_line_geometries']));
    }

    public function test_line_geometries_import_refuses_an_empty_answer(): void
    {
        $this->sncfGeometry('555000', 'Exploitée', 'LINESTRING(5 45, 5 45.1)');
        Http::fake([self::API.'*' => Http::response(['total_count' => 0, 'results' => []])]);

        $this->artisan('vff:import-sncf-line-geometries')->expectsOutputToContain('sans aucune géométrie : référentiel conservé.')->assertFailed();
        $this->assertSame(1, DB::table('sncf_line_geometries')->count());
    }

    public function test_line_geometries_import_follows_the_pagination(): void
    {
        $page = fn (int $from, int $n) => ['results' => array_map(fn ($i) => [
            'code_ligne' => '100000', 'libelle' => 'Exploitée', 'idgaia' => "g-{$i}",
            'geo_shape' => ['geometry' => ['type' => 'LineString', 'coordinates' => [[2.0, 48.0 + $i / 1000], [2.0, 48.0005 + $i / 1000]]]],
        ], range($from, $from + $n - 1))];
        Http::fake([self::API.'formes-des-lignes-du-rfn/*' => Http::sequence()->push($page(0, 100))->push($page(100, 7))]);

        $this->artisan('vff:import-sncf-line-geometries')->expectsOutputToContain('107 tronçons insérés')->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => (int) $r['offset'] === 100 && (int) $r['limit'] === 100);
    }

    // ─── vff:import-sncf-electrification ────────────────────────────────

    /** @return array<string, mixed> */
    private function electrificationGeojson(int $padding): array
    {
        $features = [
            ['properties' => ['code_ligne' => '100000', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '017+250', 'elect' => '25000 volts alternatif', 'idgaia' => 'e-1'],
                'geometry' => ['type' => 'LineString', 'coordinates' => [[2.30, 48.80, 35.0], [2.40, 48.90, 41.0]]]],
            ['properties' => ['code_ligne' => '200000', 'pkd' => '001-250', 'pkf' => '009+000', 'elect' => '1500 volts continu'],
                'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[3.0, 47.0], [3.1, 47.1]]]]],
            ['properties' => ['code_ligne' => '300000', 'elect' => ''], 'geometry' => ['type' => 'LineString', 'coordinates' => [[4.0, 45.0], [4.1, 45.1]]]],
        ];
        for ($i = 0; $i < $padding; $i++) {
            $features[] = ['properties' => ['code_ligne' => (string) (400000 + $i), 'elect' => '15000 volts 16 2/3 Hz', 'pkd' => '000+000', 'pkf' => '001+000'],
                'geometry' => ['type' => 'LineString', 'coordinates' => [[5.0, 44.0 + $i / 100], [5.01, 44.0 + $i / 100]]]];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    public function test_electrification_import_stores_voltage_frequency_and_pk(): void
    {
        Http::fake([self::API.'liste-des-lignes-electrifiees/*' => Http::response($this->electrificationGeojson(98))]);

        $this->artisan('vff:import-sncf-electrification')->expectsOutputToContain('✓ 100 tronçons électrifiés importés.')->assertSuccessful();

        $ac = DB::table('sncf_line_electrification')->where('code_ligne', '100000')->first();
        $this->assertSame([25_000, '50.00', false, 0, 17_250, 'e-1'], [$ac->voltage_v, $ac->frequency_hz, $ac->third_rail, $ac->pkd_m, $ac->pkf_m, $ac->idgaia]);
        $dc = DB::table('sncf_line_electrification')->where('code_ligne', '200000')->first();
        $this->assertSame([1_500, 750, 9_000], [$dc->voltage_v, $dc->pkd_m, $dc->pkf_m]);
        // Géométrie 2D, toujours MultiLineString.
        $this->assertSame(['MULTILINESTRING', 2], array_values((array) DB::selectOne("SELECT GeometryType(geom) t, ST_NDims(geom) d FROM sncf_line_electrification WHERE code_ligne = '100000'")));
        $this->assertSame(0, DB::table('sncf_line_electrification')->where('code_ligne', '300000')->count());

        $this->artisan('vff:import-sncf-electrification')->assertSuccessful();
        $this->assertSame(100, DB::table('sncf_line_electrification')->count());
    }

    public function test_electrification_import_rejects_a_suspicious_answer_and_keeps_the_table(): void
    {
        Http::fake([self::API.'liste-des-lignes-electrifiees/*' => Http::sequence()
            ->push($this->electrificationGeojson(98))
            ->push($this->electrificationGeojson(10))
            ->push('down', 503)]);
        $this->artisan('vff:import-sncf-electrification')->assertSuccessful();

        $this->artisan('vff:import-sncf-electrification')->expectsOutputToContain('Réponse SNCF suspecte (13 tronçons) : import annulé.')->assertFailed();
        $this->artisan('vff:import-sncf-electrification')->expectsOutputToContain('SNCF HTTP 503')->assertFailed();
        $this->assertSame(100, DB::table('sncf_line_electrification')->count());
    }

    // ─── vff:import-sncf-level-crossings ────────────────────────────────

    public function test_level_crossings_are_upserted_once_per_installation(): void
    {
        Http::fake([self::API.'liste-des-passages-a-niveau/*' => Http::response($this->jsonFixture('Sncf/liste-des-passages-a-niveau.json'))]);

        $this->artisan('vff:import-sncf-level-crossings')
            ->expectsOutputToContain('✓ 2 PN (2 avec PK) sur 1 lignes · 3 ignorés')
            ->assertSuccessful();

        $pn = DB::table('sncf_level_crossings')->orderBy('id_if')->get(['id_if', 'libelle', 'obstacle', 'pk_m', 'commune', 'lat', 'lon']);
        $this->assertSame(['PN-1', 'PN 12', 'Route départementale 7', 5_300, 'Saint-Cloud', 48.83, 2.33], array_values((array) $pn[0]));
        $this->assertSame(['PN-2', 'PN 13', null, -150, 'Paris', 48.79, 2.29], array_values((array) $pn[1]));
        $this->assertTrue((bool) DB::selectOne("SELECT ST_Equals(geom, ST_SetSRID(ST_MakePoint(2.33, 48.83), 4326)) ok FROM sncf_level_crossings WHERE id_if = 'PN-1'")->ok);

        $snapshot = DB::table('sncf_level_crossings')->orderBy('id_if')->get(['id_if', 'libelle', 'pk_m'])->toArray();
        $this->artisan('vff:import-sncf-level-crossings')->assertSuccessful();
        $this->assertEquals($snapshot, DB::table('sncf_level_crossings')->orderBy('id_if')->get(['id_if', 'libelle', 'pk_m'])->toArray());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'limit=-1') && str_contains($r->url(), 'select=id_if%2Clibelle'));
    }

    public function test_level_crossings_import_invalidates_the_cached_map_tiles(): void
    {
        $tiles = app(LevelCrossingTiles::class);
        $n = 2 ** 11;
        $x = (int) floor((2.33 + 180) / 360 * $n);
        $y = (int) floor((1 - log(tan(deg2rad(48.83)) + 1 / cos(deg2rad(48.83))) / M_PI) / 2 * $n);
        $this->assertSame('', $tiles->tile(11, $x, $y), 'tuile vide, mise en cache');

        Http::fake([self::API.'liste-des-passages-a-niveau/*' => Http::response($this->jsonFixture('Sncf/liste-des-passages-a-niveau.json'))]);
        $this->artisan('vff:import-sncf-level-crossings')->assertSuccessful();

        $this->assertStringContainsString('PN-1', $tiles->tile(11, $x, $y));
    }

    public function test_level_crossings_import_fails_on_http_error(): void
    {
        Http::fake([self::API.'*' => Http::response('', 500)]);

        $this->artisan('vff:import-sncf-level-crossings')->expectsOutputToContain('SNCF HTTP 500')->assertFailed();
        $this->assertSame(0, DB::table('sncf_level_crossings')->count());
    }

    // ─── vff:import-sncf-refs ───────────────────────────────────────────

    private function fakeRefs(int $garesStatus = 200): void
    {
        $this->serve('liste-des-gares', $garesStatus === 200 ? $this->jsonFixture('Sncf/liste-des-gares.json') : 'down', $garesStatus);
        $this->serve('lignes-par-type', $this->jsonFixture('Sncf/lignes-par-type.json'));
        $this->serve('lignes-lgv-et-par-ecartement', $this->jsonFixture('Sncf/lignes-lgv-et-par-ecartement.json'));
    }

    public function test_refs_import_the_three_reference_datasets(): void
    {
        $this->fakeRefs();

        $this->artisan('vff:import-sncf-refs')
            ->expectsOutputToContain('✓ 3 gares · 1 lignes fret-only · 1 lignes voyageurs')
            ->expectsOutputToContain('Rac: 1 codes')
            ->expectsOutputToContain('✓ 2 tronçons · 1 codes LGV')
            ->assertSuccessful();

        $gares = DB::table('sncf_gares')->orderBy('code_uic')->get(['code_uic', 'code_ligne', 'fret', 'voyageurs', 'libelle', 'pk_m', 'department', 'lat', 'lon']);
        $this->assertSame(['87000001', '100000', true, true, 'Alpha', 0, 'Paris', 48.8, 2.3], array_values((array) $gares[0]));
        $this->assertSame(['87000002', '100000', false, true, 'Bravo', 17_250, 'Yvelines', 48.9, 2.4], array_values((array) $gares[1]));
        $this->assertSame(['87000003', '200000', true, false, 'Charlie-Fret', 2_800], array_slice(array_values((array) $gares[2]), 0, 6));
        $this->assertSame([['100000', 'Ligne'], ['990000', 'Rac']], DB::table('sncf_line_types')->orderBy('code_ligne')->get(['code_ligne', 'type_ligne'])->map(fn ($r) => [$r->code_ligne, $r->type_ligne])->all());
        $this->assertSame(409_000, DB::table('sncf_line_catlig')->where('code_ligne', '752000')->value('pkf_m'));

        // Rejouable : remplace sans doublon.
        $snapshot = $this->snapshot(['sncf_gares'], except: ['id']);
        $this->artisan('vff:import-sncf-refs', ['--only' => 'gares'])->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['sncf_gares'], except: ['id']));
    }

    public function test_refs_import_keeps_a_table_whose_source_is_down(): void
    {
        $this->fakeRefs();
        $this->artisan('vff:import-sncf-refs')->assertSuccessful();

        $this->fakeRefs(garesStatus: 504);
        $this->artisan('vff:import-sncf-refs')
            ->expectsOutputToContain('SNCF liste-des-gares HTTP 504 : table conservée.')
            ->expectsOutputToContain('Rac: 1 codes')
            ->assertFailed();

        $this->assertSame(3, DB::table('sncf_gares')->count());
        $this->assertSame(2, DB::table('sncf_line_types')->count());
    }

    public function test_refs_import_rejects_an_empty_dataset_and_an_unknown_only(): void
    {
        $this->fakeRefs();
        $this->artisan('vff:import-sncf-refs')->assertSuccessful();
        $this->serve('lignes-lgv-et-par-ecartement', []);

        $this->artisan('vff:import-sncf-refs', ['--only' => 'catlig'])
            ->expectsOutputToContain('aucun enregistrement exploitable pour sncf_line_catlig : table conservée.')
            ->assertFailed();
        $this->assertSame(2, DB::table('sncf_line_catlig')->count());

        $this->artisan('vff:import-sncf-refs', ['--only' => 'stations'])->expectsOutputToContain('--only inconnu : stations')->assertFailed();
    }

    // ─── vff:import-sncf-vmax ───────────────────────────────────────────

    public function test_vmax_takes_the_nearest_sncf_speed_of_the_same_line(): void
    {
        Http::fake([self::API.'vitesse-maximale-nominale-sur-ligne/*' => Http::response($this->jsonFixture('Sncf/vitesse-maximale-nominale-sur-ligne.json'))]);
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $near = $this->track($line, $this->current, [[48.80, 2.30], [48.90, 2.30]]);
        $far = $this->track($line, $this->current, [[48.80, 2.50], [48.90, 2.50]]);

        $this->artisan('vff:import-sncf-vmax')->expectsOutputToContain('✓ 1 segments mis à jour')->assertSuccessful();

        // 140 km/h (à 15 m, même code) plutôt que 60 (à 740 m) ou 300 (code 200000, plus proche).
        $this->assertSame(140, DB::table('line_segments')->where('id', $near->id)->value('max_speed_kmh'));
        $this->assertSame(120, DB::table('line_segments')->where('id', $far->id)->value('max_speed_kmh'));
    }

    public function test_vmax_fails_without_touching_speeds_when_sncf_is_down(): void
    {
        Http::fake([self::API.'*' => Http::response('', 503)]);
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($line, $this->current, [[48.80, 2.30], [48.90, 2.30]]);

        $this->artisan('vff:import-sncf-vmax')->expectsOutputToContain('SNCF HTTP 503 : aucune vitesse modifiée.')->assertFailed();
        $this->assertSame(120, DB::table('line_segments')->value('max_speed_kmh'));
    }

    // ─── vff:import-sncf-station-status ─────────────────────────────────

    public function test_station_status_matches_names_without_accents_and_skips_ambiguous_homonyms(): void
    {
        Http::fake([self::API.'frequentation-gares/*' => Http::response($this->jsonFixture('Sncf/frequentation-gares.json'))]);
        $stEtienne = $this->station('saint-etienne-chateaucreux', 'Saint-Etienne-Chateaucreux', 45.44, 4.40, ['department_code' => '42']);
        $montreuil = $this->station('montreuil', 'Montreuil', 50.46, 1.76, ['department_code' => '62']);
        $germainYvelines = $this->station('saint-germain-78', 'Saint-Germain', 48.89, 2.09, ['department_code' => '78']);
        $germainAilleurs = $this->station('saint-germain-71', 'Saint-Germain', 46.70, 4.90, ['department_code' => '71']);
        $neuillyA = $this->station('neuilly-a', 'Neuilly', 48.88, 2.27, ['department_code' => '92']);
        $neuillyB = $this->station('neuilly-b', 'Neuilly', 48.90, 2.50, ['department_code' => '93']);

        $this->artisan('vff:import-sncf-station-status')
            ->expectsOutputToContain('✓ 2 actives, 1 no_commercial, 1 non trouvées, 1 ambiguës')
            ->assertSuccessful();

        $statuses = DB::table('station_eras')->where('era_id', $this->current->id)->pluck('status', 'station_id')->all();
        $this->assertSame('active', $statuses[$stEtienne->id]);
        $this->assertSame('no_commercial', $statuses[$montreuil->id]);
        // Homonymes : celui du département du code postal (78), jamais l'autre.
        $this->assertSame('active', $statuses[$germainYvelines->id]);
        $this->assertArrayNotHasKey($germainAilleurs->id, $statuses);
        // Sans code postal : ambigu, aucun des deux.
        $this->assertArrayNotHasKey($neuillyA->id, $statuses);
        $this->assertArrayNotHasKey($neuillyB->id, $statuses);
        $this->assertSame('Source: SNCF frequentation-gares 2024 (3200000 voyageurs)', DB::table('station_eras')->where('station_id', $stEtienne->id)->value('note'));

        $this->artisan('vff:import-sncf-station-status')->assertSuccessful();
        $this->assertSame(3, DB::table('station_eras')->count());
    }

    public function test_station_status_fails_when_sncf_is_down(): void
    {
        Http::fake([self::API.'*' => Http::response('', 500)]);
        $this->station('montreuil', 'Montreuil', 50.46, 1.76);

        $this->artisan('vff:import-sncf-station-status')->expectsOutputToContain('SNCF HTTP 500 (offset 0) : import interrompu.')->assertFailed();
        $this->assertSame(0, DB::table('station_eras')->count());
    }

    // ─── vff:import-sncf-signalling ─────────────────────────────────────

    private function fakeSignalling(int $kvbStatus = 200): void
    {
        $this->serve('mode-de-cantonnement-des-lignes', $this->jsonFixture('Sncf/mode-de-cantonnement-des-lignes.records.json'));
        $this->serve('lignes-equipees-de-kvb', $kvbStatus === 200 ? $this->jsonFixture('Sncf/lignes-equipees-de-kvb.json') : '', $kvbStatus);
    }

    public function test_signalling_import_maps_block_systems_and_adds_kvb_outside_high_speed(): void
    {
        $this->fakeSignalling();
        $classic = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $lgv = $this->rfnLine('752000', 'LGV Sud-Est');
        DB::table('sncf_line_catlig')->insert(['code_ligne' => '752000', 'catlig' => 'Ligne à grande vitesse']);

        $this->artisan('vff:import-sncf-signalling')
            ->expectsOutputToContain('✓ 4 tranches insérées')
            ->expectsOutputToContain('✓ 1 portions KVB rapprochées')
            ->expectsOutputToContain('État final : 4 tranches sur 2 lignes (1 avec KVB)')
            ->assertSuccessful();

        $ranges = DB::table('line_signalling')->orderBy('line_id')->orderBy('pk_start_m')
            ->get(['line_id', 'pk_start_m', 'pk_end_m', 'signalling', 'train_protection', 'kvb_equipped', 'note']);
        $this->assertSame([$classic->id, 0, 10_000, 'BAL', ['KVB'], true], [$ranges[0]->line_id, $ranges[0]->pk_start_m, $ranges[0]->pk_end_m, $ranges[0]->signalling, json_decode($ranges[0]->train_protection, true), $ranges[0]->kvb_equipped]);
        // PK inversés dans la source : remis dans l'ordre.
        $this->assertSame([10_000, 17_250, 'BMVU', null, false], [$ranges[1]->pk_start_m, $ranges[1]->pk_end_m, $ranges[1]->signalling, $ranges[1]->train_protection, $ranges[1]->kvb_equipped]);
        // « Sans cantonnement » : une valeur à part, pas un trou ; ni protection ni KVB.
        $this->assertSame([17_250, 18_000, 'NONE', null, false], [$ranges[2]->pk_start_m, $ranges[2]->pk_end_m, $ranges[2]->signalling, $ranges[2]->train_protection, $ranges[2]->kvb_equipped]);
        $this->assertSame(['NONE'], json_decode((string) DB::table('line_signalling')->where('signalling', 'NONE')->value('signalling_systems'), true));
        // LGV : cabine TVM (protection intégrée), jamais de KVB.
        $this->assertSame([$lgv->id, 'TVM-430', ['TVM-430'], false], [$ranges[3]->line_id, $ranges[3]->signalling, json_decode($ranges[3]->train_protection, true), $ranges[3]->kvb_equipped]);
        $this->assertSame('Source : SNCF Open Data, mode-de-cantonnement-des-lignes', $ranges[0]->note);

        $snapshot = $this->snapshot(['line_signalling'], except: ['id']);
        $this->artisan('vff:import-sncf-signalling')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['line_signalling'], except: ['id']));
    }

    public function test_signalling_import_reads_letter_kilometres_of_the_rer_c_lines(): void
    {
        $this->serve('mode-de-cantonnement-des-lignes', ['results' => [
            ['code_ligne' => '983000', 'libelle' => 'Block automatique lumineux', 'pkd' => 'D+000', 'pkf' => 'D+841'],
            ['code_ligne' => '984000', 'libelle' => 'Block automatique lumineux', 'pkd' => 'D+841', 'pkf' => 'H+566'],
        ]]);
        $this->serve('lignes-equipees-de-kvb', ['results' => []]);
        $first = $this->rfnLine('983000', 'Ligne des Invalides à Quai-d\'Orsay');
        $second = $this->rfnLine('984000', 'Ligne de Quai-d\'Orsay à Paris-Austerlitz');

        $this->artisan('vff:import-sncf-signalling')->expectsOutputToContain('✓ 2 tranches insérées')->assertSuccessful();

        // Une lettre = 1 000 m : la seconde ligne commence où la première finit, 3 725 m de long.
        $this->assertSame([3_000, 3_841], [(int) DB::table('line_signalling')->where('line_id', $first->id)->value('pk_start_m'), (int) DB::table('line_signalling')->where('line_id', $first->id)->value('pk_end_m')]);
        $this->assertSame([3_841, 7_566], [(int) DB::table('line_signalling')->where('line_id', $second->id)->value('pk_start_m'), (int) DB::table('line_signalling')->where('line_id', $second->id)->value('pk_end_m')]);
    }

    public function test_signalling_import_deduces_other_ranges_from_the_operating_regime(): void
    {
        $this->serve('mode-de-cantonnement-des-lignes', ['results' => [
            ['code_ligne' => '700000', 'libelle' => '_Autre', 'pkd' => '000+000', 'pkf' => '030+000'],
        ]]);
        $this->serve('lignes-equipees-de-kvb', ['results' => []]);
        $this->serve('regime-dexploitation-des-lignes', ['results' => [
            ['code_ligne' => '700000', 'exploitati' => 'Voie unique à trafic restreint (consigne de ligne)', 'pkd' => '000+000', 'pkf' => '010+000'],
            ['code_ligne' => '700000', 'exploitati' => 'Voie unique', 'pkd' => '010+000', 'pkf' => '020+000'],
            ['code_ligne' => '700000', 'exploitati' => 'Voie unique à signalisation simplifiée', 'pkd' => '020+000', 'pkf' => '030+000'],
        ]]);
        $line = $this->rfnLine('700000', 'Ligne de Alpha à Bravo');

        $this->artisan('vff:import-sncf-signalling')->expectsOutputToContain('✓ 2 tranches insérées (dont 2 déduites')->assertSuccessful();

        $ranges = DB::table('line_signalling')->where('line_id', $line->id)->orderBy('pk_start_m')->get(['pk_start_m', 'pk_end_m', 'signalling', 'source_label', 'note']);
        $this->assertSame([[0, 10_000, 'NONE'], [20_000, 30_000, 'CT-VU']], $ranges->map(fn ($r) => [$r->pk_start_m, $r->pk_end_m, $r->signalling])->all());
        $this->assertSame('_Autre', $ranges[0]->source_label);
        $this->assertStringContainsString('EPSF', $ranges[0]->note);
    }

    public function test_signalling_import_keeps_both_systems_of_a_combined_label(): void
    {
        $this->serve('mode-de-cantonnement-des-lignes', ['results' => [
            ['code_ligne' => '408000', 'libelle' => 'European train control system 2/Transmission voie-machine 300', 'pkd' => '001+950', 'pkf' => '178+385'],
            ['code_ligne' => '408000', 'libelle' => 'Transmission voie-machine 300', 'pkd' => '000+000', 'pkf' => '001+950'],
            ['code_ligne' => '408000', 'libelle' => 'European train control system 2/Système inconnu', 'pkd' => '178+385', 'pkf' => '180+000'],
        ]]);
        $this->serve('lignes-equipees-de-kvb', ['results' => [
            ['code_ligne' => '408000', 'pkd' => '000+000', 'pkf' => '180+000'],
        ]]);
        $lgv = $this->rfnLine('408000', 'Ligne de Connerré à Rennes (LGV)');

        $this->artisan('vff:import-sncf-signalling')
            ->expectsOutputToContain('✓ 2 tranches insérées')
            ->expectsOutputToContain('Libellés inconnus, tranches ignorées : European train control system 2/Système inconnu')
            ->assertSuccessful();

        $ranges = DB::table('line_signalling')->where('line_id', $lgv->id)->orderBy('pk_start_m')
            ->get(['signalling', 'signalling_systems', 'source_label', 'train_protection', 'kvb_equipped']);
        $this->assertSame(['TVM-300', ['TVM-300'], 'Transmission voie-machine 300'], [$ranges[0]->signalling, json_decode($ranges[0]->signalling_systems, true), $ranges[0]->source_label]);
        // ETCS niveau 2 et TVM 300 gardés tous les deux ; contrôle de vitesse des deux ; jamais de KVB en cabine.
        $this->assertSame(
            ['ETCS_L2', ['ETCS_L2', 'TVM-300'], 'European train control system 2/Transmission voie-machine 300', ['ERTMS_L2', 'TVM-300'], false],
            [$ranges[1]->signalling, json_decode($ranges[1]->signalling_systems, true), $ranges[1]->source_label, json_decode($ranges[1]->train_protection, true), $ranges[1]->kvb_equipped],
        );
    }

    public function test_signalling_import_keeps_existing_ranges_when_a_dataset_is_down(): void
    {
        $this->fakeSignalling();
        $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->artisan('vff:import-sncf-signalling')->assertSuccessful();
        $before = $this->snapshot(['line_signalling']);

        $this->fakeSignalling(kvbStatus: 500);
        $this->artisan('vff:import-sncf-signalling')
            ->expectsOutputToContain('SNCF lignes-equipees-de-kvb HTTP 500 : signalisation conservée.')
            ->assertFailed();
        $this->assertSame($before, $this->snapshot(['line_signalling']));
    }

    // ─── vff:import-sncf (lignes-par-statut) ────────────────────────────

    public function test_import_sncf_creates_lines_and_segments_and_is_replayable(): void
    {
        Http::fake(['data.sncf.com/*' => Http::response($this->jsonFixture('Sncf/lignes-par-statut.geojson'))]);
        // Code stocké avec zéro de tête : même ligne que « 070000 » / « 70000 » de la source.
        $existing = $this->rfnLine('070 000', 'Ligne de Alpha à Bravo');

        $this->artisan('vff:import-sncf')
            ->expectsOutputToContain('→ 4 features reçues.')
            ->expectsOutputToContain('Traités: 4 | Lines créées: 1 | Segments: 3 | Skippés: 2')
            ->assertSuccessful();

        $this->assertSame(2, DB::table('lines')->count());
        $created = DB::table('lines')->where('slug', 'rfn-200000')->first();
        $this->assertSame(['Ligne 200000', '200000'], [$created->name, $created->code]);
        $seg = DB::table('line_segments')->where('line_id', $existing->id)->first();
        $this->assertSame(['in_service', 'mixed', true, 25_000, 160, 0, 17_250], [$seg->status, $seg->traffic_type, $seg->electrified, $seg->voltage_v, $seg->max_speed_kmh, $seg->pk_start_m, $seg->pk_end_m]);
        // « Fret seul » : fret ; MultiLineString découpée en 2 tronçons.
        $this->assertSame(['freight', 'freight'], DB::table('line_segments')->where('line_id', $created->id)->pluck('traffic_type')->all());

        // Rejouable : aucun tronçon en double.
        $this->artisan('vff:import-sncf')->expectsOutputToContain('Lines créées: 0 | Segments: 0 | Skippés: 5')->assertSuccessful();
        $this->assertSame(3, DB::table('line_segments')->count());
    }

    public function test_import_sncf_all_statuses_keeps_closed_lines_without_traffic(): void
    {
        Http::fake(['data.sncf.com/*' => Http::response($this->jsonFixture('Sncf/lignes-par-statut.geojson'))]);

        $this->artisan('vff:import-sncf', ['--all-statuses' => true])->assertSuccessful();

        $closed = DB::table('line_segments')->join('lines', 'lines.id', '=', 'line_id')->where('lines.code', '300000')->first(['status', 'traffic_type', 'lines.name']);
        $this->assertSame(['abandoned', 'unknown', 'Ligne de Fermée'], [$closed->status, $closed->traffic_type, $closed->name]);
    }

    /** @return iterable<string, array{mixed, int, string}> */
    public static function brokenSncf(): iterable
    {
        yield 'HTTP 500' => ['down', 500, 'SNCF Open Data returned HTTP 500'];
        yield 'sans features' => [['type' => 'FeatureCollection'], 200, 'Réponse SNCF sans « features » : rien importé.'];
    }

    #[DataProvider('brokenSncf')]
    public function test_import_sncf_fails_cleanly(mixed $body, int $status, string $error): void
    {
        Http::fake(['data.sncf.com/*' => Http::response($body, $status)]);

        $this->artisan('vff:import-sncf')->expectsOutputToContain($error)->assertFailed();
        $this->assertSame(0, DB::table('line_segments')->count());
    }
}
