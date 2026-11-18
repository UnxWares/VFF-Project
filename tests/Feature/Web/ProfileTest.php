<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Profil (GET/PUT /profile) : réservé aux membres, props en liste blanche,
 * seuls nom / bio / langue sont modifiables (ni rôle, ni réputation, ni
 * e-mail, ni avatar : aucun envoi de fichier n'est accepté ici).
 */
class ProfileTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->put('/profile', ['name' => 'X', 'locale_preference' => 'fr'])->assertRedirect('/login');
        $this->getJson('/profile')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_profile_page_exposes_exactly_the_whitelisted_fields(): void
    {
        $user = $this->member('contributor', [
            'name' => 'Jeanne', 'email' => 'jeanne@example.org', 'bio' => 'Passionnée de la ligne des Causses.',
            'reputation' => 42, 'locale_preference' => 'de',
        ]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Profile')
                ->has('profile', fn (Assert $p) => $p
                    ->where('id', $user->id)
                    ->where('name', 'Jeanne')
                    ->where('email', 'jeanne@example.org')
                    ->where('bio', 'Passionnée de la ligne des Causses.')
                    ->where('role', 'contributor')
                    ->where('reputation', 42)
                    ->where('avatar_path', null)
                    ->where('locale_preference', 'de')
                    ->whereType('email_verified_at', 'string')
                    ->where('created_at', $user->created_at->toIso8601String())
                    ->missing('password')
                    ->missing('remember_token')));
    }

    public function test_update_changes_name_bio_and_language(): void
    {
        $user = $this->member(null, ['name' => 'Ancien', 'locale_preference' => 'fr']);

        $this->actingAs($user)
            ->withCookie('locale', 'fr')
            ->from('/profile')
            ->put('/profile', ['name' => 'Nouveau nom', 'bio' => 'Cheminot retraité.', 'locale_preference' => 'en'])
            ->assertRedirect('/profile')
            ->assertSessionHasNoErrors()
            // Confirmation dans la langue tout juste choisie.
            ->assertSessionHas('success', 'Profile updated.')
            ->assertCookie('locale', 'en');

        $user->refresh();
        $this->assertSame('Nouveau nom', $user->name);
        $this->assertSame('Cheminot retraité.', $user->bio);
        $this->assertSame('en', $user->locale_preference);
    }

    public function test_bio_can_be_cleared(): void
    {
        $user = $this->member(null, ['bio' => 'À effacer']);

        $this->actingAs($user)->put('/profile', ['name' => $user->name, 'bio' => null, 'locale_preference' => 'fr'])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->bio);
    }

    public function test_update_cannot_escalate_privileges_or_change_protected_fields(): void
    {
        $user = $this->member(null, ['email' => 'moi@example.org', 'reputation' => 3]);

        $this->actingAs($user)->put('/profile', [
            'name' => 'Moi',
            'locale_preference' => 'fr',
            'role' => 'admin',
            'reputation' => 100000,
            'email' => 'admin@vff-project.org',
            'password' => 'piratage',
            'avatar_path' => '/etc/passwd',
            'avatar' => UploadedFile::fake()->create('avatar.png', 12, 'image/png'),
            'id' => 999,
        ])->assertSessionHasNoErrors();

        $fresh = User::findOrFail($user->id);
        $this->assertSame('Moi', $fresh->name);
        $this->assertSame('visitor', $fresh->role->value);
        $this->assertSame(3, $fresh->reputation);
        $this->assertSame('moi@example.org', $fresh->email);
        $this->assertNull($fresh->avatar_path);
        $this->assertSame($user->getRawOriginal('password'), $fresh->getRawOriginal('password'));
        $this->assertSame(1, User::count());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: array<int, string>}> */
    public static function invalidUpdates(): array
    {
        return [
            'nom absent' => [['locale_preference' => 'fr'], ['name']],
            'nom trop long' => [['name' => str_repeat('n', 256), 'locale_preference' => 'fr'], ['name']],
            'bio trop longue' => [['name' => 'A', 'bio' => str_repeat('b', 2001), 'locale_preference' => 'fr'], ['bio']],
            'langue absente' => [['name' => 'A'], ['locale_preference']],
            'langue non gérée' => [['name' => 'A', 'locale_preference' => 'es'], ['locale_preference']],
            'langue injectée' => [['name' => 'A', 'locale_preference' => "fr'; DROP TABLE users; --"], ['locale_preference']],
        ];
    }

    #[DataProvider('invalidUpdates')]
    public function test_update_validation(array $payload, array $errors): void
    {
        $user = $this->member(null, ['name' => 'Inchangé', 'locale_preference' => 'de']);

        $this->actingAs($user)->put('/profile', $payload)->assertSessionHasErrors($errors);

        $fresh = $user->fresh();
        $this->assertSame('Inchangé', $fresh->name);
        $this->assertSame('de', $fresh->locale_preference);
    }

    public function test_locale_rule_lists_exactly_the_supported_languages(): void
    {
        $user = $this->member();

        foreach (['fr', 'en', 'de'] as $locale) {
            $this->actingAs($user)->put('/profile', ['name' => 'A', 'locale_preference' => $locale])->assertSessionHasNoErrors();
            $this->assertSame($locale, $user->fresh()->locale_preference);
        }
    }

    public function test_profile_update_only_accepts_put(): void
    {
        $this->actingAs($this->member())->post('/profile', ['name' => 'A', 'locale_preference' => 'fr'])->assertStatus(405);
    }

    public function test_mass_assignment_of_role_is_ignored_at_model_level(): void
    {
        $user = User::create([
            'name' => 'Direct', 'email' => 'direct@example.org', 'password' => 'motdepasse8',
            'role' => 'admin', 'reputation' => 5000,
        ]);

        $fresh = $user->fresh();
        $this->assertSame('visitor', $fresh->role->value, 'role ne doit pas être affectable en masse');
        $this->assertSame(0, $fresh->reputation);
        $this->assertNotContains('role', $user->getFillable());
        $this->assertNotContains('reputation', $user->getFillable());
        $this->assertContains('password', $user->getHidden());
        $this->assertContains('remember_token', $user->getHidden());
        $this->assertArrayNotHasKey('password', $fresh->toArray());
    }
}
