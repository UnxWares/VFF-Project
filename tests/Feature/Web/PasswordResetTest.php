<?php

namespace Tests\Feature\Web;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Mot de passe oublié (broker `users`) : envoi du lien, page de saisie,
 * réinitialisation, et messages du broker traduits via lang/{fr,en,de}/auth.php
 * (clés auth.passwords.*).
 *
 * Bug corrigé : le contrôleur traduisait le statut brut (« passwords.sent ») ;
 * faute de lang/fr/passwords.php, l'utilisateur francophone recevait le texte
 * anglais du framework (« We have emailed your password reset link. »).
 */
class PasswordResetTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
    }

    public function test_forgot_page_renders(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword')->where('locale', 'fr'));
    }

    public function test_reset_page_exposes_token_and_email_from_the_link(): void
    {
        $this->get('/reset-password/jeton-123?email=marie%40example.org')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'jeton-123')
                ->where('email', 'marie@example.org'));

        $this->get('/reset-password/jeton-123')
            ->assertInertia(fn (Assert $page) => $page->where('email', null));
    }

    public function test_link_is_sent_with_a_translated_confirmation(): void
    {
        Notification::fake();
        $user = $this->member(null, ['email' => 'marie@example.org']);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'marie@example.org'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Un lien de réinitialisation vous a été envoyé par e-mail.');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'marie@example.org']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function sentMessages(): array
    {
        return [
            'anglais' => ['en', 'auth.passwords.sent'],
            'allemand' => ['de', 'auth.passwords.sent'],
        ];
    }

    #[DataProvider('sentMessages')]
    public function test_confirmation_follows_the_locale(string $locale, string $key): void
    {
        Notification::fake();
        $this->member(null, ['email' => 'marie@example.org']);

        $this->withCookie('locale', $locale)
            ->post('/forgot-password', ['email' => 'marie@example.org'])
            ->assertSessionHas('success', __($key, [], $locale));
        $this->assertNotSame($key, __($key, [], $locale), "Clé {$key} absente en {$locale}");
    }

    public function test_unknown_email_gets_a_translated_error(): void
    {
        Notification::fake();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'personne@example.org'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email' => 'Aucun compte ne correspond à cette adresse e-mail.'])
            ->assertSessionHasInput('email', 'personne@example.org');

        Notification::assertNothingSent();
    }

    public function test_a_second_request_within_the_minute_is_throttled(): void
    {
        Notification::fake();
        $user = $this->member(null, ['email' => 'marie@example.org']);

        $this->post('/forgot-password', ['email' => 'marie@example.org'])->assertSessionHasNoErrors();
        $this->post('/forgot-password', ['email' => 'marie@example.org'])
            ->assertSessionHasErrors(['email' => 'Trop de demandes, patientez un peu avant de réessayer.']);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function invalidForgotPayloads(): array
    {
        return [
            'vide' => [[]],
            'mal formé' => [['email' => 'marie']],
            'tableau' => [['email' => ['marie@example.org']]],
        ];
    }

    #[DataProvider('invalidForgotPayloads')]
    public function test_forgot_validation(array $payload): void
    {
        Notification::fake();
        $this->post('/forgot-password', $payload)->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_the_password_and_rotates_the_remember_token(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->member(null, ['email' => 'marie@example.org', 'remember_token' => 'ancien-jeton']);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'marie@example.org',
            'password' => 'nouveau-mdp',
            'password_confirmation' => 'nouveau-mdp',
        ])
            ->assertRedirect('/login')
            ->assertSessionHas('success', 'Votre mot de passe a été réinitialisé.');

        $user->refresh();
        $this->assertTrue(Hash::check('nouveau-mdp', $user->password));
        $this->assertNotSame('ancien-jeton', $user->remember_token);
        $this->assertSame(60, strlen((string) $user->remember_token));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'marie@example.org']);
        Event::assertDispatched(PasswordReset::class, fn ($e) => $e->user->is($user));

        // Le nouveau mot de passe ouvre bien une session.
        $this->post('/login', ['email' => 'marie@example.org', 'password' => 'nouveau-mdp'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_token_is_refused_with_a_translated_error(): void
    {
        $user = $this->member(null, ['email' => 'marie@example.org']);
        Password::createToken($user);

        $this->from('/reset-password/faux')
            ->post('/reset-password', [
                'token' => 'jeton-invente',
                'email' => 'marie@example.org',
                'password' => 'nouveau-mdp',
                'password_confirmation' => 'nouveau-mdp',
            ])
            ->assertRedirect('/reset-password/faux')
            ->assertSessionHasErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré.']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_a_token_cannot_reset_another_account(): void
    {
        $marie = $this->member(null, ['email' => 'marie@example.org']);
        $paul = $this->member(null, ['email' => 'paul@example.org']);
        $token = Password::createToken($marie);

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'paul@example.org',
            'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp',
        ])->assertSessionHasErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré.']);

        $this->assertTrue(Hash::check('password', $paul->fresh()->password));
        $this->assertTrue(Hash::check('password', $marie->fresh()->password));
    }

    public function test_unknown_account_on_reset(): void
    {
        $this->post('/reset-password', [
            'token' => 'x', 'email' => 'personne@example.org',
            'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp',
        ])->assertSessionHasErrors(['email' => 'Aucun compte ne correspond à cette adresse e-mail.']);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: array<int, string>}> */
    public static function invalidResetPayloads(): array
    {
        $ok = ['token' => 't', 'email' => 'marie@example.org', 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp'];

        return [
            'vide' => [[], ['token', 'email', 'password']],
            'mot de passe court' => [[...$ok, 'password' => 'court', 'password_confirmation' => 'court'], ['password']],
            'confirmation différente' => [[...$ok, 'password_confirmation' => 'autre-chose'], ['password']],
            'e-mail invalide' => [[...$ok, 'email' => 'marie'], ['email']],
        ];
    }

    #[DataProvider('invalidResetPayloads')]
    public function test_reset_validation(array $payload, array $errors): void
    {
        $this->post('/reset-password', $payload)->assertSessionHasErrors($errors);
    }
}
