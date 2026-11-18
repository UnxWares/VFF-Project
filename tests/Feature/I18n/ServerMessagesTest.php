<?php

namespace Tests\Feature\I18n;

use App\Models\User;
use App\Support\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Chantier « i18n-serveur » : tout texte produit par le serveur (erreurs de
 * validation, échec de connexion, pages d'erreur, courriels) suit la langue
 * choisie par la page (cookie `locale`, middleware SetLocale).
 */
class ServerMessagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> */
    public static function requiredName(): array
    {
        return [
            'fr' => ['fr', 'Le champ nom est obligatoire.'],
            'en' => ['en', 'The name field is required.'],
            'de' => ['de', 'Das Feld Name ist erforderlich.'],
        ];
    }

    #[DataProvider('requiredName')]
    public function test_register_validation_error_follows_the_locale(string $locale, string $expected): void
    {
        $this->withCookie('locale', $locale)
            ->post('/register', ['email' => 'a@example.org', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])
            ->assertSessionHasErrors(['name' => $expected]);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function invalidEmail(): array
    {
        return [
            'fr' => ['fr', 'Le champ adresse électronique doit être une adresse électronique valide.'],
            'en' => ['en', 'The email address field must be a valid email address.'],
            'de' => ['de', 'Das Feld E-Mail-Adresse muss eine gültige E-Mail-Adresse sein.'],
        ];
    }

    #[DataProvider('invalidEmail')]
    public function test_login_validation_error_follows_the_locale(string $locale, string $expected): void
    {
        $this->withCookie('locale', $locale)
            ->post('/login', ['email' => 'pas-un-courriel', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => $expected]);
    }

    public function test_password_confirmation_error_uses_the_attribute_name(): void
    {
        $this->withCookie('locale', 'fr')
            ->post('/register', ['name' => 'Paul', 'email' => 'p@example.org', 'password' => 'motdepasse1', 'password_confirmation' => 'autre'])
            ->assertSessionHasErrors(['password' => 'La confirmation du champ mot de passe ne correspond pas.']);
    }

    public function test_email_already_taken_is_translated(): void
    {
        User::factory()->create(['email' => 'pris@example.org']);

        $this->withCookie('locale', 'de')
            ->post('/register', ['name' => 'Paul', 'email' => 'pris@example.org', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])
            ->assertSessionHasErrors(['email' => 'Der Wert für E-Mail-Adresse ist bereits vergeben.']);
    }

    public function test_auth_failed_is_translated_in_three_languages(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $this->assertNotSame('auth.failed', __('auth.failed'), $locale);
            $this->assertNotSame('auth.password', __('auth.password'), $locale);
            $this->assertStringContainsString(':seconds', Lang::get('auth.throttle', [], $locale, false) ?: '');
        }
        $this->assertSame('These credentials do not match our records.', __('auth.failed', [], 'en'));
        $this->assertSame('Identifiants invalides.', __('auth.failed', [], 'fr'));
        $this->assertSame('Ungültige Anmeldedaten.', __('auth.failed', [], 'de'));
    }

    public function test_login_failure_is_sent_to_the_front_in_the_page_language(): void
    {
        $this->withCookie('locale', 'de')
            ->post('/login', ['email' => 'inconnu@example.org', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Ungültige Anmeldedaten.']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function notFoundTitles(): array
    {
        return [
            'fr' => ['fr', 'Page introuvable'],
            'en' => ['en', 'Not Found'],
            'de' => ['de', 'Seite nicht gefunden'],
        ];
    }

    #[DataProvider('notFoundTitles')]
    public function test_unknown_url_renders_a_translated_404(string $locale, string $title): void
    {
        $this->withCookie('locale', $locale)
            ->get('/cette-page-nexiste-pas')
            ->assertNotFound()
            ->assertSee('<title>'.$title.'</title>', false)
            ->assertSee('<html lang="'.$locale.'"', false);
    }

    public function test_http_error_titles_exist_in_french_and_german(): void
    {
        foreach (['Unauthorized', 'Payment Required', 'Forbidden', 'Not Found', 'Page Expired', 'Too Many Requests', 'Server Error', 'Service Unavailable'] as $key) {
            foreach (['fr', 'de'] as $locale) {
                $this->assertNotSame($key, __($key, [], $locale), "{$key} non traduit en {$locale}");
            }
        }
    }

    public function test_password_reset_mail_strings_are_translated(): void
    {
        $this->assertSame('Réinitialiser le mot de passe', __('Reset Password', [], 'fr'));
        $this->assertSame('Passwort zurücksetzen', __('Reset Password', [], 'de'));
        $this->assertSame('Ce lien de réinitialisation du mot de passe expirera dans 60 minutes.', __('This password reset link will expire in :count minutes.', ['count' => 60], 'fr'));
    }

    public function test_api_validation_errors_stay_in_english(): void
    {
        $this->withCookie('locale', 'fr')
            ->getJson('/api/v1/route')
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'required'));
    }

    public function test_server_only_namespaces_are_not_sent_to_the_front(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $keys = array_keys(Translations::load($locale));
            foreach (Translations::SERVER_ONLY as $namespace) {
                $this->assertEmpty(
                    array_filter($keys, fn ($k) => str_starts_with($k, $namespace.'.')),
                    "{$namespace} ne doit pas partir au front ({$locale})",
                );
            }
            $this->assertContains('auth.failed', $keys);
        }
    }

    public function test_server_only_files_have_the_same_keys_in_three_languages(): void
    {
        foreach (Translations::SERVER_ONLY as $namespace) {
            $reference = $this->flatKeys(require lang_path("fr/{$namespace}.php"));
            foreach (['en', 'de'] as $locale) {
                $keys = $this->flatKeys(require lang_path("{$locale}/{$namespace}.php"));
                $this->assertSame([], array_values(array_diff($reference, $keys)), "{$namespace} : clés manquantes en {$locale}");
                $this->assertSame([], array_values(array_diff($keys, $reference)), "{$namespace} : clés en trop en {$locale}");
            }
        }
    }

    public function test_json_framework_strings_have_the_same_keys_in_french_and_german(): void
    {
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);
        $de = json_decode((string) file_get_contents(lang_path('de.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(array_keys($fr), array_keys($de));
    }

    /** @return list<string> */
    private function flatKeys(array $items, string $prefix = ''): array
    {
        $keys = [];
        foreach ($items as $k => $v) {
            is_array($v) ? array_push($keys, ...$this->flatKeys($v, "{$prefix}{$k}.")) : $keys[] = "{$prefix}{$k}";
        }

        return $keys;
    }
}
