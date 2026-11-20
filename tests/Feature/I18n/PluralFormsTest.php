<?php

namespace Tests\Feature\I18n;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pluriels des textes de contribution (propositions, éditeur, espace de contribution) : jamais de
 * « lien(s) », « Verbindung(en) » ou « link(s) », mais une forme au singulier et une au pluriel
 * (clés `_one` et `_other`, choisies par i18next d'après `count` dans l'interface).
 */
class PluralFormsTest extends TestCase
{
    private const FILES = ['proposals', 'editor', 'contribute'];

    /** @return iterable<string, array{string}> */
    public static function languages(): iterable
    {
        yield 'fr' => ['fr'];
        yield 'en' => ['en'];
        yield 'de' => ['de'];
    }

    #[DataProvider('languages')]
    public function test_no_text_writes_a_plural_in_brackets(string $lang): void
    {
        foreach (self::FILES as $file) {
            foreach (Arr::dot(require lang_path("{$lang}/{$file}.php")) as $key => $text) {
                $this->assertDoesNotMatchRegularExpression('/\p{L}\((s|e|en|n|es|x)\)/u', (string) $text, "{$lang}/{$file}.{$key}");
            }
        }
    }

    #[DataProvider('languages')]
    public function test_the_impact_notices_have_a_singular_and_a_plural(string $lang): void
    {
        foreach (['proposals.review.impact_text', 'proposals.series.impact_text'] as $key) {
            $one = trans("{$key}_one", ['count' => 1], $lang);
            $other = trans("{$key}_other", ['count' => 3], $lang);
            $this->assertNotSame("{$key}_one", $one, $lang);
            $this->assertNotSame("{$key}_other", $other, $lang);
            $this->assertStringContainsString('1 ', $one);
            $this->assertStringContainsString('3 ', $other);
            $this->assertNotSame($one, str_replace('3 ', '1 ', $other), "{$lang} : le singulier diffère du pluriel");
        }
        $this->assertStringContainsString('1 lien de la série', trans('proposals.series.impact_text_one', ['count' => 1], 'fr'));
        $this->assertStringContainsString('3 liens de la série', trans('proposals.series.impact_text_other', ['count' => 3], 'fr'));
    }
}
