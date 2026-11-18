<?php

namespace Tests\Unit\Static;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règle éditoriale VFF : pas de tiret long « — », de tiret demi-cadratin « – »
 * ni de « -- » dans les textes affichés ; le tiret simple « - » les remplace.
 *
 * Périmètre : traductions (lang/*), chaînes et balisage du front
 * (resources/js : .svelte et .js), vues Blade. Les commentaires de code,
 * les blocs <style>, les expressions régulières et les propriétés CSS
 * (`--vff-accent`, `i--`) sont hors règle.
 */
class TypographicDashTest extends TestCase
{
    /** Tirets longs, ou « -- » isolé (pas `--var`, `i--`, `<!--`, `-->`). */
    public const DASH = '/[\x{2014}\x{2013}]|(?<![-\w!<])--(?![-\w>])/u';

    public function test_translations_have_no_long_dash(): void
    {
        $offenders = [];
        foreach (glob(SourceText::root().'/lang/*/*.php') ?: [] as $file) {
            $relative = substr($file, strlen(SourceText::root()) + 1);
            foreach ($this->flatten(require $file) as $key => $value) {
                if (preg_match(self::DASH, $value)) {
                    $offenders[] = "{$relative} [{$key}] : {$value}";
                }
            }
        }

        $this->assertSame([], $offenders, "Tiret long dans une traduction : utiliser « - ».\n".implode("\n", $offenders));
    }

    public function test_front_and_views_have_no_long_dash(): void
    {
        $offenders = [];
        foreach (SourceText::files('resources/js', ['.svelte', '.js']) as $file) {
            $source = SourceText::read($file);
            $offenders = [...$offenders, ...$this->scan($file, str_ends_with($file, '.svelte') ? SourceText::svelte($source) : SourceText::javascript($source))];
        }
        foreach (SourceText::files('resources/views', ['.blade.php']) as $file) {
            $offenders = [...$offenders, ...$this->scan($file, SourceText::blade(SourceText::read($file)))];
        }

        $this->assertSame([], $offenders, "Tiret long dans un texte affiché : utiliser « - ».\n".implode("\n", $offenders));
    }

    /**
     * Phase 2 : fichiers encore en cours d'édition par d'autres chantiers, à retirer
     * de cette liste une fois leurs tirets remplacés.
     */
    /**
     * Liste blanche : une ligne est tolérée si elle reconnaît ou normalise un tiret
     * venu d'une source externe (Wikipédia, SNCF, OSM, Wikidata) : expression
     * régulière, classe de caractères, str_replace/strtr. Les commentaires SQL
     * (`-- ...` en début de ligne ou après du SQL, cf. SQL_COMMENT) et la syntaxe de
     * ligne de commande sont hors règle.
     */
    private const NORMALIZATION = '/preg_|str_replace|strtr|mb_str|split\(|\[[^\]]*[\x{2013}\x{2014}\x{2012}\x{2015}]|[\x{2013}\x{2014}][^\]]*\]|\\s[*+]?[\x{2013}\x{2014}]|\|[\x{2013}\x{2014}]|[\x{2013}\x{2014}]\||trim\(.*[\x{2013}\x{2014}]/u';

    /**
     * Commentaire SQL en fin de ligne (`... IS NOT NULL -- raison`) : syntaxe, pas ponctuation.
     * Reconnu à un mot-clé SQL en MAJUSCULES avant le « -- » ; le remplacer par « - »
     * casse la requête (incident du 2026-10-20 sur EraGraphBuilder et BuildLineReferences).
     */
    public const SQL_COMMENT = '/\b(SELECT|FROM|WHERE|JOIN|AND|OR|NOT|NULL|UNION|ALL|CASE|WHEN|THEN|ELSE|END|GROUP BY|ORDER BY|LIMIT|EXISTS|LATERAL|ON|AS|IN|IS|SET|VALUES|WITH|RETURNING)\b.* -- /';

    /** Fin des options d'une commande shell (`git diff … -- chemins`, `podman exec … -- cmd`) : syntaxe. */
    public const SHELL_SEPARATOR = '/\\b(git|pnpm|npm|npx|podman|docker|exec|xargs|php|artisan|composer|bin\\/\\w+)\\b.* -- /';

    /** Commentaires, docblocks, docs et données : aucun tiret long ni « -- » de ponctuation. */
    public function test_comments_docs_and_data_have_no_long_dash(): void
    {
        $root = SourceText::root();
        $files = [
            ...SourceText::files('app', ['.php']),
            ...SourceText::files('database', ['.php']),
            ...SourceText::files('docs', ['.md']),
            ...SourceText::files('routes', ['.php']),
            ...SourceText::files('config', ['.php']),
            ...SourceText::files('.github', ['.yml', '.yaml']),
            ...SourceText::files('resources/js', ['.js', '.svelte']),
            ...SourceText::files('resources/css', ['.css']),
            ...array_filter(['README.md', 'CONTRIBUTING.md', 'ROADMAP.md', 'ARCHITECTURE.md', 'Makefile'], fn ($f) => is_file("{$root}/{$f}")),
        ];

        $offenders = [];
        foreach ($files as $file) {
            foreach (explode("\n", SourceText::read($file)) as $n => $line) {
                $dash = preg_match('/[\x{2014}\x{2013}]/u', $line)
                    || (preg_match('/\S -- \S/', $line) && ! preg_match(self::SQL_COMMENT, $line) && ! preg_match(self::SHELL_SEPARATOR, $line));
                if ($dash && ! preg_match(self::NORMALIZATION, $line)) {
                    $offenders[] = $file.':'.($n + 1).' : '.mb_substr(trim($line), 0, 140);
                }
            }
        }

        $this->assertSame([], $offenders, "Tiret long ou « -- » de ponctuation : utiliser « - ».\n".implode("\n", $offenders));
    }

    public function test_trailing_sql_comment_is_syntax_not_punctuation(): void
    {
        $this->assertMatchesRegularExpression(self::SQL_COMMENT, 'WHERE NOT EXISTS (SELECT 1 FROM g_snode sn WHERE sn.id = g_gap.gap_node) -- fusionné dans une gare');
        $this->assertMatchesRegularExpression(self::SQL_COMMENT, "FROM source_traces WHERE properties->'supersedes' IS NOT NULL -- (pas l'opérateur « ? »)");
        $this->assertDoesNotMatchRegularExpression(self::SQL_COMMENT, '// Ligne fermée en 1938 -- puis rouverte en 1946');
        $this->assertDoesNotMatchRegularExpression(self::SQL_COMMENT, "'description' => 'Paris -- Lyon'");

        $this->assertMatchesRegularExpression(self::SHELL_SEPARATOR, 'git diff --name-only "origin/main...HEAD" -- resources/js resources/css');
        $this->assertDoesNotMatchRegularExpression(self::SHELL_SEPARATOR, '// La gare -- fermée en 1938 -- reste debout');
    }

    /**
     * Le détecteur lui-même : ce qui est affiché est vu, le reste ignoré.
     *
     * @return iterable<string, array{string, string, bool}>
     */
    public static function samples(): iterable
    {
        yield 'balisage' => ['svelte', '<p>Paris — Lyon</p>', true];
        yield 'demi-cadratin' => ['svelte', '<p>1905–1937</p>', true];
        yield 'double tiret' => ['svelte', '<p>Paris -- Lyon</p>', true];
        yield 'chaîne du script' => ['svelte', "<script>const t = 'A — B';</script>", true];
        yield 'gabarit' => ['js', 'const t = `${a} — ${b}`;', true];
        yield 'attribut' => ['svelte', '<img alt="Gare — 1900" />', true];
        yield 'commentaire HTML' => ['svelte', '<!-- Paris — Lyon -->', false];
        yield 'commentaire JS' => ['svelte', "<script>// A — B\n/* C — D */ const x = 1;</script>", false];
        yield 'style' => ['svelte', '<style>.a { --gap: 1rem; content: "—"; }</style>', false];
        yield 'variable CSS en ligne' => ['svelte', '<i style="--c:#ff6500; color: var(--vff-accent)"></i>', false];
        yield 'décrément' => ['js', 'for (let i = n; i--; ) { --j; }', false];
        yield 'expression régulière' => ['js', 'const parts = name.split(/\\s*[–—-]\\s*/u);', false];
        yield 'division puis chaîne' => ['js', "const r = a / b; const s = 'ok';", false];
        yield 'blade' => ['blade', "{{-- A — B --}}\n<p>{{ \$x }}</p>", false];
    }

    #[DataProvider('samples')]
    public function test_detector(string $kind, string $source, bool $flagged): void
    {
        $text = match ($kind) {
            'svelte' => SourceText::svelte($source),
            'blade' => SourceText::blade($source),
            default => SourceText::javascript($source),
        };

        $this->assertSame($flagged, (bool) preg_match(self::DASH, $text), $text);
    }

    /** @return list<string> */
    private function scan(string $file, string $displayed): array
    {
        if (! preg_match_all(self::DASH, $displayed, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $lines = explode("\n", $displayed);
        $found = [];
        foreach ($matches[0] as [, $offset]) {
            $line = SourceText::lineOf($displayed, $offset);
            $found[$line] = "{$file}:{$line} : ".trim($lines[$line - 1]);
        }

        return array_values($found);
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array<string, string>
     */
    private function flatten(array $items, string $prefix = ''): array
    {
        $flat = [];
        foreach ($items as $key => $value) {
            $compound = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $flat += is_array($value) ? $this->flatten($value, $compound) : [$compound => (string) $value];
        }

        return $flat;
    }
}
