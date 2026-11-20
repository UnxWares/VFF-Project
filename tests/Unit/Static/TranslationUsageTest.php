<?php

namespace Tests\Unit\Static;

use App\Support\I18nChecker;
use PHPUnit\Framework\TestCase;

/**
 * Chaque clé de traduction littérale du front existe dans la locale
 * canonique (lang/fr) : `$i18n.t('ns.cle')`, `t('ns.cle')`, et les tables de
 * libellés (`labelKey: 'ns.cle'`, `titleKey`, `textKey`…).
 *
 * `vff:i18n-check` ne fait que SIGNALER ces clés (avertissement) ; ici elles
 * sont bloquantes, sans exception, car une clé inconnue s'affiche telle
 * quelle à l'écran. La parité FR → EN / DE reste du ressort de
 * `vff:i18n-check` (CI), qui la contrôle déjà.
 */
class TranslationUsageTest extends TestCase
{
    public function test_every_literal_front_key_exists_in_french(): void
    {
        $checker = new I18nChecker(SourceText::root().'/lang', [], strict: true);

        $unknown = array_map(
            fn ($i) => "{$i['namespace']}.{$i['key']} ({$i['detail']})",
            $checker->checkUsage(SourceText::root().'/resources/js'),
        );

        $this->assertSame([], $unknown, "Clé utilisée par le front mais absente de lang/fr :\n".implode("\n", $unknown));
    }
}
