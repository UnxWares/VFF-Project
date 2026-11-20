<?php

namespace Tests\Unit\Static;

/**
 * Lecture statique des sources du front et des vues (sans application ni base) :
 * fichiers d'un dossier, retrait des commentaires et des blocs non affichés.
 *
 * Les parties retirées sont remplacées par des espaces (retours à la ligne
 * conservés) : les numéros de ligne des signalements restent exacts.
 */
final class SourceText
{
    /** Dossiers jamais analysés (copie égarée d'un vendor/ Composer). */
    private const EXCLUDED = ['resources/images/vendor/'];

    /** Mots-clés après lesquels un « / » ouvre une expression régulière. */
    private const REGEX_KEYWORDS = ['return', 'typeof', 'case', 'in', 'of', 'void', 'throw', 'delete', 'new', 'else', 'do'];

    public static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * Fichiers d'un dossier du dépôt (récursif), chemins relatifs triés.
     *
     * @param  list<string>  $suffixes  ex. ['.svelte', '.js']
     * @return list<string>
     */
    public static function files(string $directory, array $suffixes): array
    {
        $base = self::root().'/'.$directory;
        if (! is_dir($base)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen(self::root()) + 1);
            if (! $file->isFile() || array_filter(self::EXCLUDED, fn ($e) => str_starts_with($relative, $e))) {
                continue;
            }
            foreach ($suffixes as $suffix) {
                if (str_ends_with($relative, $suffix)) {
                    $files[] = $relative;
                    break;
                }
            }
        }
        sort($files);

        return $files;
    }

    public static function read(string $relative): string
    {
        return (string) file_get_contents(self::root().'/'.$relative);
    }

    /** Remplace un fragment par des espaces, en gardant ses retours à la ligne. */
    public static function blank(string $fragment): string
    {
        return (string) preg_replace('/[^\n]/', ' ', $fragment);
    }

    /** Numéro de ligne (1 = première) d'une position en octets. */
    public static function lineOf(string $text, int $offset): int
    {
        return substr_count(substr($text, 0, $offset), "\n") + 1;
    }

    /**
     * Code JavaScript sans commentaires ni expressions régulières littérales ;
     * les chaînes (y compris les gabarits `…${…}…`) sont conservées.
     */
    public static function javascript(string $code): string
    {
        $i = 0;

        return self::scanCode($code, $i, false);
    }

    /**
     * Partie affichée d'un composant Svelte : balisage et chaînes JS, sans
     * <style>, sans commentaires HTML ni commentaires JS.
     */
    public static function svelte(string $source): string
    {
        $source = self::blankMatches('#<style\b[^>]*>.*?</style>#si', $source);
        $source = self::blankMatches('#<!--.*?-->#s', $source);

        return (string) preg_replace_callback(
            '#(<script\b[^>]*>)(.*?)(</script>)#si',
            fn ($m) => $m[1].self::javascript($m[2]).$m[3],
            $source,
        );
    }

    /** Partie affichée d'une vue Blade : sans commentaires, <style> ni <script>. */
    public static function blade(string $source): string
    {
        foreach (['#\{\{--.*?--\}\}#s', '#<!--.*?-->#s', '#<style\b[^>]*>.*?</style>#si', '#<script\b[^>]*>.*?</script>#si'] as $pattern) {
            $source = self::blankMatches($pattern, $source);
        }

        return $source;
    }

    private static function blankMatches(string $pattern, string $source): string
    {
        return (string) preg_replace_callback($pattern, fn ($m) => self::blank($m[0]), $source);
    }

    /**
     * Parcourt du code jusqu'à la fin, ou jusqu'à l'accolade fermante d'un
     * `${…}` de gabarit ($untilBrace).
     */
    private static function scanCode(string $code, int &$i, bool $untilBrace): string
    {
        $out = '';
        $n = strlen($code);
        $depth = 0;
        $regexAllowed = true;
        $word = '';

        while ($i < $n) {
            $c = $code[$i];
            $next = $code[$i + 1] ?? '';

            if ($c === '/' && $next === '/') {
                $end = strpos($code, "\n", $i);
                $end = $end === false ? $n : $end;
                $out .= self::blank(substr($code, $i, $end - $i));
                $i = $end;

                continue;
            }
            if ($c === '/' && $next === '*') {
                $end = strpos($code, '*/', $i + 2);
                $end = $end === false ? $n : $end + 2;
                $out .= self::blank(substr($code, $i, $end - $i));
                $i = $end;

                continue;
            }
            if ($c === '\'' || $c === '"') {
                $start = $i++;
                while ($i < $n && $code[$i] !== $c && $code[$i] !== "\n") {
                    $i += $code[$i] === '\\' ? 2 : 1;
                }
                $i++;
                $out .= substr($code, $start, $i - $start);
                $regexAllowed = false;
                $word = '';

                continue;
            }
            if ($c === '`') {
                $out .= $c;
                $i++;
                while ($i < $n && $code[$i] !== '`') {
                    if ($code[$i] === '\\') {
                        $out .= substr($code, $i, 2);
                        $i += 2;
                    } elseif ($code[$i] === '$' && ($code[$i + 1] ?? '') === '{') {
                        $out .= '${';
                        $i += 2;
                        $out .= self::scanCode($code, $i, true);
                    } else {
                        $out .= $code[$i++];
                    }
                }
                $out .= $i < $n ? '`' : '';
                $i++;
                $regexAllowed = false;
                $word = '';

                continue;
            }
            if ($c === '/' && $regexAllowed) {
                $start = $i++;
                $inClass = false;
                while ($i < $n && $code[$i] !== "\n") {
                    if ($code[$i] === '\\') {
                        $i += 2;

                        continue;
                    }
                    if ($code[$i] === '[') {
                        $inClass = true;
                    } elseif ($code[$i] === ']') {
                        $inClass = false;
                    } elseif ($code[$i] === '/' && ! $inClass) {
                        break;
                    }
                    $i++;
                }
                $i++;
                while ($i < $n && ctype_alpha($code[$i])) {
                    $i++;
                }
                $out .= self::blank(substr($code, $start, $i - $start));
                $regexAllowed = false;

                continue;
            }

            if ($untilBrace && $c === '{') {
                $depth++;
            } elseif ($untilBrace && $c === '}') {
                if ($depth === 0) {
                    $out .= '}';
                    $i++;

                    return $out;
                }
                $depth--;
            }

            $out .= $c;
            $i++;
            if (ctype_alnum($c) || $c === '_' || $c === '$') {
                $word .= $c;
                $regexAllowed = in_array($word, self::REGEX_KEYWORDS, true);
            } elseif (! ctype_space($c)) {
                $word = '';
                $regexAllowed = ! in_array($c, [')', ']', '}'], true);
            } else {
                $word = '';
            }
        }

        return $out;
    }
}
