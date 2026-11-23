<?php

namespace App\Services\Materials;

use App\Services\Media\CommonsClient;
use App\Services\Media\CommonsMetadata;
use Illuminate\Support\Sleep;

/**
 * Lecture du matériel roulant dans Wikidata (CC0) et des images dans Wikimedia Commons.
 *
 * Politesse : requêtes groupées (SPARQL par lots de 100 éléments, wbgetentities et imageinfo par
 * 50), une seconde au moins entre deux appels (CommonsClient), pause d'une seconde entre deux lots,
 * User-Agent générique de VFF, maxlag et Retry-After respectés, et cache local (SourceCache) :
 * une relance ne redemande que ce qui manque.
 */
class WikidataMaterialSource
{
    /** Propriétés lues sur une série (le reste de l'élément n'est pas gardé en cache). */
    public const PROPERTIES = [
        'P31', 'P279', 'P17', 'P137', 'P176', 'P729', 'P730', 'P1092', 'P2052', 'P2109', 'P930', 'P1064',
        'P2978', 'P2802', 'P1449', 'P18', 'P373', 'P2669', 'P5817',
    ];

    private const BATCH_SPARQL = 100;

    private const BATCH_API = 50;

    /** Fichiers lus par catégorie Commons (categoryFiles) : les premiers suffisent pour choisir une image. */
    private const CATEGORY_FILES = 20;

    /** Éléments testés par requête SPARQL pour `seriesAmong()` (les requêtes sont longues à évaluer). */
    private const BATCH_SERIES = 400;

    public function __construct(
        private readonly CommonsClient $client,
        private readonly SourceCache $cache,
        private readonly WikidataMaterialRules $rules,
        private readonly int $pauseMs = 1000,
    ) {}

    /**
     * Séries de matériel liées à la France : instances d'une sous-classe de « classe de matériel
     * roulant ferroviaire », exploitées par une compagnie française ou rattachées à la France.
     *
     * @return list<string> identifiants Wikidata triés
     */
    public function candidates(bool $refresh = false): array
    {
        $cached = $refresh ? [] : $this->cache->load('candidates');
        if (isset($cached['qids']) && is_array($cached['qids'])) {
            return self::withExtraSeries($cached['qids']);
        }

        $class = $this->rules->rollingStockClass();
        $country = $this->rules->country();
        $rows = $this->client->sparql(<<<SPARQL
            SELECT DISTINCT ?item WHERE {
              ?meta wdt:P279* wd:{$class} .
              ?item wdt:P31 ?meta .
              { ?item wdt:P137 ?operator . ?operator wdt:P17 wd:{$country} . } UNION { ?item wdt:P17 wd:{$country} . }
            }
            SPARQL);
        $qids = array_values(array_unique(array_filter(array_map(fn (array $row): ?string => self::qid($row['item']['value'] ?? null), $rows))));
        sort($qids, SORT_NATURAL);
        $this->cache->save('candidates', ['qids' => $qids, 'fetched_at' => now()->toIso8601String()]);

        return self::withExtraSeries($qids);
    }

    /**
     * Ajoute les séries de database/data/material_extra_series.php (articles rangés dans le matériel français dont
     * l'élément Wikidata n'a ni exploitant ni pays) aux candidats de la requête.
     *
     * @param  list<string>  $qids
     * @return list<string>
     */
    private static function withExtraSeries(array $qids): array
    {
        if (! config('vff.materials.extra_series', true)) {
            return $qids;
        }
        /** @var list<array{material: string, title: string, category: string}> $extra */
        $extra = require database_path('data/material_extra_series.php');
        $all = array_values(array_unique([...$qids, ...array_column($extra, 'material')]));
        sort($all, SORT_NATURAL);

        return $all;
    }

