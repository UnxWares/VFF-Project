<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Messages de validation des formulaires de compte : traduits (jamais l'anglais du framework en
 * français ou en allemand), et l'erreur de confirmation dit clairement que les deux mots de passe
 * diffèrent. Les formulaires contrôlent d'abord dans le navigateur ; ces messages sont ceux du serveur.
 */
class AuthValidationMessagesTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function mismatchMessages(): array
    {
        return [
            'français' => ['fr', 'fr', 'Les deux mots de passe ne sont pas identiques.'],
            'allemand' => ['de', 'de', 'Die beiden Passwörter stimmen nicht überein.'],
            'anglais' => ['en', 'en', 'The two passwords do not match.'],
        ];
    }

    #[DataProvider('mismatchMessages')]
    public function test_register_confirmation_mismatch_is_translated(string $cookie, string $locale, string $expected): void
    {
        $this->withCookie('locale', $cookie)
            ->post('/register', [
                'name' => 'Marie',
                'email' => 'marie@example.org',
                'password' => 'motdepasse1',
                'password_confirmation' => 'motdepasse2',
            ])
            ->assertSessionHasErrors(['password' => $expected]);
    }

    public function test_empty_login_is_reported_in_french(): void
    {
        $this->withCookie('locale', 'fr')->post('/login', [])
            ->assertSessionHasErrors([
                'email' => 'Le champ adresse e-mail est obligatoire.',
                'password' => 'Le champ mot de passe est obligatoire.',
            ]);
    }

    public function test_short_password_names_the_minimum_in_german(): void
    {
        $this->withCookie('locale', 'de')->post('/register', [
            'name' => 'Marie',
            'email' => 'marie@example.org',
            'password' => 'court',
            'password_confirmation' => 'court',
        ])->assertSessionHasErrors(['password' => 'Das Feld Passwort muss mindestens 8 Zeichen lang sein.']);
    }

    public function test_already_used_email_has_a_readable_message(): void
    {
        $this->member(null, ['email' => 'marie@example.org']);

        $this->withCookie('locale', 'fr')->post('/register', [
            'name' => 'Marie',
            'email' => 'marie@example.org',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
        ])->assertSessionHasErrors(['email' => 'Cette adresse e-mail est déjà associée à un compte.']);
    }
}
