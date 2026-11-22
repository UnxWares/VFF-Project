<?php

namespace Tests\Feature\Monitoring;

use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Page de santé détaillée pour le moniteur de disponibilité (GET /up/details). */
class HealthDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('health.era-computed-at');
    }

    public function test_sans_jeton_configure_seul_l_etat_est_donne(): void
    {
        config(['monitoring.health_token' => null]);

        $response = $this->getJson('/up/details');

        $response->assertOk()->assertJsonStructure(['status'])->assertJsonMissingPath('checks')->assertJsonMissingPath('release');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_avec_jeton_configure_une_demande_sans_jeton_est_refusee(): void
    {
        config(['monitoring.health_token' => 'secret-de-test']);

        $this->getJson('/up/details')->assertUnauthorized()->assertExactJson(['status' => 'unauthorized']);
        $this->getJson('/up/details?token=faux')->assertUnauthorized();
        $this->withToken('faux')->getJson('/up/details')->assertUnauthorized();
    }

    public function test_le_jeton_donne_le_detail_en_en_tete_ou_en_parametre(): void
    {
        config(['monitoring.health_token' => 'secret-de-test', 'monitoring.release' => 'v1.2.3']);
        Era::factory()->create(['is_current' => true]);

        $a = $this->withToken('secret-de-test')->getJson('/up/details');
        $a->assertOk()->assertJsonPath('release', 'v1.2.3')->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonStructure(['status', 'release', 'checks' => ['database', 'valkey', 'queue', 'disk' => ['free_mb', 'used_percent'], 'era']]);
        $this->getJson('/up/details?token=secret-de-test')->assertOk();
    }

    public function test_la_reponse_ne_contient_ni_chemin_ni_nom_de_machine(): void
    {
        config(['monitoring.health_token' => 'secret-de-test']);

        $body = $this->withToken('secret-de-test')->get('/up/details')->getContent();

        $this->assertStringNotContainsString(base_path(), (string) $body);
        $this->assertStringNotContainsString((string) gethostname(), (string) $body);
    }

    public function test_base_coupee_donne_503_down(): void
    {
        $normal = config('database.default');
        config([
            'monitoring.health_token' => 'secret-de-test',
            'database.connections.coupee' => [
                'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'rien', 'username' => 'x', 'password' => 'x',
                'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'sslmode' => 'prefer',
            ],
            'database.default' => 'coupee',
        ]);

        $response = $this->withToken('secret-de-test')->getJson('/up/details');
        // Retour à la vraie base avant la fin du test (RefreshDatabase annule sa transaction dessus).
        config(['database.default' => $normal]);

        $response->assertStatus(503)->assertJsonPath('status', 'down')->assertJsonPath('checks.database.status', 'down');
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    public function test_seuil_depasse_reste_en_200_degrade(): void
    {
        config(['monitoring.health_token' => 'secret-de-test', 'monitoring.disk_min_free_mb' => PHP_INT_MAX]);

        $this->withToken('secret-de-test')->getJson('/up/details')->assertOk()
            ->assertJsonPath('status', 'degraded')->assertJsonPath('checks.disk.status', 'degraded');
    }

    public function test_debit_limite_par_adresse(): void
    {
        config(['monitoring.health_per_minute' => 2, 'monitoring.health_token' => null]);

        $this->getJson('/up/details')->assertOk();
        $this->getJson('/up/details')->assertOk();
        $this->getJson('/up/details')->assertStatus(429);
    }
}