    /**
     * Parmi des éléments quelconques, ceux qui sont des séries de matériel : instances d'une
     * sous-classe de « classe de matériel roulant ferroviaire » (même définition que `candidates()`,
     * sans le rattachement à la France).
     *
     * @param  list<string>  $qids
     * @return array<string, bool> par identifiant
     */
    public function seriesAmong(array $qids, bool $refresh = false): array
    {
        $class = $this->rules->rollingStockClass();

        return $this->cache->fill('series_among', $qids, self::BATCH_SERIES, function (array $chunk) use ($class): array {
            $this->pause();
            $values = implode(' ', array_map(fn (string $q): string => "wd:{$q}", $chunk));
            $rows = $this->client->sparql(<<<SPARQL
                SELECT DISTINCT ?item WHERE {
                  VALUES ?item { {$values} }
                  ?meta wdt:P279* wd:{$class} .
                  ?item wdt:P31 ?meta .
                }
                SPARQL);
            $out = array_fill_keys($chunk, false);
            foreach ($rows as $row) {
                if (($item = self::qid($row['item']['value'] ?? null)) !== null) {
                    $out[$item] = true;
                }
            }

            return $out;
        }, $refresh);
    }

    /**
     * Classes consultées par les règles (catégories, exclusions) dont chaque série descend : classes
     * parentes (P279, transitif) et classe d'appartenance directe (P31).
     *
     * @param  list<string>  $qids
     * @return array<string, list<string>>
     */
    public function ancestors(array $qids, bool $refresh = false): array
    {
        $roots = implode(' ', array_map(fn (string $q): string => "wd:{$q}", $this->rules->roots()));

        // Le cache dépend de la liste des classes consultées : une règle ajoutée relit les ancêtres.
        $table = $this->cache->fill('ancestors-'.substr(md5($roots), 0, 8), $qids, self::BATCH_SPARQL, function (array $chunk) use ($roots): array {
            $this->pause();
            $values = implode(' ', array_map(fn (string $q): string => "wd:{$q}", $chunk));
            $rows = $this->client->sparql(<<<SPARQL
                SELECT DISTINCT ?item ?root WHERE {
                  VALUES ?item { {$values} }
                  VALUES ?root { {$roots} }
                  { ?item wdt:P279+ ?root . } UNION { ?item wdt:P31 ?root . }
                }
                SPARQL);
            $out = array_fill_keys($chunk, []);
            foreach ($rows as $row) {
                $item = self::qid($row['item']['value'] ?? null);
                $root = self::qid($row['root']['value'] ?? null);
                if ($item !== null && $root !== null) {
                    $out[$item][] = $root;
                }
            }

            return $out;
        }, $refresh);

        return array_map(fn ($v): array => is_array($v) ? array_values(array_unique($v)) : [], $table);
    }

    /**
     * Éléments réduits aux champs utiles : libellés, descriptions et alias (fr, en), articles
     * Wikipédia (fr, en) et déclarations des propriétés PROPERTIES (rang déprécié écarté).
     *
     * @param  list<string>  $qids
     * @return array<string, array<string, mixed>|null>
     */
    public function entities(array $qids, bool $refresh = false): array
    {
        return $this->cache->fill('entities', $qids, self::BATCH_API, function (array $chunk): array {
            $this->pause();
            $json = $this->client->wikidata([
                'action' => 'wbgetentities',
                'ids' => implode('|', $chunk),
                'props' => 'labels|aliases|descriptions|claims|sitelinks',
                'languages' => 'fr|en',
                'sitefilter' => 'frwiki|enwiki',
            ]);
            $out = [];
            foreach ($json['entities'] ?? [] as $qid => $entity) {
                if (! isset($entity['missing'])) {
                    // Date de lecture : la « date de vérification » de l'état d'utilisation (P5817).
                    $out[(string) $qid] = self::trimEntity($entity) + ['read_on' => now()->toDateString()];
                }
            }

            return $out;
        }, $refresh);
    }

