<?php

namespace Tests\Unit;

use App\Support\I18nChecker;
use PHPUnit\Framework\TestCase;

/**
 * Vérificateur de traductions (I18nChecker) : contenu exact des problèmes
 * remontés (détail lisible des placeholders, nombre de clés d'un fichier
 * manquant), poursuite de l'analyse après un problème, placeholders répétés ou
 * réordonnés, clés imbriquées, valeurs non textuelles, mode strict.
 */
class I18nCheckerDetailsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/vff-i18n-details-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $data */
    private function writeLang(string $locale, string $namespace, array $data): void
    {
        $dir = "{$this->root}/lang/{$locale}";
        is_dir($dir) || mkdir($dir, 0777, true);
        file_put_contents("{$dir}/{$namespace}.php", '<?php return '.var_export($data, true).';');
    }

    private function writeJs(string $file, string $source): void
    {
        is_dir($this->root.'/js') || mkdir($this->root.'/js', 0777, true);
        file_put_contents($this->root.'/js/'.$file, $source);
    }

    private function checker(bool $strict = false): I18nChecker
    {
        return new I18nChecker($this->root.'/lang', [], $strict);
    }

    public function test_issue_records_are_complete_and_readable(): void
    {
        $this->writeLang('fr', 'about', ['a' => 'A', 'b' => 'B']);
        $this->writeLang('fr', 'home', ['hello' => 'Bonjour :name, il reste :count lignes']);
        $this->writeLang('de', 'home', ['hello' => 'Hallo :name']);

        $this->assertSame([
            ['severity' => 'error', 'type' => 'missing_file', 'locale' => 'de', 'namespace' => 'about', 'key' => null, 'detail' => '2 clé(s)'],
            ['severity' => 'error', 'type' => 'placeholder_mismatch', 'locale' => 'de', 'namespace' => 'home', 'key' => 'hello',
                'detail' => 'attendu [count, name], trouvé [name]'],
        ], $this->checker()->check(['de']));
    }

    public function test_analysis_goes_on_after_a_missing_key(): void
    {
        $this->writeLang('fr', 'home', ['title' => 'Titre', 'hello' => 'Bonjour :name', 'bye' => 'Au revoir']);
        $this->writeLang('de', 'home', ['hello' => 'Hallo', 'bye' => '   ']);

        $issues = $this->checker()->check(['de']);

        $this->assertSame(['missing_key:title', 'placeholder_mismatch:hello', 'empty_value:bye'],
            array_map(fn ($i) => $i['type'].':'.$i['key'], $issues));
        $this->assertSame('Titre', $issues[0]['detail'], 'valeur française en détail d\'une clé manquante');
    }

    public function test_repeated_or_reordered_placeholders_are_not_a_mismatch(): void
    {
        $this->writeLang('fr', 'home', [
            'twice' => ':name et encore :name',
            'order' => ':from vers :to',
            'blank' => '  ',
        ]);
        $this->writeLang('en', 'home', [
            'twice' => ':name only once',
            'order' => 'to :to from :from',
            'blank' => '',
        ]);

        $this->assertSame([], $this->checker(true)->check(['en']), 'valeur française vide : traduction vide acceptée');
    }

    public function test_nested_keys_and_non_string_values_are_flattened(): void
    {
        $this->writeLang('fr', 'home', ['menu' => ['open' => 'Ouvrir', 'count' => 3], 7 => 'sept']);

        $checker = $this->checker();
        $this->assertSame(['menu.open' => 'Ouvrir', 'menu.count' => '3', '7' => 'sept'], $checker->load('fr', 'home'));
        $this->assertSame(['home'], $checker->namespaces('fr'));
        $this->assertSame([], $checker->namespaces('xx'));
    }

    public function test_watch_only_namespaces(): void
    {
        $default = new I18nChecker($this->root.'/lang');
        $this->assertTrue($default->isWatchOnly('maps'));
        $this->assertFalse($default->isWatchOnly('home'));
        $this->assertFalse((new I18nChecker($this->root.'/lang', []))->isWatchOnly('maps'));
    }

    public function test_strict_mode_turns_unknown_usages_into_errors_but_keeps_other_warnings(): void
    {
        $this->writeLang('fr', 'home', ['title' => 'Titre']);
        $this->writeLang('de', 'home', ['title' => 'Titel', 'old' => 'Alt']);
        $this->writeJs('Page.svelte', "<h1>{\$t('home.title')}</h1><p>{\$t('home.section.missing')}</p>");

        $usage = $this->checker(true)->checkUsage($this->root.'/js');
        $this->assertSame([['severity' => 'error', 'type' => 'unknown_usage', 'locale' => 'fr', 'namespace' => 'home',
            'key' => 'section.missing', 'detail' => 'Page.svelte']], $usage);
        $this->assertSame('warning', $this->checker()->checkUsage($this->root.'/js')[0]['severity']);

        $this->assertSame(['warning'], array_column($this->checker(true)->check(['de']), 'severity'), 'clé obsolète : avertissement même en strict');
    }

    public function test_unknown_usage_is_reported_once_and_comments_are_ignored(): void
    {
        $this->writeLang('fr', 'home', ['title' => 'Titre']);
        $this->writeJs('A.svelte', "<script>/* t('home.block_comment') */ const a = \$t('home.unknown');</script>");
        $this->writeJs('B.js', "// t('home.line_comment')\nexport const b = t(\"home.unknown\") + t('home.title');");

        $issues = $this->checker()->checkUsage($this->root.'/js');

        $this->assertSame(['unknown'], array_column($issues, 'key'));
        $this->assertSame([], $this->checker()->checkUsage($this->root.'/absent'));
    }

    public function test_i18next_plural_keys_are_known_usages(): void
    {
        // `t('home.portions', { count })` lit `home.portions_one` / `home.portions_other`.
        $this->writeLang('fr', 'home', ['portions_one' => ':count portion', 'portions_other' => ':count portions']);
        $this->writeJs('P.svelte', "<p>{\$i18n.t('home.portions', { count: 2 })} {\$i18n.t('home.lonely')}</p>");

        $this->assertSame(['lonely'], array_column($this->checker(true)->checkUsage($this->root.'/js'), 'key'));
    }
}
