<?php

namespace Tests\Feature;

use Tests\TestCase;

class I18nCheckCommandTest extends TestCase
{
    private string $lang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lang = sys_get_temp_dir().'/vff-i18n-cmd-'.bin2hex(random_bytes(4));
        foreach (['fr', 'en', 'de'] as $locale) {
            mkdir("{$this->lang}/{$locale}", 0777, true);
        }
        file_put_contents("{$this->lang}/fr/nav.php", "<?php return ['maps' => 'Nos cartes', 'lines' => 'Lignes'];");
        file_put_contents("{$this->lang}/en/nav.php", "<?php return ['maps' => 'Our maps', 'lines' => 'Lines'];");
        $this->app->useLangPath($this->lang);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->lang));
        parent::tearDown();
    }

    public function test_fails_when_a_french_key_is_missing_in_german(): void
    {
        file_put_contents("{$this->lang}/de/nav.php", "<?php return ['maps' => 'Unsere Karten'];");

        $this->artisan('vff:i18n-check', ['--no-usage' => true])
            ->expectsOutputToContain('nav.lines')
            ->assertFailed();
    }

    public function test_passes_when_translations_are_complete(): void
    {
        file_put_contents("{$this->lang}/de/nav.php", "<?php return ['maps' => 'Unsere Karten', 'lines' => 'Strecken'];");

        $this->artisan('vff:i18n-check', ['--no-usage' => true])
            ->expectsOutputToContain('Aucune clé FR manquante')
            ->assertSuccessful();
    }
}