    /**
     * Libellés (fr, en) des éléments cités : constructeurs, exploitants, alimentations, écartements.
     *
     * @param  list<string>  $qids
     * @return array<string, array{fr: ?string, en: ?string}|null>
     */
    public function labels(array $qids, bool $refresh = false): array
    {
        return $this->cache->fill('labels', $qids, self::BATCH_API, function (array $chunk): array {
            $this->pause();
            $json = $this->client->wikidata([
                'action' => 'wbgetentities', 'ids' => implode('|', $chunk), 'props' => 'labels', 'languages' => 'fr|en',
            ]);
            $out = [];
            foreach ($json['entities'] ?? [] as $qid => $entity) {
                $out[(string) $qid] = [
                    'fr' => $entity['labels']['fr']['value'] ?? null,
                    'en' => $entity['labels']['en']['value'] ?? null,
                ];
            }

            return $out;
        }, $refresh);
    }

    /**
     * Largeur en millimètres des écartements cités (P2049 de l'élément d'écartement).
     *
     * @param  list<string>  $qids
     * @return array<string, int|null>
     */
    public function gaugeWidths(array $qids, bool $refresh = false): array
    {
        $mm = $this->rules->millimetre();

        return $this->cache->fill('gauges', $qids, self::BATCH_API, function (array $chunk) use ($mm): array {
            $this->pause();
            $json = $this->client->wikidata(['action' => 'wbgetentities', 'ids' => implode('|', $chunk), 'props' => 'claims']);
            $out = [];
            foreach ($json['entities'] ?? [] as $qid => $entity) {
                foreach ($entity['claims']['P2049'] ?? [] as $claim) {
                    $value = $claim['mainsnak']['datavalue']['value'] ?? null;
                    if (is_array($value) && self::qid($value['unit'] ?? null) === $mm) {
                        $out[(string) $qid] = (int) round((float) $value['amount']);
                        break;
                    }
                }
            }

            return $out;
        }, $refresh);
    }

    /**
     * Métadonnées Commons des images (auteur, licence, adresses), normalisées par CommonsMetadata.
     *
     * @param  list<string>  $files  noms de fichiers tels que dans Wikidata (sans « File: »)
     * @return array<string, array<string, mixed>|null> par nom de fichier
     */
    public function commonsFiles(array $files, bool $refresh = false): array
    {
        return $this->cache->fill('commons', $files, self::BATCH_API, function (array $chunk): array {
            $this->pause();
            $titles = array_map(fn (string $f): string => 'File:'.$f, $chunk);
            $ids = $this->client->resolveFileTitles($titles);
            $infos = $ids !== [] ? $this->client->fileInfo(array_values($ids)) : [];
            $out = [];
            foreach ($chunk as $file) {
                $id = $ids['File:'.str_replace('_', ' ', $file)] ?? null;
                if ($id !== null && isset($infos[$id])) {
                    $out[$file] = CommonsMetadata::normalize($infos[$id]);
                }
            }

            return $out;
        }, $refresh);
    }

    /**
     * Premiers fichiers d'image (JPEG, PNG, TIFF, WebP) de catégories Commons, dans l'ordre de la catégorie :
     * repli quand une série n'a pas d'image sur Wikidata (P18) ni dans l'infobox de son article. Une requête
     * par catégorie (CATEGORY_FILES fichiers au plus), avec la pause entre deux appels ; cache `commons_category_files`.
     *
     * @param  list<string>  $categories  noms de catégories sans « Category: » (P373)
     * @return array<string, list<string>|null> noms de fichiers sans « File: », par catégorie
     */
    public function categoryFiles(array $categories, bool $refresh = false): array
    {
        return $this->cache->fill('commons_category_files', $categories, 1, function (array $chunk): array {
            $this->pause();
            $out = [];
            foreach ($chunk as $category) {
                $files = [];
                foreach ($this->client->categoryMembers('Category:'.$category, 'file', self::CATEGORY_FILES) as $member) {
                    if (preg_match('/\.(?:jpe?g|png|tiff?|webp)$/iu', $member['title']) === 1) {
                        $files[] = (string) preg_replace('/^File:/u', '', $member['title']);
                    }
                }
                $out[$category] = $files;
            }

            return $out;
        }, $refresh);
    }

