<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\CreatesApplication;

/**
 * Garde-fou de tests/CreatesApplication.php : RefreshDatabase vide la base,
 * donc seule une base de test (suffixe _test, ou _test_<n> en parallèle) est
 * acceptée. La base de dev `vff` doit toujours être refusée.
 */
class TestDatabaseGuardTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function acceptedNames(): array
    {
        return [
            'base de test par défaut' => ['vff_test'],
            'base réservée à un agent' => ['vff_php_test'],
            'processus parallèle 1' => ['vff_php_test_test_1'],
            'processus parallèle 12' => ['vff_test_test_12'],
            'suffixe _test_<n> direct' => ['vff_test_4'],
        ];
    }

    /** @return array<string, array{0: string}> */
    public static function refusedNames(): array
    {
        return [
            'base de dev' => ['vff'],
            'vide' => [''],
            'suffixe sans tiret bas' => ['vfftest'],
            '_test au milieu' => ['vff_test_backup'],
            'testing' => ['vff_testing'],
            'numéro non numérique' => ['vff_test_x'],
            'saut de ligne final' => ["vff_test\n"],
            'tiret au lieu du tiret bas' => ['vff-test'],
            'seulement _test' => ['_test'],
            'majuscules _TEST' => ['VFF_TEST'],
            'injection' => ['vff; DROP DATABASE vff; --_test_x'],
        ];
    }

    #[DataProvider('acceptedNames')]
    public function test_accepts_test_database_names(string $name): void
    {
        $this->assertTrue(GuardProbe::isTestDatabaseName($name), $name);
    }

    #[DataProvider('refusedNames')]
    public function test_refuses_other_database_names(string $name): void
    {
        $this->assertFalse(GuardProbe::isTestDatabaseName($name), $name);
    }

    public function test_guard_throws_on_dev_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('« vff »');

        (new GuardProbe)->check($this->app('pgsql', 'vff'));
    }

    public function test_guard_accepts_parallel_database_and_in_memory_sqlite(): void
    {
        $probe = new GuardProbe;

        $probe->check($this->app('pgsql', 'vff_php_test_test_3'));
        $probe->check($this->app('sqlite', ':memory:'));

        $this->addToAssertionCount(2);
    }

    public function test_guard_refuses_sqlite_file_database(): void
    {
        $this->expectException(RuntimeException::class);

        (new GuardProbe)->check($this->app('sqlite', '/app/database/database.sqlite'));
    }

    private function app(string $connection, string $database): Application
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository([
            'database' => [
                'default' => $connection,
                'connections' => [$connection => ['database' => $database]],
            ],
        ]));

        return $app;
    }
}

/** Expose le garde-fou protégé du trait, sans démarrer l'application. */
class GuardProbe
{
    use CreatesApplication;

    public function check(Application $app): void
    {
        $this->guardAgainstNonTestDatabase($app);
    }
}
