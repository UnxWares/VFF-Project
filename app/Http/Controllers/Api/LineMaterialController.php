<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListLineMaterialsRequest;
use App\Http\Resources\LineMaterialLinkResource;
use App\Models\Line;
use App\Services\Materials\LineMaterialEnd;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

#[Group('Matériel roulant', "Les séries de matériel roulant qui ont circulé en France (locomotives, automotrices, autorails, rames TGV, voitures, wagons, tramways), leurs caractéristiques sourcées et les lignes qu'elles ont parcourues.", weight: 6)]
class LineMaterialController extends Controller
{
    /**
     * Matériel roulant d'une ligne.
     *
     * Les séries qui ont circulé (ou circulent) sur la ligne, d'après une source citée, la plus
     * récente d'abord, comme sur la fiche de la ligne : d'abord les séries datées sur la ligne, par
     * dernière année de présence (une période ouverte vaut jusqu'au retrait de la série, ou
     * aujourd'hui si elle est encore en service), puis les autres d'après l'état de la série
     * (en service, retrait le plus récent, mise en service la plus récente), puis le nom. Chaque entrée
     * donne `end`, la fin telle que les sources la connaissent. Liste complète, sans
     * pagination : une ligne compte au plus quelques dizaines de séries.
     *
     * **Ce que veut dire un lien** : une source relie la série à la ligne, jamais un nom
     * approchant. Wikidata (déclaration « véhicule habituellement utilisé », P3438), ou
     * Wikipédia : lien posé vers la série dans une section « Matériel roulant »,
     * « Exploitation » ou « Desserte » de l'article de la ligne, ou lien posé vers la ligne
     * dans une section « Utilisation », « Services » ou « Carrière » de l'article de la
     * série. Les phrases au futur, au conditionnel, sur des projets ou qui nient la
     * circulation sont écartées.
     *
     * **Preuve et fiabilité** : chaque entrée cite ses preuves (`evidence` : article,
     * révision, section, phrase, permalien), les plus fortes d'abord, et donne `confidence` :
     * `high` (Wikidata, ou section « Matériel roulant » de l'article de la ligne), `medium`
     * (autre section), `low` (article qui décrit plusieurs lignes de VFF à la fois, ou preuves
     * qui portent toutes un doute). Une preuve porte `doubt` quand sa phrase parle d'une
     * commande, d'un essai, d'une circulation exceptionnelle, d'un matériel préservé, d'un
     * acheminement vers un atelier ou d'un détournement : exemple réel, les Z 890 de la ligne de
     * Saint-Gervais à Vallorcine (« un contrat portant sur l'acquisition de 7 nouvelles rames »,
     * `doubt` = `order`). La portion parcourue se lit parfois dans le titre de section (« Section
     * de Saint-Just-en-Chaussée à Cambrai »).
     *
     * **Périodes de circulation** (`periods`) : quand une source date la circulation de la
     * série sur la ligne, chaque période donne son début (`start_date`) et sa fin
     * (`end_date`), l'une ou les deux, au format `AAAA-MM-JJ` avec leur précision (`day`,
     * `month` : date au 1er du mois, `year` : date au 1er janvier). Exemple réel : les X 4300
     * de la ligne de Lunéville à Saint-Dié, « retirés du service en novembre 2007 » d'après
     * l'article, ont `end_date` = `2007-11-01` et `end_precision` = `month`. Les dates sont
     * lues dans les phrases des articles Wikipédia de la ligne (`origin` = `line_article`) et
     * de la série (`material_article`), ou dans les qualificatifs de début et de fin de
     * Wikidata (`wikidata`). Seules les périodes sûres sont servies : `high` (jour ou mois),
     * `medium` (année) ; une date approximative (« vers 1937 », « années 1970 ») ou une
     * phrase ambiguë ne l'est jamais. Une fin nulle ne veut pas dire que la série circule
     * encore. Les périodes ne reprennent aucune phrase ; `from_date` et `to_date` restent
     * réservés aux liens saisis dans VFF.
     *
     * **Licences** : les phrases citées sont des extraits de Wikipédia (CC BY-SA 4.0) :
     * citez l'article avec le permalien `url`. Les fiches de matériel viennent de Wikidata
     * (CC0) ; la vignette (`material.thumb_url`) est un fichier Commons dont la licence est
     * donnée par la fiche de la série.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-67000')]
    #[QueryParameter('type', 'Catégorie de matériel : `locomotive_vapeur`, `locomotive_diesel`, `locomotive_electrique`, `automotrice`, `bimode`, `autorail`, `rame_tgv`, `voiture`, `wagon`, `tramway`, `engin_service`.', type: 'string', example: 'autorail')]
    #[QueryParameter('source', 'Provenance du lien : `wikidata` ou `wikipedia`.', type: 'string', example: 'wikipedia')]
    #[QueryParameter('min_confidence', 'Fiabilité minimale : `high` (liens les plus sûrs seulement), `medium` (high et medium) ou `low` (tous, comme sans ce paramètre).', type: 'string', example: 'medium')]
    #[ApiResponse(200, 'Matériel roulant de la ligne, avec la preuve de chaque lien et ses périodes de circulation datées.', examples: [[
        'line' => ['slug' => 'rfn-67000', 'code' => '067000', 'name' => 'Ligne de Lunéville à St-Dié'],
        'data' => [MaterialExamples::LINE_LINK, MaterialExamples::LINE_LINK_DATED],
        'meta' => ['total' => 13],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue (slug introuvable).', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, 'Paramètre invalide (catégorie, source ou fiabilité inconnues) : détail par paramètre dans `errors`.', examples: [MaterialExamples::LINE_MATERIALS_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Line $line, ListLineMaterialsRequest $request): JsonResponse
    {
        $links = $line->materialLinks()
            ->with(['material', 'era:id,year', 'shownPeriods'])
            ->join('materials', 'materials.id', '=', 'line_materials.material_id')
            ->when($request->filled('type'), fn ($q) => $q->where('materials.type', $request->input('type')))
            ->when($request->filled('source'), fn ($q) => $q->where('line_materials.source', $request->input('source')))
            ->when($request->confidences() !== [], fn ($q) => $q->whereIn('line_materials.confidence', $request->confidences()))
            ->select('line_materials.*')
            ->get();
        $links = new Collection(LineMaterialEnd::sortLinks($links));

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'data' => LineMaterialLinkResource::collection($links)->resolve($request),
            'meta' => ['total' => $links->count()],
        ])->header('Cache-Control', 'public, max-age=600');
    }
}
