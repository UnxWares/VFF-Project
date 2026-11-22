<?php

namespace App\Services\Journal;

use App\Support\Seo\StructuredData;

/**
 * Mise en forme commune des articles pour la page, l'API REST, GraphQL et le serveur MCP : les mêmes
 * champs partout, pour que chaque sortie dise la même chose.
 */
final class JournalPresenter
{
    /** Auteur affiché et déclaré : l'équipe, jamais une personne (clé de traduction). */
    public const AUTHOR_KEY = 'journal.author';

    public function __construct(
        private readonly JournalRenderer $renderer,
    ) {}

    /**
     * Entrée de liste : sans le texte.
     *
     * @return array{slug: string, title: string, date: string, updated: ?string, summary: string, lang: string, locales: list<string>, image: array<string, mixed>|null, lines: list<string>, stations: list<string>, path: string}
     */
    public function summary(JournalArticle $article): array
    {
        return [
            'slug' => $article->slug,
            'title' => $article->title,
            'date' => $article->date,
            'updated' => $article->updated,
            'summary' => $article->summary,
            'lang' => $article->locale,
            'locales' => $article->locales,
            'image' => $article->image,
            'lines' => $article->lines,
            'stations' => $article->stations,
            'path' => '/journal/'.$article->slug,
        ];
    }

    /**
     * Article complet : texte rendu, sommaire, fiches citées, durée de lecture, mention de transparence.
     *
     * @return array<string, mixed>
     */
    public function full(JournalArticle $article): array
    {
        $rendered = $this->renderer->render($article, __('common.new_tab'), __('journal.article.footnote_back'));

        return [
            ...$this->summary($article),
            'author' => __(self::AUTHOR_KEY),
            'disclosure' => $article->disclosure,
            'html' => $rendered['html'],
            'toc' => $rendered['toc'],
            'lines' => $rendered['lines'],
            'stations' => $rendered['stations'],
            'words' => $rendered['words'],
            'reading_minutes' => $rendered['reading_minutes'],
            'license' => 'CC BY-SA 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.'.app()->getLocale(),
        ];
    }

    /**
     * Données structurées schema.org de l'article (Article), écrites par le serveur et reprises telles
     * quelles par le navigateur : une seule source, pas de double logique.
     *
     * @param  array<string, mixed>  $full  sortie de full()
     * @return array<string, mixed>
     */
    public function jsonLd(string $origin, array $full): array
    {
        $url = StructuredData::absoluteUrl($origin, (string) $full['path']);
        $image = $full['image'];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            '@id' => $url.'#article',
            'mainEntityOfPage' => $url,
            'url' => $url,
            'headline' => StructuredData::shorten((string) $full['title'], 110),
            'description' => StructuredData::shorten((string) $full['summary'], 300),
            'inLanguage' => $full['lang'],
            'datePublished' => $full['date'],
            'dateModified' => $full['updated'] ?? $full['date'],
            'wordCount' => $full['words'],
            'author' => ['@type' => 'Organization', 'name' => $full['author'], 'url' => StructuredData::absoluteUrl($origin, '/whoarewe')],
            'publisher' => ['@type' => 'Organization', 'name' => 'UnxWares', 'legalName' => 'UnxWares SAS', 'url' => 'https://www.unxwares.com'],
            'license' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'isPartOf' => ['@type' => 'Blog', 'name' => __('journal.index.title'), 'url' => StructuredData::absoluteUrl($origin, '/journal')],
            'image' => is_array($image) ? [
                '@type' => 'ImageObject',
                'contentUrl' => $image['src'],
                'width' => $image['width'],
                'height' => $image['height'],
                'caption' => $image['caption'] ?? $image['alt'],
                'creator' => ['@type' => 'Person', 'name' => $image['author']],
                'license' => $image['licence_url'] ?? null,
                'acquireLicensePage' => $image['source_url'],
            ] : null,
            'about' => array_map(fn (array $l): array => [
                '@type' => 'Place',
                'name' => $l['name'],
                'url' => StructuredData::absoluteUrl($origin, '/lines/'.$l['slug']),
            ], $full['lines']),
            'mentions' => array_map(fn (array $s): array => [
                '@type' => 'TrainStation',
                'name' => $s['name'],
                'url' => StructuredData::absoluteUrl($origin, '/stations/'.$s['slug']),
            ], $full['stations']),
        ];
    }
}
