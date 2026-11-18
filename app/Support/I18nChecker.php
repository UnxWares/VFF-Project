<?php

namespace App\Support;

use Symfony\Component\Finder\Finder;

/**
 * Compare les fichiers de traduction `lang/{locale}/*.php` à la locale
 * canonique (fr) et produit une liste de problèmes classés par gravité.
 *
 * Erreurs (font échouer `vff:i18n-check`) :
 *   - missing_file         : lang/fr/x.php existe, pas lang/de/x.php
 *   - missing_key          : clé FR absente de la traduction
 *   - placeholder_mismatch : jeu de placeholders `:name` différent du FR
 *
 * Avertissements (signalés seulement) :
 *   - extra_key / orphan_file : clé ou fichier sans équivalent FR (obsolète ?)
 *   - empty_value             : chaîne vide
 *   - unknown_usage           : clé `t('ns.cle')` utilisée dans resources/js
 *                               mais absente du FR
 *
 * Les namespaces « surveillés » (fichiers en cours d'édition par d'autres
 * chantiers) voient leurs erreurs rétrogradées en avertissements, sauf en
 * mode strict.
 */
class I18nChecker
{
    public const CANONICAL = 'fr';

    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    /**
     * Fichiers de traduction appartenant à des chantiers en cours (carte,
     * lignes, médias, gares, éditeur/contributions) : signalés, sans bloquer.
     * À vider une fois ces chantiers stabilisés (ou utiliser --strict).
     */
    public const WATCH_ONLY = ['contribute', 'editor', 'lines', 'maps', 'media', 'stations'];

    /**
     * @param  list<string>  $watchOnly
     */
    public function __construct(
        protected string $langPath,
        protected array $watchOnly = self::WATCH_ONLY,
        protected bool $strict = false,
    ) {}

    /**
     * @param  list<string>  $locales  Locales cibles (ex. ['en', 'de'])
     * @return list<array{severity: string, type: string, locale: string, namespace: string, key: ?string, detail: ?string}>
     */
    public function check(array $locales): array
    {
        $issues = [];
        $canonicalFiles = $this->namespaces(self::CANONICAL);

        foreach ($locales as $locale) {
            foreach ($canonicalFiles as $namespace) {
                $reference = $this->load(self::CANONICAL, $namespace);
                $file = $this->path($locale, $namespace);

                if (! is_file($file)) {
                    $issues[] = $this->issue(self::SEVERITY_ERROR, 'missing_file', $locale, $namespace, null, count($reference).' clé(s)');

                    continue;
                }

                $translated = $this->load($locale, $namespace);

                foreach ($reference as $key => $value) {
                    if (! array_key_exists($key, $translated)) {
                        $issues[] = $this->issue(self::SEVERITY_ERROR, 'missing_key', $locale, $namespace, $key, $value);

                        continue;
                    }

                    $expected = $this->placeholders($value);
                    $actual = $this->placeholders($translated[$key]);
                    if ($expected !== $actual) {
                        $issues[] = $this->issue(
                            self::SEVERITY_ERROR, 'placeholder_mismatch', $locale, $namespace, $key,
                            'attendu ['.implode(', ', $expected).'], trouvé ['.implode(', ', $actual).']',
                        );
                    }

                    if (trim($translated[$key]) === '' && trim($value) !== '') {
                        $issues[] = $this->issue(self::SEVERITY_WARNING, 'empty_value', $locale, $namespace, $key, null);
                    }
                }

                foreach (array_diff_key($translated, $reference) as $key => $value) {
                    $issues[] = $this->issue(self::SEVERITY_WARNING, 'extra_key', $locale, $namespace, $key, $value);
                }
            }

            foreach (array_diff($this->namespaces($locale), $canonicalFiles) as $namespace) {
                $issues[] = $this->issue(self::SEVERITY_WARNING, 'orphan_file', $locale, $namespace, null, null);
            }
        }

        return $issues;
    }

