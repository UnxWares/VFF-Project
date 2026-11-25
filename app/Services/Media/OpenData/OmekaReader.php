<?php

namespace App\Services\Media\OpenData;

/**
 * Lit les images d'une bibliothèque numérique sous Omeka S (Bibliothèque numérique du Limousin, BFM de Limoges) par son API
 * publique (`/api/items?fulltext_search=<mot>&per_page=50&page=<n>`, JSON-LD, sans clé, pas de robots.txt). Une recherche par
 * mot du bloc `searches`, les notices réunies sans doublon (clé : `o:id`). La licence est celle de la notice (`dcterms:license`
 * et `dcterms:rights`), lue plus tard par OpenDataNormalizer ligne par ligne : ici rien n'est filtré.
 *
 * Les adresses d'image sont celles que l'API donne (`thumbnail_display_urls`), jamais téléchargées.
 */
final class OmekaReader
{
    public const PAGE_SIZE = 50;

    public function __construct(private readonly OpenDataFetcher $http) {}

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array<string, mixed>>
     */
    public function read(array $dataset, ?int $limit = null): array
    {
        $rows = [];
        foreach ((array) $dataset['searches'] as $term) {
            for ($page = 1; $page <= 40; $page++) {
                $items = $this->http->getJson($dataset['resource'], ['accept' => 'application/ld+json', 'query' => ['fulltext_search' => $term, 'per_page' => (string) self::PAGE_SIZE, 'page' => (string) $page]]);
                foreach ($items as $item) {
                    if (is_array($item) && isset($item['o:id'])) {
                        $rows[(string) $item['o:id']] ??= self::row($item, $dataset);
                    }
                }
                if (count($items) < self::PAGE_SIZE || ($limit !== null && count($rows) >= $limit)) {
                    break;
                }
            }
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        return array_values($rows);
    }

    /**
     * Ligne plate d'un item Omeka S.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    public static function row(array $item, array $dataset): array
    {
        $values = static function (string $term) use ($item): array {
            $out = [];
            foreach ((array) ($item['dcterms:'.$term] ?? []) as $v) {
                $text = is_array($v) ? ($v['@value'] ?? null) : null;
                if (is_string($text) && trim($text) !== '') {
                    $out[] = trim($text);
                }
            }

            return array_values(array_unique($out));
        };
        $id = (string) $item['o:id'];
        $title = $values('title')[0] ?? (string) ($item['o:title'] ?? '');
        $subjects = array_merge($values('subject'), $values('spatial'), $values('coverage'));
        foreach ((array) ($item['o-module-folksonomy:tag'] ?? []) as $tag) {
            $url = is_array($tag) ? ($tag['@id'] ?? null) : null;
            if (is_string($url) && str_contains($url, '/api/tags/')) {
                $subjects[] = urldecode((string) substr($url, (int) strrpos($url, '/') + 1));
            }
        }
        $provenance = $values('provenance')[0] ?? null;
        $display = (array) ($item['thumbnail_display_urls'] ?? []);
        $site = rtrim((string) preg_replace('#/api/.*$#', '', (string) $dataset['resource']), '/');

        return [
            'id' => $id,
            'title' => $title,
            'author' => self::author($values('creator')),
            'date' => $values('date')[0] ?? null,
            'subjects' => implode(' | ', array_unique($subjects)),
            'commune' => self::commune($values('spatial') ?: $values('subject')),
            'inventory' => self::shelfmark($title, $provenance, $values('description')[0] ?? ''),
            'repository' => $provenance,
            'rights' => implode(' | ', array_merge($values('license'), $values('rights'))),
            'type' => implode(' | ', $values('type')),
            'description' => $values('description')[0] ?? null,
            'link' => $site.'/s/'.($dataset['site_slug'] ?? 'bibliotheque-virtuelle').'/item/'.$id,
            'image' => $display['large'] ?? null,
            'thumb' => $display['medium'] ?? null,
        ];
    }

    /** « Boudeau, Jean-Baptiste (1881-1959) » donne « Jean-Baptiste Boudeau » ; « Jean-Baptiste Audiguet (1811-1897) » donne « Jean-Baptiste Audiguet ». */
    public static function author(array $creators): ?string
    {
        foreach ($creators as $c) {
            $c = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $c));
            if ($c === '' || preg_match('/[ée]diteur|imprimeur|collectivit|anonyme|inconnu/iu', $c) === 1) {
                continue;
            }
            if (preg_match('/^([^,]+),\s*(.+)$/u', $c, $m) === 1) {
                return trim($m[2]).' '.trim($m[1]);
            }

            return $c;
        }

        return null;
    }

    /** Première localité du sujet ou de la couverture spatiale : « Saint-Priest-Taurion, Haute-Vienne » ou « Royères (Haute-Vienne, France) ». */
    public static function commune(array $places): ?string
    {
        foreach ($places as $p) {
            if (preg_match('/^([^,()]+?)\s*(?:,|\()/u', $p, $m) === 1) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /** Cote : « cote : 9Fi502/39 » (provenance ou description), sinon « (B17-n°485) » du titre du fonds Boudeau. */
    public static function shelfmark(string $title, ?string $provenance, string $description): ?string
    {
        foreach ([(string) $provenance, $description] as $text) {
            if (preg_match('/cote\s*:\s*([^\s);]+)/iu', $text, $m) === 1) {
                return $m[1];
            }
        }

        return preg_match('/\(([A-Z]\d+-n°\s?\d+)\)\s*$/u', $title, $m) === 1 ? $m[1] : null;
    }
}
