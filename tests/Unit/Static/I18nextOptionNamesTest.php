<?php

namespace Tests\Unit\Static;

use PHPUnit\Framework\TestCase;

/**
 * Les valeurs passées à `$i18n.t(clé, { … })` sont aussi les OPTIONS d'i18next : une marque
 * `:lng` dans lang/ (donc `{ lng: … }` côté front) est lue comme la langue voulue. La recherche
 * part alors dans une langue inconnue (« 3,8° O ») puis dans la langue de repli (`fr`), absente
 * du navigateur sur le site anglais ou allemand : la clé brute s'affichait (`stations.map.summary`
 * sur la fiche gare en allemand, 19/11/2026). Sur le site français, le repli masquait le défaut.
 *
 * Aucune marque des fichiers de traduction ni aucune valeur passée par le front ne doit donc
 * porter le nom d'une option d'i18next (`count` excepté : il choisit le pluriel, c'est voulu ;
 * côté front, `defaultValue` aussi : texte de repli demandé exprès).
 */
class I18nextOptionNamesTest extends TestCase
{
    /** Options de `t()` d'i18next (TOptions) qu'une valeur d'interpolation ne doit jamais masquer. */
    private const RESERVED = [
        'lng', 'lngs', 'fallbackLng', 'ns', 'defaultValue', 'context', 'replace', 'ordinal', 'postProcess',
        'returnObjects', 'returnDetails', 'joinArrays', 'keySeparator', 'nsSeparator', 'interpolation',
        'skipInterpolation', 'keyPrefix',
    ];

    /** Options passées exprès par le front (jamais comme valeur d'une marque). */
    private const INTENDED_IN_FRONT = ['defaultValue'];

    public function test_no_translation_placeholder_is_named_like_an_i18next_option(): void
    {
        $names = implode('|', self::RESERVED);
        $found = [];
        foreach (SourceText::files('lang', ['.php']) as $file) {
            foreach (explode("\n", SourceText::read($file)) as $i => $line) {
                // Même règle que lib/i18n.js (normalize) : « :nom » précédé d'un caractère hors mot.
                if (preg_match_all("/(?:^|[^A-Za-z0-9_]):({$names})(?![A-Za-z0-9_])/", $line, $m)) {
                    foreach ($m[1] as $name) {
                        $found[] = "{$file}:".($i + 1)." :{$name}";
                    }
                }
            }
        }

        $this->assertSame([], $found, "Marque au nom d'une option d'i18next (renommer, ex. :lng → :lon) :\n".implode("\n", $found));
    }

    public function test_front_never_passes_an_i18next_option_as_a_translation_value(): void
    {
        $names = implode('|', array_diff(self::RESERVED, self::INTENDED_IN_FRONT));
        $found = [];
        foreach (SourceText::files('resources/js', ['.svelte', '.js']) as $file) {
            $source = SourceText::read($file);
            // `t('clé', { … })`, `$i18n.t(…)`, `i18next.t(…)` : objet littéral en second argument.
            if (preg_match_all('/\\bt\\(\\s*[^,()]+,\\s*\\{([^{}]*)\\}/', $source, $calls, PREG_OFFSET_CAPTURE)) {
                foreach ($calls[1] as [$object, $offset]) {
                    if (preg_match_all("/(?:^|[,{\\s])({$names})\\s*[:,}]/", $object, $m)) {
                        $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                        foreach ($m[1] as $name) {
                            $found[] = "{$file}:{$line} {$name}";
                        }
                    }
                }
            }
        }

        $this->assertSame([], $found, "Valeur au nom d'une option d'i18next passée à t() :\n".implode("\n", $found));
    }
}
