<?php

declare(strict_types=1);

namespace App\Services\Heritage;

use App\Models\Media;
use App\Services\Media\CommonsClient;
use App\Services\Media\CommonsMetadata;
use Illuminate\Support\Sleep;

/**
 * Patrimoine des gares dans Wikidata (CC0), lu pour les éléments de gare de VFF (`stations.wikidata_id`) par lots
 * de BATCH éléments : protection (P1435, avec la date P580 en qualificatif), référence Mérimée ou Inventaire
 * (P380), architecte (P84), construction (P571), style (P149) et image (P18, dont la licence est ensuite lue sur
 * Wikimedia Commons : seules les images libres sont gardées).
 *
 * Politesse : une requête SPARQL par lot, une seconde de pause entre deux lots, User-Agent de VFF, cache local
 * (`<dossier>/wikidata.json`, `.nobackup`).
 */
final class WikidataStationHeritage
{
    public const BATCH = 200;

    /** Statuts de patrimoine (P1435) retenus. */
    public const PROTECTIONS = [
        'Q10387684' => 'classified',  // monument historique classé
        'Q10387575' => 'registered',  // monument historique inscrit
        'Q44078976' => 'acr',         // label Architecture contemporaine remarquable
        'Q3214312' => 'acr',          // label « Patrimoine du XXe siècle » (ancien nom du label)
        'Q16739336' => 'inventory',   // bien recensé dans l'Inventaire général du patrimoine culturel
    ];

    public function __construct(private readonly CommonsClient $client, private readonly int $pauseMs = 1000) {}

