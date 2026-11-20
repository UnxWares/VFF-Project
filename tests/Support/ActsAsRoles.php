<?php

namespace Tests\Support;

use App\Models\User;

/**
 * Comptes de test par rôle (visitor, contributor, validator, admin).
 *
 * Le hash statique de UserFactory (coût 10) est refusé par le cast `hashed`
 * quand BCRYPT_ROUNDS=4 (phpunit.xml) : on fournit un mot de passe en clair,
 * haché au coût du test.
 */
trait ActsAsRoles
{
    protected function member(?string $role = null, array $attributes = []): User
    {
        $factory = User::factory();
        if ($role !== null && $role !== 'visitor') {
            $factory = $factory->{$role}();
        }

        return $factory->create(['password' => 'password', ...$attributes]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonValidatorRoles(): array
    {
        return [
            'visiteur' => ['visitor'],
            'contributeur' => ['contributor'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validatorRoles(): array
    {
        return [
            'validateur' => ['validator'],
            'administrateur' => ['admin'],
        ];
    }
}
