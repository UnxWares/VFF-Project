<?php

namespace Tests\Feature;

use App\Support\Glossary;
use App\Support\Translations;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ReadsTranslations;
use Tests\TestCase;

/**
 * Glossaire (`/glossary`) : page Inertia, registre versionné (resources/content/glossary/terms.php) et textes
 * en français, anglais et allemand (lang/{fr,en,de}/glossary_terms.php). Aucune base de données : tout est en fichiers.
 */
class GlossaryTest extends TestCase
{
    use ReadsTranslations;

    /** @return array<string, array{0: string}> */
    public static function locales(): array
    {
        return ['fr' => ['fr'], 'en' => ['en'], 'de' => ['de']];
    }

    /** @return array{themes: list<string>, terms: array<string, array<string, mixed>>} */
    private function registry(): array
    {
        return require resource_path('content/glossary/terms.php');
    }

    public function test_the_glossary_page_lists_every_term_sorted_by_theme_by_default(): void
    {
        $this->get('/glossary')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Glossary')
                ->where('view', 'theme')
                ->has('themes', 6)
                ->has('terms', count($this->registry()['terms']))
                ->has('terms.0', fn (Assert $term) => $term
                    ->hasAll(['slug', 'name', 'definition', 'theme', 'letter', 'see', 'full', 'abbr', 'example'])
                )
            );
    }

    public function test_the_alphabetical_view_is_asked_in_the_address(): void
    {
        $this->get('/glossary?vue=alpha')->assertOk()->assertInertia(fn (Assert $page) => $page->where('view', 'alpha'));
        $this->get('/glossary?vue=n-importe-quoi')->assertOk()->assertInertia(fn (Assert $page) => $page->where('view', 'theme'));
    }

    public function test_the_page_follows_the_language_and_keeps_definitions_out_of_the_translation_file(): void
    {
        $response = $this->withCookie('locale', 'de')->get('/glossary');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'de')
            ->where('terms', fn (Collection $terms) => $terms->firstWhere('slug', 'rampe')['name'] === 'Steigung')
        );
        $dictionary = $this->dictionary((string) $response->inertiaProps('translations_url'));
        $this->assertSame('Glossar', $dictionary['glossary.title']);
        $this->assertStringStartsWith('Block automatique lumineux', $dictionary['glossary.abbr.BAL']);
        $this->assertSame([], array_filter(array_keys($dictionary), fn (string $k) => str_starts_with($k, 'glossary_terms.')));
    }

    public function test_the_notions_shown_by_the_site_are_all_defined_with_an_example(): void
    {
        $registry = $this->registry()['terms'];
        $needed = ['tvm', 'tvm-300', 'tvm-430', 'ertms-niveau-1', 'ertms-niveau-2', 'kvb', 'bapr', 'bmu', 'prci', 'poste-d-aiguillage',
            'bifurcation', 'bifurcation-lgv', 'gare-de-bifurcation', 'raccordement', 'circuit-de-voie', 'voie-partagee', 'embarcadere',
            'declassement', 'depose', 'desaffectation', 'fermeture-voyageurs', 'fermeture-fret', 'suspension-de-service', 'autorail',
            'element-automoteur', 'rame-a-turbine-a-gaz', 'bimode', 'famille-et-version', 'serie', 'materiel-preserve', 'profil-en-long',
            'declivite', 'pk', 'voie-unique', 'double-voie', 'electrification-1500', 'electrification-25000', 'halte'];
        foreach ($needed as $slug) {
            $this->assertArrayHasKey($slug, $registry, "{$slug} manque au glossaire");
        }
        foreach (['tvm-300', 'tvm-430', 'ertms-niveau-1', 'ertms-niveau-2', 'bifurcation-lgv', 'embarcadere', 'materiel-preserve'] as $slug) {
            $this->assertArrayHasKey('example', $registry[$slug], "{$slug} : exemple");
        }
    }

    public function test_the_glossary_has_between_sixty_and_one_hundred_sixty_terms(): void
    {
        $count = count($this->registry()['terms']);
        $this->assertGreaterThanOrEqual(60, $count);
        $this->assertLessThanOrEqual(160, $count);
    }

    #[DataProvider('locales')]
    public function test_every_term_has_a_name_and_a_definition_in_each_language(string $locale): void
    {
        $texts = require lang_path("{$locale}/glossary_terms.php");
        $registry = $this->registry()['terms'];

        $this->assertSame(array_keys($registry), array_keys($texts), "{$locale} : mêmes termes, dans le même ordre, que le registre");
        foreach ($texts as $slug => $text) {
            $this->assertNotSame('', trim((string) ($text['name'] ?? '')), "{$locale} {$slug} : nom");
            $this->assertGreaterThan(30, mb_strlen((string) ($text['definition'] ?? '')), "{$locale} {$slug} : définition");
            if (isset($registry[$slug]['abbr'])) {
                $this->assertSame($registry[$slug]['abbr'], $text['name'], "{$locale} {$slug} : le nom d'un sigle est le sigle");
                $this->assertNotSame('', trim((string) ($text['full'] ?? '')), "{$locale} {$slug} : développé du sigle");
            }
        }
    }

    public function test_slugs_are_valid_anchors_and_every_cross_reference_exists(): void
    {
        $registry = $this->registry();
        foreach ($registry['terms'] as $slug => $entry) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
            $this->assertContains($entry['theme'], $registry['themes'], "{$slug} : thème inconnu");
            foreach ($entry['see'] as $other) {
                $this->assertArrayHasKey($other, $registry['terms'], "{$slug} renvoie à un terme absent : {$other}");
                $this->assertNotSame($slug, $other, "{$slug} se renvoie à lui-même");
            }
        }
    }

    public function test_every_example_points_to_a_page_of_the_site(): void
    {
        foreach ($this->registry()['terms'] as $slug => $entry) {
            $example = $entry['example'] ?? null;
            if ($example === null) {
                continue;
            }
            $this->assertMatchesRegularExpression('#^/(lines|stations|materials)/[a-z0-9-]+$|^/(tourist-railways|heritage|chronology|maps\?layer=signalling)$#', $example['path'], "{$slug} : adresse");
            $this->assertTrue(isset($example['name']) xor isset($example['page']), "{$slug} : un nom de fiche ou une page");
            if (isset($example['page'])) {
                foreach (['fr', 'en', 'de'] as $locale) {
                    $ui = require lang_path("{$locale}/glossary.php");
                    $this->assertArrayHasKey($example['page'], $ui['pages'], "{$slug} : page {$example['page']} en {$locale}");
                }
            }
        }
    }

    #[DataProvider('locales')]
    public function test_the_abbreviations_of_the_sheets_match_the_acronym_terms(string $locale): void
    {
        $ui = require lang_path("{$locale}/glossary.php");
        $texts = require lang_path("{$locale}/glossary_terms.php");
        $expected = [];
        foreach ($this->registry()['terms'] as $slug => $entry) {
            if (isset($entry['abbr'])) {
                $expected[$entry['abbr']] = $texts[$slug]['full'];
            }
        }
        $this->assertSame($expected, $ui['abbr']);
        $this->assertEqualsCanonicalizing($this->registry()['themes'], array_keys($ui['themes']));
    }

    public function test_terms_come_out_alphabetically_without_regard_to_accents(): void
    {
        $names = array_column(Glossary::all('fr'), 'name');
        $this->assertSame('Aiguillage', $names[0]);
        $keys = array_map(fn (string $n): string => strtolower(Str::ascii($n)), $names);
        $sorted = $keys;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $keys);
        $this->assertSame('E', Glossary::letter('Écartement'));
        $this->assertSame('E', Glossary::letter('Ère'));
    }

    public function test_the_german_texts_name_the_german_systems_correctly(): void
    {
        $kvb = Glossary::find('kvb', 'de');
        $this->assertNotNull($kvb);
        $this->assertSame('KVB', $kvb['name']);
        $this->assertStringContainsString('Linienzugbeeinflussung (LZB)', $kvb['definition']);
        $this->assertStringContainsString('nicht', $kvb['definition']);
        $this->assertNull(Glossary::find('inconnu', 'fr'));
    }

    public function test_the_signalling_and_rolling_stock_dates_follow_their_sources(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $tvm300 = Glossary::find('tvm-300', $locale);
            $tvm430 = Glossary::find('tvm-430', $locale);
            $rtg = Glossary::find('rame-a-turbine-a-gaz', $locale);
            $this->assertNotNull($tvm300);
            $this->assertNotNull($tvm430);
            $this->assertNotNull($rtg);
            // TVM 300 : LGV Atlantique (24/09/1989) et nord de la LGV Rhône-Alpes (13/12/1992), pas « 1981 = TVM 300 ».
            $this->assertMatchesRegularExpression('/1989/', $tvm300['definition']);
            $this->assertMatchesRegularExpression('/1992/', $tvm300['definition']);
            $this->assertMatchesRegularExpression('/260/', $tvm300['definition']);
            $this->assertDoesNotMatchRegularExpression('/300 km\/h/', $tvm300['definition']);
            // TVM 430 : première section de la LGV Nord le 23/05/1993.
            $this->assertMatchesRegularExpression('/23(\.|\s)\s?(mai|May|Mai)/u', $tvm430['definition']);
            $this->assertStringContainsString('1993', $tvm430['definition']);
            // RTG : dernier service en décembre 2004.
            $this->assertStringContainsString('2004', $rtg['definition']);
        }
    }

    public function test_the_glossary_is_in_the_sitemap(): void
    {
        $this->assertStringContainsString('/glossary</loc>', (string) $this->get('/sitemap-pages-1.xml')->getContent());
    }

    public function test_glossary_texts_are_server_only(): void
    {
        $this->assertContains('glossary_terms', Translations::SERVER_ONLY);
    }
}
