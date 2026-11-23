<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * API REST publique : entrées hors bornes ou malformées (jamais de 500),
 * en-têtes de cache et CORS, limite de débit, authentification Sanctum,
 * recherche avec caractères spéciaux.
 */
class ApiRobustnessTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    // ─── /api/user (Sanctum) ─────────────────────────────────────────────

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_user_endpoint_returns_the_token_owner_without_secrets(): void
    {
        // Mot de passe en clair : le hash figé de UserFactory (coût 10) est refusé
        // par le cast « hashed » quand BCRYPT_ROUNDS=4.
        $user = User::factory()->contributor()->create(['name' => 'Ada', 'email' => 'ada@example.org', 'password' => 'secret-pass']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', 'Ada')
            ->assertJsonPath('email', 'ada@example.org')
            ->assertJsonPath('role', 'contributor');

        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertArrayNotHasKey('remember_token', $response->json());
    }

    // ─── En-têtes de cache / CORS ────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string}> */
    public static function cachedEndpoints(): array
    {
        return [
            'lignes GeoJSON' => ['/api/v1/eras/2030/lines.geojson', 'max-age=60, public'],
            'gares GeoJSON' => ['/api/v1/eras/2030/stations.geojson', 'max-age=60, public'],
            'tuile MVT' => ['/api/v1/eras/2030/tiles/8/129/88.mvt', 'max-age=60, public'],
            'GTFS' => ['/api/v1/eras/2030/gtfs.zip', 'max-age=3600, public'],
            'recherche' => ['/api/v1/search?q=paris', 'max-age=30, public'],
            'historique' => ['/api/v1/lines/paris-saint-lazare-versailles-rd/history', 'max-age=300, public'],
            'arrêts' => ['/api/v1/lines/paris-saint-lazare-versailles-rd/stops', 'max-age=300, public'],
            'signalisation' => ['/api/v1/lines/paris-saint-lazare-versailles-rd/signalling', 'max-age=600, public'],
            'médias' => ['/api/v1/lines/paris-saint-lazare-versailles-rd/media', 'max-age=300, public'],
        ];
    }

    #[DataProvider('cachedEndpoints')]
    public function test_public_endpoints_are_cacheable(string $uri, string $cacheControl): void
    {
        $this->get($uri)->assertOk()->assertHeader('Cache-Control', $cacheControl);
    }

    public function test_errors_are_not_cached_publicly(): void
    {
        $response = $this->getJson('/api/v1/lines/inconnue/history')->assertNotFound();
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_tiles_allow_cross_origin_use(): void
    {
        $this->get('/api/v1/eras/2030/tiles/8/129/88.mvt')
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    // ─── Limite de débit (throttle:public-api, 300 sans jeton) ────────────────────────────────

    public function test_rate_limit_headers_then_429_after_300_requests_per_minute(): void
    {
        $this->getJson('/api/v1/search?q=a')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '300')
            ->assertHeader('X-RateLimit-Remaining', '299');
        $this->getJson('/api/v1/search?q=a')->assertHeader('X-RateLimit-Remaining', '298');

        for ($i = 3; $i <= 300; $i++) {
            $this->getJson('/api/v1/search?q=a');
        }

        $this->getJson('/api/v1/search?q=a')
            ->assertStatus(429)
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('Retry-After');

        // La limite est commune à toute l'API v1 (même groupe de routes).
        $this->getJson('/api/v1/lines/paris-saint-lazare-versailles-rd/history')->assertStatus(429);
    }

    // ─── Ère et tuiles hors bornes ───────────────────────────────────────

    /** @return array<string, array{0: string}> */
    public static function outOfRangeTiles(): array
    {
        return [
            'zoom 15 (la carte s\'arrête au zoom 14)' => ['/api/v1/eras/2030/tiles/15/0/0.mvt'],
            'zoom 23' => ['/api/v1/eras/2030/tiles/23/0/0.mvt'],
            'zoom 99' => ['/api/v1/eras/2030/tiles/99/0/0.mvt'],
            'x hors grille' => ['/api/v1/eras/2030/tiles/8/256/88.mvt'],
            'y hors grille' => ['/api/v1/eras/2030/tiles/8/129/256.mvt'],
            'zoom 0, x = 1' => ['/api/v1/eras/2030/tiles/0/1/0.mvt'],
            'entier géant' => ['/api/v1/eras/2030/tiles/8/99999999999999999999/0.mvt'],
        ];
    }

    #[DataProvider('outOfRangeTiles')]
    public function test_tile_outside_the_grid_is_404_not_500(string $uri): void
    {
        $this->get($uri)->assertNotFound();
    }

    public function test_tile_at_grid_limits_is_valid(): void
    {
        $this->get('/api/v1/eras/2030/tiles/0/0/0.mvt')->assertOk()->assertHeader('Content-Type', 'application/x-protobuf');
        $this->get('/api/v1/eras/2030/tiles/14/16383/16383.mvt')->assertOk()->assertContent('');
    }

    /** @return array<string, array{0: string}> */
    public static function eraEndpoints(): array
    {
        return [
            'lignes' => ['lines.geojson'],
            'gares' => ['stations.geojson'],
            'tuile' => ['tiles/8/129/88.mvt'],
            'gtfs' => ['gtfs.zip'],
        ];
    }

    #[DataProvider('eraEndpoints')]
    public function test_absurd_era_year_is_404_not_500(string $suffix): void
    {
        $this->get("/api/v1/eras/99999999999999999999/{$suffix}")->assertNotFound();
        $this->get("/api/v1/eras/0/{$suffix}")->assertNotFound();
        $this->get("/api/v1/eras/1955/{$suffix}")->assertNotFound();
    }

    /** @return array<string, array{string}> */
    public static function pagedLists(): array
    {
        return [
            'journal' => ['/api/v1/journal'],
            'accidents' => ['/api/v1/accidents'],
            'passages à niveau' => ['/api/v1/level-crossings'],
        ];
    }

    #[DataProvider('pagedLists')]
    public function test_huge_page_number_is_422_not_500(string $uri): void
    {
        // (page - 1) × per_page ne tient plus dans un entier : array_slice ou l'OFFSET SQL levaient une erreur 500.
        $this->getJson("{$uri}?page=9223372036854775807")->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson("{$uri}?page=1000000")->assertOk();
    }

    // ─── PK depuis un point ──────────────────────────────────────────────

    public function test_pk_validates_coordinates_with_messages(): void
    {
        $slug = $this->line->slug;

        $this->getJson("/api/v1/lines/{$slug}/pk?lat=abc&lng=2.3")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lat'])
            ->assertJsonMissingValidationErrors(['lng']);

        // Hors bornes WGS 84 : 422 (avant : erreur PostGIS → 500).
        $this->getJson("/api/v1/lines/{$slug}/pk?lat=91&lng=2.3")->assertUnprocessable()->assertJsonValidationErrors(['lat']);
        $this->getJson("/api/v1/lines/{$slug}/pk?lat=-90.5&lng=2.3")->assertUnprocessable()->assertJsonValidationErrors(['lat']);
        $this->getJson("/api/v1/lines/{$slug}/pk?lat=48.8&lng=180.01")->assertUnprocessable()->assertJsonValidationErrors(['lng']);
        $this->getJson("/api/v1/lines/{$slug}/pk?lat=48.8&lng=2.3&era=abc")->assertUnprocessable()->assertJsonValidationErrors(['era']);

        $this->getJson("/api/v1/lines/{$slug}/pk?lat=48.8&lng=2.3&era=1850")->assertNotFound();
        $this->getJson('/api/v1/lines/inconnue/pk?lat=48.8&lng=2.3')->assertNotFound();
    }

    public function test_pk_snaps_the_far_end_and_reports_the_offset(): void
    {
        $response = $this->getJson("/api/v1/lines/{$this->line->slug}/pk?lat=48.8091&lng=2.1347")->assertOk();

        $total = $response->json('total_m');
        $this->assertIsInt($total);
        $this->assertEqualsWithDelta(15_400, $total, 600, 'longueur géodésique Saint-Lazare - Versailles');
        $this->assertSame($total, $response->json('pk_m'));
        $this->assertMatchesRegularExpression('/^\d+\+\d{3}$/', $response->json('pk_label'));
        $this->assertSame(sprintf('%d+%03d', intdiv($total, 1000), $total % 1000), $response->json('pk_label'));
        $this->assertEqualsWithDelta(0.0, $response->json('snap.distance_from_input_m'), 1.0);

        // Point à l'écart : distance au tracé non nulle, PK intermédiaire.
        $mid = $this->getJson("/api/v1/lines/{$this->line->slug}/pk?lat=48.86&lng=2.24")->assertOk();
        $this->assertGreaterThan(500, $mid->json('snap.distance_from_input_m'));
        $this->assertGreaterThan(0, $mid->json('pk_m'));
        $this->assertLessThan($total, $mid->json('pk_m'));
    }

    public function test_pk_without_geometry_at_this_era_is_a_clean_404(): void
    {
        Era::factory()->create(['year' => 1900]);

        $this->getJson("/api/v1/lines/{$this->line->slug}/pk?lat=48.8&lng=2.3&era=1900")
            ->assertNotFound()
            ->assertExactJson(['error' => 'no_geometry']);
    }

    // ─── Recherche : caractères spéciaux ─────────────────────────────────

    /** @return array<string, array{0: string}> */
    public static function hostileQueries(): array
    {
        return [
            'apostrophe' => ["l'"],
            'apostrophes SQL' => ["' OR 1=1 --"],
            'pourcents' => ['%%'],
            'soulignés' => ['__'],
            'antislash' => ['\\\\'],
            'antislash final' => ['ab\\'],
            'point-virgule' => ['; DROP TABLE lines; --'],
            'unicode' => ['北京站'],
            'emoji' => ['🚂🚃'],
            'octet nul' => ["ab\0cd"],
            'UTF-8 invalide' => ["ab\xFF\xFE"],
            'tabulations' => ["\tversailles\n"],
            'très long' => [str_repeat('x', 5000)],
        ];
    }

    #[DataProvider('hostileQueries')]
    public function test_search_never_fails_on_special_characters(string $q): void
    {
        $this->getJson('/api/v1/search?q='.rawurlencode($q))
            ->assertOk()
            ->assertJsonStructure(['lines', 'stations', 'materials', 'accidents']);

        $this->assertSame(1, Line::count(), 'la base est intacte');
    }

    public function test_search_ignores_non_string_queries(): void
    {
        $this->getJson('/api/v1/search?q[]=versailles&q[]=paris')
            ->assertOk()
            ->assertExactJson(['lines' => [], 'stations' => [], 'materials' => [], 'accidents' => [], 'communes' => []]);
    }

    public function test_search_strips_control_characters_but_keeps_the_words(): void
    {
        $this->getJson('/api/v1/search?q='.rawurlencode("\tversailles\0"))
            ->assertOk()
            ->assertJsonPath('stations.0.slug', 'versailles-rive-droite');
    }

    public function test_search_wildcards_are_literal_not_match_all(): void
    {
        // « %% » ou « __ » ne doivent pas tout renvoyer (jokers SQL échappés).
        $this->getJson('/api/v1/search?q=%25%25')->assertOk()->assertExactJson(['lines' => [], 'stations' => [], 'materials' => [], 'accidents' => [], 'communes' => []]);
        $this->getJson('/api/v1/search?q=__')->assertOk()->assertExactJson(['lines' => [], 'stations' => [], 'materials' => [], 'accidents' => [], 'communes' => []]);

        Station::factory()->create(['slug' => 'pourcent', 'name' => 'Halte 100% vélo', 'geom' => Point::makeGeodetic(45.0, 5.0)]);
        $this->getJson('/api/v1/search?q='.rawurlencode('100%'))
            ->assertOk()
            ->assertJsonCount(1, 'stations')
            ->assertJsonPath('stations.0.slug', 'pourcent');
    }

    public function test_search_handles_apostrophes_accents_and_case(): void
    {
        Station::factory()->create(['slug' => 'isle-adam', 'name' => 'L\'Isle-Adam - Parmain', 'city' => 'L\'Isle-Adam', 'geom' => Point::makeGeodetic(49.11, 2.22)]);
        Station::factory()->create(['slug' => 'saint-etienne', 'name' => 'Saint-Étienne-Châteaucreux', 'geom' => Point::makeGeodetic(45.44, 4.40)]);

        $this->getJson('/api/v1/search?q='.rawurlencode("l'isle"))
            ->assertOk()->assertJsonPath('stations.0.slug', 'isle-adam');
        $this->getJson('/api/v1/search?q='.rawurlencode('SAINT-ÉTIENNE'))
            ->assertOk()->assertJsonPath('stations.0.slug', 'saint-etienne');
        // Sans accent, comme on le tape souvent : « Saint-Etienne » trouve « Saint-Étienne ».
        $this->getJson('/api/v1/search?q='.rawurlencode('saint-etienne'))
            ->assertOk()->assertJsonPath('stations.0.slug', 'saint-etienne');
        $this->getJson('/api/v1/search?q='.rawurlencode('chateaucreux'))
            ->assertOk()->assertJsonPath('stations.0.name', 'Saint-Étienne-Châteaucreux');
    }

    public function test_search_result_shapes_types_limits_and_order(): void
    {
        foreach (range(1, 10) as $i) {
            Line::factory()->create(['name' => "Ligne de Versailles numéro {$i}", 'code' => sprintf('%06d', 900000 + $i)]);
            Station::factory()->create(['name' => "Versailles halte {$i}", 'geom' => Point::makeGeodetic(48.8, 2.1)]);
            Material::factory()->create(['name' => "Versailles automotrice {$i}"]);
        }
        Station::factory()->create(['slug' => 'porchefontaine', 'name' => 'Porchefontaine (Versailles)', 'geom' => Point::makeGeodetic(48.79, 2.15)]);

        $response = $this->getJson('/api/v1/search?q=%20versailles%20')->assertOk()
            ->assertJsonCount(8, 'lines')
            ->assertJsonCount(8, 'stations')
            ->assertJsonCount(5, 'materials')
            ->assertJsonStructure([
                'lines' => [['type', 'slug', 'name', 'code']],
                'stations' => [['type', 'slug', 'name', 'city', 'lat', 'lng']],
                'materials' => [['type', 'slug', 'name', 'kind', 'builder']],
            ]);

        $this->assertSame(['line'], array_unique(array_column($response->json('lines'), 'type')));
        $this->assertSame(['station'], array_unique(array_column($response->json('stations'), 'type')));
        $this->assertSame(['material'], array_unique(array_column($response->json('materials'), 'type')));
        $this->assertIsFloat($response->json('stations.0.lat'));
        $this->assertIsFloat($response->json('stations.0.lng'));

        // Gares commençant par la requête d'abord, puis ordre alphabétique.
        $names = array_column($response->json('stations'), 'name');
        $this->assertStringStartsWith('Versailles', $names[0]);
        $this->assertNotContains('Porchefontaine (Versailles)', $names, 'les préfixes passent avant');
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);
    }

    public function test_search_by_line_code(): void
    {
        $this->getJson('/api/v1/search?q=999')
            ->assertOk()
            ->assertJsonPath('lines.0.code', '999 000')
            ->assertJsonPath('lines.0.slug', $this->line->slug);
    }

    // ─── Arrêts, signalisation, historique : erreurs ─────────────────────

    public function test_line_endpoints_validate_era(): void
    {
        $slug = $this->line->slug;
        foreach (['stops', 'signalling', 'history'] as $endpoint) {
            $this->getJson("/api/v1/lines/{$slug}/{$endpoint}?era=1850")
                ->assertStatus($endpoint === 'history' ? 200 : 404);
        }
        $this->getJson("/api/v1/lines/{$slug}/signalling?era=abc")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['era']);
        $this->getJson("/api/v1/lines/{$slug}/signalling?era=1960")
            ->assertOk()
            ->assertJsonPath('era_year', 1960)
            ->assertExactJson([
                'line' => ['slug' => $slug, 'code' => '999 000', 'name' => $this->line->name],
                'era_year' => 1960,
                'ranges' => [],
            ]);
    }

    public function test_unknown_line_or_route_is_json_404(): void
    {
        $this->getJson('/api/v1/lines/inconnue/media')->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/api/v1/inexistant')->assertNotFound();
        $this->postJson('/api/v1/search', ['q' => 'paris'])->assertStatus(405);
    }
}
