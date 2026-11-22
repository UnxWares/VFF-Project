<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Services\Journal\JournalFeed;
use App\Services\Journal\JournalPresenter;
use App\Services\Journal\JournalRenderer;
use App\Services\Journal\JournalRepository;
use App\Support\Seo\SiteOrigin;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Journal de bord (`/journal`) : articles longs sur des lignes et sur le projet, écrits en Markdown dans
 * `resources/content/journal/` (JournalRepository). Liste paginée, page d'article, flux Atom et RSS,
 * plan du site des articles. API : `GET /api/v1/journal` (Api\JournalController).
 */
class JournalController extends Controller
{
    /** Articles par page de la liste. */
    public const PER_PAGE = 10;

    public function __construct(
        private readonly JournalRepository $journal,
        private readonly JournalPresenter $presenter,
    ) {}

    public function index(Request $request, JournalRenderer $renderer): Response
    {
        $all = $this->journal->all(app()->getLocale());
        $last = max(1, (int) ceil(count($all) / self::PER_PAGE));
        $page = filter_var($request->query('page', '1'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($page === false || $page > $last, 404);

        $slice = array_slice($all, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        // Fiches des lignes de la page, en une requête (plaques et noms).
        $codes = array_values(array_unique(array_merge([], ...array_map(fn ($a): array => $a->lines, $slice))));
        $lines = $renderer->lines($codes);

        return Inertia::render('Journal/Index', [
            'articles' => array_map(fn ($a): array => [
                ...$this->presenter->summary($a),
                'lines' => array_values(array_filter(array_map(fn (string $c): ?array => $lines[$c] ?? null, $a->lines))),
            ], $slice),
            'pagination' => ['current' => $page, 'last' => $last, 'total' => count($all), 'per_page' => self::PER_PAGE],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $article = $this->journal->find($slug, app()->getLocale());
        abort_if($article === null, 404);

        $full = $this->presenter->full($article);
        // Voisins dans l'ordre de la liste (plus récent, plus ancien).
        $all = $this->journal->all(app()->getLocale());
        $index = array_search($slug, array_map(fn ($a): string => $a->slug, $all), true);
        $neighbour = fn (int $i): ?array => isset($all[$i]) ? ['slug' => $all[$i]->slug, 'title' => $all[$i]->title, 'lang' => $all[$i]->locale] : null;

        return Inertia::render('Journal/Show', [
            'article' => [
                ...$full,
                'json_ld' => $this->presenter->jsonLd(SiteOrigin::get(), $full),
            ],
            'newer' => is_int($index) ? $neighbour($index - 1) : null,
            'older' => is_int($index) ? $neighbour($index + 1) : null,
        ]);
    }

    /** Flux Atom (`/journal/atom.xml`), dans la langue `?lang=` (fr, en, de) ou celle du visiteur. */
    public function atom(Request $request, JournalFeed $feed): HttpResponse
    {
        return $this->xml($feed->atom(SiteOrigin::get(), $this->feedLocale($request)), 'application/atom+xml', $request->query('lang') === null);
    }

    /** Flux RSS 2.0 (`/journal/rss.xml`), mêmes articles que le flux Atom. */
    public function rss(Request $request, JournalFeed $feed): HttpResponse
    {
        return $this->xml($feed->rss(SiteOrigin::get(), $this->feedLocale($request)), 'application/rss+xml', $request->query('lang') === null);
    }

    /** Plan du site des articles (`/journal/sitemap.xml`), cité par l'index `/sitemap.xml`. */
    public function sitemap(Request $request, JournalFeed $feed): HttpResponse
    {
        return $this->xml($feed->sitemap(SiteOrigin::get()), 'application/xml');
    }

    private function feedLocale(Request $request): string
    {
        $lang = $request->query('lang');
        if ($lang === null) {
            // Sans session ni cookie (hors du groupe web) : la langue vient d'Accept-Language, d'où le Vary.
            $preferred = $request->getPreferredLanguage(SetLocale::SUPPORTED);
            $locale = is_string($preferred) && in_array($preferred, SetLocale::SUPPORTED, true) ? $preferred : (string) config('app.locale', 'fr');
            app()->setLocale($locale);

            return $locale;
        }
        abort_unless(is_string($lang) && in_array($lang, SetLocale::SUPPORTED, true), 404);
        app()->setLocale($lang);

        return $lang;
    }

    private function xml(string $body, string $type, bool $varyOnLanguage = false): HttpResponse
    {
        $headers = [
            'Content-Type' => $type.'; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($varyOnLanguage) {
            $headers['Vary'] = 'Accept-Language';
        }

        return response($body, 200, $headers);
    }
}
