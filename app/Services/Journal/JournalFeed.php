<?php

namespace App\Services\Journal;

use App\Support\Seo\StructuredData;
use Illuminate\Support\Carbon;
use XMLWriter;

/**
 * Flux Atom et RSS du journal (texte complet de chaque article, liens rendus absolus) et plan du site
 * des articles. XMLWriter échappe tout le texte : un titre avec « & » ou « < » reste valide.
 */
final class JournalFeed
{
    /** Articles repris dans les flux (les plus récents). */
    public const LIMIT = 20;

    public function __construct(
        private readonly JournalRepository $journal,
        private readonly JournalPresenter $presenter,
    ) {}

    public function atom(string $origin, string $locale): string
    {
        $articles = array_slice($this->journal->all($locale), 0, self::LIMIT);
        $self = StructuredData::absoluteUrl($origin, '/journal/atom.xml').'?lang='.$locale;
        $x = self::writer();
        $x->startElement('feed');
        $x->writeAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        $x->writeAttribute('xml:lang', $locale);
        $x->writeElement('id', $self);
        $x->writeElement('title', __('journal.feed.title'));
        $x->writeElement('subtitle', __('journal.feed.subtitle'));
        self::link($x, $self, 'self', 'application/atom+xml');
        self::link($x, StructuredData::absoluteUrl($origin, '/journal'), 'alternate', 'text/html');
        $x->writeElement('updated', self::atomDate($articles === [] ? '2026-11-22' : max(array_map(fn (JournalArticle $a): string => $a->modified(), $articles))));
        $x->startElement('author');
        $x->writeElement('name', __(JournalPresenter::AUTHOR_KEY));
        $x->writeElement('uri', StructuredData::absoluteUrl($origin, '/whoarewe'));
        $x->endElement();
        $x->writeElement('rights', __('journal.feed.rights'));
        foreach ($articles as $article) {
            $full = $this->presenter->full($article);
            $url = StructuredData::absoluteUrl($origin, (string) $full['path']);
            $x->startElement('entry');
            $x->writeElement('id', $url);
            $x->startElement('title');
            $x->writeAttribute('xml:lang', $article->locale);
            $x->text($article->title);
            $x->endElement();
            self::link($x, $url, 'alternate', 'text/html');
            $x->writeElement('published', self::atomDate($article->date));
            $x->writeElement('updated', self::atomDate($article->modified()));
            $x->writeElement('summary', $article->summary);
            $x->startElement('content');
            $x->writeAttribute('type', 'html');
            $x->writeAttribute('xml:lang', $article->locale);
            $x->text(self::absolute($origin, (string) $full['html']));
            $x->endElement();
            $x->endElement();
        }
        $x->endElement();

        return $x->outputMemory();
    }

    public function rss(string $origin, string $locale): string
    {
        $articles = array_slice($this->journal->all($locale), 0, self::LIMIT);
        $x = self::writer();
        $x->startElement('rss');
        $x->writeAttribute('version', '2.0');
        $x->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $x->startElement('channel');
        $x->writeElement('title', __('journal.feed.title'));
        $x->writeElement('link', StructuredData::absoluteUrl($origin, '/journal'));
        $x->writeElement('description', __('journal.feed.subtitle'));
        $x->writeElement('language', $locale);
        $x->writeElement('copyright', __('journal.feed.rights'));
        $x->startElement('atom:link');
        $x->writeAttribute('href', StructuredData::absoluteUrl($origin, '/journal/rss.xml').'?lang='.$locale);
        $x->writeAttribute('rel', 'self');
        $x->writeAttribute('type', 'application/rss+xml');
        $x->endElement();
        foreach ($articles as $article) {
            $full = $this->presenter->full($article);
            $url = StructuredData::absoluteUrl($origin, (string) $full['path']);
            $x->startElement('item');
            $x->writeElement('title', $article->title);
            $x->writeElement('link', $url);
            $x->startElement('guid');
            $x->writeAttribute('isPermaLink', 'true');
            $x->text($url);
            $x->endElement();
            $x->writeElement('pubDate', Carbon::parse($article->date, config('app.timezone'))->toRfc2822String());
            $x->writeElement('description', self::absolute($origin, (string) $full['html']));
            $x->endElement();
        }
        $x->endElement();
        $x->endElement();

        return $x->outputMemory();
    }

    public function sitemap(string $origin): string
    {
        $x = self::writer();
        $x->startElement('urlset');
        $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $articles = $this->journal->all(JournalRepository::CANONICAL);
        $x->startElement('url');
        $x->writeElement('loc', StructuredData::absoluteUrl($origin, '/journal'));
        if ($articles !== []) {
            $x->writeElement('lastmod', max(array_map(fn (JournalArticle $a): string => $a->modified(), $articles)));
        }
        $x->endElement();
        foreach ($articles as $article) {
            $x->startElement('url');
            $x->writeElement('loc', StructuredData::absoluteUrl($origin, '/journal/'.$article->slug));
            $x->writeElement('lastmod', $article->modified());
            $x->endElement();
        }
        $x->endElement();

        return $x->outputMemory();
    }

    private static function writer(): XMLWriter
    {
        $x = new XMLWriter;
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');

        return $x;
    }

    private static function link(XMLWriter $x, string $href, string $rel, string $type): void
    {
        $x->startElement('link');
        $x->writeAttribute('href', $href);
        $x->writeAttribute('rel', $rel);
        $x->writeAttribute('type', $type);
        $x->endElement();
    }

    private static function atomDate(string $date): string
    {
        return Carbon::parse($date, config('app.timezone'))->toAtomString();
    }

    /** Liens internes (`href="/lines/…"`, ancres `#note-1`) rendus absolus : un lecteur de flux n'a pas la page. */
    private static function absolute(string $origin, string $html): string
    {
        return (string) preg_replace('/\bhref="\/(?!\/)/', 'href="'.rtrim($origin, '/').'/', $html);
    }
}
