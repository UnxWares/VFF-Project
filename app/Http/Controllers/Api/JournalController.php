<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Services\Journal\JournalArticle;
use App\Services\Journal\JournalPresenter;
use App\Services\Journal\JournalRepository;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Journal de bord', "Articles longs de l'équipe VFF sur des lignes et sur le projet : métadonnées, texte en HTML, sources en notes, lignes et gares citées, licence du texte et de l'image.", weight: 9)]
class JournalController extends Controller
{
    /** Exemple d'entrée de liste (article réel du journal). */
    private const SUMMARY_EXAMPLE = [
        'slug' => 'les-hirondelles',
        'title' => 'Les Hirondelles, d’Andelot à La Cluse par les viaducs du Haut-Jura',
        'date' => '2026-11-22',
        'updated' => null,
        'summary' => 'Près de cinquante ans de chantier, des rampes de 30 pour mille, un fer à cheval sous terre…',
        'lang' => 'fr',
        'locales' => ['fr'],
        'image' => [
            'src' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fd/Morez_viaduc_des_Crottes01_2014-09-06.jpg/1280px-Morez_viaduc_des_Crottes01_2014-09-06.jpg',
            'alt' => 'Le viaduc des Crottes, ouvrage de pierre à arches, au-dessus des maisons de Morez',
            'author' => 'KlausFoehl',
            'licence' => 'CC BY-SA 4.0',
            'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Morez_viaduc_des_Crottes01_2014-09-06.jpg',
            'width' => 1280,
            'height' => 853,
            'caption' => 'Le viaduc des Crottes à Morez, en septembre 2014.',
        ],
        'lines' => ['878000'],
        'stations' => ['andelot', 'champagnole', 'morez', 'st-claude'],
        'path' => '/journal/les-hirondelles',
        'url' => 'https://vff-project.org/journal/les-hirondelles',
    ];

    public function __construct(
        private readonly JournalRepository $journal,
        private readonly JournalPresenter $presenter,
    ) {}

