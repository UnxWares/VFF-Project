<?php

namespace Tests\Feature\Api;

use App\Models\LevelCrossing;
use App\Models\Line;
use App\Services\LevelCrossings\LevelCrossingTiles;
use App\Services\LevelCrossings\LineLevelCrossings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsLevelCrossings;
use Tests\TestCase;

/**
 * Passages à niveau dans l'API REST : liste filtrée et paginée, PN d'une ligne avec leur nombre par
 * groupe, tuiles du calque de la carte (vides sous le zoom 9, sans PN secondaires), provenance
 * SNCF Réseau sous ODbL, documentation OpenAPI.
 */
class LevelCrossingApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLevelCrossings;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Line::factory()->create(['slug' => 'rfn-70000', 'code' => '070000', 'name' => 'Ligne de Noisy-le-Sec à Strasbourg-Ville']);
        Line::factory()->create(['slug' => 'rfn-1000', 'code' => '001000', 'name' => 'Ligne de Paris-Nord à Lille']);
        $this->seedLevelCrossing('30000010436', '070000', 112866, 49.062227, 3.602119, 'CLASSE 17');
        $this->seedLevelCrossing('30000010435', '070000', 112190, 49.059126, 3.594152, 'CLASSE 11', ['commune' => 'Crézancy']);
        $this->seedLevelCrossing('30000010499', '070000', null, 49.07, 3.61, 'CLASSE 00', ['obstacle' => 'PN 3 001000 2+100']);
        $this->seedLevelCrossing('30000020001', '001000', 2100, 50.50, 3.00, 'CLASSE 32', ['commune' => 'Seclin']);
    }

    public function test_lists_level_crossings_by_line_then_pk_with_class_and_provenance(): void
    {
        $response = $this->getJson('/api/v1/level-crossings')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->assertJsonPath('meta.total', 4);

        $this->assertSame(['30000020001', '30000010435', '30000010436', '30000010499'], array_column($response->json('data'), 'id'));
        $pn = $response->json('data.2');
        $this->assertSame([
            'id' => '30000010436', 'label' => 'PN 36', 'class' => 17,
            'class_label' => 'Public pour voitures, barrières ou demi-barrières automatiques, signalisation lumineuse SAL 2 ou SAL 2B',
            'group' => 'automatic', 'pk_m' => 112866, 'pk_label' => '112+866', 'commune' => 'Courthiézy', 'crossed_way' => 'VC',
            'line' => ['slug' => 'rfn-70000', 'code' => '070000', 'name' => 'Ligne de Noisy-le-Sec à Strasbourg-Ville'],
            'line_code' => '070000', 'lat' => 49.062227, 'lng' => 3.602119,
            'provenance' => [
                'provider' => 'sncf', 'name' => 'SNCF Open Data (SNCF Réseau, Gares & Connexions, GTFS voyageurs)',
                'license' => 'ODbL 1.0 (chaque jeu utilisé : champ metas.default.license de l\'API)', 'license_status' => 'verified',
            ],
        ], $pn);
        $this->assertSame('secondary', $response->json('data.3.group'));
        $this->assertNull($response->json('data.3.pk_label'));
    }

    /** @return iterable<string, array{0: array<string, string>, 1: list<string>}> */
    public static function filters(): iterable
    {
        yield 'ligne' => [['line' => 'rfn-1000'], ['30000020001']];
        yield 'groupe' => [['group' => 'guarded'], ['30000010435']];
        yield 'classe' => [['class' => '32'], ['30000020001']];
        yield 'secondaires' => [['group' => 'secondary'], ['30000010499']];
        yield 'commune sans accents' => [['commune' => 'crezancy'], ['30000010435']];
        yield 'emprise' => [['bbox' => '3.59,49.05,3.60,49.07'], ['30000010435']];
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<string>  $expected
     */
    #[DataProvider('filters')]
    public function test_filters(array $query, array $expected): void
    {
        $ids = array_column($this->getJson('/api/v1/level-crossings?'.http_build_query($query))->assertOk()->json('data'), 'id');

        $this->assertSame($expected, $ids);
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function invalid(): iterable
    {
        yield 'ligne inconnue' => ['line=rfn-999999', 'line'];
        yield 'groupe inconnu' => ['group=barriers', 'group'];
        yield 'classe hors liste' => ['class=25', 'class'];
        yield 'emprise mal formée' => ['bbox=1,2,3', 'bbox'];
        yield 'emprise inversée' => ['bbox=5,49,3,48', 'bbox'];
        yield 'page trop grande' => ['per_page=500', 'per_page'];
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid_parameters(string $query, string $field): void
    {
        $this->getJson("/api/v1/level-crossings?{$query}")->assertStatus(422)->assertJsonValidationErrors([$field]);
    }

    public function test_line_endpoint_lists_crossings_in_pk_order_with_counts_by_group(): void
    {
        $response = $this->getJson('/api/v1/lines/rfn-70000/level-crossings')->assertOk();

        $response->assertJsonPath('line.slug', 'rfn-70000')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('summary', [
                ['group' => 'automatic', 'count' => 1],
                ['group' => 'guarded', 'count' => 1],
                ['group' => 'secondary', 'count' => 1],
            ])
            ->assertJsonPath('provenance.provider', 'sncf');
        $this->assertSame(['30000010435', '30000010436', '30000010499'], array_column($response->json('level_crossings'), 'id'));
    }

    public function test_line_without_sncf_code_has_no_crossings_and_unknown_line_is_404(): void
    {
        Line::factory()->create(['slug' => 'archeo-x', 'code' => null]);

        $this->getJson('/api/v1/lines/archeo-x/level-crossings')->assertOk()->assertJsonPath('total', 0)->assertJsonPath('summary', []);
        $this->getJson('/api/v1/lines/rfn-999999/level-crossings')->assertNotFound();
    }

    public function test_tiles_are_empty_below_zoom_nine_and_omit_secondary_crossings(): void
    {
        // Tuile z5 qui couvre le nord-est de la France : vide (trop de points à cette échelle).
        $this->get('/api/v1/level-crossings/tiles/5/16/11.mvt')->assertOk()
            ->assertHeader('Content-Type', 'application/x-protobuf');
        $this->assertSame('', $this->get('/api/v1/level-crossings/tiles/5/16/11.mvt')->getContent());

        // Tuile z11 sur Courthiézy : deux PN (le secondaire n'est pas dessiné).
        [$x, $y] = self::tileOf(49.06, 3.60, 11);
        $tile = (string) $this->get("/api/v1/level-crossings/tiles/11/{$x}/{$y}.mvt")->assertOk()->getContent();
        $this->assertStringContainsString('vff-level-crossings', $tile);
        $this->assertStringContainsString('30000010436', $tile);
        $this->assertStringContainsString('30000010435', $tile);
        $this->assertStringNotContainsString('30000010499', $tile);

        $this->get('/api/v1/level-crossings/tiles/3/9/9.mvt')->assertNotFound();
    }

    public function test_is_documented_with_parameters_errors_and_examples(): void
    {
        $paths = $this->getJson('/docs/api.json')->assertOk()->json('paths');

        $list = $paths['/level-crossings']['get'] ?? null;
        $this->assertNotNull($list);
        $this->assertSame(['Signalisation et passages à niveau'], $list['tags']);
        $this->assertGreaterThan(400, mb_strlen($list['description']));
        $this->assertEqualsCanonicalizing(['line', 'group', 'class', 'commune', 'bbox', 'per_page', 'page'], array_column($list['parameters'], 'name'));
        foreach ($list['parameters'] as $p) {
            $this->assertNotEmpty($p['description'], $p['name']);
        }
        $this->assertEqualsCanonicalizing(['200', '422', '429'], array_map('strval', array_keys($list['responses'])));
        $this->assertArrayHasKey('/lines/{line}/level-crossings', $paths);
        $this->assertArrayHasKey('/level-crossings/tiles/{z}/{x}/{y}.mvt', $paths);
        $this->assertArrayHasKey('/eras/{year}/signalling/tiles/{z}/{x}/{y}.mvt', $paths);
    }

    public function test_a_shared_sncf_code_gives_each_crossing_to_one_line_only(): void
    {
        // `lines.code` n'est pas unique : une fiche plus récente du réseau principal l'emporte sur
        // une fiche secondaire plus ancienne qui porte le même code.
        Line::where('slug', 'rfn-70000')->update(['network' => 'secondaire']);
        Line::factory()->create(['slug' => 'rfn-70000-bis', 'code' => '070000', 'name' => 'Fiche principale', 'network' => 'principal']);

        $list = $this->getJson('/api/v1/level-crossings?'.http_build_query(['line' => 'rfn-70000-bis']))->assertOk();
        $this->assertSame(['30000010435', '30000010436', '30000010499'], array_column($list->json('data'), 'id'));
        $this->assertSame(['rfn-70000-bis'], array_values(array_unique(array_column(array_column($list->json('data'), 'line'), 'slug'))));

        $all = $this->getJson('/api/v1/level-crossings')->assertOk()->assertJsonPath('meta.total', 4);
        $this->assertSame(4, count(array_unique(array_column($all->json('data'), 'id'))), 'aucun PN dupliqué');

        $this->getJson('/api/v1/level-crossings?line=rfn-70000')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/lines/rfn-70000/level-crossings')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/lines/rfn-70000-bis/level-crossings')->assertOk()->assertJsonPath('total', 3);

        [$x, $y] = self::tileOf(49.06, 3.60, 11);
        $tile = (string) $this->get("/api/v1/level-crossings/tiles/11/{$x}/{$y}.mvt")->assertOk()->getContent();
        $this->assertSame(1, substr_count($tile, '30000010436'), 'un seul point par PN dans la tuile');
        $this->assertStringContainsString('rfn-70000-bis', $tile);
        $this->assertStringNotContainsString('"rfn-70000"', $tile);
    }

    public function test_the_same_network_falls_back_to_the_oldest_sheet(): void
    {
        Line::factory()->create(['slug' => 'rfn-70000-bis', 'code' => '070000', 'name' => 'Doublon']);

        $this->getJson('/api/v1/lines/rfn-70000-bis/level-crossings')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/lines/rfn-70000/level-crossings')->assertOk()->assertJsonPath('total', 3);
        $this->assertSame('rfn-70000', LevelCrossing::where('id_if', '30000010436')->first()->line->slug);
    }

    public function test_the_line_list_reuses_the_known_line_without_a_query_per_crossing(): void
    {
        $line = Line::where('slug', 'rfn-70000')->firstOrFail();

        DB::enableQueryLog();
        $crossings = app(LineLevelCrossings::class)->list($line);
        foreach ($crossings as $crossing) {
            $this->assertSame('rfn-70000', $crossing->line->slug);
        }
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(3, $crossings->count());
        $this->assertSame(1, $queries, 'une requête pour les PN, aucune pour leur ligne');
    }

    public function test_an_unknown_class_has_no_label_instead_of_a_raw_translation_key(): void
    {
        $this->seedLevelCrossing('30000030001', '070000', 200000, 49.1, 3.7, 'CLASSE 50');

        $items = array_column($this->getJson('/api/v1/lines/rfn-70000/level-crossings')->assertOk()->json('level_crossings'), null, 'id');
        $unknown = $items['30000030001'];

        $this->assertSame(50, $unknown['class']);
        $this->assertNull($unknown['class_label']);
        $this->assertNull($unknown['group']);
    }

    public function test_tiles_are_cached_until_the_import_invalidates_them(): void
    {
        [$x, $y] = self::tileOf(49.06, 3.60, 11);
        $tiles = app(LevelCrossingTiles::class);
        $this->assertStringNotContainsString('30000030002', $tiles->tile(11, $x, $y));

        $this->seedLevelCrossing('30000030002', '070000', 113000, 49.061, 3.601);
        $this->assertStringNotContainsString('30000030002', $tiles->tile(11, $x, $y), 'tuile en cache');

        $tiles->invalidate();
        $this->assertStringContainsString('30000030002', $tiles->tile(11, $x, $y));
    }

    /** @return array{0: int, 1: int} colonne et rangée XYZ d'un point */
    private static function tileOf(float $lat, float $lon, int $z): array
    {
        $n = 2 ** $z;
        $x = (int) floor(($lon + 180) / 360 * $n);
        $y = (int) floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n);

        return [$x, $y];
    }
}
