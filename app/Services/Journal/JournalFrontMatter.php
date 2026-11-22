<?php

namespace App\Services\Journal;

use League\CommonMark\Extension\FrontMatter\Data\FrontMatterDataParserInterface;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;

/**
 * En-tête de métadonnées d'un article du journal (bloc entre deux lignes `---` en tête du fichier
 * Markdown), dans un sous-ensemble strict de YAML qui suffit aux articles et ne demande aucune
 * bibliothèque de plus :
 *
 *     title: Les Hirondelles, la ligne qui grimpe dans le Haut-Jura
 *     date: 2026-11-22
 *     lines:
 *       - 878000
 *     image:
 *       src: https://upload.wikimedia.org/...
 *       author: "Nom de l'auteur"
 *
 * Une clé par ligne (`clé: valeur`) ; une clé sans valeur ouvre soit une liste (lignes indentées
 * `- valeur`), soit un dictionnaire d'un niveau (lignes indentées `clé: valeur`). Guillemets simples
 * ou doubles facultatifs autour d'une valeur ; ligne commençant par `#` ignorée. Toute autre forme
 * est refusée avec le numéro de la ligne : un en-tête mal écrit se voit au test, pas sur le site.
 */
final class JournalFrontMatter implements FrontMatterDataParserInterface
{
    /**
     * @return array<string, string|array<int|string, string>> valeur, liste ou dictionnaire
     *
     * @throws InvalidFrontMatterException
     */
    public function parse(string $frontMatter): array
    {
        $data = [];
        // Clé ouverte (sans valeur sur sa ligne) et forme de son contenu : `list`, `map`, ou null tant
        // qu'aucune ligne indentée n'a été lue.
        $open = null;
        $shape = null;
        // CommonMark transmet le bloc avec sa ligne `---` d'ouverture (la fermeture est déjà retirée).
        $frontMatter = (string) preg_replace('/\A---\R/', '', $frontMatter);
        foreach (preg_split('/\R/', $frontMatter) ?: [] as $i => $raw) {
            $line = rtrim($raw);
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            $number = $i + 2;
            if (preg_match('/^([a-z_]+):(?:\s+(.*))?$/', $line, $m) === 1) {
                $key = $m[1];
                if (array_key_exists($key, $data)) {
                    throw new InvalidFrontMatterException("Clé « {$key} » répétée (ligne {$number}).");
                }
                $value = isset($m[2]) ? self::scalar($m[2]) : '';
                $data[$key] = $value === '' ? [] : $value;
                $open = $value === '' ? $key : null;
                $shape = null;

                continue;
            }
            $item = $open !== null && preg_match('/^\s+-\s+(.+)$/', $line, $m) === 1 ? ['list', null, $m[1]] : null;
            $item ??= $open !== null && preg_match('/^\s+([a-z_]+):\s+(.+)$/', $line, $m) === 1 ? ['map', $m[1], $m[2]] : null;
            if ($item === null || $open === null) {
                throw new InvalidFrontMatterException("Ligne {$number} illisible : « {$line} ».");
            }
            if ($shape !== null && $shape !== $item[0]) {
                throw new InvalidFrontMatterException("Liste et dictionnaire mêlés sous « {$open} » (ligne {$number}).");
            }
            $shape = $item[0];
            /** @var array<int|string, string> $content */
            $content = $data[$open];
            if ($item[1] === null) {
                $content[] = self::scalar($item[2]);
            } else {
                $content[$item[1]] = self::scalar($item[2]);
            }
            $data[$open] = $content;
        }

        return $data;
    }

    /** Valeur sans ses guillemets englobants (le texte entre eux est gardé tel quel). */
    private static function scalar(string $value): string
    {
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
