<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * `Vite::asset()` ne trouve un fichier que si le build le range dans le manifeste, donc seulement si le code
 * du front (JS, Svelte, CSS) l'importe ou l'appelle. Une vue qui lit une image que plus rien n'importe répond
 * 500 sur un build de production (les pages de documentation de l'API l'ont fait avec le logo), alors que
 * le serveur de développement la sert.
 */
class ViteAssetReferencesTest extends TestCase
{
    public function test_every_asset_read_by_a_view_is_referenced_by_the_front_end_code(): void
    {
        $root = dirname(__DIR__, 2);
        $assets = [];
        foreach ($this->files($root.'/resources/views', ['php']) as $view) {
            if (preg_match_all("/Vite::asset\(\s*'([^']+)'\s*\)/", (string) file_get_contents($view), $m)) {
                foreach ($m[1] as $path) {
                    $assets[$path] = basename($view);
                }
            }
        }
        $this->assertNotEmpty($assets, 'Aucun appel à Vite::asset() trouvé : le test ne vérifie plus rien.');

        $front = '';
        foreach ([$root.'/resources/js', $root.'/resources/css'] as $dir) {
            foreach ($this->files($dir, ['js', 'svelte', 'css']) as $file) {
                $front .= (string) file_get_contents($file)."\n";
            }
        }

        foreach ($assets as $path => $view) {
            $this->assertMatchesRegularExpression(
                '/(import[^\n]*|url\([^\n]*)'.preg_quote(basename($path), '/').'/',
                $front,
                "$view lit $path avec Vite::asset(), mais aucun fichier de resources/js ou resources/css ne l'importe : "
                .'il manquerait au manifeste du build de production.',
            );
        }
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function files(string $dir, array $extensions): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }
}
