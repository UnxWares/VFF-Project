<?php

namespace Tests\Unit\Static;

use PHPUnit\Framework\TestCase;

/**
 * Parcours de mise en production (docs/MISE-EN-PRODUCTION.md, deploy/bin/mise-en-production.sh) : le script est
 * prudent (aucune suppression), chaque étape a sa section dans le document, et tout ce qu'ils citent existe dans le
 * dépôt (fichiers, commandes artisan de VFF). Les fichiers du kit ne se contredisent pas (dossier de l'application,
 * socket PHP-FPM, essai de restauration sur la base temporaire).
 */
class MiseEnProductionTest extends TestCase
{
    /** Commandes artisan citées qui viennent de Laravel ou de ses paquets (pas de app/Console/Commands). */
    private const FRAMEWORK_COMMANDS = [
        'config:cache', 'key:generate', 'migrate', 'migrate:status', 'optimize', 'queue:restart', 'queue:work',
        'reverb:restart', 'reverb:start', 'route:cache', 'schedule:list', 'schedule:run', 'storage:link', 'tinker',
        'view:cache',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function read(string $path): string
    {
        return (string) file_get_contents(self::root().'/'.$path);
    }

    private static function script(): string
    {
        return self::read('deploy/bin/mise-en-production.sh');
    }

    private static function doc(): string
    {
        return self::read('docs/MISE-EN-PRODUCTION.md');
    }

    /** @return list<string> */
    private static function steps(): array
    {
        preg_match('/^ETAPES=\(([^)]*)\)/m', self::script(), $m);

        return preg_split('/\s+/', trim($m[1] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public function test_the_script_is_executable_strict_and_never_deletes(): void
    {
        $path = self::root().'/deploy/bin/mise-en-production.sh';
        $script = self::script();

        $this->assertTrue(is_executable($path));
        $this->assertStringContainsString('set -euo pipefail', $script);
        foreach (['--verifier)', '--appliquer)', '--etape)', '--hors-ligne)', '--liste)'] as $option) {
            $this->assertStringContainsString($option, $script);
        }
        $this->assertDoesNotMatchRegularExpression('/(^|[\s;|&(])rm\s/m', $script, 'aucune suppression');
        $this->assertDoesNotMatchRegularExpression('/\brsync\b|--delete|-delete\b|\bshred\b|dropdb|drop (database|table|role)/i', $script);
        $this->assertStringContainsString('chemin_permis', $script, 'les chemins écrits sont vérifiés contre des préfixes littéraux');
    }

    /** Corps d'une fonction du script (jusqu'à l'accolade fermante en début de ligne). */
    private static function function(string $name): string
    {
        preg_match('/^'.preg_quote($name, '/').'\(\) \{\n(.*?)^\}/ms', self::script(), $m);

        return $m[1] ?? '';
    }

    public function test_migrations_never_run_without_a_recent_checked_backup_even_as_a_single_step(): void
    {
        $migrations = self::function('etape_migrations');
        $this->assertStringContainsString('migrate --force', $migrations);

        // « --appliquer --etape migrations » (nouvelle version) saute l'étape sauvegarde : l'étape vérifie elle-même
        // qu'une sauvegarde contrôlée récente existe avant de changer le schéma (retour arrière = restauration).
        $guard = strpos($migrations, 'sauvegarde_recente');
        $this->assertNotFalse($guard, 'etape_migrations vérifie la sauvegarde');
        $this->assertLessThan(strpos($migrations, 'migrate --force'), $guard);
        $this->assertMatchesRegularExpression('/\[ -n "\$\(sauvegarde_recente\)" \] \|\| die /', $migrations);

        $recent = self::function('sauvegarde_recente');
        $this->assertStringContainsString('-mmin -1560', $recent);
        $this->assertStringContainsString('.sha256', $recent);
        $this->assertStringContainsString('.counts', $recent);
    }

    public function test_root_creates_the_commons_cache_only_under_the_storage_of_the_application(): void
    {
        $commons = self::function('etape_commons');
        $create = strpos($commons, 'install -d');
        $this->assertNotFalse($create);

        // Le chemin vient de .env : root ne crée pas un dossier de vff ailleurs que sous storage/ (ni avec « .. »).
        $guard = strpos($commons, '"${VFF_DIR:?}"/storage/*)');
        $this->assertNotFalse($guard, 'etape_commons borne le cache à $VFF_DIR/storage/');
        $this->assertLessThan($create, $guard);
        $this->assertStringContainsString('*/../*|*/..)', $commons);
    }

    public function test_every_step_has_a_function_and_a_section_of_the_document(): void
    {
        $steps = self::steps();
        $this->assertCount(19, $steps);

        foreach ($steps as $i => $step) {
            $function = 'etape_'.str_replace('-', '_', $step).'()';
            $this->assertStringContainsString($function, self::script(), "fonction de l'étape $step");
            $this->assertMatchesRegularExpression(
                '/^## '.($i + 1).'\. .*\(`'.preg_quote($step, '/').'`\)/m',
                self::doc(),
                'section « ## '.($i + 1).". … (`$step`) » du document, dans l'ordre du script",
            );
        }
    }

    public function test_every_repository_path_cited_exists(): void
    {
        $text = self::doc()."\n".self::script();
        preg_match_all('#(?<![\w/.-])((?:deploy|docs|scripts|config|routes|app)/[A-Za-z0-9_./-]*[A-Za-z0-9_])#', $text, $m);
        $paths = array_unique($m[1]);
        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $path = rtrim($path, '.');
            if (str_contains($path, '*')) {
                continue;
            }
            $this->assertFileExists(self::root().'/'.$path, "chemin cité : $path");
        }
    }

    public function test_every_artisan_command_cited_exists(): void
    {
        $text = self::doc()."\n".self::script()."\n".self::read('docs/PLANIFICATION.md')."\n".self::read('docs/DEPLOIEMENT-SERVICES.md');
        preg_match_all('/\bartisan(?:_vff)?\s+([a-z][a-z0-9-]*:[a-z0-9:-]+|tinker|migrate|optimize)\b/', $text, $m);
        $commands = array_unique($m[1]);
        $this->assertContains('vff:refresh-current-era', $commands);

        $sources = self::read('routes/console.php');
        foreach (glob(self::root().'/app/Console/Commands/*.php') ?: [] as $file) {
            $sources .= (string) file_get_contents($file);
        }
        foreach ($commands as $command) {
            if (str_starts_with($command, 'vff:')) {
                $this->assertMatchesRegularExpression(
                    '/\$signature\s*=\s*[\'"]'.preg_quote($command, '/').'[\s\'"]/',
                    $sources,
                    "commande $command absente de app/Console/Commands",
                );
            } else {
                $this->assertContains($command, self::FRAMEWORK_COMMANDS, "commande $command : à vérifier puis ajouter à la liste");
            }
        }
    }

    public function test_the_kit_uses_one_application_directory_and_one_php_socket(): void
    {
        $this->assertMatchesRegularExpression('/^APP_DIR=\/srv\/vff\s/m', self::read('deploy/backup/backup.env.example'));
        $this->assertStringNotContainsString('/srv/vff/current', self::read('deploy/backup/backup.env.example'));
        $this->assertStringContainsString('root /srv/vff/public;', self::read('deploy/nginx/vff-project.org.conf'));

        preg_match('/^listen = (\S+)$/m', self::read('deploy/php-fpm/vff.conf'), $socket);
        $this->assertNotEmpty($socket);
        $this->assertStringContainsString('server unix:'.($socket[1] ?? '').';', self::read('deploy/nginx/vff-project.org.conf'));
        $this->assertMatchesRegularExpression('/^user = vff$/m', self::read('deploy/php-fpm/vff.conf'));
        $this->assertMatchesRegularExpression('/^php_admin_value\[post_max_size\] = 12M$/m', self::read('deploy/php-fpm/vff.conf'));
    }

    public function test_the_restore_test_checks_the_temporary_database_even_with_a_cached_configuration(): void
    {
        $restore = self::read('deploy/backup/vff-restore-test.sh');

        // Avec « php artisan optimize », Laravel ignore les variables d'environnement : sans APP_CONFIG_CACHE vers un
        // fichier absent, vff:check-invariants lirait la base de production au lieu de la base restaurée.
        $this->assertMatchesRegularExpression('/APP_CONFIG_CACHE="\/tmp\/[^"]+"/', $restore);
        $this->assertStringContainsString('DB_DATABASE="$temp"', $restore);
        $this->assertStringContainsString('DB_USERNAME="${PGUSER:?}"', $restore);
        $this->assertStringContainsString('--no-comments', $restore, 'extensions de template1 : COMMENT ON EXTENSION refusé au rôle de sauvegarde');
    }

    public function test_tls_does_not_ask_for_ocsp_stapling(): void
    {
        $tls = self::read('deploy/nginx/snippets/vff-tls.conf');

        $this->assertMatchesRegularExpression('/^ssl_stapling off;$/m', $tls);
        $this->assertDoesNotMatchRegularExpression('/^ssl_stapling on;$/m', $tls);
    }
}
