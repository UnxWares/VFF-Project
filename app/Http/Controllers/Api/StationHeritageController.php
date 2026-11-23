<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Heritage\StationHeritage;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
class StationHeritageController extends Controller
{
    /**
     * Patrimoine du bâtiment voyageurs d'une gare.
     *
     * Le bâtiment voyageurs et les ouvrages de la gare (remise, halle, quai monumental) classés ou inscrits aux monuments
     * historiques, labellisés « Architecture contemporaine remarquable » ou recensés à l'Inventaire général du patrimoine
     * culturel, d'après la base Mérimée du ministère de la Culture (POP, Licence Ouverte 2.0) et Wikidata (CC0).
     *
     * - `items[]` : le bâtiment voyageurs d'abord, puis les ouvrages ; chaque objet porte `protection` (`classified`,
     *   `registered`, `acr`, `inventory`), `protection_date` (date de l'arrêté), `scope` (`partial` : seulement certaines
     *   parties), `designations[]` (toutes les mentions), `title` (titre de la notice), `architects[]` (`name`, `role`),
     *   `built` (`from`, `to`, `period`), `style`, `reference` (notice Mérimée ou élément Wikidata, avec son lien),
     *   `image` (image libre de Wikimedia Commons avec sa licence et son auteur, nulle sinon), `source`
     *   (`merimee` ou `wikidata`), `match_method` (comment la gare a été trouvée : `wikidata`, `name-commune`,
     *   `proximity`) et `provenance` ;
     * - `protected` : vrai quand un objet est classé ou inscrit ;
     * - `attribution` : mention à reproduire.
     *
     * `heritage` vaut `null` quand VFF ne connaît aucun patrimoine pour la gare : ce n'est pas « sans intérêt ».
     * Même bloc que `heritage` de `GET /api/v1/stations/{station}`.
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par l\'annuaire ou la recherche.', example: 'limoges-benedictins')]
    #[ApiResponse(200, 'Le patrimoine de la gare (`heritage` nul si aucun n\'est connu).', examples: [[
        'station' => ['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'wikidata_id' => 'Q658277'],
        'heritage' => [
            'items' => [[
                'element' => 'building', 'protection' => 'registered', 'protection_date' => '1975-01-15', 'scope' => null,
                'designations' => [['kind' => 'registered', 'date' => '1975-01-15']],
                'title' => 'Gare des Bénédictins', 'denomination' => 'gare',
                'architects' => [['name' => 'Roger Gonthier', 'role' => 'architecte']],
                'built' => ['from' => 1925, 'to' => null, 'period' => '1er quart 20e siècle'], 'style' => null,
                'reference' => ['id' => 'PA00100349', 'kind' => 'PA', 'url' => 'https://www.pop.culture.gouv.fr/notice/merimee/PA00100349'],
                'wikidata_id' => 'Q658277',
                'image' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Exemple.jpg/500px-Exemple.jpg', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Exemple.jpg', 'file' => 'Exemple.jpg', 'license' => 'CC BY-SA 4.0', 'author' => 'Auteur'],
                'source' => 'merimee', 'source_updated' => '2026-11-19', 'match_method' => 'wikidata',
                'provenance' => ['provider' => 'pop', 'name' => 'POP, Plateforme ouverte du patrimoine (base Mérimée, ministère de la Culture)', 'license' => 'Licence Ouverte 2.0 (Etalab)', 'license_status' => 'verified'],
            ]],
            'protected' => true,
            'attribution' => StationHeritage::ATTRIBUTION,
        ],
    ]])]
    #[ApiResponse(404, 'Gare inconnue.', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Station $station, StationHeritage $heritage): JsonResponse
    {
        return response()->json([
            'station' => ['slug' => $station->slug, 'name' => $station->name, 'wikidata_id' => $station->wikidata_id],
            'heritage' => $heritage->forStation((int) $station->id),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
