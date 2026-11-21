<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Support\Translations;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Dictionnaire de l'interface d'une langue (GET /i18n/{locale}/{version}.json), lu par le navigateur avant
 * le premier affichage (resources/js/lib/i18n.js, loadTranslations) au lieu d'être recopié dans chaque page.
 *
 * L'adresse porte l'empreinte du contenu (Translations::url) : quand elle est la bonne, la réponse se garde
 * un an sans revalidation (`immutable`). Une ancienne empreinte (page restée ouverte pendant un déploiement)
 * reçoit le dictionnaire actuel, sans cache long, plutôt qu'une erreur qui laisserait la page sans texte.
 * Route hors du groupe web : ni session ni cookie, la réponse est la même pour tous.
 */
final class TranslationsController
{
    /** Durée de cache d'une adresse à la bonne empreinte (s). */
    public const MAX_AGE = 31_536_000;

    public function __invoke(Request $request, string $locale, string $version): Response
    {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

        $current = Translations::payload($locale)['version'];
        $gzip = str_contains(strtolower((string) $request->header('Accept-Encoding', '')), 'gzip');

        $response = new Response('', 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => $version === $current ? 'public, max-age='.self::MAX_AGE.', immutable' : 'no-cache',
            'Vary' => 'Accept-Encoding',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        // Le corps compressé et le corps brut ne sont pas les mêmes octets : deux ETag.
        $response->setEtag($current.($gzip ? '-gz' : ''));
        if ($response->isNotModified($request)) {
            return $response;
        }

        if ($gzip) {
            $response->headers->set('Content-Encoding', 'gzip');

            return $response->setContent(Translations::gzipped($locale));
        }

        return $response->setContent(Translations::payload($locale)['json']);
    }
}
