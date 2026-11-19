<?php

namespace App\Support;

/**
 * Garde-fou des commandes qui vident la base (migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback,
 * db:wipe) : elles ne tournent que sur une base jetable, dont le nom finit par « _test », éventuellement suivi du
 * numéro de processus que Laravel ajoute en parallèle (« vff_test_test_3 ») : même règle que
 * tests/CreatesApplication.php. Partout ailleurs (base de développement `vff`, production), Laravel les refuse.
 *
 * Incident du 19/11/2026 : `DB_DATABASE=…_test bin/php artisan migrate:fresh` a vidé la base de développement, car la
 * variable posée sur l'hôte n'entrait pas dans le conteneur et `.env` désignait `vff`.
 */
final class DatabaseGuard
{
    public static function isDisposable(?string $database): bool
    {
        return $database !== null && preg_match('/^[A-Za-z0-9_]+_test(?:_\d+)?$/D', $database) === 1;
    }
}