    /**
     * Clés littérales `t('ns.cle')` / `$t("ns.cle")` utilisées dans le front
     * mais absentes de la locale canonique. Les clés construites
     * dynamiquement (`t('lines.event_types.' + type)`) ne sont pas vues.
     *
     * @return list<array{severity: string, type: string, locale: string, namespace: string, key: ?string, detail: ?string}>
     */
    public function checkUsage(string $sourcePath): array
    {
        if (! is_dir($sourcePath)) {
            return [];
        }

        $known = [];
        foreach ($this->namespaces(self::CANONICAL) as $namespace) {
            foreach (array_keys($this->load(self::CANONICAL, $namespace)) as $key) {
                $known["{$namespace}.{$key}"] = true;
            }
        }

        $issues = [];
        $seen = [];
        $finder = (new Finder)->files()->in($sourcePath)->name(['*.svelte', '*.js', '*.ts']);

        foreach ($finder as $file) {
            $source = $this->stripComments($file->getContents());
            // Appels directs `t('ns.cle')`, et clés passées par des tables de
            // libellés (`labelKey: 'maps.legend.x'`, `titleKey`, `textKey`…)
            // traduites plus loin par `t(r.labelKey)`.
            preg_match_all('/(?<![\w$])\$?t\(\s*[\'"]([a-z][a-z0-9_]*(?:\.[A-Za-z0-9_]+)+)[\'"]/', $source, $calls);
            preg_match_all('/\b[a-z][A-Za-z]*Key\s*:\s*[\'"]([a-z][a-z0-9_]*(?:\.[A-Za-z0-9_]+)+)[\'"]/', $source, $tables);

            foreach (array_unique([...$calls[1], ...$tables[1]]) as $fullKey) {
                // Préfixe de clé dynamique (`t('lines.types.' + x)`) : ignoré. Pluriel i18next
                // (`t('editor.x', { count })` lit `editor.x_one` / `editor.x_other`) : clé connue.
                if (isset($known[$fullKey]) || isset($known["{$fullKey}_other"]) || isset($seen[$fullKey]) || str_ends_with($fullKey, '.')) {
                    continue;
                }
                $seen[$fullKey] = true;

                [$namespace, $key] = explode('.', $fullKey, 2);
                $issues[] = $this->issue(
                    self::SEVERITY_WARNING, 'unknown_usage', self::CANONICAL, $namespace, $key,
                    $file->getRelativePathname(),
                );
            }
        }

        return $issues;
    }

    /**
     * Retire les commentaires (exemples d'usage dans la doc) : blocs
     * `/* … *\/` et `<!-- … -->`, lignes commençant par `//`.
     */
    protected function stripComments(string $source): string
    {
        $source = preg_replace(['#/\*.*?\*/#s', '#<!--.*?-->#s', '#^\s*//.*$#m'], '', $source);

        return (string) $source;
    }

    /**
     * @return list<string>
     */
    public function namespaces(string $locale): array
    {
        $files = glob($this->langPath.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.'*.php') ?: [];
        $namespaces = array_map(fn ($f) => basename($f, '.php'), $files);
        sort($namespaces);

        return $namespaces;
    }

    public function isWatchOnly(string $namespace): bool
    {
        return in_array($namespace, $this->watchOnly, true);
    }

    /**
     * Carte plate `cle.sous_cle => valeur` d'un fichier de traduction.
     *
     * @return array<string, string>
     */
    public function load(string $locale, string $namespace): array
    {
        /** @var mixed $contents */
        $contents = require $this->path($locale, $namespace);

        return is_array($contents) ? $this->flatten($contents) : [];
    }

    protected function path(string $locale, string $namespace): string
    {
        return $this->langPath.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$namespace.'.php';
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array<string, string>
     */
    protected function flatten(array $items, string $prefix = ''): array
    {
        $flat = [];
        foreach ($items as $key => $value) {
            $compound = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $flat += $this->flatten($value, $compound);
            } else {
                $flat[$compound] = (string) $value;
            }
        }

        return $flat;
    }

    /**
     * Placeholders Laravel `:name` (convertis en `{{name}}` côté i18next).
     * Ignore les « : » de typographie française (« Note : ») et les URL.
     *
     * @return list<string>
     */
    protected function placeholders(string $value): array
    {
        preg_match_all('/(?<![\w\/:]):([a-zA-Z_][a-zA-Z0-9_]*)/', $value, $matches);
        $found = array_values(array_unique($matches[1]));
        sort($found);

        return $found;
    }

    /**
     * @return array{severity: string, type: string, locale: string, namespace: string, key: ?string, detail: ?string}
     */
    protected function issue(string $severity, string $type, string $locale, string $namespace, ?string $key, ?string $detail): array
    {
        if ($severity === self::SEVERITY_ERROR && ! $this->strict && $this->isWatchOnly($namespace)) {
            $severity = self::SEVERITY_WARNING;
        }

        if ($severity === self::SEVERITY_WARNING && $this->strict && $type === 'unknown_usage') {
            $severity = self::SEVERITY_ERROR;
        }

        return compact('severity', 'type', 'locale', 'namespace', 'key', 'detail');
    }
}
