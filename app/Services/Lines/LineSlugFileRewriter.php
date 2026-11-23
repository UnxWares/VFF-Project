<?php

namespace App\Services\Lines;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Remplace, dans les fichiers versionnés du projet (données, tests, exemples de la documentation), chaque
 * ancien slug de ligne retiré par son slug actuel (vff:neutralize-line-slugs --rewrite-files). Seul un slug
 * entier est remplacé : « archeo-x » ne touche ni « archeo-x-2 » ni l'empreinte « archeo-name: ».
 * Le fichier des correspondances lui-même n'est jamais réécrit.
 */
final class LineSlugFileRewriter
{
    /** Dossiers parcourus, depuis la racine du projet. */
    public const ROOTS = ['app', 'database/data', 'docs', 'graphql', 'resources/js', 'tests'];

    /** Extensions de fichiers texte parcourues. */
    public const EXTENSIONS = ['php', 'json', 'md', 'graphql', 'svelte', 'js', 'ts', 'html'];

    /** Un slug entier, borné par autre chose qu'une lettre, un chiffre ou un tiret. */
    private const TOKEN = '/(?<![a-z0-9-])'.NeutralLineSlugs::PREFIX.'[a-z0-9]+(?:-[a-z0-9]+)*(?![a-z0-9-])/';

    public function __construct(private readonly LineSlugRedirects $redirects) {}

    /** @return array{content: string, count: int} texte réécrit et nombre de slugs remplacés */
    public function rewrite(string $content): array
    {
        $count = 0;
        $out = preg_replace_callback(self::TOKEN, function (array $m) use (&$count): string {
            $to = $this->redirects->target($m[0]);
            if ($to === null) {
                return $m[0];
            }
            $count++;

            return $to;
        }, $content);

        return ['content' => $out ?? $content, 'count' => $count];
    }

    /**
     * Fichiers à réécrire sous la racine, avec le nombre de slugs remplacés ; écrits sauf `$dryRun`.
     *
     * @return array<string, int> chemin relatif → slugs remplacés
     */
    public function rewriteTree(string $base, bool $dryRun): array
    {
        $skip = realpath(LineSlugRedirects::path()) ?: LineSlugRedirects::path();
        $changed = [];
        foreach (self::ROOTS as $root) {
            $dir = $base.'/'.$root;
            if (! is_dir($dir)) {
                continue;
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || ! in_array($file->getExtension(), self::EXTENSIONS, true)) {
                    continue;
                }
                $path = $file->getPathname();
                if ((realpath($path) ?: $path) === $skip) {
                    continue;
                }
                $content = (string) file_get_contents($path);
                if (! str_contains($content, NeutralLineSlugs::PREFIX)) {
                    continue;
                }
                ['content' => $new, 'count' => $count] = $this->rewrite($content);
                if ($count === 0) {
                    continue;
                }
                $changed[substr($path, strlen($base) + 1)] = $count;
                if (! $dryRun) {
                    file_put_contents($path, $new);
                }
            }
        }
        ksort($changed);

        return $changed;
    }
}
