<?php

namespace Tests\Feature\Api;

use App\Enums\AccidentLocationPrecision;
use App\Models\Accident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `GET /api/v1/accidents.geojson` : points des accidents localisés pour le calque « Accidents »
 * de la carte. Accident sans lieu absent (rien d'inventé), lieu à la commune près signalé,
 * filtre `to`, cache HTTP, documentation OpenAPI complète.
 */
class AccidentMapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Accident::factory()->on('2013-07-12')->at(48.6072222, 2.3022222)->toReview()->create([
            'slug' => '2013-07-12-bretigny-sur-orge', 'kind' => 'derailment', 'commune_name' => 'Brétigny-sur-Orge',
            'department_code' => '91', 'deaths' => 7, 'injuries' => 192,
        ]);
        Accident::factory()->on('1891-07-26')->at(48.84, 2.42)->create([
            'slug' => '1891-07-26-saint-mande', 'kind' => 'collision', 'commune_name' => 'Saint-Mandé',
            'department_code' => '94', 'deaths' => 43, 'injuries' => null,
            'location_precision' => AccidentLocationPrecision::Commune->value,
        ]);
        // Lieu inconnu : jamais sur la carte.
        Accident::factory()->on('1914-01-01')->create(['slug' => '1914-01-01-sarry', 'geom' => null, 'location_precision' => null]);
    }

    public function test_returns_a_compact_feature_collection_of_located_accidents_in_date_order(): void
    {
        $response = $this->getJson('/api/v1/accidents.geojson')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=600, public');

        $response->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(2, 'features')
            ->assertJsonPath('features.0.properties.slug', '1891-07-26-saint-mande')
            ->assertJsonPath('features.1.properties.slug', '2013-07-12-bretigny-sur-orge');

        $bretigny = $response->json('features.1');
        $this->assertSame('Point', $bretigny['geometry']['type']);
        $this->assertSame([2.30222, 48.60722], $bretigny['geometry']['coordinates']);
        $this->assertSame([
            'slug' => '2013-07-12-bretigny-sur-orge', 'date' => '2013-07-12', 'date_precision' => 'day', 'year' => 2013,
            'kind' => 'derailment', 'place' => 'Brétigny-sur-Orge', 'department' => '91', 'precision' => 'exact',
            'deaths' => 7, 'injuries' => 192, 'to_review' => true,
        ], $bretigny['properties']);
    }

    public function test_marks_a_place_known_only_by_its_commune_and_keeps_an_unknown_toll_null(): void
    {
        $saintMande = $this->getJson('/api/v1/accidents.geojson')->assertOk()->json('features.0.properties');

        $this->assertSame('commune', $saintMande['precision']);
        $this->assertNull($saintMande['injuries']);
        $this->assertFalse($saintMande['to_review']);
    }

    public function test_filters_accidents_up_to_a_year(): void
    {
        $this->getJson('/api/v1/accidents.geojson?to=1900')
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.year', 1891);
        $this->getJson('/api/v1/accidents.geojson?to=2013')->assertOk()->assertJsonCount(2, 'features');
    }

    /** @return array<string, array{string}> */
    public static function invalidYears(): array
    {
        return ['trop tôt' => ['1799'], 'trop tard' => ['2101'], 'pas un nombre' => ['mille']];
    }

    #[DataProvider('invalidYears')]
    public function test_rejects_a_year_out_of_bounds(string $year): void
    {
        $this->getJson("/api/v1/accidents.geojson?to={$year}")->assertStatus(422)->assertJsonValidationErrors('to');
    }

    public function test_is_documented_with_a_described_parameter_errors_and_an_example(): void
    {
        // Le point du chemin (« .geojson ») empêche la notation pointée de json() : lecture en deux temps.
        $operation = $this->getJson('/docs/api.json')->assertOk()->json('paths')['/accidents.geojson']['get'] ?? null;

        $this->assertNotNull($operation, 'Point d\'accès absent de la spécification');
        $this->assertLessThanOrEqual(60, mb_strlen($operation['summary']));
        $this->assertGreaterThan(200, mb_strlen($operation['description']));
        $this->assertSame(['Accidents'], $operation['tags']);
        $this->assertCount(1, $operation['parameters']);
        $this->assertSame('to', $operation['parameters'][0]['name']);
        $this->assertNotEmpty($operation['parameters'][0]['description']);
        $this->assertSame(1950, $operation['parameters'][0]['example']);
        // 401 et 403 : jeton invalide ou d'un compte suspendu (ApiAuthDocumentation).
        $this->assertEqualsCanonicalizing(['200', '401', '403', '422', '429'], array_map('strval', array_keys($operation['responses'])));
        foreach (['200', '401', '403', '422', '429'] as $code) {
            $this->assertNotEmpty($operation['responses'][$code]['content']['application/json']['schema']['examples'] ?? null, "réponse {$code} sans exemple");
        }
    }
}
