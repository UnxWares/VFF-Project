<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\LineArchiveDocument;
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
class LineArchiveController extends Controller
{
    /** Exemple réel : ligne de Limoges-Bénédictins à Angoulême, notice de 1860 (cf. réponse 200). */
    private const EXAMPLE_DOCUMENT = [
        'gallica_ark' => 'ark:/12148/bpt6k1192344q',
        'gallica_url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k1192344q',
        'catalogue_ark' => 'ark:/12148/cb315821610',
        'catalogue_url' => 'https://catalogue.bnf.fr/ark:/12148/cb315821610',
        'title' => "Utilité d'un chemin de fer de Rochefort à Limoges par Angoulême et plus particulièrement du chemin de fer direct d'Angoulême à Limoges",
        'type' => 'monograph',
        'authors' => ['Villemandy, Alfred de'],
        'date' => ['label' => '1860', 'year' => 1860, 'year_to' => null, 'exact' => null, 'precision' => 'year'],
        'shelfmark' => 'V-54984',
        'match' => [
            'score' => 10,
            'threshold' => ArchiveMatcher::THRESHOLD,
            'max_score' => ArchiveMatcher::MAX_SCORE,
            'reasons' => [
                ['code' => 'rail_route', 'points' => 4], ['code' => 'ends', 'points' => 2], ['code' => 'rail_word', 'points' => 2],
                ['code' => 'date_project', 'points' => 1], ['code' => 'doc_type', 'points' => 1],
            ],
            'places' => ['Limoges', 'Angoulême'],
        ],
        'record_updated_on' => '2025-06-16',
        'fetched_at' => '2026-11-20T10:12:31+01:00',
    ];

    public function __construct(private readonly LineArchives $archives) {}

