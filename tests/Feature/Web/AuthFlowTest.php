<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Connexion, inscription, déconnexion (guard `web`, session Inertia) :
 * redirections, messages d'erreur traduits, rôle forcé à l'inscription,
 * limitation des tentatives de connexion, routes réservées aux invités.
 */
class AuthFlowTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('auth.user', null)
                ->where('flash.success', null)
                ->where('flash.error', null));
    }

    public function test_register_page_renders_for_guests(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register')->where('auth.user', null));
    }

    /** @return array<string, array{0: string}> */
    public static function guestOnlyPages(): array
    {
        return [
            'connexion' => ['/login'],
            'inscription' => ['/register'],
            'mot de passe oublié' => ['/forgot-password'],
            'réinitialisation' => ['/reset-password/un-jeton'],
        ];
    }

    #[DataProvider('guestOnlyPages')]
    public function test_authenticated_users_are_sent_home_from_guest_pages(string $uri): void
    {
        $this->actingAs($this->member())->get($uri)->assertRedirect('/');
    }

    public function test_login_with_valid_credentials_authenticates_and_redirects_home(): void
    {
        $user = $this->member('contributor', ['email' => 'marie@example.org']);

        $this->post('/login', ['email' => 'marie@example.org', 'password' => 'password'])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_redirects_to_the_page_the_guest_wanted(): void
    {
        $this->member(null, ['email' => 'paul@example.org']);

        $this->get('/profile')->assertRedirect('/login');
        $this->post('/login', ['email' => 'paul@example.org', 'password' => 'password'])
            ->assertRedirect('/profile');
    }

    public function test_login_ignores_a_redirect_parameter_pointing_elsewhere(): void
    {
        $this->member(null, ['email' => 'paul@example.org']);

        $response = $this->post('/login?redirect=https://evil.example', [
            'email' => 'paul@example.org',
            'password' => 'password',
            'redirect' => 'https://evil.example/phishing',
        ]);

        $response->assertRedirect('/');
        $this->assertSame('localhost', parse_url((string) $response->headers->get('Location'), PHP_URL_HOST));
    }

    public function test_remember_me_sets_the_recaller_cookie(): void
    {
        $this->member(null, ['email' => 'paul@example.org']);

        $this->post('/login', ['email' => 'paul@example.org', 'password' => 'password', 'remember' => '1'])
            ->assertRedirect('/')
            ->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_login_without_remember_sets_no_recaller_cookie(): void
    {
        $this->member(null, ['email' => 'paul@example.org']);

        $this->post('/login', ['email' => 'paul@example.org', 'password' => 'password'])
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    public function test_wrong_password_returns_the_translated_failure_and_keeps_only_the_email(): void
    {
        $this->member(null, ['email' => 'paul@example.org']);

        $this->from('/login')
            ->withCookie('locale', 'fr')
            ->post('/login', ['email' => 'paul@example.org', 'password' => 'mauvais'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Identifiants invalides.'])
            ->assertSessionHasInput('email', 'paul@example.org')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_failure_message_follows_the_locale(): void
    {
        $this->member(null, ['email' => 'paul@example.org']);

        $this->withCookie('locale', 'en')
            ->post('/login', ['email' => 'paul@example.org', 'password' => 'mauvais'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    }

    public function test_unknown_email_gives_the_same_message_as_a_wrong_password(): void
    {
        $this->withCookie('locale', 'fr')
            ->post('/login', ['email' => 'inconnu@example.org', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Identifiants invalides.']);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: array<int, string>}> */
    public static function invalidLogins(): array
    {
        return [
            'rien' => [[], ['email', 'password']],
            'e-mail mal formé' => [['email' => 'pas-un-email', 'password' => 'x'], ['email']],
            'mot de passe absent' => [['email' => 'a@example.org'], ['password']],
            'e-mail tableau' => [['email' => ['a@example.org'], 'password' => 'x'], ['email']],
        ];
    }

    #[DataProvider('invalidLogins')]
    public function test_login_validation(array $payload, array $errors): void
    {
        $this->post('/login', $payload)->assertSessionHasErrors($errors);
        $this->assertGuest();
    }

    public function test_login_validation_message_is_the_translator_one(): void
    {
        $this->post('/login', ['password' => 'x'])
            ->assertSessionHasErrors(['email' => __('validation.required', ['attribute' => __('validation.attributes.email')])]);
    }

    public function test_login_is_throttled_after_five_failures_per_email_and_ip(): void
    {
        $this->member(null, ['email' => 'cible@example.org']);
        // Sans cookie, le client de test envoie « Accept-Language: en-us » (Symfony).
        $this->withCookie('locale', 'fr');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'cible@example.org', 'password' => 'essai-'.$i])
                ->assertSessionHasErrors(['email' => 'Identifiants invalides.']);
        }

        // Même avec le BON mot de passe : bloqué, message traduit avec le délai.
        $response = $this->post('/login', ['email' => 'cible@example.org', 'password' => 'password']);
        $response->assertSessionHasErrors('email');
        $this->assertMatchesRegularExpression(
            '/^Trop de tentatives\. Réessayez dans \d+ secondes\.$/u',
            session('errors')->first('email'),
        );
        $this->assertGuest();

        // Le compteur est propre à l'adresse : une autre reste utilisable.
        $other = $this->member(null, ['email' => 'autre@example.org']);
        $this->post('/login', ['email' => 'autre@example.org', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($other);
    }

    public function test_throttle_key_ignores_email_case(): void
    {
        $this->member(null, ['email' => 'cible@example.org']);

        foreach (['CIBLE@example.org', 'Cible@Example.org', 'cible@EXAMPLE.org', 'cible@example.ORG', 'cible@example.org'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'faux']);
        }

        $this->post('/login', ['email' => 'cible@example.org', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login_resets_the_failure_counter(): void
    {
        $this->member(null, ['email' => 'cible@example.org']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => 'cible@example.org', 'password' => 'faux']);
        }
        $this->post('/login', ['email' => 'cible@example.org', 'password' => 'password'])->assertRedirect('/');
        $this->post('/logout');

        // Compteur remis à zéro : 4 nouveaux échecs ne bloquent toujours pas.
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => 'cible@example.org', 'password' => 'faux']);
        }
        $this->post('/login', ['email' => 'cible@example.org', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_register_creates_a_visitor_with_a_hashed_password(): void
    {
        $this->withCookie('locale', 'de')
            ->post('/register', [
                'name' => 'Jeanne Rail',
                'email' => 'jeanne@example.org',
                'password' => 'motdepasse8',
                'password_confirmation' => 'motdepasse8',
                'accept_terms' => '1',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('success', __('auth.registered', [], 'de'));

        $user = User::where('email', 'jeanne@example.org')->sole();
        $this->assertSame('visitor', $user->role->value);
        $this->assertSame(0, $user->reputation);
        $this->assertSame('de', $user->locale_preference);
        $this->assertNotSame('motdepasse8', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('motdepasse8', $user->password));
        $this->assertAuthenticatedAs($user);
        // Acceptation des CGU : date de l'inscription et version en vigueur (config/legal.php).
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertTrue($user->terms_accepted_at->isToday());
        $this->assertSame(config('legal.versions.use-conditions'), $user->terms_version);
    }

    /** @return array<string, array{0: mixed}> */
    public static function refusedTerms(): array
    {
        return ['absente' => [null], 'décochée' => ['0'], 'fantaisiste' => ['peut-être']];
    }

    #[DataProvider('refusedTerms')]
    public function test_register_requires_the_terms_box(mixed $value): void
    {
        $payload = ['name' => 'Jeanne', 'email' => 'jeanne@example.org', 'password' => 'motdepasse8', 'password_confirmation' => 'motdepasse8'];
        if ($value !== null) {
            $payload['accept_terms'] = $value;
        }

        $this->withCookie('locale', 'fr')->post('/register', $payload)
            ->assertSessionHasErrors(['accept_terms' => 'Cochez la case pour accepter les conditions générales d’utilisation.']);
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_register_cannot_grant_a_role_reputation_or_other_privileged_field(): void
    {
        $this->post('/register', [
            'name' => 'Pirate',
            'email' => 'pirate@example.org',
            'password' => 'motdepasse8',
            'password_confirmation' => 'motdepasse8',
            'accept_terms' => '1',
            'role' => 'admin',
            'terms_version' => '1999-01-01',
            'reputation' => 99999,
            'email_verified_at' => '2020-01-01 00:00:00',
            'avatar_path' => '../../.env',
        ])->assertRedirect('/');

        $user = User::where('email', 'pirate@example.org')->sole();
        $this->assertSame('visitor', $user->role->value);
        $this->assertSame(0, $user->reputation);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->avatar_path);
        $this->assertSame(config('legal.versions.use-conditions'), $user->terms_version);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: array<int, string>}> */
    public static function invalidRegistrations(): array
    {
        $ok = ['name' => 'Jeanne', 'email' => 'jeanne@example.org', 'password' => 'motdepasse8', 'password_confirmation' => 'motdepasse8'];

        return [
            'rien' => [[], ['name', 'email', 'password']],
            'nom trop long' => [[...$ok, 'name' => str_repeat('a', 256)], ['name']],
            'e-mail invalide' => [[...$ok, 'email' => 'jeanne'], ['email']],
            'e-mail trop long' => [[...$ok, 'email' => str_repeat('a', 250).'@example.org'], ['email']],
            'mot de passe court' => [[...$ok, 'password' => 'court7!', 'password_confirmation' => 'court7!'], ['password']],
            'confirmation différente' => [[...$ok, 'password_confirmation' => 'autrechose'], ['password']],
        ];
    }

    #[DataProvider('invalidRegistrations')]
    public function test_register_validation(array $payload, array $errors): void
    {
        $this->post('/register', $payload)->assertSessionHasErrors($errors);
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_register_refuses_an_email_already_taken(): void
    {
        $this->member(null, ['email' => 'pris@example.org']);

        $this->post('/register', [
            'name' => 'Doublon', 'email' => 'pris@example.org',
            'password' => 'motdepasse8', 'password_confirmation' => 'motdepasse8',
        ])->assertSessionHasErrors(['email' => __('validation.custom.email.unique')]);

        $this->assertSame(1, User::count());
    }

    public function test_logout_requires_post_and_invalidates_the_session(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get('/logout')->assertStatus(405);

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest('web');
    }

    public function test_guest_cannot_logout(): void
    {
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_shared_auth_props_never_expose_secrets(): void
    {
        $user = $this->member('validator', ['name' => 'Valérie', 'email' => 'valerie@example.org', 'locale_preference' => 'en']);

        $this->actingAs($user)->get('/whoarewe')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => 'Valérie',
                'email' => 'valerie@example.org',
                'role' => 'validator',
                'reputation' => $user->reputation,
                'avatar_path' => null,
                'locale_preference' => 'en',
            ]));
    }
}
