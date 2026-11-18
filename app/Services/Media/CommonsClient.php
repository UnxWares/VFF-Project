<?php

namespace App\Services\Media;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Client minimal et POLI pour l'API MediaWiki de Wikimedia Commons et pour
 * Wikidata (SPARQL + wbgetentities).
 *
 * Politesse : User-Agent identifiant VFF, requêtes strictement séquentielles,
 * intervalle minimal entre deux appels, `maxlag=5` (on cède la place quand
 * les serveurs sont chargés) et respect de `Retry-After` sur 429/503.
 * Attentes via Illuminate\Support\Sleep (simulables en test : Sleep::fake()).
 */
class CommonsClient
{
    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    private const COMMONS_API = 'https://commons.wikimedia.org/w/api.php';

    private const WIKIDATA_API = 'https://www.wikidata.org/w/api.php';

    private const SPARQL = 'https://query.wikidata.org/sparql';

    /** Largeur des miniatures demandées (taille « standard » Wikimedia). */
    public const THUMB_WIDTH = 500;

    /** Champs extmetadata utiles (réduit la taille des réponses). */
    private const EXTMETADATA = 'LicenseShortName|License|LicenseUrl|UsageTerms|AttributionRequired|Attribution|Artist|Credit|DateTimeOriginal|ImageDescription|ObjectName|Copyrighted|NonFree|Restrictions|GPSLatitude|GPSLongitude|Categories';

    private float $lastRequestAt = 0.0;

    public int $requestCount = 0;

    /** Nombre de reprises après erreur transitoire (maxlag, 429, recherche saturée). */
    public int $retryCount = 0;

    public function __construct(private int $minIntervalMs = 250) {}

