<?php

namespace App\Services\Bibliography;

use App\Services\WikimediaClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Ouvrages et articles que Wikidata (CC0) rattache à un élément (ligne ou gare) : l'élément est « décrit par » l'œuvre
 * (P1343) ou l'œuvre a pour sujet principal l'élément (P921). Seuls les livres (Q571), leurs éditions (Q3331189) et les articles (Q191067, Q13442814, Q18918145) sont gardés ; titre (P1476), auteurs (P50 ou P2093), éditeur (P123), date (P577) et ISBN (P212, P957).
 *
 * Une requête SPARQL par lot de QIDS, une toutes les 5 secondes au moins, avec le User-Agent du projet ; chaque réponse
 * est gardée sur disque (storage/app/data/bibliographie/wikidata, dossier marqué `.nobackup`) : une relance relit le
 * cache. `offline` : cache seulement, un lot absent est une erreur ; `refresh` : le cache est ignoré.
 */
class WikidataBooks
{
    public const BATCH = 80;

    private ?float $lastRequestAt = null;

    public int $networkRequests = 0;

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $offline = false,
        private readonly bool $refresh = false,
        private readonly int $intervalMs = 5000,
    ) {}

    public static function default(bool $offline = false, bool $refresh = false, int $intervalMs = 5000): self
    {
        return new self(storage_path('app/data/bibliographie/wikidata'), $offline, $refresh, $intervalMs);
    }

    /**
     * @param  list<string>  $qids  identifiants Wikidata (« Q123 »)
     * @return array<string, list<BibliographyRecord>> par identifiant d'élément
     *
     * @throws RuntimeException requête refusée, réponse illisible, ou lot absent du cache hors ligne
     */
    public function forItems(array $qids): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique(array_filter($qids, fn (string $q): bool => preg_match('/^Q\d+$/', $q) === 1))), self::BATCH) as $chunk) {
            foreach ($this->parse($this->json($chunk)) as $qid => $records) {
                $out[$qid] = [...($out[$qid] ?? []), ...$records];
            }
        }

        return $out;
    }

    /** @param  list<string>  $chunk */
    public static function query(array $chunk): string
    {
        $values = implode(' ', array_map(fn (string $q): string => 'wd:'.$q, $chunk));

        return <<<SPARQL
            SELECT ?item ?work ?workLabel ?title ?isbn13 ?isbn10 ?date ?authorLabel ?authorName ?publisherLabel ?class WHERE {
              VALUES ?item { {$values} }
              { ?item wdt:P1343 ?work } UNION { ?work wdt:P921 ?item }
              ?work wdt:P31 ?class .
              VALUES ?class { wd:Q571 wd:Q3331189 wd:Q13442814 wd:Q18918145 wd:Q191067 }
              OPTIONAL { ?work wdt:P1476 ?title }
              OPTIONAL { ?work wdt:P212 ?isbn13 }
              OPTIONAL { ?work wdt:P957 ?isbn10 }
              OPTIONAL { ?work wdt:P577 ?date }
              OPTIONAL { ?work wdt:P50 ?author . ?author rdfs:label ?authorLabel . FILTER(LANG(?authorLabel) = "fr") }
              OPTIONAL { ?work wdt:P2093 ?authorName }
              OPTIONAL { ?work wdt:P123 ?publisher . ?publisher rdfs:label ?publisherLabel . FILTER(LANG(?publisherLabel) = "fr") }
              SERVICE wikibase:label { bd:serviceParam wikibase:language "fr,en". }
            }
            SPARQL;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, list<BibliographyRecord>>
     */
    public function parse(array $json): array
    {
        /** @var array<string, array<string, array{title: ?string, isbn: ?string, year: ?int, authors: list<string>, publisher: ?string, article: bool}>> $works */
        $works = [];
        foreach ($json['results']['bindings'] ?? [] as $b) {
            $item = self::qid($b['item']['value'] ?? '');
            $work = self::qid($b['work']['value'] ?? '');
            if ($item === null || $work === null) {
                continue;
            }
            $w = $works[$item][$work] ?? ['title' => null, 'isbn' => null, 'year' => null, 'authors' => [], 'publisher' => null, 'article' => false];
            $label = $b['title']['value'] ?? $b['workLabel']['value'] ?? null;
            // Un libellé qui n'est que l'identifiant (aucun libellé en français ni en anglais) n'est pas un titre.
            if ($w['title'] === null && $label !== null && preg_match('/^Q\d+$/', $label) !== 1) {
                $w['title'] = $label;
            }
            $w['isbn'] ??= Isbn::normalize($b['isbn13']['value'] ?? null) ?? Isbn::normalize($b['isbn10']['value'] ?? null);
            if (isset($b['date']['value']) && preg_match('/^(\d{4})/', $b['date']['value'], $y) === 1) {
                $w['year'] ??= (int) $y[1];
            }
            foreach (['authorLabel', 'authorName'] as $field) {
                $name = $b[$field]['value'] ?? null;
                if ($name !== null && ! in_array($name, $w['authors'], true) && count($w['authors']) < 4) {
                    $w['authors'][] = $name;
                }
            }
            $w['publisher'] ??= $b['publisherLabel']['value'] ?? null;
            $w['article'] = $w['article'] || in_array(self::qid($b['class']['value'] ?? ''), ['Q13442814', 'Q18918145', 'Q191067'], true);
            $works[$item][$work] = $w;
        }

        $out = [];
        foreach ($works as $item => $byWork) {
            foreach ($byWork as $work => $w) {
                if ($w['title'] === null || mb_strlen($w['title']) < 4) {
                    continue;
                }
                $out[$item][] = new BibliographyRecord(
                    kind: $w['article'] ? BibliographyRecord::ARTICLE : BibliographyRecord::BOOK,
                    title: mb_substr($w['title'], 0, 1000),
                    authors: $w['authors'],
                    publisher: $w['publisher'] === null ? null : mb_substr($w['publisher'], 0, 255),
                    place: null,
                    year: $w['year'],
                    isbn: $w['isbn'],
                    journal: null,
                    provider: 'wikidata',
                    url: 'https://www.wikidata.org/wiki/'.$work,
                );
            }
        }

        return $out;
    }

    private static function qid(string $uri): ?string
    {
        return preg_match('#/(Q\d+)$#', $uri, $m) === 1 ? $m[1] : null;
    }

    /**
     * @param  list<string>  $chunk
     * @return array<string, mixed>
     */
    private function json(array $chunk): array
    {
        $path = $this->cacheDir.'/'.sha1(implode(',', $chunk)).'.json';
        if (! $this->refresh && is_file($path)) {
            $cached = json_decode((string) file_get_contents($path), true);
            if (is_array($cached)) {
                return $cached;
            }
        }
        if ($this->offline) {
            throw new RuntimeException('lot Wikidata absent du cache local (--offline)');
        }
        $json = $this->fetch(self::query($chunk));
        File::ensureDirectoryExists($this->cacheDir);
        if (! is_file($this->cacheDir.'/.nobackup')) {
            File::put($this->cacheDir.'/.nobackup', '');
        }
        File::put($path, (string) json_encode($json, JSON_UNESCAPED_UNICODE));

        return $json;
    }

    /** @return array<string, mixed> */
    private function fetch(string $query): array
    {
        $error = 'aucun essai';
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->throttle();
            $this->networkRequests++;
            $res = Http::withUserAgent(WikimediaClient::USER_AGENT)->timeout(90)
                ->withHeaders(['Accept' => 'application/sparql-results+json'])
                ->asForm()->post(WikimediaClient::WIKIDATA_SPARQL, ['query' => $query]);
            if ($res->successful() && is_array($res->json()) && isset($res->json()['results'])) {
                return $res->json();
            }
            $error = 'HTTP '.$res->status();
            if ($res->status() !== 429 && $res->status() < 500) {
                break;
            }
            $retry = (int) $res->header('Retry-After');
            Sleep::sleep($retry > 0 ? min($retry, 120) : 10 * $attempt);
        }

        throw new RuntimeException("SPARQL de Wikidata : {$error}");
    }

    private function throttle(): void
    {
        $now = microtime(true);
        if ($this->lastRequestAt !== null) {
            $wait = (int) round($this->intervalMs * 1000 - ($now - $this->lastRequestAt) * 1_000_000);
            if ($wait > 0) {
                Sleep::usleep($wait);
            }
        }
        $this->lastRequestAt = microtime(true);
    }
}
