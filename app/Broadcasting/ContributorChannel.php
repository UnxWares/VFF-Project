<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * Canal privé `users.{id}.contributions` : l'état des contributions d'un compte, ouvert à ce compte
 * seulement (comme la page « Mes contributions »).
 */
final class ContributorChannel
{
    public const PATTERN = 'users.{id}.contributions';

    public static function name(int $userId): string
    {
        return str_replace('{id}', (string) $userId, self::PATTERN);
    }

    public function join(User $user, string $id): bool
    {
        return ctype_digit($id) && (int) $user->id === (int) $id;
    }
}
