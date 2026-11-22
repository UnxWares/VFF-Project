<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Station;
use App\Services\Sncf\SncfOpenDataCache;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-sncf-platforms (jeu « Liste des quais », SNCF Réseau, ODbL) : rattachement des quais aux gares
 * par le code UIC du référentiel SNCF, puis par le nom ; --dry-run n'écrit rien ; le bloc `platforms` est le
 * même dans l'API REST, GraphQL et MCP.
 */
class ImportSncfPlatformsTest extends TestCase
{
    use CommandSandbox;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const FIXTURES = 'tests/Fixtures/Sncf';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::preventStrayRequests();
        Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        // Brest : rattachée par le code UIC du référentiel SNCF ; Bravo : par son nom, faute de code UIC.
        Station::factory()->create(['slug' => 'brest', 'name' => 'Brest', 'uic_code' => '87474007', 'status' => 'active',
            'geom' => Point::makeGeodetic(48.3879, -4.4787)]);
        Station::factory()->create(['slug' => 'bravo', 'name' => 'Bravo', 'status' => 'active', 'geom' => Point::makeGeodetic(48.9003, 2.4004)]);
        Station::factory()->create(['slug' => 'charlie', 'name' => 'Charlie', 'uic_code' => '87000000', 'status' => 'active',
            'geom' => Point::makeGeodetic(47.0, 3.0)]);
        DB::table('sncf_gares')->insert(['code_uic' => '87474007', 'code_ligne' => '420000', 'libelle' => 'Brest', 'voyageurs' => true,
            'fret' => false, 'lat' => 48.388, 'lon' => -4.479]);
    }

    public function test_dry_run_measures_and_writes_nothing(): void
    {
        $this->artisan('vff:import-sncf-platforms', ['--dry-run' => true, '--cache-dir' => self::FIXTURES])
            ->expectsOutputToContain('Rattachés par code UIC : 1 groupes, 1 gares VFF')
            ->expectsOutputToContain('Rattachés par nom et distance (1500 m au plus) : 1 groupes, 1 gares VFF')
            ->expectsOutputToContain('gare non nommée dans le jeu : 1')
            ->expectsOutputToContain('sans quai connu : 1')
            ->expectsOutputToContain('aucune écriture en base')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('station_platforms')->count());
    }

    public function test_import_writes_every_record_and_replaces_the_previous_import(): void
    {
        $this->artisan('vff:import-sncf-platforms', ['--cache-dir' => self::FIXTURES])->assertSuccessful();
        $this->artisan('vff:import-sncf-platforms', ['--cache-dir' => self::FIXTURES])->assertSuccessful();

        $this->assertSame(6, DB::table('station_platforms')->count());
        $brest = DB::table('stations')->where('slug', 'brest')->value('id');
        $this->assertSame(3, DB::table('station_platforms')->where('station_id', $brest)->where('match_method', 'uic')->count());
        $this->assertSame(2, DB::table('station_platforms')->where('match_method', 'name')->count());
        $this->assertSame(1, DB::table('station_platforms')->whereNull('station_id')->count());
        $this->assertSame('2020-05-26', substr((string) DB::table('station_platforms')->value('dataset_modified'), 0, 10));
    }

    public function test_a_dataset_under_another_licence_is_refused(): void
    {
        Http::fake([SncfOpenDataCache::API.'liste-des-quais*' => Http::response(['metas' => ['default' => ['license' => 'CC BY-NC-SA 4.0', 'publisher' => 'SNCF Réseau']]])]);

        $this->artisan('vff:import-sncf-platforms', ['--dry-run' => true, '--refresh' => true])
            ->expectsOutputToContain('Licence ou éditeur inattendus')
            ->assertFailed();
    }

    public function test_the_platforms_block_is_the_same_in_rest_graphql_and_mcp(): void
    {
        $this->artisan('vff:import-sncf-platforms', ['--cache-dir' => self::FIXTURES])->assertSuccessful();

        $rest = $this->getJson('/api/v1/stations/brest')->assertOk()->json('data.platforms');
        $this->assertSame(2, $rest['count']);
        $this->assertSame(387, $rest['longest_m']);
        $this->assertSame(699, $rest['total_length_m']);
        $this->assertSame(['label' => 'Quai E/F', 'code_ligne' => '420000', 'length_m' => 387, 'pk_start_m' => 622_017, 'pk_end_m' => 622_404], $rest['items'][0]);
        $this->assertSame('2020-05-26', $rest['dataset_modified']);
        $this->assertStringContainsString('SNCF Réseau', $rest['attribution']);
        $this->assertSame(['sncf', 'verified'], [$rest['provenance']['provider'], $rest['provenance']['license_status']]);
        $this->assertNull($this->getJson('/api/v1/stations/charlie')->assertOk()->json('platforms'));

        $gql = $this->graphQL('{ stations(first: 10) { data { slug platforms { count longest_m total_length_m items { label code_ligne length_m pk_start_m pk_end_m } dataset_modified attribution provenance { provider license license_status } } } } }')
            ->assertJsonMissingPath('errors')->json('data.stations.data');
        $bySlug = array_column($gql, 'platforms', 'slug');
        $this->assertSame($rest['items'], $bySlug['brest']['items']);
        $this->assertSame(2, $bySlug['bravo']['count']);
        $this->assertNull($bySlug['charlie']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'get_station', 'arguments' => ['station' => 'brest']]])
            ->assertOk()->json('result');
        $this->assertFalse($result['isError'], $result['content'][0]['text']);
        $mcp = json_decode($result['content'][0]['text'], true);
        $this->assertSame($rest['items'], $mcp['platforms']['items']);
        $this->assertSame($rest['longest_m'], $mcp['platforms']['longest_m']);
    }

    public function test_platforms_labelled_with_the_name_of_a_closed_station_follow_the_open_station_of_the_same_site(): void
    {
        // « Grasse » : quais de Marseille-St-Charles (mêmes lignes, PK recoupés, 100 m) rattachés par erreur à une fiche fermée
        // qui porte le code UIC de Grasse (Alpes-Maritimes).
        Station::factory()->create(['slug' => 'marseille-st-charles', 'name' => 'Marseille-St-Charles', 'uic_code' => '87751008', 'status' => 'active',
            'geom' => Point::makeGeodetic(43.3030, 5.3811)]);
        Station::factory()->create(['slug' => 'grasse', 'name' => 'Grasse', 'uic_code' => '87757724', 'status' => 'declassified',
            'geom' => Point::makeGeodetic(43.3028, 5.3806)]);
        DB::table('sncf_gares')->insert([
            ['code_uic' => '87751008', 'code_ligne' => '830000', 'libelle' => 'Marseille-St-Charles', 'voyageurs' => true, 'fret' => false, 'lat' => 43.303, 'lon' => 5.381],
            ['code_uic' => '87757724', 'code_ligne' => '830000', 'libelle' => 'Grasse', 'voyageurs' => true, 'fret' => false, 'lat' => 43.3028, 'lon' => 5.3806],
            ['code_uic' => '87295063', 'code_ligne' => '212000', 'libelle' => 'Hirson', 'voyageurs' => true, 'fret' => false, 'lat' => 49.9145, 'lon' => 4.0846],
        ]);
        Station::factory()->create(['slug' => 'hirson', 'name' => 'Hirson', 'uic_code' => '87295063', 'status' => 'active',
            'geom' => Point::makeGeodetic(49.9145, 4.0846)]);

        $this->artisan('vff:import-sncf-platforms', ['--cache-dir' => 'tests/Fixtures/SncfSite'])
            ->expectsOutputToContain('Rattachés au même site qu\'une gare ouverte')
            ->expectsOutputToContain('1 PK, 1 coordonnées')
            ->assertSuccessful();

        $id = fn (string $slug) => DB::table('stations')->where('slug', $slug)->value('id');
        $this->assertSame(0, DB::table('station_platforms')->where('station_id', $id('grasse'))->count());
        $this->assertSame(['site'], DB::table('station_platforms')->where('sncf_station_name', 'Grasse')->pluck('match_method')->all());
        $this->assertSame($id('marseille-st-charles'), (int) DB::table('station_platforms')->where('sncf_station_name', 'Grasse')->value('station_id'));
        // Hirson : le « Quai 2 » de PK 000-126 à 010+299 garde sa longueur, mais plus de PK ni de bout à 8 km.
        $quai2 = DB::table('station_platforms')->where('sncf_station_name', 'Hirson')->where('label', 'Quai 2')->first();
        $this->assertSame([425, null, null], [(int) $quai2->length_m, $quai2->pk_start_m, $quai2->pk_end_m]);
        $this->assertSame('uic', $quai2->match_method);
    }
}
