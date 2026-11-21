<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Noms des lignes anonymes (SNCF Open Data, Wikidata, gares SNCF) et trafic
 * dérivé de la fréquentation des gares : sources simulées (tests/Fixtures),
 * cascade de priorité, provenance, sources en panne.
 */
class NamingAndTrafficCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->current = $this->era(2030, current: true);
    }

    private function fakeSources(int $sncfStatus = 200, int $wikidataStatus = 200): void
    {
        Http::fake([
            'ressources.data.sncf.com/*' => Http::response($sncfStatus === 200 ? $this->jsonFixture('Sncf/mode-de-cantonnement-des-lignes.json') : 'Service Unavailable', $sncfStatus),
            'query.wikidata.org/*' => Http::response($wikidataStatus === 200 ? $this->jsonFixture('Wikidata/lignes-p1671.json') : 'Too Many Requests', $wikidataStatus),
        ]);
    }

    /** @return array<int|string, array{string, string|null}> code => [nom, provenance] */
    private function names(): array
    {
        return DB::table('lines')->orderBy('code')->get(['code', 'name', 'alt_names'])
            ->mapWithKeys(fn ($l) => [$l->code => [$l->name, json_decode((string) $l->alt_names, true)['name_source'] ?? null]])->all();
    }

    // ─── vff:backfill-line-names-sncf ───────────────────────────────────

    public function test_backfill_renames_only_anonymous_lines_from_the_sncf_label(): void
    {
        $this->fakeSources();
        $this->rfnLine('100000', 'Ligne 100000');
        $this->rfnLine('700000', 'Ligne de Juliet à Kilo (ancien nom)');
        $this->rfnLine('800000', 'Ligne 800000');

        $this->artisan('vff:backfill-line-names-sncf')
            ->expectsOutputToContain('2 libellés uniques SNCF récupérés')
            ->expectsOutputToContain('✓ 1 noms backfillés')
            ->expectsOutputToContain('Restant anonymes : 1')
            ->assertSuccessful();

        $this->assertSame('Ligne de Alpha à Bravo', DB::table('lines')->where('code', '100000')->value('name'));
        // Nom déjà renseigné : conservé sans --force ; libellé SNCF égal au code : ignoré.
        $this->assertSame('Ligne de Juliet à Kilo (ancien nom)', DB::table('lines')->where('code', '700000')->value('name'));
        $this->assertSame('Ligne 800000', DB::table('lines')->where('code', '800000')->value('name'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'mode-de-cantonnement-des-lignes/records')
            && $r['select'] === 'code_ligne,lib_ligne' && (int) $r['limit'] === 100 && (int) $r['offset'] === 0);

        $this->artisan('vff:backfill-line-names-sncf', ['--force' => true])->expectsOutputToContain('✓ 1 noms backfillés')->assertSuccessful();
        $this->assertSame('Ligne de Juliet à Kilo', DB::table('lines')->where('code', '700000')->value('name'));
    }

    public function test_backfill_fails_cleanly_when_sncf_is_down(): void
    {
        $this->fakeSources(sncfStatus: 503);
        $this->rfnLine('100000', 'Ligne 100000');

        $this->artisan('vff:backfill-line-names-sncf')
            ->expectsOutputToContain('SNCF Open Data indisponible (HTTP 503) : aucun nom modifié.')
            ->assertFailed();
        $this->assertSame('Ligne 100000', DB::table('lines')->value('name'));
    }

    public function test_backfill_fails_cleanly_on_a_connection_error(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->rfnLine('100000', 'Ligne 100000');

        $this->artisan('vff:backfill-line-names-sncf')->expectsOutputToContain('connexion impossible')->assertFailed();
        $this->assertSame('Ligne 100000', DB::table('lines')->value('name'));
    }

    // ─── vff:name-lines ─────────────────────────────────────────────────

    private function namingFixtures(): void
    {
        $this->rfnLine('100000', 'Ligne 100000');            // SNCF
        $this->rfnLine('200000', 'Ligne 200000');            // Wikidata (préférer « Ligne de … »)
        $this->rfnLine('300000', '300000');                  // gares extrêmes SNCF (en capitales)
        $this->rfnLine('400000', 'Ligne 400 000');           // raccordement, gare la plus proche
        $this->rfnLine('500000', 'Ligne 500000');            // aucune source : reste anonyme
        $this->rfnLine('600000', 'Ligne de Golf à Hotel');   // déjà nommée

        DB::table('sncf_gares')->insert([
            ['code_ligne' => '300000', 'libelle' => 'PARIS-BASTILLE', 'pk_m' => 0, 'lat' => 48.853, 'lon' => 2.369],
            ['code_ligne' => '300000', 'libelle' => 'VINCENNES', 'pk_m' => 6_000, 'lat' => 48.847, 'lon' => 2.433],
            ['code_ligne' => '300000', 'libelle' => 'BOISSY-SAINT-LÉGER', 'pk_m' => 22_000, 'lat' => 48.751, 'lon' => 2.511],
            ['code_ligne' => '900000', 'libelle' => 'Echo-Ville', 'pk_m' => 1_000, 'lat' => 45.001, 'lon' => 4.0501],
        ]);
        DB::table('sncf_line_types')->insert(['code_ligne' => '400000', 'type_ligne' => 'Rac']);
        DB::insert("INSERT INTO sncf_line_geometries (code_ligne, libelle, geom) VALUES ('400000', 'Exploitée', ST_GeomFromText('LINESTRING(4.0 45.0, 4.1 45.0)', 4326))");
    }

    public function test_name_lines_cascade_sncf_then_wikidata_then_stations(): void
    {
        $this->fakeSources();
        $this->namingFixtures();

        $this->artisan('vff:name-lines')
            ->expectsOutputToContain('1 lignes nommées depuis Wikidata')
            ->expectsOutputToContain("1 lignes nommées d'après leurs gares extrêmes")
            ->expectsOutputToContain("1 lignes nommées d'après la gare la plus proche")
            ->expectsOutputToContain('✓ Lignes encore anonymes : 1')
            ->assertSuccessful();

        $names = $this->names();
        $this->assertSame(['Ligne de Alpha à Bravo', 'sncf'], $names['100000']);
        $this->assertSame(['Ligne de Charlie à Delta', 'wikidata'], $names['200000']);
        $this->assertSame(['Ligne de Paris-Bastille à Boissy-Saint-Léger', 'derived_stations'], $names['300000']);
        $this->assertSame(['Raccordement près de Echo-Ville', 'derived_nearest'], $names['400000']);
        $this->assertSame(['Ligne 500000', null], $names['500000']);
        $this->assertSame('Ligne de Golf à Hotel', $names['600000'][0]);

        // Rejouable : plus rien à nommer, rien ne bouge.
        $snapshot = $this->snapshot(['lines']);
        $this->artisan('vff:name-lines', ['--skip-sncf' => true])->expectsOutputToContain('0 lignes nommées depuis Wikidata')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['lines']));
    }

    public function test_name_lines_goes_on_without_wikidata(): void
    {
        $this->fakeSources(wikidataStatus: 429);
        $this->namingFixtures();

        $this->artisan('vff:name-lines', ['--skip-sncf' => true])
            ->expectsOutputToContain('Wikidata indisponible (HTTP 429) : étape ignorée.')
            ->expectsOutputToContain('0 lignes nommées depuis Wikidata')
            ->assertSuccessful();

        $names = $this->names();
        $this->assertSame(['Ligne 100000', null], $names['100000']); // étape SNCF sautée
        $this->assertSame(['Ligne 200000', null], $names['200000']);
        $this->assertSame('Ligne de Paris-Bastille à Boissy-Saint-Léger', $names['300000'][0]);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'sncf.com'));
    }

    // ─── vff:derive-traffic ─────────────────────────────────────────────

    public function test_derive_traffic_types_lines_with_a_passenger_station(): void
    {
        Http::fake(['data.sncf.com/*' => Http::response([
            ['nom_gare' => 'Alpha', 'total_voyageurs_2024' => 120_000],
            ['nom_gare' => "Gare d'Orient", 'total_voyageurs_2024' => 1_500],
            ['nom_gare' => 'Lgv-Gare', 'total_voyageurs_2023' => 900_000],
            ['nom_gare' => 'Fret-Only', 'total_voyageurs_2024' => 0],
        ])]);
        $pax = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($pax, $this->current, [[48.0, 2.0], [48.1, 2.0]], ['traffic_type' => 'unknown']);
        $this->track($pax, $this->current, [[48.1, 2.0], [48.2, 2.0]], ['status' => 'dismantled', 'traffic_type' => 'mixed']);
        $this->station('alpha', 'Alpha', 48.0001, 2.0001);
        $quote = $this->rfnLine('150000', "Ligne d'Orient");
        $this->track($quote, $this->current, [[46.0, 3.0], [46.1, 3.0]], ['traffic_type' => 'unknown']);
        $this->station('orient', "Gare d'Orient", 46.0001, 3.0001);
        $lgv = $this->rfnLine('752000', 'LGV Sud-Est');
        $this->track($lgv, $this->current, [[47.0, 4.0], [47.1, 4.0]], ['traffic_type' => 'unknown']);
        $this->station('lgv-gare', 'Lgv-Gare', 47.0001, 4.0001);
        $freight = $this->rfnLine('200000', 'Ligne de Fret');
        $this->track($freight, $this->current, [[45.0, 5.0], [45.1, 5.0]], ['traffic_type' => 'mixed']);
        $this->station('fret-only', 'Fret-Only', 45.0001, 5.0001);

        $this->artisan('vff:derive-traffic')->expectsOutputToContain('3 noms de gares voyageurs.')->assertSuccessful();

        $traffic = fn ($line) => DB::table('line_segments')->where('line_id', $line->id)->orderByRaw('ST_YMin(geom)')->pluck('traffic_type')->all();
        // Ligne classique à voyageurs : mixte ; tronçon déposé : aucun trafic.
        $this->assertSame(['mixed', 'unknown'], $traffic($pax));
        // Apostrophe dans le nom de gare : paramètre lié, pas d'erreur SQL.
        $this->assertSame(['mixed'], $traffic($quote));
        $this->assertSame(['passenger'], $traffic($lgv));
        $this->assertSame(['freight'], $traffic($freight));
    }

    public function test_derive_traffic_fails_when_the_source_is_down(): void
    {
        Http::fake(['data.sncf.com/*' => Http::response('<html>502</html>', 502)]);
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($line, $this->current, [[48.0, 2.0], [48.1, 2.0]]);

        $this->artisan('vff:derive-traffic')->expectsOutputToContain('Échec téléchargement fréquentation-gares')->assertFailed();
        $this->assertSame('mixed', DB::table('line_segments')->value('traffic_type'));
    }
}
