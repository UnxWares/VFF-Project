<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\ReadsTranslations;
use Tests\TestCase;

/**
 * Choix de la langue (middleware SetLocale) : cookie posé par /locale/{code},
 * puis langue du profil du membre connecté, puis Accept-Language, puis la
 * langue par défaut. Props partagées traduites et attribut <html lang>.
 *
 * Bug corrigé : la langue choisie dans le profil (locale_preference) était
 * enregistrée mais jamais appliquée à l'interface.
 */
class LocaleResolutionTest extends TestCase
{
    use ActsAsRoles;
    use ReadsTranslations;
    use RefreshDatabase;

    /** @return array<string, array{0: ?string, 1: ?string, 2: string}> */
    public static function resolutions(): array
    {
        return [
            'cookie seul' => ['de', null, 'de'],
            'cookie prioritaire sur l\'en-tête' => ['en', 'de-DE,de;q=0.9', 'en'],
            'cookie invalide ignoré' => ['xx', 'de', 'de'],
            'cookie vide ignoré' => ['', 'en', 'en'],
            'en-tête allemand régional' => [null, 'de-DE,de;q=0.9', 'de'],
            'en-tête anglais' => [null, 'en-GB', 'en'],
            'poids q respecté' => [null, 'en;q=0.1, de;q=0.9', 'de'],
            'langue non gérée → première gérée' => [null, 'es-ES,es;q=0.9', 'fr'],
            'en-tête absent → défaut' => [null, '', 'fr'],
        ];
    }

    #[DataProvider('resolutions')]
    public function test_locale_resolution_order(?string $cookie, ?string $acceptLanguage, string $expected): void
    {
        $request = $this->withHeader('Accept-Language', (string) $acceptLanguage);
        if ($cookie !== null) {
            $request = $request->withCookie('locale', $cookie);
        }

        $response = $request->get('/whoarewe')->assertOk();

        $response->assertInertia(fn (Assert $page) => $page->where('locale', $expected));
        $this->assertStringContainsString('<html lang="'.$expected.'">', $response->getContent());
    }

    public function test_member_language_preference_applies_without_cookie(): void
    {
        $user = $this->member(null, ['locale_preference' => 'de']);

        $this->actingAs($user)->withHeader('Accept-Language', 'en')->get('/whoarewe')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'de'));

        // Un choix explicite (cookie de /locale/{code}) reste prioritaire.
        $this->actingAs($user)->withCookie('locale', 'en')->get('/whoarewe')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_unsupported_stored_preference_is_ignored(): void
    {
        $user = $this->member(null, ['locale_preference' => 'it']);

        $this->actingAs($user)->withHeader('Accept-Language', 'en')->get('/whoarewe')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_profile_language_change_takes_effect_immediately(): void
    {
        $user = $this->member(null, ['locale_preference' => 'fr']);

        $this->actingAs($user)->withCookie('locale', 'fr')
            ->put('/profile', ['name' => $user->name, 'locale_preference' => 'de'])
            ->assertCookie('locale', 'de')
            ->assertSessionHas('success', __('auth.profile_updated', [], 'de'));
    }

    /** @return array<string, array{0: string}> */
    public static function locales(): array
    {
        return ['français' => ['fr'], 'anglais' => ['en'], 'allemand' => ['de']];
    }

    #[DataProvider('locales')]
    public function test_shared_props_are_translated_for_each_locale(string $locale): void
    {
        $this->withCookie('locale', $locale)->get('/donate')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Donate')
                ->where('locale', $locale)
                ->where('available_locales', ['fr', 'en', 'de'])
                ->where('app.name', config('app.name'))
                ->where('translations_url', function (string $u) use ($locale): bool {
                    $t = $this->dictionary($u);

                    return ($t['nav.maps'] ?? null) === __('nav.maps', [], $locale)
                        && ($t['auth.failed'] ?? null) === __('auth.failed', [], $locale)
                        && ! array_key_exists('nav', $t);
                }));
    }

    public function test_translations_differ_between_locales(): void
    {
        $fr = $this->dictionary($this->withCookie('locale', 'fr')->get('/donate')->viewData('page')['props']['translations_url']);
        $de = $this->dictionary($this->withCookie('locale', 'de')->get('/donate')->viewData('page')['props']['translations_url']);

        $this->assertNotSame($fr['auth.failed'], $de['auth.failed']);
        $this->assertSame('Identifiants invalides.', $fr['auth.failed']);
    }

    public function test_locale_cookie_is_encrypted_http_only_and_lax(): void
    {
        $response = $this->get('/locale/de');
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'locale');

        $this->assertNotNull($cookie);
        $this->assertNotSame('de', $cookie->getValue(), 'cookie chiffré (EncryptCookies)');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $response->assertCookie('locale', 'de');
    }

    public function test_locale_route_only_accepts_supported_codes(): void
    {
        foreach (['fr', 'en', 'de'] as $code) {
            $this->get("/locale/{$code}")->assertRedirect('/')->assertCookie('locale', $code);
        }
        foreach (['FR', 'es', 'fr-FR', '../fr', 'de%00'] as $code) {
            $this->get('/locale/'.$code)->assertNotFound();
        }
    }
}
