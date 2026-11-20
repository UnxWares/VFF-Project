<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SheetMediaRequest;
use App\Models\Line;
use App\Services\Api\MediaGallery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineMediaController extends Controller
{
    /**
     * Médias d'une ligne.
     *
     * Photos, cartes postales, plans, cartes et documents d'archive liés à la
     * ligne, paginés.
     *
     * **Droits** : les médias externes (Wikimedia Commons…) ne sont pas
     * hébergés par VFF ; `thumb_url` (vignette) et `display_url` (grande
     * taille) pointent chez le fournisseur. Chaque média porte ses propres
     * droits, à afficher avec l'image : `attribution` (ligne de crédit prête à
     * l'emploi), `author`, `license` (telle que déclarée par la source, `null`
     * si inconnue), `license_url`, `source_url` (page d'origine),
     * `rights_status` (`public_domain` domaine public, `open_license` licence
     * libre), `commercial_ok` (usage commercial permis), `share_alike`
     * (partage dans les mêmes conditions exigé), `attribution_required`.
     *
     * **Datation** : `depicted_year` est l'année représentée (pas celle de la
     * mise en ligne), `depicted_year_to` la fin d'une fourchette, et
     * `date_precision` dit ce qu'on en sait : `day`, `month`, `year`, `decade`,
     * `circa` (vers), `range` (fourchette), `before` / `after` (avant, après).
     * `captured_at` : date de prise de vue (AAAA-MM-JJ) quand elle est connue.
     *
     * **Contributions** : `contribution_id` donne le numéro de la contribution
     * validée qui a publié le média (Commons ou fichier déposé, `provider` =
     * `upload`, servi par VFF), `null` pour un média importé. Une image
     * proposée et pas encore validée n'apparaît jamais ici.
     *
     * `facets` compte les médias de la ligne par type, période, droits et
     * fournisseur, sur l'ensemble des médias de la ligne (sans les filtres).
     *
     * Même réponse pour une gare (`/stations/{station}/media`) et une série de
     * matériel (`/materials/{material}/media`).
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-420000')]
    #[ApiResponse(200, 'Une page de médias de la ligne, avec la pagination et les compteurs par filtre.', examples: [[
        'line' => ['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest'],
        'data' => [[
            'id' => 42917, 'provider' => 'commons', 'provider_label' => 'Wikimedia Commons', 'type' => 'photo',
            'title' => 'Ouest 111 Buddicom', 'caption' => 'Ouest 111 Buddicom',
            'thumb_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/38/Ouest_111_Buddicom.jpg/500px-Ouest_111_Buddicom.jpg',
            'display_url' => 'https://upload.wikimedia.org/wikipedia/commons/3/38/Ouest_111_Buddicom.jpg',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Ouest_111_Buddicom.jpg',
            'width' => 586, 'height' => 424, 'depicted_year' => 1850, 'depicted_year_to' => null, 'date_precision' => 'circa',
            'captured_at' => null, 'author' => null, 'attribution' => 'Public domain, via Wikimedia Commons',
            'license' => 'Public domain', 'license_url' => null, 'rights_status' => 'public_domain',
            'commercial_ok' => true, 'share_alike' => false, 'attribution_required' => false, 'featured' => false, 'contribution_id' => null, 'location' => null,
        ]],
        'meta' => ['current_page' => 1, 'per_page' => 24, 'total' => 800, 'last_page' => 34, 'sort' => 'oldest'],
        'facets' => [
            'types' => ['photo' => 753, 'postcard' => 42, 'map' => 5],
            'periods' => ['pre1900' => 7, '1900_1944' => 41, '1945_1969' => 2, '1970_1999' => 0, 'since2000' => 729, 'undated' => 21],
            'rights' => ['public_domain' => 175, 'open_license' => 625],
            'providers' => ['commons' => 800],
        ],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue (slug introuvable).')]
    public function index(Line $line, SheetMediaRequest $request, MediaGallery $gallery): JsonResponse
    {
        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            ...$gallery->page($line, $request->validated()),
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
