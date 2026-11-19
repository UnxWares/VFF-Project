<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Support\ApiCodeSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exemples JavaScript (`fetch`) et Python (`requests`) joints à la spécification OpenAPI
 * (`x-codeSamples`, affichés par Scalar à côté de curl) : présents sur les points d'accès
 * principaux, cohérents avec les paramètres documentés, et dont les requêtes sont acceptées par l'API.
 */
class ApiCodeSamplesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array<string, mixed>> opérations de la spécification, par identifiant */
    private function operations(): array
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $operations = [];
        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $operation) {
                $operations[$operation['operationId']] = $operation + ['_path' => $path];
            }
        }

        return $operations;
    }

    public function test_main_endpoints_carry_javascript_and_python_samples(): void
    {
        $operations = $this->operations();

        $this->assertEqualsCanonicalizing(
            ['lines.index', 'lines.show', 'stations.index', 'events.index', 'media.index', 'materials.index', 'accidents.index', 'search', 'route', 'eras.compare'],
            array_keys(ApiCodeSamples::SAMPLES),
        );
        foreach (array_keys(ApiCodeSamples::SAMPLES) as $id) {
            $samples = $operations[$id]['x-codeSamples'] ?? [];
            $this->assertSame(['JavaScript', 'Python'], array_column($samples, 'lang'), "{$id} : exemples");
            $this->assertStringContainsString('await fetch(url)', $samples[0]['source']);
            $this->assertStringContainsString('requests.get(', $samples[1]['source']);
            $this->assertStringContainsString('raise_for_status()', $samples[1]['source']);
            foreach ($samples as $sample) {
                $this->assertStringContainsString(ApiCodeSamples::BASE_URL.ApiCodeSamples::SAMPLES[$id]['path'], $sample['source'], "{$id} : adresse");
            }
        }
    }

    public function test_samples_use_documented_parameters_and_documented_paths(): void
    {
        $operations = $this->operations();
        foreach (ApiCodeSamples::SAMPLES as $id => $sample) {
            $queryParameters = array_column(array_filter($operations[$id]['parameters'] ?? [], fn (array $p) => $p['in'] === 'query'), 'name');
            foreach (array_keys($sample['query']) as $name) {
                $this->assertContains($name, $queryParameters, "{$id} : le paramètre « {$name} » n'est pas documenté");
            }
            // Le chemin réel correspond au gabarit de l'opération : /lines/{line} => /lines/rfn-420000.
            $pattern = '#^'.preg_replace('/\\\{[^}]+\\\}/', '[^/]+', preg_quote($operations[$id]['_path'], '#')).'$#';
            $this->assertMatchesRegularExpression($pattern, $sample['path'], "{$id} : chemin");
        }
    }

    public function test_sample_requests_are_accepted_by_the_api(): void
    {
        // Les filtres par ligne refusent un slug inconnu (422) : la ligne des exemples existe.
        Line::factory()->create(['slug' => 'rfn-420000', 'code' => '420000']);
        foreach (ApiCodeSamples::SAMPLES as $id => $sample) {
            $status = $this->getJson('/api/v1'.$sample['path'].($sample['query'] !== [] ? '?'.http_build_query($sample['query']) : ''))->status();
            // Peu de données : 200 pour les listes, 404 pour une fiche, une gare ou une ère absentes ; jamais 422 ni 500.
            $this->assertContains($status, [200, 404], "{$id} : HTTP {$status}");
        }
    }

    public function test_generated_programs_are_stable_and_runnable_text(): void
    {
        $javascript = ApiCodeSamples::javascript('events.index');
        $this->assertStringContainsString("department: \"29\",\n    type: \"closure_passenger\"", $javascript);
        $this->assertStringContainsString('throw new Error(`HTTP ${response.status}`)', $javascript);

        $python = ApiCodeSamples::python('route');
        $this->assertStringContainsString('"era": 1930,', $python, 'entier non guillemeté en Python');
        $this->assertStringContainsString('timeout=30', $python);

        $this->assertStringContainsString('http://127.0.0.1:8000/api/v1/lines', ApiCodeSamples::forOperation('lines.index', 'http://127.0.0.1:8000/api/v1')[0]['source']);
    }

    public function test_samples_use_no_long_dash(): void
    {
        foreach (array_keys(ApiCodeSamples::SAMPLES) as $id) {
            foreach (ApiCodeSamples::forOperation($id) as $sample) {
                $this->assertDoesNotMatchRegularExpression('/[—–]| -- /u', $sample['source'], "{$id}");
            }
        }
    }
}
