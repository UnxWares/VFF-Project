<?php

namespace App\GraphQL\Resolvers;

use App\Http\Middleware\SetLocale;
use App\Services\Journal\JournalArticle;
use App\Services\Journal\JournalPresenter;
use App\Services\Journal\JournalRepository;
use GraphQL\Error\Error;

/**
 * Requêtes GraphQL `journalArticles` et `journalArticle` : mêmes articles et mêmes champs que l'API REST
 * `GET /api/v1/journal` et `GET /api/v1/journal/{slug}` (JournalPresenter). Les codes et slugs de l'en-tête
 * sont dans `line_codes` et `station_slugs` ; les fiches citées résolues (`lines`, `stations`) et le texte
 * ne sont remplis que pour un article demandé seul.
 */
final class JournalResolver
{
    public function __construct(
        private readonly JournalRepository $journal,
        private readonly JournalPresenter $presenter,
    ) {}

    /**
     * @param  array{lang?: string, line?: string|null}  $args
     * @return list<array<string, mixed>>
     */
    public function articles(mixed $root, array $args): array
    {
        $lang = $this->locale($args['lang'] ?? 'fr');
        $articles = $this->journal->all($lang);
        if (! empty($args['line'])) {
            $articles = array_values(array_filter($articles, fn (JournalArticle $a): bool => in_array($args['line'], $a->lines, true)));
        }

        return array_map(fn (JournalArticle $a): array => $this->shape($this->presenter->summary($a)), $articles);
    }

    /**
     * @param  array{slug: string, lang?: string}  $args
     * @return array<string, mixed>|null
     */
    public function article(mixed $root, array $args): ?array
    {
        $lang = $this->locale($args['lang'] ?? 'fr');
        $article = $this->journal->find($args['slug'], $lang);

        return $article === null ? null : $this->shape($this->presenter->full($article), $article);
    }

    private function locale(string $lang): string
    {
        if (! in_array($lang, SetLocale::SUPPORTED, true)) {
            throw new Error('Langue inconnue : fr, en ou de.');
        }
        app()->setLocale($lang);

        return $lang;
    }

    /**
     * @param  array<string, mixed>  $data  sortie de JournalPresenter
     * @return array<string, mixed>
     */
    private function shape(array $data, ?JournalArticle $article = null): array
    {
        $full = $article !== null;

        return [
            ...$data,
            'line_codes' => $full ? $article->lines : $data['lines'],
            'station_slugs' => $full ? $article->stations : $data['stations'],
            'lines' => $full ? $data['lines'] : null,
            'stations' => $full ? $data['stations'] : null,
            'url' => rtrim((string) config('app.url'), '/').$data['path'],
        ];
    }
}
