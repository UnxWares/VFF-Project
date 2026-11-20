<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * GET /locale/{code} : pose le cookie de langue et renvoie vers la page
 * d'origine (Referer), UNIQUEMENT si elle est sur le même hôte. Sinon la route
 * serait une redirection ouverte (lien piégé vers un site d'hameçonnage).
 */
class LocaleRedirectTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function safeReferers(): array
    {
        return [
            'page du site' => ['http://localhost/lines', '/lines'],
            'page avec requête' => ['http://localhost/maps?era=1930&z=7', '/maps?era=1930&z=7'],
            'racine' => ['http://localhost/', '/'],
            'hôte sans chemin' => ['http://localhost', '/'],
            'fragment ignoré' => ['http://localhost/lines/abc#carte', '/lines/abc'],
        ];
    }

    #[DataProvider('safeReferers')]
    public function test_redirects_back_to_a_page_of_the_same_site(string $referer, string $target): void
    {
        $this->get('/locale/en', ['Referer' => $referer])
            ->assertRedirect($target)
            ->assertCookie('locale', 'en');
    }

    /** @return array<string, array{0: ?string}> */
    public static function unsafeReferers(): array
    {
        return [
            'aucun Referer' => [null],
            'autre site' => ['https://evil.example/phishing'],
            'sous-domaine trompeur' => ['http://localhost.evil.example/x'],
            'identifiants avant l\'hôte' => ['http://localhost@evil.example/x'],
            'chemin protocole-relatif' => ['http://localhost//evil.example/x'],
            'chemin avec antislash' => ['http://localhost/\\evil.example/x'],
            'schéma javascript' => ['javascript:alert(1)'],
            'Referer illisible' => ['http:///'],
            'chemin relatif seul' => ['lines'],
        ];
    }

    #[DataProvider('unsafeReferers')]
    public function test_never_redirects_outside_the_site(?string $referer): void
    {
        $response = $this->get('/locale/de', $referer === null ? [] : ['Referer' => $referer]);

        $response->assertRedirect('/')->assertCookie('locale', 'de');
        $location = $response->headers->get('Location');
        $this->assertSame('localhost', parse_url($location, PHP_URL_HOST), "Location : {$location}");
        $this->assertDoesNotMatchRegularExpression('#^https?://localhost[/\\\\]{2}#', $location);
    }

    public function test_unsupported_locale_does_not_set_a_cookie(): void
    {
        // Route contrainte aux langues supportées : 404, pas de cookie.
        $this->get('/locale/xx', ['Referer' => 'http://localhost/lines'])->assertNotFound()->assertCookieMissing('locale');
    }

    public function test_cookie_lasts_one_year(): void
    {
        $cookie = collect($this->get('/locale/fr')->headers->getCookies())->first(fn ($c) => $c->getName() === 'locale');

        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(now()->addYear()->getTimestamp(), $cookie->getExpiresTime(), 86400 * 2);
    }
}
