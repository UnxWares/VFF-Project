<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\LineArchiveRecord;
use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\LineArchives;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineArchiveRecordController extends Controller
{
    /** Exemple réel : ligne du Mans à Mézidon, photographie de la gare d'Alençon (cf. réponse 200). */
    private const EXAMPLE_RECORD = [
        'source' => 'sncf-archives',
        'reference' => '33453',
        'url' => 'https://www.archives.sncf.com/esplanade-de-la-gare-dalencon-situee-sur-la-ligne-du-mans-a-mezidon/',
        'title' => "Esplanade de la gare d'Alençon située sur la ligne du Mans à Mézidon",
        'type' => 'image',
        'theme' => 'L\'expérience du voyage',
        'sub_theme' => null,
        'holder' => null,
        'box' => null,
        'rights' => 'SNCF-MEDIATHEQUE',
        'date' => ['label' => '1986-09', 'year' => 1986, 'year_to' => null, 'exact' => '1986-09-01', 'precision' => 'month'],
        'match' => [
            'score' => 11,
            'threshold' => ArchiveMatcher::THRESHOLD,
            'max_score' => ArchiveMatcher::MAX_SCORE,
            'reasons' => [
                ['code' => 'rail_route', 'points' => 4], ['code' => 'ends', 'points' => 2], ['code' => 'rail_word', 'points' => 2],
                ['code' => 'date_in_life', 'points' => 2], ['code' => 'doc_type', 'points' => 1],
            ],
            'places' => ['Alençon', 'Le Mans', 'Mézidon'],
        ],
        'fetched_at' => '2026-11-22T01:07:22+01:00',
    ];

    public function __construct(private readonly LineArchives $archives) {}

    /**
     * Notices des fonds d'archives d'une ligne (Archives SNCF).
     *
     * Notices d'inventaire des **fonds d'archives** dont le titre désigne la ligne. Seule source à ce jour : le
     * jeu de données **« Archives SNCF »** du Service Archives Documentation du groupe SNCF (dossiers d'archives,
     * photographies de la Médiathèque SNCF et films numérisés, consultables sur archives.sncf.com), publié sur
     * ressources.data.sncf.com sous ODbL. Les notices sont importées par `vff:import-sncf-archives`, puis
     * rattachées par le **même score explicable** que les documents de Gallica (`GET /api/v1/lines/{line}/archives`),
     * calculé sur le titre, ou sur la description quand le titre ne nomme aucune ligne (raison
     * `read_in_description`, 0 point : une seule ligne décrite en voie ferrée « de X à Y » dès le début du texte,
     * sans les mentions en passant « par », « via », « sur la ligne de ») : `rail_route` (4), `route` (3) ou
     * `two_places` (1, plus `only_line_places`),
     * `ends` (2), `rail_word` (2) ou `rail_hint` (1), date `date_in_life` (2), `date_project` (1),
     * `date_after_closure` (1), `date_unknown` (0), `doc_type` (1). Seuil : 9 sur 11 ; un titre qui convient à
     * plus de deux lignes n'est rattaché à aucune.
     *
     * Deux règles propres à ce fonds : un nom de compagnie ne désigne pas une ligne (« Compagnie du chemin de fer
     * de Paris à Orléans » est retiré du titre avant le calcul), et dans « située sur les lignes du Mans à Mézidon
     * et d'Argentan à Granville » chaque trajet de la liste compte comme une ligne. Les notices des thèmes qui
     * portent sur des personnes (fichiers des cheminots blessés et tués, contentieux, questions de personnel) ne
     * sont jamais rattachées. Précision mesurée sur deux tirages de 40 notices relues à la main : 78 justes,
     * 2 douteuses, aucune fausse ; même mesure pour les notices rattachées par leur description (23/11/2026).
     *
     * Chaque notice : `source` (fonds, `sncf-archives`), `reference` (numéro du dossier, pas toujours unique),
     * `url` (notice et document sur archives.sncf.com, à donner au lecteur), `title`, `type` (`document` :
     * dossier d'archives ; `image` : photographie ou affiche ; `video` : film), `theme` et `sub_theme`
     * (classement du fonds), `holder` (service versant), `box` (boîte d'archives), `rights` (titulaire des
     * droits indiqué par la notice), `date` (`label` ; `year` ; `year_to` : fin d'un dossier qui couvre
     * plusieurs années ; `exact` : AAAA-MM-JJ pour une prise de vue datée au jour ou au mois ; `precision` :
     * `day`, `month`, `year`, `range` ou `unknown`), `match` (score, seuil, raisons point par point, lieux
     * reconnus) et `fetched_at` (récupération du jeu de données). Ordre : du plus ancien au plus récent, notices
     * sans date en dernier. Liste vide pour une ligne sans notice retenue.
     *
     * **Droits** : le jeu de données est sous ODbL ; reprenez la mention `attribution`. VFF ne garde ni la
     * description des notices (elle peut nommer des agents), ni l'image, ni le document : leur réutilisation
     * suit les conditions du site archives.sncf.com.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-430000')]
    #[QueryParameter('type', 'Ne garder qu\'un type de notice : `document` (dossier d\'archives), `image` (photographie, affiche) ou `video` (film). Par défaut : tous.', type: 'string', example: 'image')]
    #[QueryParameter('source', 'Ne garder qu\'un fonds : `sncf-archives` (Archives SNCF). Par défaut : tous.', type: 'string', example: 'sncf-archives')]
    #[ApiResponse(200, "Notices des fonds d'archives rattachées à la ligne (liste vide s'il n'y en a aucune).", examples: [[
        'line' => ['slug' => 'rfn-430000', 'code' => '430000', 'name' => 'Ligne du Mans à Mézidon'],
        'total' => 1,
        'records' => [self::EXAMPLE_RECORD],
        'attribution' => LineArchives::RECORDS_ATTRIBUTION,
        'provenance' => [
            'provider' => 'sncf-archives',
            'name' => 'Archives SNCF (Service Archives Documentation du groupe SNCF, jeu de données « archives-sncf-sardo »)',
            'license' => 'Jeu de données : ODbL 1.0 ; documents numérisés : conditions d\'utilisation du site archives.sncf.com (droits indiqués sur chaque notice, « SNCF-MEDIATHEQUE » pour les photographies)',
            'license_status' => 'verified',
        ],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, 'Type de notice ou fonds inconnu.', examples: [[
        'message' => 'The selected type is invalid.',
        'errors' => ['type' => ['The selected type is invalid.']],
    ]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Line $line, Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', Rule::in(LineArchiveRecord::TYPES)],
            'source' => ['nullable', 'string', Rule::in(LineArchiveRecord::SOURCES)],
        ]);
        $records = $this->archives->records(
            $line,
            $request->filled('type') ? $request->string('type')->toString() : null,
            $request->filled('source') ? $request->string('source')->toString() : null,
        );

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'total' => $records->count(),
            'records' => $records->map(fn (LineArchiveRecord $r): array => $this->archives->recordToApi($r))->values(),
            'attribution' => LineArchives::RECORDS_ATTRIBUTION,
            'provenance' => $this->archives->recordsProvenance(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
