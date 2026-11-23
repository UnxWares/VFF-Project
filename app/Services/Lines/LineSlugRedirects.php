<?php

namespace App\Services\Lines;

use Illuminate\Container\Attributes\Singleton;

/**
 * Anciens slugs de ligne et slug qui les remplace (fichier versionné database/data/line_slug_redirects.php,
 * écrit par vff:neutralize-line-slugs). Une ancienne adresse de fiche ne renvoie jamais 404 : le site et
 * l'API répondent 301 vers la nouvelle (App\Http\Middleware\RedirectRetiredLineSlug), GraphQL et le
 * serveur MCP acceptent l'ancien slug et renvoient la fiche sous le nouveau (Line::redirectSlugFor).
 *
 * Un ancien slug n'est jamais redonné à une autre ligne (vff:link-traces le tient pour pris).
 */
#[Singleton]
final class LineSlugRedirects
{
    /** Chemin du fichier, depuis la racine du projet. */
    public const FILE = 'database/data/line_slug_redirects.php';

    /** Renommages successifs suivis au plus (une boucle s'arrête là). */
    private const MAX_HOPS = 5;

    /** @var array<string, string> ancien slug → nouveau slug */
    private readonly array $map;

    /** @param  ?array<string, string>  $map  correspondances (par défaut le fichier versionné) */
    public function __construct(?array $map = null)
    {
        $this->map = $map ?? self::read(self::path());
    }

    public static function path(): string
    {
        return dirname(__DIR__, 3).'/'.self::FILE;
    }

    /**
     * Correspondances d'un fichier (vide s'il n'existe pas encore).
     *
     * @return array<string, string>
     */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $rows = require $path;

        return is_array($rows) ? array_filter($rows, fn (mixed $to, mixed $from): bool => is_string($from) && is_string($to), ARRAY_FILTER_USE_BOTH) : [];
    }

    /** Slug actuel d'un ancien slug, renommages successifs suivis ; null si ce slug n'a jamais été retiré. */
    public function target(string $slug): ?string
    {
        $seen = [$slug => true];
        $current = $slug;
        for ($i = 0; $i < self::MAX_HOPS && isset($this->map[$current]); $i++) {
            $current = $this->map[$current];
            if (isset($seen[$current])) {
                return null;
            }
            $seen[$current] = true;
        }

        return $current === $slug ? null : $current;
    }

    public function isRetired(string $slug): bool
    {
        return isset($this->map[$slug]);
    }

    /** @return array<string, string> ancien slug → nouveau slug */
    public function all(): array
    {
        return $this->map;
    }
}
