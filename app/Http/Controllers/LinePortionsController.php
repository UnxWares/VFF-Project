<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Services\LinePhases;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tracé de la carte d'une fiche de ligne (GET /lines/{slug}/portions/{version}.json) : les portions de
 * LinePhases (géométrie simplifiée, longueur, sources), dans l'ordre que désignent les phases de la page.
 * La page ne les porte plus dans ses props (LineSheet::PAGE_OMITTED) : la carte les lit ici quand elle
 * s'affiche (resources/js/lib/linePortions.js). La fiche complète de l'API (GET /api/v1/lines/{slug}) les
 * donne toujours, avec la règle des droits des tracés.
 *
 * L'adresse porte l'empreinte des lectures dont viennent les portions (LinePhases::forLine, `version`) :
 * quand elle est la bonne, la réponse se garde un an sans revalidation (`immutable`), et le service worker
 * en garde une copie pour la fiche lue hors ligne. Une ancienne empreinte (page ouverte avant une mise à
 * jour des tronçons) reçoit le tracé actuel, sans cache long. Route hors du groupe web, comme le
 * dictionnaire de l'interface : ni session ni cookie, la même réponse pour tous.
 */
final class LinePortionsController
{
    /** Durée de cache d'une adresse à la bonne empreinte (s). */
    public const MAX_AGE = 31_536_000;

    /** Forme de l'empreinte dans l'adresse. */
    public const VERSION_PATTERN = '[0-9a-f]{12}';

    /** Adresse du tracé d'une ligne à une version donnée. */
    public static function url(string $slug, string $version): string
    {
        return route('lines.portions', ['slug' => $slug, 'version' => $version], false);
    }

    public function __invoke(Request $request, string $slug, string $version): Response
    {
        $line = Line::query()->where('slug', $slug)->firstOrFail();
        ['version' => $current, 'portions' => $portions] = LinePhases::portions($line);
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

        $json = json_encode(['version' => $current, 'portions' => $portions], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($gzip) {
            $response->headers->set('Content-Encoding', 'gzip');

            return $response->setContent((string) gzencode($json, 6));
        }

        return $response->setContent($json);
    }
}
