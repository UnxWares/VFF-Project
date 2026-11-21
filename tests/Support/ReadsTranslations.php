<?php

namespace Tests\Support;

/**
 * Dictionnaire de l'interface qu'une page désigne (prop `translations_url`, App\Http\Controllers\
 * TranslationsController) : lu comme le navigateur le lit, par son adresse.
 */
trait ReadsTranslations
{
    /**
     * @return array<string, string> map plate « namespace.cle » => texte
     */
    protected function dictionary(string $url): array
    {
        /** @var array<string, string> $dictionary */
        $dictionary = $this->get($url)->assertOk()->json();

        return $dictionary;
    }
}
