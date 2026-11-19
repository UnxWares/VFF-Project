<?php

namespace Tests\Unit\Support;

use App\Support\DatabaseGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Seules les bases de test (suffixe « _test ») peuvent être vidées par migrate:fresh, migrate:refresh,
 * migrate:reset, migrate:rollback ou db:wipe ; la base de développement `vff` et la production jamais.
 */
class DatabaseGuardTest extends TestCase
{
    /** @return array<string, array{?string, bool}> */
    public static function databases(): array
    {
        return [
            'base de développement' => ['vff', false],
            'base de test commune' => ['vff_test', true],
            'base de test d\'un agent' => ['vff_jour_moderation_recherche_test', true],
            'suffixe au milieu du nom' => ['vff_test_copie', false],
            'base restaurée' => ['vff_restore', false],
            'nom absent' => [null, false],
            'nom vide' => ['', false],
        ];
    }

    #[DataProvider('databases')]
    public function test_only_test_databases_are_disposable(?string $database, bool $disposable): void
    {
        $this->assertSame($disposable, DatabaseGuard::isDisposable($database));
    }
}
