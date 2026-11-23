<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\ReadsTranslations;
use Tests\TestCase;

/**
 * Pages publiques (Inertia) : pas d'erreur 500, bon composant Svelte (dont le
 * fichier doit exister dans resources/js/Pages), props partagées présentes.
 */
class PublicPagesTest extends TestCase
{
    use BuildsRailNetwork;
    use ReadsTranslations;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function staticPages(): array
    {
        return [
            'qui sommes-nous' => ['/whoarewe', 'WhoAreWe'],
            'contribuer' => ['/contribute', 'Contribute'],
            'dons' => ['/donate', 'Donate'],
            'mentions légales' => ['/legal', 'Legal'],
            'confidentialité' => ['/privacy', 'Privacy'],
            'CGU' => ['/use-conditions', 'UseConditions'],
            'accessibilité' => ['/accessibility', 'Accessibility'],
            'glossaire' => ['/glossary', 'Glossary'],
            'patrimoine protégé' => ['/heritage', 'Heritage/Index'],
            'doc API' => ['/api', 'ApiDocs'],
            'crédits tech' => ['/credits/tech', 'Credits/Tech'],
            'crédits médias' => ['/credits/media', 'Credits/Media'],
            'carte intégrable' => ['/embed/maps', 'Embed/Maps'],
            'connexion' => ['/login', 'Auth/Login'],
            'inscription' => ['/register', 'Auth/Register'],
            'mot de passe oublié' => ['/forgot-password', 'Auth/ForgotPassword'],
            'matériels' => ['/materials', 'Materials/Index'],
        ];
    }

    #[DataProvider('staticPages')]
    public function test_static_page_renders(string $uri, string $component): void
    {
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->get($uri)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('locale', 'fr')
                ->has('available_locales', 3)
                ->where('translations_url', fn (string $u) => str_starts_with($u, '/i18n/fr/'))
                ->missing('translations')
                ->has('auth')
                ->where('auth.user', null)
            );
    }

    public function test_home_exposes_network_stats_and_threatened_lines(): void
    {
        $this->line->update([
            'is_threatened' => true,
            'advocacy' => ['title' => 'Sauvons la ligne', 'message' => '…', 'cta_label' => 'Agir', 'cta_url' => 'https://example.org'],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('stats', fn (Assert $stats) => $stats
                    ->whereType('lines_count', 'integer')
                    ->whereType('stations_count', 'integer')
                    ->where('materials_count', 1)
                    ->where('eras_count', 2)
                    ->whereType('total_km', 'integer')
                )
                ->has('threatened', 1, fn (Assert $line) => $line
                    ->where('slug', $this->line->slug)
                    ->where('name', $this->line->name)
                    ->where('advocacy.title', 'Sauvons la ligne')
                    ->etc()
                )
            );
    }

    public function test_maps_lists_eras_with_data_flag(): void
    {
        $this->get('/maps')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Maps')
                ->where('currentEraYear', 2030)
                ->whereType('dataVersion', 'string')
                ->has('eras', 2)
                ->where('eras.0.year', 1960)
                ->where('eras.0.has_data', true)
                ->where('eras.1.year', 2030)
                ->where('eras.1.is_current', true)
            );
    }

    public function test_material_page_lists_its_lines(): void
    {
        $this->get('/materials/'.$this->material->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Materials/Show')
                ->where('material.slug', $this->material->slug)
                ->has('lines', 1)
                ->where('lines.0.slug', $this->line->slug)
            );
    }

    public function test_station_page_lists_serving_lines_and_neighbors(): void
    {
        $this->get('/stations/'.$this->origin->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stations/Show')
                ->where('station.slug', 'paris-saint-lazare')
                ->where('station.status', 'active')
                ->whereType('station.lat', 'double')
                ->whereType('station.lng', 'double')
                ->where('lines.0.slug', $this->line->slug)
                ->where('lines.0.active_current', true)
                ->has('neighbors')
                ->etc()
            );
    }

    public function test_unknown_station_is_404(): void
    {
        $this->get('/stations/gare-inexistante')->assertNotFound();
    }

    public function test_locale_switch_sets_cookie_and_translates_shared_props(): void
    {
        $this->get('/locale/de')
            ->assertRedirect('/')
            ->assertCookie('locale', 'de');

        $this->withCookie('locale', 'de')
            ->get('/whoarewe')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'de')
                // Le dictionnaire est une map PLATE (« nav.maps » => …) : pas de notation pointée.
                ->where('translations_url', fn (string $u) => ($this->dictionary($u)['nav.maps'] ?? null) === __('nav.maps', [], 'de')
                    && __('nav.maps', [], 'de') !== __('nav.maps', [], 'fr'))
            );
    }

    public function test_unsupported_locale_is_404(): void
    {
        $this->get('/locale/xx')->assertNotFound();
    }

    public function test_health_endpoint(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_is_plain_text_without_external_resource(): void
    {
        $response = $this->get('/up')->assertOk();

        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('OK', $response->getContent());
        $this->assertStringNotContainsString('http', (string) $response->getContent());
    }

    public function test_health_endpoint_answers_503_in_maintenance(): void
    {
        $this->artisan('down')->assertSuccessful();

        try {
            $this->get('/up')->assertStatus(503);
        } finally {
            $this->artisan('up')->assertSuccessful();
        }
    }

    public function test_csp_log_channel_writes_outside_the_real_file_during_tests(): void
    {
        $path = (string) config('logging.channels.csp.path');

        $this->assertStringContainsString('logs/testing/', $path);
        $this->assertStringNotContainsString(storage_path('logs/csp.log'), $path);
    }

    public function test_client_errors_log_channel_writes_outside_the_real_file_during_tests(): void
    {
        $path = (string) config('logging.channels.client-errors.path');

        $this->assertStringContainsString('logs/testing/', $path);
        $this->assertStringNotContainsString(storage_path('logs/client-errors.log'), $path);
    }
}
