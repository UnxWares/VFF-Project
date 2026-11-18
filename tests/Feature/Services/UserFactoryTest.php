<?php

namespace Tests\Feature\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Factory des comptes : utilisable sans mot de passe explicite (hash au coût
 * courant, accepté par le cast `hashed`), rôles et réputation cohérents.
 */
class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_user_has_the_password_password_and_the_visitor_role(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(UserRole::Visitor, $user->role);
        $this->assertSame(0, $user->reputation);
        $this->assertNotNull($user->email_verified_at);
        $this->assertArrayNotHasKey('password', $user->toArray(), 'jamais sérialisé');
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_role_states(): void
    {
        $this->assertSame(UserRole::Contributor, User::factory()->contributor()->create()->role);
        $this->assertSame(UserRole::Validator, User::factory()->validator()->create()->role);
        $this->assertSame(UserRole::Admin, User::factory()->admin()->create()->role);
        $this->assertNull(User::factory()->unverified()->create()->email_verified_at);
    }

    public function test_login_works_with_the_factory_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }
}
