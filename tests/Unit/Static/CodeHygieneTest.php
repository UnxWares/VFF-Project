<?php

namespace Tests\Unit\Static;

use PHPUnit\Framework\TestCase;

/**
 * Hygiène du code, par lecture statique des sources (sans application ni base) :
 *   - `env()` seulement dans config/ (hors de config/, il renvoie null dès que la configuration est en cache) ;
 *   - aucun reste de débogage (dd, dump, var_dump, ray, print_r ; console.log, debugger) ;
 *   - Svelte 5 en runes uniquement : ni `export let`, ni `$:`, ni `on:évènement`, ni dispatcher, ni <slot> ;
 *   - chaque migration est réversible (méthode down).
 */
class CodeHygieneTest extends TestCase
{
    /** @param  list<string>  $files */
    private function offences(array $files, string $pattern, callable $prepare, string $why): array
    {
        $found = [];
        foreach ($files as $file) {
            $source = $prepare(SourceText::read($file));
            if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$text, $offset]) {
                    $found[] = "{$file}:".SourceText::lineOf($source, $offset)." : {$why} ({$text})";
                }
            }
        }

        return $found;
    }

    /** Code PHP sans commentaires ni chaînes (les appels cités dans un texte ne comptent pas). */
    private static function php(string $source): string
    {
        $out = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                $out .= in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)
                    ? SourceText::blank($token[1])
                    : $token[1];
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function phpFiles(): array
    {
        return [
            ...SourceText::files('app', ['.php']),
            ...SourceText::files('routes', ['.php']),
            ...SourceText::files('database', ['.php']),
            ...SourceText::files('bootstrap', ['.php']),
            ...SourceText::files('resources/views', ['.php']),
        ];
    }

    public function test_env_is_only_read_in_the_config_directory(): void
    {
        $found = $this->offences(self::phpFiles(), '/(?<![\w>:$])env\s*\(/', self::php(...), 'env() hors de config/');

        $this->assertSame([], $found, implode("\n", $found));
    }

    public function test_no_debugging_leftover_in_php(): void
    {
        $found = $this->offences(
            self::phpFiles(),
            '/(?<![\w>:$])(?:dd|ddd|dump|var_dump|ray|print_r)\s*\(/',
            self::php(...),
            'reste de débogage',
        );

        $this->assertSame([], $found, implode("\n", $found));
    }

    public function test_no_debugging_leftover_in_the_front(): void
    {
        $found = $this->offences(
            SourceText::files('resources/js', ['.js', '.svelte']),
            '/\bconsole\.(?:log|debug|trace|dir)\s*\(|\bdebugger\b/',
            fn (string $s) => SourceText::javascript(SourceText::svelte($s)),
            'reste de débogage',
        );

        $this->assertSame([], $found, implode("\n", $found));
    }

    public function test_svelte_components_use_runes_only(): void
    {
        $legacy = [
            '/\bexport\s+let\s/' => '`export let` (utiliser $props())',
            '/^\s*\$:\s/m' => 'déclaration réactive `$:` (utiliser $derived / $effect)',
            '/(?<=\s)on:[a-z]+/' => 'directive `on:` (utiliser un attribut onclick…)',
            '/\bcreateEventDispatcher\b/' => 'createEventDispatcher (utiliser une fonction en prop)',
            '/<slot\b/' => '<slot> (utiliser {@render children()})',
            '/\$\$(?:props|restProps|slots)\b/' => '$$props / $$restProps / $$slots',
            '/\b(?:beforeUpdate|afterUpdate)\b/' => 'beforeUpdate / afterUpdate (utiliser $effect.pre / $effect)',
        ];
        $found = [];
        foreach (SourceText::files('resources/js', ['.svelte']) as $file) {
            $source = SourceText::svelte(SourceText::read($file));
            foreach ($legacy as $pattern => $why) {
                if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as [, $offset]) {
                        $found[] = "{$file}:".SourceText::lineOf($source, $offset)." : syntaxe Svelte 4, {$why}";
                    }
                }
            }
        }

        $this->assertSame([], $found, implode("\n", $found));
    }

    public function test_every_migration_can_be_rolled_back(): void
    {
        $irreversible = [];
        $files = SourceText::files('database/migrations', ['.php']);
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            if (! preg_match('/function\s+down\s*\(/', SourceText::read($file))) {
                $irreversible[] = "{$file} : pas de méthode down()";
            }
        }

        $this->assertSame([], $irreversible, implode("\n", $irreversible));
    }

    public function test_the_hygiene_patterns_do_catch_what_they_target(): void
    {
        // Garde-fou : un motif cassé laisserait passer la régression qu'il vise.
        $this->assertSame(1, preg_match('/(?<![\w>:$])env\s*\(/', self::php("<?php\n\$x = env('A');")));
        $this->assertSame(0, preg_match('/(?<![\w>:$])env\s*\(/', self::php("<?php\n// env('A') est dans config/\n\$x = config('a');")));
        $this->assertSame(0, preg_match('/(?<![\w>:$])env\s*\(/', self::php("<?php\n\$x = \$app->env('local');")));
        $this->assertSame(1, preg_match('/(?<=\s)on:[a-z]+/', '<button on:click={go}>'));
        $this->assertSame(1, preg_match('/\bexport\s+let\s/', 'export let name;'));
    }
}
