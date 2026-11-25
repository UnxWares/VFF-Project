<?php

namespace Tests\Feature\Rinf;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\Rinf\RinfParameterComparison;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Paramètres du RINF sur l'extrait réel de la ligne 579000 (Beillant - Angoulême, tests/Fixtures/rinf/line-579000,
 * paramètres des voies lus le 25/11/2026, RINF © ERA) : import des colonnes, puis comparaison par PK à des données de
 * SNCF Réseau posées pour le test (électrification, vitesses, signalisation, radio, quais), servie à l'identique par
 * REST, GraphQL et MCP.
 */
class RinfParametersComparisonTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
        $this->era = Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-579000', 'code' => '579000', 'name' => 'Ligne de Beillant à Angoulême']);
        Station::factory()->create(['slug' => 'cognac', 'name' => 'Cognac', 'type' => 'gare', 'geom' => Point::makeGeodetic(45.6846, -0.3266)]);
        (new ReflectionProperty(RinfParameterComparison::class, 'ready'))->setValue(null, null);
    }

    private function import(): void
    {
        $this->artisan('vff:import-rinf', ['--offline' => true, '--line' => '579000', '--cache-dir' => base_path('tests/Fixtures/rinf')])->assertSuccessful();
    }

    /** Données de SNCF Réseau de la ligne, choisies pour donner chaque statut. */
    private function seedSncf(): void
    {
        $now = now();
        DB::table('sncf_line_electrification')->insert(['code_ligne' => '579000', 'rg_troncon' => 1, 'pkd_m' => 30000, 'pkf_m' => 40000,
            'elect' => '1500 volts continu', 'voltage_v' => 1500, 'frequency_hz' => 0, 'third_rail' => false, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[-235, 17095, 120], [17095, 17277, 110], [17277, 44427, 130], [44427, 62631, 110]] as [$a, $b, $v]) {
            DB::table('line_speed_ranges')->insert(['line_id' => $this->line->id, 'era_id' => $this->era->id, 'pk_start_m' => $a, 'pk_end_m' => $b,
                'max_speed_kmh' => $v, 'source' => 'sncf', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('line_signalling')->insert(['line_id' => $this->line->id, 'era_id' => $this->era->id, 'pk_start_m' => -252, 'pk_end_m' => 64667,
            'signalling' => 'BAL', 'signalling_systems' => '["BAL"]', 'train_protection' => '["KVB"]', 'kvb_equipped' => true,
            'source_label' => 'Block automatique lumineux', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[-252, 63732, 'gsm-gfu', 'Global System for Mobil- Groupe Fermé d\'utilisateurs'], [63732, 64667, 'gsm-r', 'GSMR (Global System for Mobile Raywails)']] as [$a, $b, $system, $label]) {
            DB::table('line_radio_portions')->insert(['line_id' => $this->line->id, 'code_ligne' => '579000', 'rg_troncon' => 1, 'pk_start_m' => $a, 'pk_end_m' => $b,
                'system' => $system, 'label' => $label, 'source' => 'sncf', 'created_at' => $now, 'updated_at' => $now]);
        }
        $cognac = Station::where('slug', 'cognac')->value('id');
        foreach ([['Quai  VE', 150, 17240], ['Quai X', 100, 17300]] as [$label, $length, $pk]) {
            DB::table('station_platforms')->insert(['station_id' => $cognac, 'sncf_station_name' => 'Cognac', 'code_ligne' => '579000', 'rg_troncon' => 1,
                'label' => $label, 'length_m' => $length, 'pk_start_m' => $pk, 'pk_end_m' => $pk + $length, 'source' => 'sncf', 'fetched_at' => $now,
                'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function test_the_import_writes_the_declared_parameters_of_each_section_track(): void
    {
        $this->import();

        $cognac = DB::table('rinf_tracks')->where('section_uri', 'http://data.europa.eu/949/sectionOfLine/579000-1_FR0000003513_FR9900004367')->first();
        $this->assertSame(['none', null, null, 'none', false, null, 'D4', '4+', 1435],
            [$cognac->contact_line, $cognac->energy_supply, $cognac->voltage_v, $cognac->etcs_level, $cognac->gsm_r, $cognac->gsm_r_version,
                $cognac->load_category, $cognac->national_load_class, $cognac->track_gauge_mm]);
        $this->assertSame(['KVB', 'DAAT', 'Crocodile'], json_decode((string) $cognac->train_protection, true));
        $this->assertSame(['GB', 'FR-3.3', 'GI2'], json_decode((string) $cognac->gauging_profiles, true));
        // GSM-R déclaré sur les deux voies de la dernière section, version Baseline 1.
        $this->assertSame([true, 'Baseline 1'], array_values((array) DB::table('rinf_tracks')
            ->where('section_uri', 'http://data.europa.eu/949/sectionOfLine/579000-1_FR0000000960_FR0000000153')->first(['gsm_r', 'gsm_r_version'])));
        // Voies des points d'exploitation : aucun paramètre (colonnes nulles).
        $this->assertSame(0, DB::table('rinf_tracks')->where('parent', 'op')->whereNotNull('contact_line')->count());
        $this->assertSame(25, DB::table('rinf_tracks')->where('parent', 'section')->whereNotNull('track_gauge_mm')->count());
    }

    public function test_each_parameter_is_compared_by_pk_with_its_status(): void
    {
        $this->import();
        $this->seedSncf();
        $json = $this->getJson('/api/v1/lines/rfn-579000/rinf-parameters')->assertOk()->json();
        $params = array_column($json['parameters'], null, 'key');
        $this->assertSame(RinfParameterComparison::PARAMETERS, array_keys($params));

        // Électrification : le RINF dit « aucune » partout ; SNCF Réseau 1 500 V de 30 à 40 km, aucune ailleurs.
        $e = $params['electrification'];
        $this->assertSame(['sncf', 10000], [$e['compared_with'], $e['summary']['disagree_m']]);
        $this->assertSame([1, 30000, 40000, 'disagree', 'none', '1500V-DC', 1500, 0.0, '1500 volts continu'], [
            $e['portions'][1]['rg_troncon'], $e['portions'][1]['pk_start_m'], $e['portions'][1]['pk_end_m'], $e['portions'][1]['status'],
            $e['portions'][1]['rinf']['key'], $e['portions'][1]['vff']['key'], $e['portions'][1]['vff']['voltage_v'], (float) $e['portions'][1]['vff']['frequency_hz'],
            $e['portions'][1]['vff']['source_label']]);
        $this->assertSame(['agree', 'disagree', 'agree'], array_column($e['portions'], 'status'));

        // Vitesse : plus forte vitesse de SNCF Réseau sur chaque section ; 140 au RINF contre 130 de Cognac à Sireuil.
        $v = array_map(fn (array $p): array => [$p['pk_start_m'], $p['pk_end_m'], $p['status'], $p['rinf']['key'] ?? null, $p['vff']['key'] ?? null], $params['max_speed']['portions']);
        $this->assertSame([[-235, -31, 'disagree', '110', '120'], [-31, 17095, 'agree', '120', '120'], [17095, 17277, 'agree', '110', '110'],
            [17277, 44427, 'disagree', '140', '130'], [44427, 62631, 'agree', '110', '110'], [62631, 64667, 'rinf_only', '100', null]], $v);
        $this->assertSame(['VU'], $params['max_speed']['portions'][3]['rinf']['tracks']);
        // Section à voie unique et voies 1, 2 : les voies principales seules, pas la voie E de Sireuil.
        $this->assertSame(['V1', 'V2', 'VU'], $params['max_speed']['portions'][4]['rinf']['tracks']);

        // Contrôle de vitesse : KVB des deux côtés ; tous les systèmes du RINF restent dans `values`.
        $t = $params['train_protection'];
        $this->assertSame([['vff_only', null, 'KVB'], ['agree', 'KVB', 'KVB']], array_map(fn (array $p): array => [$p['status'], $p['rinf']['key'] ?? null, $p['vff']['key']], $t['portions']));
        $this->assertSame(['KVB', 'DAAT', 'Crocodile'], $t['portions'][1]['rinf']['values']);
        $this->assertSame(['KVB', 'BAL'], $t['portions'][1]['vff']['values']);
        $this->assertSame(['rinf_m' => 64902, 'vff_m' => 64919, 'agree_m' => 64902, 'disagree_m' => 0, 'rinf_only_m' => 0, 'vff_only_m' => 17], $t['summary']);

        // Radio : GSM-R des deux côtés au bout de la ligne, aucun ailleurs.
        $this->assertSame([['vff_only', null, 'none'], ['agree', 'none', 'none'], ['agree', 'gsm-r', 'gsm-r']],
            array_map(fn (array $p): array => [$p['status'], $p['rinf']['key'] ?? null, $p['vff']['key']], $params['radio']['portions']));
        $this->assertSame(['Baseline 1'], $params['radio']['portions'][2]['rinf']['values']);

        // Sans autre source dans VFF : RINF seul.
        foreach (['gauging', 'load_capability', 'track_gauge'] as $key) {
            $this->assertNull($params[$key]['compared_with']);
            $this->assertSame(['rinf_only'], array_values(array_unique(array_column($params[$key]['portions'], 'status'))), $key);
        }
        $this->assertSame(['GA+FR-3.3+GI2', 'GB+FR-3.3+GI2'], array_column(array_column($params['gauging']['portions'], 'rinf'), 'key'));
        $this->assertSame(['D4'], array_values(array_unique(array_column(array_column($params['load_capability']['portions'], 'rinf'), 'key'))));
        $this->assertSame('1435', $params['track_gauge']['portions'][0]['rinf']['key']);

        // Quais : « Quai VE » de Cognac rapproché (145 m au RINF, 150 m chez SNCF Réseau : d'accord à 5 m près, hauteur 300 mm),
        // « Quai X » chez SNCF Réseau seul, « Quai VD » au RINF seul.
        $quais = collect((array) $json['platforms']['items']);
        $ve = $quais->firstWhere('ref', 'Quai VE');
        $this->assertSame(['agree', 17240, 'cognac', 145, 300, 150], [$ve['status'], $ve['pk_m'], $ve['station']['slug'], $ve['rinf']['length_m'], $ve['rinf']['height_mm'], $ve['vff']['length_m']]);
        $this->assertSame('vff_only', $quais->firstWhere('ref', 'Quai X')['status']);
        $this->assertSame(['rinf_only', 17277], [$quais->firstWhere('ref', 'Quai VD')['status'], $quais->firstWhere('ref', 'Quai VD')['pk_m']]);
        $this->assertSame(1, $json['platforms']['summary']['vff_only']);

        $this->assertSame(['rinf', 'sncf'], array_column($json['sources'], 'key'));
        $this->assertSame(15, count($json['sections']));
        $this->assertSame(['uopid' => 'FR0000003513', 'name' => 'Cognac'], collect((array) $json['sections'])->firstWhere('pk_start_m', 17277)['from']);
    }

    /**
     * A platform of the same station and name whose length one source does not give is not counted in agreement: no
     * comparison was made (25/11/2026: 77 of the 317 RINF platforms without a length were matched and counted agreeing).
     */
    public function test_a_platform_without_length_on_one_side_has_its_own_status(): void
    {
        $this->import();
        $this->seedSncf();
        DB::table('station_platforms')->insert(['station_id' => Station::where('slug', 'cognac')->value('id'), 'sncf_station_name' => 'Cognac',
            'code_ligne' => '579000', 'rg_troncon' => 1, 'label' => 'Quai VD', 'length_m' => null, 'pk_start_m' => 17260, 'pk_end_m' => null,
            'source' => 'sncf', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $json = $this->getJson('/api/v1/lines/rfn-579000/rinf-parameters')->assertOk()->json('platforms');
        $vd = collect((array) $json['items'])->first(fn (array $q): bool => $q['ref'] === 'Quai VD' && ($q['station']['slug'] ?? null) === 'cognac');
        $this->assertSame([RinfParameterComparison::PLATFORM_LENGTH_UNKNOWN, 145, null], [$vd['status'], $vd['rinf']['length_m'], $vd['vff']['length_m']]);
        $this->assertSame([1, 1], [$json['summary']['agree'], $json['summary']['length_unknown']]);

        $gql = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-579000") { rinf_parameters { platforms { summary { agree length_unknown } } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.line.rinf_parameters.platforms.summary');
        $this->assertSame(['agree' => 1, 'length_unknown' => 1], $gql);
    }

    public function test_graphql_and_mcp_serve_the_same_content(): void
    {
        $this->import();
        $this->seedSncf();
        $rest = $this->getJson('/api/v1/lines/rfn-579000/rinf-parameters')->assertOk()->json();
        $value = '{ key values voltage_v frequency_hz speed_kmh tracks source_label }';
        $gql = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-579000") { rinf_parameters {
            sections { uri national_line rg_troncon pk_start_m pk_end_m pk_start_label pk_end_label length_m from { uopid name } to { uopid name } tracks }
            parameters { key compared_with summary { rinf_m vff_m agree_m disagree_m rinf_only_m vff_only_m }
                portions { rg_troncon pk_start_m pk_end_m pk_start_label pk_end_label length_m status rinf '.$value.' vff '.$value.' } }
            platforms { summary { rinf vff agree disagree rinf_only vff_only tolerance_m length_unknown }
                items { pk_m station { slug name } operational_point { uopid name } ref track status rinf { length_m height_mm } vff { label length_m pk_start_m pk_end_m } } }
            sources { key label licence dataset fetched_at url attribution licence_url } } } }'])->assertOk()->assertJsonMissingPath('errors')->json('data.line.rinf_parameters');
        foreach (['sections', 'parameters', 'platforms', 'sources'] as $key) {
            $this->assertEquals($rest[$key], $gql[$key], $key);
        }

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_rinf_parameters',
                'arguments' => ['line' => '579000', 'parameters' => ['max_speed', 'electrification'], 'disagreements_only' => true]]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) ($result['isError'] ?? false));
        $mcp = json_decode((string) $result['content'][0]['text'], true);
        $this->assertSame(['electrification', 'max_speed'], array_column($mcp['parameters'], 'key'));
        $this->assertSame([['disagree'], ['disagree']], array_map(fn (array $p): array => array_values(array_unique(array_column($p['portions'], 'status'))), $mcp['parameters']));
        $this->assertSame(array_column($rest['parameters'], 'summary', 'key')['max_speed'], $mcp['parameters'][1]['summary']);
        $this->assertSame([], $mcp['platforms']['items']);
        $this->assertContains('rinf', array_column((array) $mcp['attribution']['sources'], 'provider'));
        $this->assertContains('sncf', array_column((array) $mcp['attribution']['sources'], 'provider'));
        $this->assertStringContainsString('European Union Agency for Railways', $mcp['sources'][0]['attribution']);
    }

    public function test_a_line_without_rinf_or_before_the_migration_answers_empty_lists(): void
    {
        $other = Line::factory()->create(['slug' => 'rfn-123456', 'code' => '123456']);
        $json = $this->getJson('/api/v1/lines/'.$other->slug.'/rinf-parameters')->assertOk()->json();
        $this->assertSame([[], [], []], [$json['sections'], $json['platforms']['items'], $json['sources']]);
        $this->assertSame(RinfParameterComparison::PARAMETERS, array_column($json['parameters'], 'key'));

        // Colonnes des paramètres pas encore créées (migration à passer) : 200 et listes vides, jamais d'erreur.
        $this->import();
        Schema::table('rinf_tracks', fn (Blueprint $t) => $t->dropColumn('contact_line'));
        (new ReflectionProperty(RinfParameterComparison::class, 'ready'))->setValue(null, null);
        $json = $this->getJson('/api/v1/lines/rfn-579000/rinf-parameters')->assertOk()->json();
        $this->assertSame([], $json['sections']);
        $this->assertSame([], $json['parameters'][0]['portions']);
    }
}