    /**
     * Liste des articles du journal.
     *
     * Articles publiés, du plus récent au plus ancien (date de publication, puis slug), sans leur texte.
     * Chaque article est rendu dans la langue demandée (`lang`) quand il y existe, sinon en français,
     * langue de référence : `lang` de chaque entrée dit la langue du texte renvoyé et `locales` celles
     * dans lesquelles l'article existe.
     *
     * `lines` : codes RFN des lignes dont parle l'article (fiche : `/api/v1/lines/rfn-{code}`) ;
     * `stations` : slugs des gares citées (`/api/v1/stations/{slug}`). `image` : photo de Wikimedia
     * Commons avec auteur et licence (chaque image garde sa licence), ou `null`.
     *
     * **Pagination** : `page` et `per_page` (1 à 50, 10 par défaut) ; `meta` donne `current_page`,
     * `last_page`, `per_page` et `total`.
     */
    #[QueryParameter('lang', 'Langue souhaitée : `fr`, `en` ou `de`. Un article sans traduction est renvoyé en français.', type: 'string', default: 'fr', example: 'en')]
    #[QueryParameter('line', 'Code RFN à 6 chiffres : seulement les articles qui parlent de cette ligne.', type: 'string', example: '878000')]
    #[QueryParameter('per_page', "Nombre d'articles par page (1 à 50).", type: 'int', default: 10, example: 10)]
    #[QueryParameter('page', 'Numéro de page, de 1 à 1 000 000 (une page au-delà de la dernière est vide).', type: 'int', default: 1, example: 1)]
    #[ApiResponse(200, 'Une page de la liste des articles.', examples: [[
        'data' => [self::SUMMARY_EXAMPLE],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 2],
    ]])]
    #[ApiResponse(422, 'Paramètre invalide (langue inconnue, code de ligne mal formé, `per_page` ou `page` hors limites).', examples: [[
        'message' => 'The selected lang is invalid.',
        'errors' => ['lang' => ['The selected lang is invalid.']],
    ]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Request $request): JsonResponse
    {
        $valid = $request->validate([
            'lang' => ['sometimes', Rule::in(SetLocale::SUPPORTED)],
            'line' => ['sometimes', 'string', 'regex:/^\d{6}$/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ]);
        $lang = $this->locale($valid);
        $all = $this->journal->all($lang);
        if (isset($valid['line'])) {
            $all = array_values(array_filter($all, fn (JournalArticle $a): bool => in_array($valid['line'], $a->lines, true)));
        }
        $perPage = (int) ($valid['per_page'] ?? 10);
        $page = (int) ($valid['page'] ?? 1);
        $origin = $request->getSchemeAndHttpHost();

        return response()->json([
            'data' => array_map(fn (JournalArticle $a): array => $this->withUrl($origin, $this->presenter->summary($a)),
                array_slice($all, ($page - 1) * $perPage, $perPage)),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil(count($all) / $perPage)),
                'per_page' => $perPage,
                'total' => count($all),
            ],
        ])->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Article du journal.
     *
     * Tous les champs de la liste, plus : `author` (toujours l'équipe, jamais une personne), `disclosure`
     * (mention de lien d'intérêts, ou `null`), `html` (texte rendu : HTML écrit dans le Markdown échappé,
     * liens internes relatifs au site, liens externes en `target="_blank"`, sources en notes de fin
     * `<div class="footnotes">`), `toc` (titres de niveau 2 : `id` de l'ancre et texte), `lines` et
     * `stations` remplacés par les fiches citées qui existent (`code`, `slug`, `name` ; `slug`, `name`,
     * `city`), `words`, `reading_minutes` (220 mots par minute), `license` et `license_url` (texte sous
     * CC BY-SA 4.0 ; l'image garde la sienne) et `json_ld` (données structurées schema.org `Article`).
     */
    #[PathParameter('slug', "Slug de l'article, tel que renvoyé par la liste.", example: 'les-hirondelles')]
    #[QueryParameter('lang', 'Langue souhaitée : `fr`, `en` ou `de`. Sans traduction, le texte français est renvoyé (`lang` de la réponse).', type: 'string', default: 'fr', example: 'fr')]
    #[ApiResponse(200, "L'article complet.", examples: [self::SUMMARY_EXAMPLE + [
        'author' => 'L’équipe VFF',
        'disclosure' => null,
        'html' => '<p>La <a href="/lines/rfn-878000">ligne d’Andelot-en-Montagne à La Cluse</a> traverse le Haut-Jura…</p>',
        'toc' => [['id' => 'une-ligne-construite-en-six-fois', 'label' => 'Une ligne construite en six fois']],
        'words' => 1469,
        'reading_minutes' => 7,
        'license' => 'CC BY-SA 4.0',
        'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr',
    ]])]
    #[ApiResponse(404, 'Article inconnu ou pas encore publié.', examples: [['message' => 'Article inconnu.']])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Request $request, string $slug): JsonResponse
    {
        $valid = $request->validate(['lang' => ['sometimes', Rule::in(SetLocale::SUPPORTED)]]);
        $lang = $this->locale($valid);
        $article = $this->journal->find($slug, $lang);
        if ($article === null) {
            return response()->json(['message' => 'Article inconnu.'], 404);
        }
        $origin = $request->getSchemeAndHttpHost();
        $full = $this->presenter->full($article);

        return response()->json($this->withUrl($origin, $full + ['json_ld' => $this->presenter->jsonLd($origin, $full)]))
            ->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Langue des textes de la réponse (date, auteur, libellés des liens), appliquée à l'application.
     *
     * @param  array<string, mixed>  $valid
     */
    private function locale(array $valid): string
    {
        $lang = (string) ($valid['lang'] ?? JournalRepository::CANONICAL);
        app()->setLocale($lang);

        return $lang;
    }

    /**
     * @param  array<string, mixed>  $article
     * @return array<string, mixed>
     */
    private function withUrl(string $origin, array $article): array
    {
        return $article + ['url' => rtrim($origin, '/').$article['path']];
    }
}
