<?php

namespace Tests\Unit;

use App\Support\I18nChecker;
use PHPUnit\Framework\TestCase;

class I18nCheckerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/vff-i18n-'.bin2hex(random_bytes(4));

        $this->writeLang('fr', 'home', [
            'title' => 'Bienvenue',
            'hello' => 'Bonjour :name, il reste :count lignes',
            'note' => 'Note : voir https://vff-project.org',
            'nested' => ['a' => 'A', 'b' => 'B'],
        ]);
        $this->writeLang('fr', 'maps', ['legend' => 'Légende']);
        $this->writeLang('fr', 'only_fr', ['x' => 'X']);

        $this->writeLang('de', 'home', [
            'title' => 'Willkommen',
            'hello' => 'Hallo :name',                       // :count perdu
            'note' => 'Hinweis: siehe https://vff-project.org', // pas de placeholder
            'nested' => ['a' => ''],                          // b manquant, a vide
            'obsolete' => 'Alt',
        ]);
        $this->writeLang('de', 'maps', []);
        $this->writeLang('de', 'orphan', ['y' => 'Y']);

        mkdir($this->root.'/js', 0777, true);
        file_put_contents($this->root.'/js/Page.svelte', <<<'SVELTE'
            <script>
                // Exemple : $i18n.t('commented.key') - ignoré
                const label = $i18n.t('home.title');
                const prefix = $i18n.t('home.dynamic.' + kind);
            </script>
            <!-- {$i18n.t('html.comment')} -->
            <h1>{$i18n.t('home.missing_in_fr')}</h1>
            <p>{i18next.t("maps.legend")}</p>
            SVELTE);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    public function test_detects_missing_keys_files_and_placeholders(): void
    {
        $issues = (new I18nChecker($this->root.'/lang', ['maps']))->check(['de']);
        $index = $this->index($issues);

        $this->assertSame('error', $index['missing_key:home.nested.b']);
        $this->assertSame('error', $index['placeholder_mismatch:home.hello']);
        $this->assertSame('error', $index['missing_file:only_fr']);
        $this->assertSame('warning', $index['empty_value:home.nested.a']);
        $this->assertSame('warning', $index['extra_key:home.obsolete']);
        $this->assertSame('warning', $index['orphan_file:orphan']);

        // « Note : » (typo FR) et « https:// » ne sont pas des placeholders.
        $this->assertArrayNotHasKey('placeholder_mismatch:home.note', $index);
        $this->assertArrayNotHasKey('missing_key:home.title', $index);
    }

    public function test_watch_only_namespaces_are_downgraded_unless_strict(): void
    {
        $lenient = $this->index((new I18nChecker($this->root.'/lang', ['maps']))->check(['de']));
        $strict = $this->index((new I18nChecker($this->root.'/lang', ['maps'], strict: true))->check(['de']));

        $this->assertSame('warning', $lenient['missing_key:maps.legend']);
        $this->assertSame('error', $strict['missing_key:maps.legend']);
    }

    public function test_usage_scan_reports_unknown_literal_keys_only(): void
    {
        $issues = (new I18nChecker($this->root.'/lang', []))->checkUsage($this->root.'/js');
        $keys = array_map(fn ($i) => $i['namespace'].'.'.$i['key'], $issues);

        $this->assertSame(['home.missing_in_fr'], $keys);
        $this->assertSame('warning', $issues[0]['severity']);
    }

    public function test_usage_scan_reads_label_key_tables(): void
    {
        file_put_contents($this->root.'/js/legend.js', <<<'JS'
            // { labelKey: 'commented.key' }
            export const ROWS = [
                { key: 'a', labelKey: 'maps.legend' },
                { key: 'b', titleKey: 'maps.unknown_title', textKey: "home.title" },
            ];
            JS);

        $issues = (new I18nChecker($this->root.'/lang', []))->checkUsage($this->root.'/js');
        $keys = array_map(fn ($i) => $i['namespace'].'.'.$i['key'], $issues);
        sort($keys);

        $this->assertSame(['home.missing_in_fr', 'maps.unknown_title'], $keys);
    }

    public function test_complete_translation_has_no_issue(): void
    {
        $this->writeLang('en', 'home', [
            'title' => 'Welcome',
            'hello' => 'Hello :name, :count lines left',
            'note' => 'Note: see https://vff-project.org',
            'nested' => ['a' => 'A', 'b' => 'B'],
        ]);
        $this->writeLang('en', 'maps', ['legend' => 'Legend']);
        $this->writeLang('en', 'only_fr', ['x' => 'X']);

        $this->assertSame([], (new I18nChecker($this->root.'/lang', [], strict: true))->check(['en']));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeLang(string $locale, string $namespace, array $data): void
    {
        $dir = "{$this->root}/lang/{$locale}";
        is_dir($dir) || mkdir($dir, 0777, true);
        file_put_contents("{$this->root}/lang/{$locale}/{$namespace}.php", '<?php return '.var_export($data, true).';');
    }

    /**
     * @param  list<array{severity: string, type: string, namespace: string, key: ?string}>  $issues
     * @return array<string, string>
     */
    private function index(array $issues): array
    {
        $out = [];
        foreach ($issues as $i) {
            $out[$i['type'].':'.$i['namespace'].($i['key'] !== null ? '.'.$i['key'] : '')] = $i['severity'];
        }

        return $out;
    }
}
