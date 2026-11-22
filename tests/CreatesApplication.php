<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\ParallelTesting;
use PDO;
use PDOException;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Plusieurs suites en parallèle (agents, terminaux) : chacune sa base, ex.
        // VFF_TEST_DB=vff_unit_test. Le garde-fou ci-dessous impose toujours le suffixe _test.
        if (($database = getenv('VFF_TEST_DB')) !== false && $database !== '') {
            $app['config']->set('database.connections.'.$app['config']->get('database.default').'.database', $database);
        }

        $this->guardAgainstNonTestDatabase($app);
        $this->ensureBaseTestDatabaseExists($app);

        // Tests parallèles (artisan test --parallel, Infection) : Laravel bascule
        // ensuite chaque processus sur « <base>_test_<n> ». On revérifie APRÈS cette
        // bascule (rappel enregistré après celui de Laravel, donc exécuté après lui)
        // et AVANT RefreshDatabase, qui viderait la base.
        ParallelTesting::setUpTestCase(fn () => $this->guardAgainstNonTestDatabase($app));

        return $app;
    }

    /**
     * Crée la base de test nommée par VFF_TEST_DB si elle n'existe pas encore.
     *
     * Laravel (artisan test --parallel) se connecte à cette base pour créer « <base>_test_<n> » :
     * absente, chaque test échoue à la mise en place (« database ... does not exist »), des centaines
     * d'erreurs pour une base jamais créée (21/11/2026). Le nom a déjà passé le garde-fou (suffixe
     * _test ou _test_<n>) ; les extensions PostGIS viennent de la migration. Une fois par processus.
     */
    protected function ensureBaseTestDatabaseExists(Application $app): void
    {
        static $checked = [];

        $connection = $app['config']->get('database.default');
        $config = (array) $app['config']->get("database.connections.{$connection}");
        $database = (string) ($config['database'] ?? '');

        if (($config['driver'] ?? '') !== 'pgsql' || isset($checked[$database]) || ! self::isTestDatabaseName($database)) {
            return;
        }
        $checked[$database] = true;

        try {
            $pdo = new PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=postgres', $config['host'] ?? '127.0.0.1', $config['port'] ?? 5432),
                (string) ($config['username'] ?? ''),
                (string) ($config['password'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
            $exists->execute([$database]);

            if ($exists->fetchColumn() === false) {
                // Le nom est sûr (^[A-Za-z0-9_]+$, vérifié ci-dessus), donc cité sans risque.
                $pdo->exec('CREATE DATABASE "'.$database.'"');
            }
        } catch (PDOException) {
            // Base créée entre-temps par un autre processus, serveur arrêté ou droits insuffisants :
            // le premier test qui touche la base le signale avec son propre message.
        }
    }

    /**
     * Garde-fou : les tests Feature utilisent RefreshDatabase (migrate:fresh).
     * On refuse de démarrer si la connexion par défaut ne vise pas une base
     * dédiée aux tests (cf. isTestDatabaseName) : la base de dev `vff` (données
     * OSM/SNCF importées pendant des heures) est intouchable.
     */
    protected function guardAgainstNonTestDatabase(Application $app): void
    {
        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        if (! self::isTestDatabaseName($database)) {
            throw new RuntimeException(
                "Tests refusés : la base « {$database} » n'est pas une base de test (suffixe _test ou _test_<n> attendu). "
                .'Vérifie DB_DATABASE dans phpunit.xml (vff_test).'
            );
        }
    }

    /**
     * Nom de base acceptable pour les tests : suffixe « _test » (vff_test,
     * vff_php_test), éventuellement suivi du numéro de processus que Laravel
     * ajoute en parallèle (vff_php_test_test_3). Jamais la base de dev `vff`,
     * ni un nom où « _test » n'est pas le suffixe (vff_test_backup, vff_testing).
     */
    public static function isTestDatabaseName(string $database): bool
    {
        return preg_match('/^[A-Za-z0-9_]+_test(?:_\d+)?$/D', $database) === 1;
    }
}
