<?php

namespace App\Services\Materials;

use App\Services\WikimediaClient;
use Illuminate\Support\Sleep;

/**
 * Articles de Wikipédia en français lus pour relier matériel et lignes : wikitexte (réduit aux
 * sections utiles) avec l'identifiant de révision, pour citer un permalien `oldid`, et
 * redirections vers chaque article.
 *
 * Politesse : 50 titres par requête (WikimediaClient : maxlag, Retry-After, User-Agent de VFF),
 * pause d'une seconde entre deux requêtes, cache local (SourceCache).
 */
class WikipediaArticleSource
{
    private const BATCH = 50;

    public function __construct(
        private readonly WikimediaClient $client,
        private readonly SourceCache $cache,
        private readonly int $pauseMs = 1000,
    ) {}

    /**
     * Sections utiles des articles demandés (motif de WikipediaMaterialSections), indexées par
     * titre DEMANDÉ ; `title` est le titre final (redirections suivies).
     *
     * @param  list<string>  $titles
     * @return array<string, array{title: string, revid: int, sections: list<array{title: string, level: int, text: string}>}|null>
     */
    public function sections(string $cacheName, array $titles, string $pattern, bool $refresh = false): array
    {
        return $this->cache->fill($cacheName, $titles, self::BATCH, function (array $chunk) use ($pattern): array {
            $this->pause();
            $out = [];
            foreach ($this->client->fetchPages($chunk) as $requested => $page) {
                $out[$requested] = [
                    'title' => $page['title'],
                    'revid' => $page['revid'],
                    'sections' => WikipediaMaterialSections::sections($page['content'], $pattern),
                ];
            }

            return $out;
        }, $refresh);
    }

    /**
     * Infobox « matériel ferroviaire » de chaque article (introduction seule), avec la révision lue.
     *
     * @param  list<string>  $titles
     * @return array<string, array{title: string, revid: int, parameters: array<string, string>, read_on: string}|null> par titre demandé ; null si l'article n'existe pas
     */
    public function infoboxes(string $cacheName, array $titles, bool $refresh = false): array
    {
        return $this->cache->fill($cacheName, $titles, self::BATCH, function (array $chunk): array {
            $this->pause();
            $out = [];
            foreach ($this->client->fetchPages($chunk, leadOnly: true) as $requested => $page) {
                $out[$requested] = [
                    'title' => $page['title'],
                    'revid' => $page['revid'],
                    'parameters' => WikipediaMaterialInfobox::parameters($page['content']),
                    'read_on' => now()->toDateString(),
                ];
            }

            return $out;
        }, $refresh);
    }

    /**
     * Titres qui redirigent vers chaque article (espace principal).
     *
     * @param  list<string>  $titles  titres finaux
     * @return array<string, list<string>|null>
     */
    public function redirects(string $cacheName, array $titles, bool $refresh = false): array
    {
        return $this->cache->fill($cacheName, $titles, self::BATCH, function (array $chunk): array {
            $out = array_fill_keys($chunk, []);
            $continue = [];
            do {
                $this->pause();
                $json = $this->client->api([
                    'action' => 'query', 'prop' => 'redirects', 'titles' => implode('|', $chunk),
                    'rdlimit' => 'max', 'rdnamespace' => 0, 'rdprop' => 'title',
                ] + $continue);
                foreach ($json['query']['pages'] ?? [] as $page) {
                    $title = (string) ($page['title'] ?? '');
                    foreach ($page['redirects'] ?? [] as $redirect) {
                        $out[$title][] = (string) $redirect['title'];
                    }
                }
                $continue = $json['continue'] ?? [];
            } while ($continue !== []);

            return $out;
        }, $refresh);
    }

    /**
     * Catégories visibles des articles (sans le préfixe « Catégorie: »).
     *
     * @param  list<string>  $titles
     * @return array<string, list<string>|null>
     */
    public function categories(string $cacheName, array $titles, bool $refresh = false): array
    {
        return $this->cache->fill($cacheName, $titles, self::BATCH, function (array $chunk): array {
            $out = array_fill_keys($chunk, []);
            $continue = [];
            do {
                $this->pause();
                $json = $this->client->api([
                    'action' => 'query', 'prop' => 'categories', 'titles' => implode('|', $chunk),
                    'clshow' => '!hidden', 'cllimit' => 'max',
                ] + $continue);
                foreach ($json['query']['pages'] ?? [] as $page) {
                    $title = (string) ($page['title'] ?? '');
                    foreach ($page['categories'] ?? [] as $category) {
                        $out[$title][] = (string) preg_replace('/^Catégorie:/u', '', (string) $category['title']);
                    }
                }
                $continue = $json['continue'] ?? [];
            } while ($continue !== []);

            return $out;
        }, $refresh);
    }

    /**
     * Élément Wikidata de chaque article (redirections et normalisation suivies).
     *
     * @param  list<string>  $titles
     * @return array<string, string|null> identifiant Q… par titre demandé ; null si l'article n'existe pas ou n'a pas d'élément
     */
    public function wikidataIds(string $cacheName, array $titles, bool $refresh = false): array
    {
        return $this->cache->fill($cacheName, $titles, self::BATCH, function (array $chunk): array {
            $this->pause();
            $json = $this->client->api([
                'action' => 'query', 'prop' => 'pageprops', 'ppprop' => 'wikibase_item',
                'titles' => implode('|', $chunk), 'redirects' => 1,
            ]);
            $hops = [];
            foreach (['normalized', 'redirects'] as $kind) {
                foreach ($json['query'][$kind] ?? [] as $hop) {
                    $hops[$kind][(string) $hop['from']] = (string) $hop['to'];
                }
            }
            $items = [];
            foreach ($json['query']['pages'] ?? [] as $page) {
                $items[(string) ($page['title'] ?? '')] = $page['pageprops']['wikibase_item'] ?? null;
            }
            $out = [];
            foreach ($chunk as $requested) {
                $title = $hops['normalized'][$requested] ?? $requested;
                $title = $hops['redirects'][$title] ?? $title;
                $out[$requested] = $items[$title] ?? null;
            }

            return $out;
        }, $refresh);
    }

    private function pause(): void
    {
        if ($this->pauseMs > 0) {
            Sleep::usleep($this->pauseMs * 1000);
        }
    }
}
