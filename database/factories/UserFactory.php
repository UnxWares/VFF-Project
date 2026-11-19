<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Mot de passe « password » haché une seule fois par processus. */
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Haché au coût courant (BCRYPT_ROUNDS) : un hash figé de coût 10 est refusé par
            // le cast `hashed` quand les tests tournent à BCRYPT_ROUNDS=4.
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Visitor->value,
            'bio' => null,
            'avatar_path' => null,
            'locale_preference' => 'fr',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function contributor(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Contributor->value,
        ]);
    }

    public function validator(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Validator->value,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin->value,
        ]);
    }
}