    /**
     * Déclarations « véhicule habituellement utilisé » (P3438) des lignes et services français,
     * avec leurs bornes de période (P580, P582) quand elles existent.
     *
     * @return list<array{subject: string, material: string, from: ?string, to: ?string}>
     */
    public function vehicleUsage(bool $refresh = false): array
    {
        $cached = $refresh ? [] : $this->cache->load('vehicle_usage');
        if (isset($cached['rows']) && is_array($cached['rows'])) {
            return $cached['rows'];
        }

        $country = $this->rules->country();
        $rows = $this->client->sparql(<<<SPARQL
            SELECT DISTINCT ?subject ?material ?from ?to WHERE {
              ?subject p:P3438 ?statement ; wdt:P17 wd:{$country} .
              ?statement ps:P3438 ?material .
              FILTER NOT EXISTS { ?statement wikibase:rank wikibase:DeprecatedRank }
              OPTIONAL { ?statement pq:P580 ?from }
              OPTIONAL { ?statement pq:P582 ?to }
            }
            SPARQL);
        $out = [];
        foreach ($rows as $row) {
            $subject = self::qid($row['subject']['value'] ?? null);
            $material = self::qid($row['material']['value'] ?? null);
            if ($subject !== null && $material !== null) {
                $out[] = [
                    'subject' => $subject,
                    'material' => $material,
                    'from' => isset($row['from']['value']) ? substr((string) $row['from']['value'], 0, 10) : null,
                    'to' => isset($row['to']['value']) ? substr((string) $row['to']['value'], 0, 10) : null,
                ];
            }
        }
        $this->cache->save('vehicle_usage', ['rows' => $out, 'fetched_at' => now()->toIso8601String()]);

        return $out;
    }

    /**
     * Déclarations « véhicule habituellement utilisé » (P3438) datées par un qualificatif de début (P580)
     * ou de fin (P582), avec la précision de chaque date (9 année, 10 mois, 11 jour) : `vehicleUsage()`
     * ne garde que la date, qui ne dit pas si « 1954-01-01 » est un jour ou une année. Une seule requête,
     * gardée en cache (`vehicle_usage_periods`) ; hors ligne, seul le cache est lu.
     *
     * @return list<array{subject: string, material: string, from: ?string, from_precision: ?int, to: ?string, to_precision: ?int}>
     */
    public function vehicleUsagePeriods(bool $refresh = false, bool $offline = false): array
    {
        $cached = $refresh ? [] : $this->cache->load('vehicle_usage_periods');
        if (isset($cached['rows']) && is_array($cached['rows'])) {
            return $cached['rows'];
        }
        if ($offline) {
            return [];
        }

        $country = $this->rules->country();
        $rows = $this->client->sparql(<<<SPARQL
            SELECT DISTINCT ?subject ?material ?from ?fromPrecision ?to ?toPrecision WHERE {
              ?subject p:P3438 ?statement ; wdt:P17 wd:{$country} .
              ?statement ps:P3438 ?material .
              FILTER NOT EXISTS { ?statement wikibase:rank wikibase:DeprecatedRank }
              OPTIONAL { ?statement pqv:P580 ?fromValue . ?fromValue wikibase:timeValue ?from ; wikibase:timePrecision ?fromPrecision . }
              OPTIONAL { ?statement pqv:P582 ?toValue . ?toValue wikibase:timeValue ?to ; wikibase:timePrecision ?toPrecision . }
              FILTER(BOUND(?from) || BOUND(?to))
            }
            SPARQL);
        $out = [];
        foreach ($rows as $row) {
            $subject = self::qid($row['subject']['value'] ?? null);
            $material = self::qid($row['material']['value'] ?? null);
            if ($subject !== null && $material !== null) {
                $out[] = [
                    'subject' => $subject,
                    'material' => $material,
                    'from' => isset($row['from']['value']) ? substr((string) $row['from']['value'], 0, 10) : null,
                    'from_precision' => isset($row['fromPrecision']['value']) ? (int) $row['fromPrecision']['value'] : null,
                    'to' => isset($row['to']['value']) ? substr((string) $row['to']['value'], 0, 10) : null,
                    'to_precision' => isset($row['toPrecision']['value']) ? (int) $row['toPrecision']['value'] : null,
                ];
            }
        }
        $this->cache->save('vehicle_usage_periods', ['rows' => $out, 'fetched_at' => now()->toIso8601String()]);

        return $out;
    }

