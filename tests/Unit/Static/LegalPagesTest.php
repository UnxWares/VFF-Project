<?php

namespace Tests\Unit\Static;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Pages légales (mentions, confidentialité, CGU, accessibilité) : chaque section affichée existe
 * dans les trois langues, les traductions gardent les mêmes liens que le français, les liens
 * internes mènent à une route, les informations de l'éditeur sont présentes et la liste des
 * cookies de la politique de confidentialité correspond à ce que fait le code.
 */
class LegalPagesTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'de'];

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pages(): array
    {
        return [
            'mentions légales' => ['legal', 'Legal'],
            'confidentialité' => ['privacy', 'Privacy'],
            'CGU' => ['use_conditions', 'UseConditions'],
            'accessibilité' => ['accessibility', 'Accessibility'],
        ];
    }

    /**
     * @return list<string>
     */
    private function sections(string $component): array
    {
        $svelte = file_get_contents(resource_path("js/Pages/{$component}.svelte"));
        $this->assertSame(1, preg_match('/sections=\{\[(.*?)\]\}/s', $svelte, $m), "{$component} : liste des sections");
        preg_match_all("/'([a-z_]+)'/", $m[1], $names);

        return $names[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function texts(string $locale, string $namespace): array
    {
        return require lang_path("{$locale}/{$namespace}.php");
    }

    #[DataProvider('pages')]
    public function test_every_section_exists_in_every_locale(string $namespace, string $component): void
    {
        $sections = $this->sections($component);
        $this->assertNotEmpty($sections);

        foreach (self::LOCALES as $locale) {
            $texts = $this->texts($locale, $namespace);
            foreach (['meta_title', 'meta_description'] as $key) {
                $this->assertNotSame('', trim($texts[$key] ?? ''), "{$locale}/{$namespace}.{$key}");
            }
            $this->assertStringContainsString(':date', $texts['hero']['updated'] ?? '', "{$locale}/{$namespace}.hero.updated");
            foreach ($sections as $section) {
                $this->assertNotSame('', trim($texts[$section]['title'] ?? ''), "{$locale}/{$namespace}.{$section}.title");
                $this->assertNotSame('', trim($texts[$section]['paragraph'] ?? ''), "{$locale}/{$namespace}.{$section}.paragraph");
            }
            $this->assertSame([], array_diff(array_keys($texts), [...$sections, 'meta_title', 'meta_description', 'hero']),
                "{$locale}/{$namespace} : section écrite mais jamais affichée");
        }
    }

    #[DataProvider('pages')]
    public function test_translations_keep_the_french_links(string $namespace, string $component): void
    {
        $fr = $this->texts('fr', $namespace);
        foreach (['en', 'de'] as $locale) {
            $texts = $this->texts($locale, $namespace);
            foreach ($this->sections($component) as $section) {
                $this->assertSame(
                    $this->hrefs($fr[$section]['paragraph'], 'fr'),
                    $this->hrefs($texts[$section]['paragraph'], $locale),
                    "{$locale}/{$namespace}.{$section} : liens différents du français",
                );
                foreach (['<li', '<td', '<code', '<strong'] as $tag) {
                    $this->assertSame(
                        substr_count($fr[$section]['paragraph'], $tag),
                        substr_count($texts[$section]['paragraph'], $tag),
                        "{$locale}/{$namespace}.{$section} : nombre de {$tag}>",
                    );
                }
            }
        }
    }

    /**
     * Liens d'un texte, la page de licence Creative Commons dans la langue du texte ramenée au français.
     *
     * @return list<string>
     */
    private function hrefs(string $html, string $locale): array
    {
        preg_match_all('/href="([^"]+)"/', $html, $m);

        return array_map(fn ($href) => str_replace("/deed.{$locale}", '/deed.fr', $href), $m[1]);
    }

    public function test_internal_links_lead_to_a_route(): void
    {
        foreach (self::pages() as [$namespace]) {
            foreach (self::LOCALES as $locale) {
                preg_match_all('/href="(\/[^"#]*)"/', json_encode($this->texts($locale, $namespace), JSON_UNESCAPED_SLASHES), $m);
                foreach (array_unique($m[1]) as $path) {
                    try {
                        Route::getRoutes()->match(Request::create($path));
                    } catch (HttpException) {
                        $this->fail("{$locale}/{$namespace} : le lien {$path} ne mène à aucune route");
                    }
                }
            }
        }
        $this->addToAssertionCount(1);
    }

    public function test_publisher_details_are_complete(): void
    {
        foreach (self::LOCALES as $locale) {
            $legal = json_encode($this->texts($locale, 'legal'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach (['UnxWares', '945 207 991', '945 207 991 00015', 'FR93 945 207 991', '78 avenue des Champs-Élysées', 'Baptiste Gosselin', 'contact@vff-project.org'] as $needle) {
                $this->assertStringContainsString($needle, $legal, "{$locale}/legal : {$needle}");
            }
            $privacy = json_encode($this->texts($locale, 'privacy'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->assertStringContainsString('privacy@vff-project.org', $privacy, "{$locale}/privacy");
        }
    }

    public function test_no_placeholder_is_left(): void
    {
        foreach (self::pages() as [$namespace]) {
            foreach (self::LOCALES as $locale) {
                $source = file_get_contents(lang_path("{$locale}/{$namespace}.php"));
                $this->assertDoesNotMatchRegularExpression('/à compléter|to be completed|TODO|XXX|lorem ipsum|example\.org/i', $source, "{$locale}/{$namespace}");
            }
        }
    }

    public function test_cookie_list_matches_the_code(): void
    {
        $privacy = $this->texts('fr', 'privacy')['cookies']['paragraph'];

        $this->assertSame('vff_session', Str::slug('VFF', '_').'_session');
        $this->assertStringContainsString('APP_NAME=VFF', file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString('<code>vff_session</code>', $privacy);
        $this->assertStringContainsString("cookie('locale'", file_get_contents(app_path('Http/Controllers/LocaleController.php')));
        $this->assertStringContainsString("const COOKIE = 'theme'", file_get_contents(resource_path('js/lib/stores/theme.svelte.js')));
        $this->assertStringContainsString("'vff:trace-draft:v1'", file_get_contents(resource_path('js/Pages/Contribute/Trace.svelte')));
        foreach (['XSRF-TOKEN', 'remember_web_', 'locale', 'theme', 'vff:trace-draft:v1'] as $name) {
            $this->assertStringContainsString("<code>{$name}", $privacy, "cookie {$name}");
        }
        $this->assertSame(120, (int) config('session.lifetime'), 'durée de session annoncée : 2 heures');
    }
}