    /**
     * @param  list<string>  $qids
     * @return array<string, array<string, mixed>> par identifiant Q ; seules les gares qui ont un statut ou une référence
     */
    public function fetch(array $qids, string $dir, bool $refresh = false): array
    {
        $dir = rtrim($dir, '/');
        $cache = $dir.'/wikidata.json';
        if (! $refresh && is_file($cache)) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached) && is_array($cached['items'] ?? null)) {
                return $cached['items'];
            }
        }

        $items = [];
        foreach (array_chunk(array_values(array_unique($qids)), self::BATCH) as $i => $chunk) {
            if ($i > 0) {
                Sleep::usleep($this->pauseMs * 1000);
            }
            $values = implode(' ', array_map(fn (string $q): string => 'wd:'.$q, $chunk));
            $rows = $this->client->sparql(<<<SPARQL
                SELECT ?item ?prot ?start ?ref ?arch ?archL ?built ?style ?styleL ?img WHERE {
                  VALUES ?item { {$values} }
                  FILTER(EXISTS { ?item wdt:P1435 ?x } || EXISTS { ?item wdt:P380 ?y })
                  OPTIONAL { ?item p:P1435 ?s . ?s ps:P1435 ?prot . OPTIONAL { ?s pq:P580 ?start } }
                  OPTIONAL { ?item wdt:P380 ?ref }
                  OPTIONAL { ?item wdt:P84 ?arch . ?arch rdfs:label ?archL FILTER(lang(?archL) = 'fr') }
                  OPTIONAL { ?item wdt:P571 ?built }
                  OPTIONAL { ?item wdt:P149 ?style . ?style rdfs:label ?styleL FILTER(lang(?styleL) = 'fr') }
                  OPTIONAL { ?item wdt:P18 ?img }
                }
                SPARQL);
            $items += self::fold($rows);
        }
        $items = $this->withImages($items);

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (! is_file($dir.'/.nobackup')) {
            touch($dir.'/.nobackup');
        }
        file_put_contents($cache, json_encode(['fetched_at' => now()->toDateTimeString(), 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $items;
    }

    /**
     * Regroupe les lignes SPARQL (produit cartésien des propriétés multiples) par élément.
     *
     * Un élément : `protections` (liste `{kind, date}`), `refs` (références P380), `architects` (noms), `built`
     * (année), `style`, `image` (nom du fichier sur Commons).
     *
     * @param  list<array<string, array{value: string}>>  $bindings
     * @return array<string, array<string, mixed>>
     */
    public static function fold(array $bindings): array
    {
        $items = [];
        foreach ($bindings as $b) {
            $qid = self::qid($b['item']['value'] ?? '');
            if ($qid === null) {
                continue;
            }
            $item = $items[$qid] ?? ['protections' => [], 'refs' => [], 'architects' => [], 'built' => null, 'style' => null, 'image' => null];
            $prot = self::qid($b['prot']['value'] ?? '');
            if ($prot !== null && isset(self::PROTECTIONS[$prot])) {
                $date = isset($b['start']['value']) ? self::date($b['start']['value']) : null;
                $key = self::PROTECTIONS[$prot].'|'.$prot;
                $item['protections'][$key] = ['kind' => self::PROTECTIONS[$prot], 'class' => $prot, 'date' => $date] + ($item['protections'][$key] ?? []);
            }
            $ref = trim($b['ref']['value'] ?? '');
            if (preg_match('/^(PA|IA|EA)\d{8}$/', $ref) === 1) {
                $item['refs'][$ref] = $ref;
            }
            $arch = trim($b['archL']['value'] ?? '');
            if ($arch !== '') {
                $item['architects'][$arch] = $arch;
            }
            if (isset($b['built']['value']) && preg_match('/^(\d{4})/', $b['built']['value'], $m) === 1) {
                $item['built'] ??= (int) $m[1];
            }
            $style = trim($b['styleL']['value'] ?? '');
            if ($style !== '') {
                $item['style'] ??= $style;
            }
            if (isset($b['img']['value'])) {
                $item['image'] ??= self::fileName($b['img']['value']);
            }
            $items[$qid] = $item;
        }

        return array_map(fn (array $item): array => [
            'protections' => array_values($item['protections']),
            'refs' => array_values($item['refs']),
            'architects' => array_values($item['architects']),
        ] + $item, $items);
    }

    /** « http://www.wikidata.org/entity/Q123 » → « Q123 ». */
    public static function qid(string $uri): ?string
    {
        return preg_match('#/(Q\d+)$#', $uri, $m) === 1 ? $m[1] : null;
    }

    /** « 1975-01-15T00:00:00Z » → « 1975-01-15 » (les dates à l'année seule, 1er janvier, sont gardées telles quelles). */
    public static function date(string $iso): ?string
    {
        return preg_match('/^(\d{4}-\d{2}-\d{2})T/', $iso, $m) === 1 ? $m[1] : null;
    }

    /** Nom de fichier de Commons dans une adresse `Special:FilePath/Nom%20du%20fichier.jpg`. */
    public static function fileName(string $url): ?string
    {
        $pos = strrpos($url, '/Special:FilePath/');
        if ($pos === false) {
            return null;
        }
        $name = str_replace('_', ' ', rawurldecode(substr($url, $pos + strlen('/Special:FilePath/'))));

        return $name !== '' ? $name : null;
    }

    /**
     * Ajoute à chaque élément à image la vignette, la page, la licence et l'auteur lus sur Commons ; une image dont la
     * licence n'est pas libre (ou inconnue) est retirée.
     *
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function withImages(array $items): array
    {
        $files = [];
        foreach ($items as $item) {
            if ($item['image'] !== null) {
                $files['File:'.$item['image']] = true;
            }
        }
        $meta = [];
        foreach ($this->client->fileInfoByTitles(array_keys($files)) as $page) {
            $info = CommonsMetadata::normalize($page);
            $meta[self::key($page['title'])] = $info;
        }
        foreach ($items as &$item) {
            $info = $item['image'] !== null ? ($meta[self::key('File:'.$item['image'])] ?? null) : null;
            $free = $info !== null
                && in_array($info['rights_status'], [Media::RIGHTS_PUBLIC_DOMAIN, Media::RIGHTS_OPEN_LICENSE], true)
                && $info['commercial_ok'] !== false
                && $info['display_url'] !== null
                && str_starts_with((string) $info['mime'], 'image/')
                && ! str_contains((string) $info['mime'], 'svg');
            $item['image'] = $free ? [
                'file' => $item['image'],
                'url' => $info['display_url'],
                'source_url' => $info['source_url'],
                'license' => $info['license'],
                'author' => $info['author'],
            ] : null;
        }

        return $items;
    }

    private static function key(string $title): string
    {
        return mb_strtolower(str_replace('_', ' ', $title));
    }
}