    /**
     * Appel GET à l'API Commons (formatversion=2). Gère maxlag / 429 / 503.
     *
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    public function commons(array $params): array
    {
        return $this->mediawiki(self::COMMONS_API, $params);
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    public function wikidata(array $params): array
    {
        return $this->mediawiki(self::WIKIDATA_API, $params);
    }

    /** @return list<array<string, mixed>> */
    public function sparql(string $query): array
    {
        $res = $this->send(fn () => Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/sparql-results+json',
        ])->timeout(180)->asForm()->post(self::SPARQL, ['query' => $query]));

        return $res->json('results.bindings', []);
    }

    /**
     * Infos de catégories (existence, nb de fichiers / sous-catégories).
     *
     * @param  list<string>  $titles  Titres « Category:… »
     * @return array<string, array{exists: bool, files: int, subcats: int}> clé = titre normalisé
     */
    public function categoryInfo(array $titles): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($titles)), 50) as $chunk) {
            $data = $this->commons([
                'action' => 'query',
                'titles' => implode('|', $chunk),
                'prop' => 'categoryinfo',
            ]);
            $aliases = [];
            foreach ($data['query']['normalized'] ?? [] as $n) {
                $aliases[$n['to']] = $n['from'];
            }
            foreach ($data['query']['pages'] ?? [] as $p) {
                $info = [
                    'exists' => empty($p['missing']) && isset($p['categoryinfo']),
                    'files' => (int) ($p['categoryinfo']['files'] ?? 0),
                    'subcats' => (int) ($p['categoryinfo']['subcats'] ?? 0),
                ];
                $out[$p['title']] = $info;
                if (isset($aliases[$p['title']])) {
                    $out[$aliases[$p['title']]] = $info;
                }
            }
        }

        return $out;
    }

    /**
     * Membres d'une catégorie (`file`, `subcat` ou `file|subcat`), paginés.
     *
     * @return list<array{pageid: int, title: string, type: string}>
     */
    public function categoryMembers(string $category, string $type = 'file|subcat', int $max = 1000): array
    {
        $out = [];
        $continue = [];
        do {
            $data = $this->commons([
                'action' => 'query',
                'list' => 'categorymembers',
                'cmtitle' => $category,
                'cmtype' => $type,
                'cmprop' => 'ids|title|type',
                'cmlimit' => min(500, max(1, $max - count($out))),
            ] + $continue);
            foreach ($data['query']['categorymembers'] ?? [] as $m) {
                $out[] = ['pageid' => (int) $m['pageid'], 'title' => $m['title'], 'type' => $m['type'] ?? 'file'];
            }
            $continue = $data['continue'] ?? [];
        } while ($continue !== [] && count($out) < $max);

        return array_slice($out, 0, $max);
    }

    /**
     * Fichiers géolocalisés autour d'un point (namespace File:).
     *
     * @return list<array{pageid: int, title: string, lat: float, lon: float}>
     */
    public function geosearch(float $lat, float $lon, int $radiusM, int $limit = 500): array
    {
        $data = $this->commons([
            'action' => 'query',
            'list' => 'geosearch',
            'gscoord' => sprintf('%.6f|%.6f', $lat, $lon),
            'gsradius' => max(10, min(10000, $radiusM)),
            'gslimit' => min(500, $limit),
            'gsnamespace' => 6,
        ]);

        return array_map(fn ($g) => [
            'pageid' => (int) $g['pageid'],
            'title' => $g['title'],
            'lat' => (float) $g['lat'],
            'lon' => (float) $g['lon'],
        ], $data['query']['geosearch'] ?? []);
    }

    /**
     * Métadonnées complètes (imageinfo + extmetadata) d'un lot de fichiers.
     *
     * @param  list<int>  $pageIds
     * @return array<int, array<string, mixed>> pageid → page brute (title, imageinfo[0])
     */
    public function fileInfo(array $pageIds): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($pageIds)), 50) as $chunk) {
            $data = $this->commons([
                'action' => 'query',
                'pageids' => implode('|', $chunk),
                'prop' => 'imageinfo',
                'iiprop' => 'extmetadata|url|size|mime',
                'iiurlwidth' => self::THUMB_WIDTH,
                'iiextmetadatalanguage' => 'fr',
                'iiextmetadatafilter' => self::EXTMETADATA,
            ]);
            foreach ($data['query']['pages'] ?? [] as $p) {
                if (! empty($p['missing']) || empty($p['imageinfo'][0])) {
                    continue;
                }
                $out[(int) $p['pageid']] = ['title' => $p['title'], 'info' => $p['imageinfo'][0]];
            }
        }

        return $out;
    }

    /**
     * Catégories VISIBLES d'un lot de pages (requête légère, sans extmetadata),
     * utilisée pour pré-filtrer les fichiers trouvés par geosearch.
     *
     * @param  list<int>  $pageIds
     * @return array<int, list<string>> pageid → catégories (sans préfixe)
     */
    public function categoriesOf(array $pageIds): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($pageIds)), 50) as $chunk) {
            $continue = [];
            do {
                $data = $this->commons([
                    'action' => 'query',
                    'pageids' => implode('|', $chunk),
                    'prop' => 'categories',
                    'clshow' => '!hidden',
                    'cllimit' => 'max',
                ] + $continue);
                foreach ($data['query']['pages'] ?? [] as $p) {
                    $id = (int) ($p['pageid'] ?? 0);
                    $out[$id] ??= [];
                    foreach ($p['categories'] ?? [] as $c) {
                        $out[$id][] = substr($c['title'], 9);
                    }
                }
                $continue = $data['continue'] ?? [];
            } while ($continue !== []);
        }

        return $out;
    }

    /**
     * Résout des titres de fichiers (« File:… ») en pageids.
     *
     * @param  list<string>  $titles
     * @return array<string, int> titre → pageid
     */
    public function resolveFileTitles(array $titles): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($titles)), 50) as $chunk) {
            $data = $this->commons(['action' => 'query', 'titles' => implode('|', $chunk)]);
            foreach ($data['query']['pages'] ?? [] as $p) {
                if (empty($p['missing']) && isset($p['pageid'])) {
                    $out[$p['title']] = (int) $p['pageid'];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    private function mediawiki(string $endpoint, array $params): array
    {
        $params += ['format' => 'json', 'formatversion' => 2, 'maxlag' => 5];

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $res = $this->send(fn () => Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(60)
                ->get($endpoint, $params));
            $json = $res->json() ?? [];
            $code = (string) ($json['error']['code'] ?? '');
            $info = (string) ($json['error']['info'] ?? '');
            // Erreurs transitoires (serveurs chargés) : on patiente et on réessaie.
            if ($code === 'maxlag' || $code === 'ratelimited' || str_starts_with($code, 'internal_api_error')
                || str_contains($info, 'too busy') || str_contains($info, 'try again')) {
                $this->retryCount++;
                Sleep::sleep(min(60, 5 * $attempt * $attempt));

                continue;
            }
            if (isset($json['error'])) {
                throw new RuntimeException('API MediaWiki : '.($json['error']['info'] ?? $json['error']['code']));
            }

            return $json;
        }

        throw new RuntimeException('API MediaWiki : serveurs surchargés (maxlag / recherche indisponible)');
    }

    /** Envoi avec intervalle minimal et reprise sur 429/503/erreurs réseau. */
    private function send(callable $request): Response
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $wait = $this->minIntervalMs / 1000 - (microtime(true) - $this->lastRequestAt);
            if ($wait > 0) {
                Sleep::usleep((int) ($wait * 1_000_000));
            }
            $this->lastRequestAt = microtime(true);
            $this->requestCount++;
            try {
                /** @var Response $res */
                $res = $request();
            } catch (ConnectionException $e) {
                Sleep::sleep(5 * $attempt);

                continue;
            }
            if (in_array($res->status(), [429, 503, 502, 504], true)) {
                $this->retryCount++;
                $retry = (int) ($res->header('Retry-After') ?: 10 * $attempt);
                Sleep::sleep(min(120, max(5, $retry)));

                continue;
            }

            return $res->throw();
        }

        throw new RuntimeException('Wikimedia injoignable après plusieurs tentatives');
    }
}