    /**
     * Élément wbgetentities réduit : déclarations simplifiées en {v: valeur, q: qualificatifs}.
     *
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>
     */
    public static function trimEntity(array $entity): array
    {
        $claims = [];
        foreach (self::PROPERTIES as $property) {
            foreach ($entity['claims'][$property] ?? [] as $claim) {
                if (($claim['rank'] ?? 'normal') === 'deprecated') {
                    continue;
                }
                $value = self::snakValue($claim['mainsnak'] ?? []);
                if ($value === null) {
                    continue;
                }
                $qualifiers = [];
                foreach (['P580', 'P582', 'P585', 'P1092', 'P1114'] as $q) {
                    foreach ($claim['qualifiers'][$q] ?? [] as $snak) {
                        if (($qv = self::snakValue($snak)) !== null) {
                            $qualifiers[$q][] = $qv;
                        }
                    }
                }
                $claims[$property][] = ['v' => $value, 'q' => $qualifiers, 'rank' => $claim['rank'] ?? 'normal'];
            }
        }

        $text = fn (string $key): array => array_filter([
            'fr' => $entity[$key]['fr']['value'] ?? null,
            'en' => $entity[$key]['en']['value'] ?? null,
        ]);

        return [
            'labels' => $text('labels'),
            'descriptions' => $text('descriptions'),
            'aliases' => array_filter([
                'fr' => array_column($entity['aliases']['fr'] ?? [], 'value'),
                'en' => array_column($entity['aliases']['en'] ?? [], 'value'),
            ]),
            'sitelinks' => array_filter([
                'frwiki' => $entity['sitelinks']['frwiki']['title'] ?? null,
                'enwiki' => $entity['sitelinks']['enwiki']['title'] ?? null,
            ]),
            'claims' => $claims,
        ];
    }

    /**
     * Valeur d'un snak : identifiant (Q…), texte, {amount, unit}, {time, precision} ou {text, language}.
     *
     * @param  array<string, mixed>  $snak
     */
    private static function snakValue(array $snak): mixed
    {
        if (($snak['snaktype'] ?? 'value') !== 'value') {
            return null;
        }
        $value = $snak['datavalue']['value'] ?? null;

        return match ($snak['datavalue']['type'] ?? null) {
            'wikibase-entityid' => $value['id'] ?? null,
            'string' => $value,
            'quantity' => ['amount' => (float) $value['amount'], 'unit' => self::qid($value['unit'] ?? null)],
            'time' => ['time' => (string) $value['time'], 'precision' => (int) $value['precision']],
            'monolingualtext' => ['text' => (string) $value['text'], 'language' => (string) $value['language']],
            default => null,
        };
    }

    /** Identifiant Q… d'une adresse d'entité Wikidata, null sinon. */
    public static function qid(mixed $uri): ?string
    {
        return is_string($uri) && preg_match('~(Q\d+)$~', $uri, $m) ? $m[1] : null;
    }

    private function pause(): void
    {
        if ($this->pauseMs > 0) {
            Sleep::usleep($this->pauseMs * 1000);
        }
    }
}
