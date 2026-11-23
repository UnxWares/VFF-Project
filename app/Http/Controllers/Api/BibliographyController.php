<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Station;
use App\Services\Bibliography\SheetBibliography;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class BibliographyController extends Controller
{
    /** Exemple réel : la ligne des Causses (Béziers à Neussargues), un ouvrage rattaché par le nom d'usage de la ligne. */
    private const EXAMPLE_ITEM = [
        'kind' => 'book',
        'title' => "L'épopée de la ligne des Causses : histoire du chemin de fer de Graissessac à Béziers, de la ligne de Béziers à Neussargues et de son antenne vers Saint-Affrique",
        'authors' => ['Jean-Michel Biscarat', 'Gilles Degenève', 'Philippe Marassé'],
        'publisher' => 'Les éditions du Cabri',
        'place' => 'Breil-sur-Roya',
        'year' => 2025,
        'isbn' => '9782914603928',
        'journal' => null,
        'url' => 'https://catalogue.bnf.fr/ark:/12148/cb487003656',
        'source' => 'bnf',
        'match' => ['basis' => 'title_alias', 'provider' => 'bnf', 'score' => null, 'reasons' => [['code' => 'line_name', 'points' => 0]], 'origin_url' => 'https://catalogue.bnf.fr/ark:/12148/cb487003656'],
        'fetched_on' => '2026-11-23',
    ];

    public function __construct(private readonly SheetBibliography $bibliography) {}

    /**
     * Bibliographie d'une ligne.
     *
     * Les ouvrages et articles qui traitent de la ligne : monographies, revues ferroviaires, études locales. Chaque
     * `items[]` porte `kind` (`book` ou `article`), `title`, `authors` (« Prénom Nom », quatre au plus), `publisher`,
     * `place`, `year`, `isbn` (ISBN-13 sans séparateur, s'il existe), `journal` (revue d'un article), `url` (notice du
     * catalogue général de la BnF, à défaut la référence d'origine : révision de l'article de Wikipédia qui la cite,
     * élément Wikidata) et `source` (`bnf`, `wikipedia` ou `wikidata`). VFF ne sert que les données de la notice, jamais
     * le contenu de l'ouvrage. Les plus récents d'abord, sans date en dernier ; 60 au plus (`total` donne le nombre
     * rattaché).
     *
     * `match` dit pourquoi la référence est rattachée à la ligne, avec prudence (jamais un ouvrage général sur les
     * chemins de fer) : `basis` vaut `title` (le titre nomme deux lieux de la ligne, dont un trajet ou ses extrémités, avec
     * un mot du rail : même score que `/archives`, `score` et `reasons`), `title_alias` (le titre écrit un nom d'usage de la
     * ligne, « la ligne des Causses »), `article_bibliography` (référence de la section « Bibliographie » de l'article de
     * Wikipédia de la ligne, dont le titre nomme la ligne), `article_reference` (référence citée par une note de cet article,
     * mêmes conditions) ou `wikidata` (« décrit par » ou « sujet principal »). `match.origin_url` : la notice, la révision
     * de l'article ou l'élément qui porte le rattachement.
     *
     * **Droits** : notices de la BnF sous Licence Ouverte (Etalab), à citer avec la source et la date de récupération
     * (`fetched_on`) ; références de Wikipédia (CC BY-SA 4.0) et de Wikidata (CC0). Reprenez la mention `attribution`.
     * Liste vide pour une ligne sans référence rattachée.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-722000')]
    #[ApiResponse(200, 'Ouvrages et articles rattachés à la ligne (liste vide s\'il n\'y en a aucun).', examples: [[
        'line' => ['slug' => 'rfn-722000', 'code' => '722000', 'name' => 'Ligne de Béziers à Neussargues'],
        'total' => 1,
        'items' => [self::EXAMPLE_ITEM],
        'attribution' => SheetBibliography::ATTRIBUTION,
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function line(Line $line): JsonResponse
    {
        $bibliography = $this->bibliography->forLine($line);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'total' => $bibliography['total'],
            'items' => $bibliography['items'],
            'attribution' => SheetBibliography::ATTRIBUTION,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Bibliographie d'une gare.
     *
     * Les ouvrages et articles qui traitent de la gare, au même format que `GET /api/v1/lines/{line}/bibliography`. Le
     * rattachement est plus strict encore : le titre doit nommer la gare (« la gare de Limoges-Bénédictins ») et un nom porté
     * par plusieurs gares n'est jamais rattaché (`match.basis` : `title`, `article_bibliography`, `article_reference` ou
     * `wikidata`). Les ouvrages sur la ligne ou sur la commune ne sont pas la bibliographie de la gare : ils sont sur la
     * fiche de la ligne. Liste vide pour une gare sans référence rattachée.
     */
    #[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par l\'annuaire ou la recherche.', example: 'limoges-benedictins')]
    #[ApiResponse(200, 'Ouvrages et articles rattachés à la gare (liste vide s\'il n\'y en a aucun).', examples: [[
        'station' => ['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins'],
        'total' => 0,
        'items' => [],
        'attribution' => SheetBibliography::ATTRIBUTION,
    ]])]
    #[ApiResponse(404, 'Gare inconnue.')]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function station(Station $station): JsonResponse
    {
        $bibliography = $this->bibliography->forStation($station);

        return response()->json([
            'station' => ['slug' => $station->slug, 'name' => $station->name],
            'total' => $bibliography['total'],
            'items' => $bibliography['items'],
            'attribution' => SheetBibliography::ATTRIBUTION,
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
