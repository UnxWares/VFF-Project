<?php

namespace Tests\Feature\Services;

use App\Support\Translations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Traductions partagées au front (Translations::load) : map plate
 * « namespace.cle.sous_cle », repli sur le français pour les clés absentes,
 * mise en cache par locale hors mode debug.
 */
class TranslationsTest extends TestCase
{
    private string $langPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->langPath = storage_path('framework/testing/lang-'.getmypid());
        File::deleteDirectory($this->langPath);
        File::ensureDirectoryExists($this->langPath.'/fr');
        File::ensureDirectoryExists($this->langPath.'/de');
        File::put($this->langPath.'/fr/nav.php', "<?php return ['maps' => 'Cartes', 'menu' => ['open' => 'Ouvrir', 'close' => 'Fermer'], 'count' => 3];");
        File::put($this->langPath.'/fr/home.php', "<?php return ['title' => 'Bienvenue :name'];");
        File::put($this->langPath.'/de/nav.php', "<?php return ['maps' => 'Karten', 'menu' => ['open' => 'Öffnen']];");
        $this->app->useLangPath($this->langPath);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->langPath);
        parent::tearDown();
    }

    public function test_canonical_locale_is_flattened_with_dotted_keys(): void
    {
        config(['app.debug' => true]);

        $this->assertSame([
            'home.title' => 'Bienvenue :name',
            'nav.maps' => 'Cartes',
            'nav.menu.open' => 'Ouvrir',
            'nav.menu.close' => 'Fermer',
            'nav.count' => '3',
        ], Translations::load('fr'));
    }

    public function test_missing_keys_fall_back_to_french(): void
    {
        config(['app.debug' => true]);

        $de = Translations::load('de');

        $this->assertSame('Karten', $de['nav.maps']);
        $this->assertSame('Öffnen', $de['nav.menu.open']);
        $this->assertSame('Fermer', $de['nav.menu.close'], 'clé absente en allemand : valeur française');
        $this->assertSame('Bienvenue :name', $de['home.title'], 'fichier absent en allemand : valeurs françaises');
        $this->assertCount(5, $de);
    }

    public function test_unknown_locale_directory_gives_the_french_map(): void
    {
        config(['app.debug' => true]);

        $this->assertSame(Translations::load('fr'), Translations::load('xx'));
    }

    public function test_result_is_cached_per_locale_outside_debug_mode(): void
    {
        config(['app.debug' => false]);

        $first = Translations::load('de');
        $this->assertSame($first, Cache::get('translations.de'));
        $this->assertFalse(Cache::has('translations.fr'));

        // Fichier modifié après coup : la valeur en cache est servie telle quelle.
        File::put($this->langPath.'/de/nav.php', "<?php return ['maps' => 'Landkarten'];");
        $this->assertSame('Karten', Translations::load('de')['nav.maps']);

        // En debug, toujours relu sur disque.
        config(['app.debug' => true]);
        $this->assertSame('Landkarten', Translations::load('de')['nav.maps']);
    }
}
