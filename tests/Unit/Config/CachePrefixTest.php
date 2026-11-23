<?php

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Préfixe des clés de cache : une base de test (E2E en local) ne doit jamais écrire sous les clés du site de dev,
 * qui partage le même Valkey (le seeder E2E y avait remplacé les fiches des compagnies, 23/11/2026).
 */
final class CachePrefixTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            $this->setEnv($name, $value === false ? null : $value);
        }
        parent::tearDown();
    }

    /** @return iterable<string, array{0: ?string, 1: string}> */
    public static function databases(): iterable
    {
        yield 'base de dev' => ['vff', 'vff_cache_'];
        yield 'sans base' => [null, 'vff_cache_'];
        yield 'base de test E2E' => ['vff_e2e_test', 'vff_cache_vff_e2e_test_'];
        yield 'suffixe _test au milieu' => ['vff_test_backup', 'vff_cache_'];
    }

    #[DataProvider('databases')]
    public function test_a_test_database_gets_its_own_cache_prefix(?string $database, string $prefix): void
    {
        $this->override('APP_NAME', 'VFF');
        $this->override('CACHE_PREFIX', null);
        $this->override('DB_DATABASE', $database);

        $this->assertSame($prefix, (require __DIR__.'/../../../config/cache.php')['prefix']);
    }

    public function test_an_explicit_prefix_wins(): void
    {
        $this->override('CACHE_PREFIX', 'autre_');
        $this->override('DB_DATABASE', 'vff_e2e_test');

        $this->assertSame('autre_', (require __DIR__.'/../../../config/cache.php')['prefix']);
    }

    private function override(string $name, ?string $value): void
    {
        if (! array_key_exists($name, $this->saved)) {
            $this->saved[$name] = getenv($name);
        }
        $this->setEnv($name, $value);
    }

    private function setEnv(string $name, ?string $value): void
    {
        if ($value === null) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);

            return;
        }
        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}
