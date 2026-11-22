<?php

namespace Tests\Unit\Support;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\CreatesApplication;

/**
 * Une base VFF_TEST_DB jamais créée faisait échouer chaque test d'un `artisan test --parallel`
 * (« database ... does not exist », 457 erreurs le 21/11/2026) : tests/CreatesApplication.php la crée.
 */
class EnsureBaseTestDatabaseTest extends TestCase
{
    private function adminPdo(): PDO
    {
        return new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'vff', 'vff', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function appWith(string $driver, string $database): Application
    {
        $app = new Application(dirname(__DIR__, 3));
        $app->instance('config', new Repository(['database' => [
            'default' => 'pgsql',
            'connections' => ['pgsql' => [
                'driver' => $driver, 'host' => '127.0.0.1', 'port' => 5432, 'username' => 'vff', 'password' => 'vff', 'database' => $database,
            ]],
        ]]));

        return $app;
    }

    private function ensure(Application $app): void
    {
        $runner = new class
        {
            use CreatesApplication;

            public function run(Application $app): void
            {
                $this->ensureBaseTestDatabaseExists($app);
            }
        };
        $runner->run($app);
    }

    private function exists(PDO $pdo, string $database): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $stmt->execute([$database]);

        return $stmt->fetchColumn() !== false;
    }

    public function test_missing_base_is_created_once_and_a_second_call_is_harmless(): void
    {
        $pdo = $this->adminPdo();
        $database = 'vff_ensure_'.bin2hex(random_bytes(4)).'_test';
        $this->assertMatchesRegularExpression('/^vff_ensure_[0-9a-f]{8}_test$/D', $database);

        try {
            $this->assertFalse($this->exists($pdo, $database));
            $this->ensure($this->appWith('pgsql', $database));
            $this->assertTrue($this->exists($pdo, $database));
            $this->ensure($this->appWith('pgsql', $database));
            $this->assertTrue($this->exists($pdo, $database));
        } finally {
            $pdo->exec('DROP DATABASE IF EXISTS "'.$database.'"');
        }
    }

    public function test_a_name_that_is_not_a_test_base_is_never_created(): void
    {
        $pdo = $this->adminPdo();
        $database = 'vff_ensure_'.bin2hex(random_bytes(4)).'_copie';

        $this->ensure($this->appWith('pgsql', $database));

        $this->assertFalse($this->exists($pdo, $database));
    }
}
