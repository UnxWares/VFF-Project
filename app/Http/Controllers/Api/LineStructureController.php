<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Structures\LineStructures;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineStructureController extends Controller
{
    /**
     * Ouvrages d'art d'une ligne.
     *
     * Tunnels, viaducs et grands ponts que traverse la ligne, dans l'ordre des points kilométriques (PK), ceux
     * d'une ligne fermée ou déposée compris (l'ouvrage reste, même sans voie).
     *
     * **Ouvrages retenus** : les tunnels de 50 m ou plus et les ponts de 100 m ou plus de la BD TOPO de l'IGN
     * (tronçons de voie en tunnel ou sur pont, assemblés en un ouvrage), les tunnels et ponts nommés de la
     * BD TOPO, et les ouvrages ferroviaires de Wikidata. `kind` vaut `tunnel`, `viaduct` (nom en « Viaduc »
     * ou classe Wikidata de viaduc) ou `bridge`.
     *
     * **Champs** : `length_m` (mesurée sur le tracé de la BD TOPO, `length_source` = `bdtopo` ; sinon la
     * longueur de Wikidata, `wikidata`) ; `pk_start_m`, `pk_end_m` et leurs libellés `km+mmm`, PK estimés
     * en projetant les extrémités sur le profil en long de la ligne, calé sur les PK officiels des gares
     * (nuls sans profil ou sans calage ; ouvrage connu par un seul point : début et fin égaux) ;
     * `distance_from_m`, `distance_to_m` le long du profil (`GET /lines/{line}/profile`) ; `built`
     * (construction), `opened` (mise en service), `closed` (fermeture), chacun `{date, precision}` avec
     * `precision` = `year`, `month` ou `day` (la date d'une année seule est le 1er janvier) ; `heritage`
     * vaut `classified` (classé monument historique), `registered` (inscrit), `protected` (protégé
     * au titre des monuments historiques, notice Mérimée « PA », classement ou inscription non précisé) ou nul, `merimee_ref` la
     * référence de la base Mérimée ; `image`, photo libre de Wikimedia Commons (auteur, licence et page du
     * fichier à reprendre) ; `era_years`, ères de VFF où la ligne est en service à l'ouvrage, et
     * `current`, vrai si elle l'est à l'ère actuelle ; `offset_m`, écart entre l'ouvrage et le tracé ;
     * `sources[]`, source de chaque champ (`bdtopo`, `bdtopo-construction`, `wikidata`, `wikipedia`, `merimee`).
     *
     * **Résumé** (`summary`) : nombre d'ouvrages par type, longueur totale des tunnels, ouvrage le plus long.
     *
     * **Licence** : tracés et longueurs IGN, Licence Ouverte 2.0 ; noms, dates et protection Wikidata, CC0 ;
     * images sous la licence de chaque fichier. Reprenez la mention du champ `attribution`.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     summary: array{count: int, by_kind: array{tunnel: int, viaduct: int, bridge: int}, tunnel_length_m: int,
     *         longest: ?array{name: ?string, kind: string, length_m: int}},
     *     items: array<array{id: int, kind: string, name: ?string, length_m: ?int, length_source: ?string,
     *         pk_start_m: ?int, pk_end_m: ?int, pk_start_label: ?string, pk_end_label: ?string,
     *         distance_from_m: ?int, distance_to_m: ?int,
     *         built: ?array{date: string, precision: string}, opened: ?array{date: string, precision: string},
     *         closed: ?array{date: string, precision: string}, heritage: ?string, merimee_ref: ?string,
     *         wikidata_id: ?string, wikipedia_url: ?string,
     *         image: ?array{thumb_url: string, width: ?int, height: ?int, file: ?string, author: ?string, license: ?string, license_url: ?string, source_url: ?string},
     *         era_years: array<int>, current: bool, lat: float, lon: float, offset_m: int,
     *         sources: array<array{source: string, url: string, fields: array<string>}>}>,
     *     attribution: string,
     *     provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-722000')]
    #[ApiResponse(200, "Ouvrages d'art de la ligne (liste vide si aucun n'est connu).", examples: [[
        'line' => ['slug' => 'rfn-722000', 'code' => '722000', 'name' => 'Ligne de Béziers à Neussargues'],
        'summary' => [
            'count' => 1, 'by_kind' => ['tunnel' => 0, 'viaduct' => 1, 'bridge' => 0], 'tunnel_length_m' => 0,
            'longest' => ['name' => 'viaduc de Garabit', 'kind' => 'viaduct', 'length_m' => 573],
        ],
        'items' => [[
            'id' => 278, 'kind' => 'viaduct', 'name' => 'viaduc de Garabit', 'length_m' => 573, 'length_source' => 'bdtopo',
            'pk_start_m' => 675700, 'pk_end_m' => 676273, 'pk_start_label' => '675+700', 'pk_end_label' => '676+273',
            'distance_from_m' => 244140, 'distance_to_m' => 244713,
            'built' => ['date' => '1884-01-01', 'precision' => 'year'], 'opened' => ['date' => '1888-05-27', 'precision' => 'day'],
            'closed' => null, 'heritage' => 'classified', 'merimee_ref' => 'PA00093592',
            'wikidata_id' => 'Q1333442', 'wikipedia_url' => 'https://fr.wikipedia.org/wiki/Viaduc_de_Garabit',
            'image' => null, 'era_years' => [1890, 1900, 1910, 1920, 1930, 1940, 1950, 1960, 1970, 1980, 1990, 2000, 2010, 2020, 2030], 'current' => true,
            'lat' => 44.977, 'lon' => 3.173, 'offset_m' => 0,
            'sources' => [
                ['source' => 'bdtopo', 'url' => 'https://geoservices.ign.fr/bdtopo', 'fields' => ['geometry', 'length_m']],
                ['source' => 'wikidata', 'url' => 'https://www.wikidata.org/wiki/Q1333442', 'fields' => ['name', 'built_on', 'heritage', 'merimee_ref', 'image']],
                ['source' => 'wikipedia', 'url' => 'https://fr.wikipedia.org/w/index.php?oldid=241366178', 'fields' => ['opened_on']],
            ],
        ]],
        'attribution' => LineStructures::ATTRIBUTION,
        'provenance' => ['provider' => 'ign', 'name' => 'IGN (BD TOPO, BD ORTHO historique, RGE ALTI)', 'license' => "Licence Ouverte 2.0 (Etalab), données publiques de l'IGN libres depuis le 1er janvier 2021", 'license_status' => 'verified'],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Line $line, LineStructures $structures): JsonResponse
    {
        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            ...$structures->forLine((int) $line->id),
            'attribution' => LineStructures::ATTRIBUTION,
            'provenance' => $structures->provenance(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
