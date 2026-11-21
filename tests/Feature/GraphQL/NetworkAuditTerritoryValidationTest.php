<?php

namespace Tests\Feature\GraphQL;

use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `segmentAudits` et `stationAudits` refusent un département ou une région inconnus, comme
 * `GET /api/v1/audits/segments` et `/stations` (422) et les outils MCP `segment_audits` et
 * `station_audits` : une page vide sans erreur laissait croire que le territoire n'avait aucun défaut.
 */
class NetworkAuditTerritoryValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Era::factory()->create(['year' => 2030, 'label' => 'Aujourd’hui', 'is_current' => true]);
    }

    /** @return array<string, array{string, string, string}> */
    public static function unknownTerritories(): array
    {
        return [
            'tronçons, département inconnu' => ['segmentAudits', 'department: "99"', 'department'],
            'tronçons, région inconnue' => ['segmentAudits', 'region: "99"', 'region'],
            'gares, département inconnu' => ['stationAudits', 'department: "99"', 'department'],
            'gares, région inconnue' => ['stationAudits', 'region: "99"', 'region'],
        ];
    }

    #[DataProvider('unknownTerritories')]
    public function test_an_unknown_territory_is_a_validation_error(string $field, string $args, string $key): void
    {
        $this->getJson('/api/v1/audits/'.($field === 'segmentAudits' ? 'segments' : 'stations').'?'.str_replace([': ', '"'], ['=', ''], $args))
            ->assertUnprocessable()->assertJsonValidationErrors($key);

        $errors = $this->postJson('/graphql', ['query' => "{ {$field}({$args}) { total } }"])->assertOk()->json('errors');
        $this->assertNotEmpty($errors, "{$field}({$args})");
        $this->assertArrayHasKey($key, $errors[0]['extensions']['validation'] ?? [], json_encode($errors) ?: '');
    }

    public function test_known_territories_and_a_lowercase_department_still_pass(): void
    {
        foreach (['department: "61"', 'department: "2a"', 'region: "28"'] as $args) {
            foreach (['segmentAudits', 'stationAudits'] as $field) {
                $this->postJson('/graphql', ['query' => "{ {$field}({$args}) { total } }"])
                    ->assertOk()->assertJsonMissingPath('errors')->assertJsonPath("data.{$field}.total", 0);
            }
        }
    }
}