    /**
     * Documents d'archives d'une ligne (Gallica, BnF).
     *
     * Documents numérisés en accès libre dans **Gallica**, la bibliothèque numérique de la Bibliothèque
     * nationale de France, dont le titre parle de la ligne : projets, plans et cartes du tracé, mémoires,
     * guides, photographies. Ils sont trouvés par `vff:import-gallica` dans le catalogue général de la BnF
     * (critère « numérisé, accès libre ») puis rattachés à la ligne par un **score explicable**, calculé sur
     * le seul titre de la notice :
     *
     * - `rail_route` (4 points) : le titre décrit une voie ferrée entre deux lieux de la ligne qui se suivent
     *   (« chemin de fer de Limoges à Angoulême », « ligne de Toulouse à Bayonne ») ; à défaut `route` (3) : un
     *   trajet entre eux (« de Paris au Havre », « entre Paris et Le Havre », « rapide Paris - Bordeaux », deux
     *   villes voisines d'une liste « entre Paris, Rouen, Le Havre et Dieppe », qui n'est souvent qu'une étoile de
     *   destinations) ; à
     *   défaut `two_places` (1) : il nomme deux lieux de la ligne (communes ou gares, extrémités de son nom actuel
     *   ou de ses anciens noms), et `only_line_places` (1) s'il ne nomme aucun lieu situé hors de la ligne ;
     * - `ends` (2) : ces deux lieux sont les extrémités de la ligne ; pas pour une variante dont le nom donne un
     *   lieu de passage (« via Grenoble », « par Gisors ») quand le titre ne nomme pas ce lieu et qu'une autre
     *   ligne passe aussi par les lieux nommés ;
     * - `rail_word` (2) : chemin de fer, voie ferrée, tramway, railway, embranchement, ferroviaire, « ligne de
     *   X à Y » ; ou `rail_hint` (1) : seulement gare, train, locomotive ou wagon ;
     * - date : `date_in_life` (2) entre la première date connue de la ligne et sa fermeture ; `date_project`
     *   (1) dans les 40 ans avant (projets, enquêtes) ; `date_after_closure` (1) après la fermeture ;
     *   `date_unknown` (0) document sans date ou ligne sans date connue ; `date_doubtful` (0) le titre imprime une
     *   année à plus de 40 ans de la date de la notice ;
     * - `doc_type` (1) : texte imprimé (`monograph`), carte (`map`), image fixe (`image`) ou manuscrit (`manuscript`) ;
     * - `secondary_mention` (0) : la ligne n'est citée que comme raccordement d'une autre (« raccordement avec la
     *   ligne de Paris à Bordeaux ») ; ses extrémités ne comptent alors pas.
     *
     * Une ligne qui existait à la date du document passe avant une ligne alors en projet, et la ligne dont le
     * titre nomme le plus de lieux passe avant celle qui n'en partage qu'une partie. Seul le meilleur score garde
     * le document ; à égalité, une ligne reliée par un trajet passe avant une ligne seulement nommée.
     *
     * Un document n'est retenu qu'à partir de 9 points sur 11 (`match.threshold`, `match.max_score`), avec un
     * mot du rail, sans autre moyen de transport dans le titre (métro, canal, bateaux à vapeur, autocar…) sauf
     * s'il parle de « chemin de fer de X à Y », et avec une date compatible (jamais avant 1820 ni plus de 40 ans
     * avant la première date de la ligne). Un titre qui convient aussi bien à plus de deux lignes n'est
     * rattaché à aucune. Les journaux et périodiques ne sont pas cherchés. Liste vide pour une ligne sans
     * document retenu.
     *
     * Chaque document : identifiant `gallica_ark` et lien `gallica_url` vers Gallica, notice `catalogue_ark`
     * et `catalogue_url`, `title` (titre propre et premier complément), `type`, `authors` (tels que la notice
     * les écrit), `date` (`label` : date imprimée dans la notice, « [ca 1850] » ; `year` ; `year_to` : fin d'un
     * intervalle ; `exact` : AAAA-MM-JJ quand la notice donne le jour ou le mois ; `precision` : `day`,
     * `month`, `year`, `circa` (date approchée), `range`, `before` (au plus tard `year`, « Avant 1885 »), `after`
     * (au plus tôt `year`) ou `unknown`), `shelfmark` (cote de l'original à la BnF), `match`
     * (score, seuil, raisons point par point, lieux reconnus dans le titre), `record_updated_on` (dernière
     * mise à jour de la notice à la BnF) et `fetched_at` (récupération par VFF). Ordre : du plus ancien au
     * plus récent, documents sans date en dernier.
     *
     * **Droits** : les métadonnées des notices de la BnF sont sous Licence Ouverte (Etalab) ; reprenez la
     * mention `attribution` et la date de récupération. VFF ne stocke ni ne sert aucune image, vignette ni
     * texte numérisé : la réutilisation des documents eux-mêmes suit les conditions d'utilisation de Gallica
     * (non commerciale libre avec la mention « Source gallica.bnf.fr / BnF », commerciale soumise à redevance).
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-610000')]
    #[QueryParameter('type', 'Ne garder qu\'un type de document : `monograph` (texte imprimé), `map` (carte, plan), `image` (photographie, estampe, affiche) ou `manuscript`. Par défaut : tous.', type: 'string', example: 'map')]
    #[ApiResponse(200, "Documents d'archives rattachés à la ligne (liste vide s'il n'y en a aucun).", examples: [[
        'line' => ['slug' => 'rfn-610000', 'code' => '610000', 'name' => 'Ligne de Limoges-Bénédictins à Angoulême'],
        'total' => 1,
        'documents' => [self::EXAMPLE_DOCUMENT],
        'attribution' => LineArchives::ATTRIBUTION,
        'provenance' => [
            'provider' => 'gallica',
            'name' => 'Gallica, bibliothèque numérique de la BnF (notices du catalogue général de la Bibliothèque nationale de France)',
            'license' => 'Métadonnées descriptives de la BnF : Licence Ouverte (Etalab) depuis le 1er janvier 2014. Documents numérisés : conditions d\'utilisation des contenus de Gallica (réutilisation non commerciale libre et gratuite avec la mention de source, réutilisation commerciale soumise à autorisation et à redevance)',
            'license_status' => 'verified',
        ],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, 'Type de document inconnu.', examples: [[
        'message' => 'The selected type is invalid.',
        'errors' => ['type' => ['The selected type is invalid.']],
    ]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Line $line, Request $request): JsonResponse
    {
        $request->validate(['type' => ['nullable', 'string', Rule::in(LineArchiveDocument::TYPES)]]);
        $documents = $this->archives->documents($line, $request->filled('type') ? $request->string('type')->toString() : null);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'total' => $documents->count(),
            'documents' => $documents->map(fn (LineArchiveDocument $d): array => $this->archives->toApi($d))->values(),
            'attribution' => LineArchives::ATTRIBUTION,
            'provenance' => $this->archives->provenance(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
