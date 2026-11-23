<?php

namespace App\Services\Bibliography;

use App\Models\BibliographyEntry;
use App\Models\BibliographyLink;
use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\CatalogueSruClient;
use App\Services\Archives\GallicaImporter;
use App\Services\Archives\LinePlaceIndex;
use App\Services\Archives\LinePlaces;
use App\Services\StationArticleCache;
use App\Services\WikimediaClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Import de la bibliographie des lignes et des gares (vff:import-bibliography), en trois sources indépendantes :
 *
 *   - `bnf` : une requête au catalogue général de la BnF par couple d'extrémités de la ligne (livres, titre qui nomme
 *     les deux lieux et un mot du rail) et par nom d'usage ; chaque notice est jugée pour toutes les lignes (score
 *     d'ArchiveMatcher, une ligne ou deux au plus) ; pour les gares : titre qui nomme « la gare de X » ;
 *   - `wikipedia` : les références du wikitexte de l'article de la ligne ou de la gare (cache de StationArticleCache),
 *     rattachées si leur titre nomme la ligne (BibliographyMatcher) ;
 *   - `wikidata` : « décrit par » (P1343) et « sujet principal » (P921).
 *
 * Chaque candidat est un tableau : subject (`line` ou `station`), id, record, provider, basis, score, reasons,
 * origin_url, fetched_at. Rien n'est écrit avant store() ; une source dont une requête échoue est « en erreur » :
 * ses liens ne bougent pas pour le sujet concerné.
 */
final class BibliographyImporter
{
    public function __construct(private readonly BibliographyMatcher $matcher = new BibliographyMatcher) {}

    /** Requête CQL des livres dont le titre nomme les deux lieux et un mot du rail. */
    public static function cqlRoute(string $a, string $b): string
    {
        $term = fn (string $s): string => trim((string) preg_replace('/["\\\\]+/', ' ', $s));

        return sprintf(
            'bib.title all "%s" and bib.title all "%s" and bib.title any "%s" and bib.recordtype any "mon" and bib.doctype any "a"',
            $term($a), $term($b), GallicaImporter::RAIL_TERMS,
        );
    }

    /** Requête CQL des livres dont le titre nomme un nom d'usage de ligne (« ligne des Causses »). */
    public static function cqlAlias(string $words): string
    {
        $term = trim((string) preg_replace('/["\\\\]+/', ' ', $words));

        return sprintf('bib.title all "%s" and bib.title any "%s" and bib.recordtype any "mon" and bib.doctype any "a"', $term, 'ligne chemin chemins fer ferree train trains tramway');
    }

    /** Requête CQL des livres dont le titre nomme « la gare de X ». */
    public static function cqlStation(string $term): string
    {
        $term = trim((string) preg_replace('/["\\\\]+/', ' ', $term));

        return sprintf('bib.title all "gare" and bib.title all "%s" and bib.recordtype any "mon" and bib.doctype any "a"', $term);
    }

    /**
     * Notices de la BnF rattachées aux lignes.
     *
     * @param  list<LinePlaces>  $lines
     * @param  (callable(LinePlaces): void)|null  $onLine
     * @return array{candidates: list<array<string, mixed>>, failed: array<string, int>, errors: list<string>, stats: array<string, int>, judged: list<array<string, mixed>>}
     */
    public function bnfLines(CatalogueSruClient $client, LinePlaceIndex $index, LineAliases $aliases, array $lines, int $threshold = ArchiveMatcher::THRESHOLD, ?callable $onLine = null): array
    {
        $parser = new BnfBookParser;
        $parse = $parser->parse(...);
        $errors = [];
        $failed = [];
        $queries = 0;
        $truncated = 0;
        /** @var array<string, array{record: BibliographyRecord, fetched_at: ?int}> $records */
        $records = [];
        foreach ($lines as $line) {
            $queriesOfLine = array_map(fn (array $p): string => self::cqlRoute($p[0], $p[1]), $line->queryPairs);
            foreach ($aliases->for($line->id) as $alias) {
                $queriesOfLine[] = self::cqlAlias(implode(' ', $alias['key']));
            }
            $found = [];
            foreach ($queriesOfLine as $cql) {
                $queries++;
                try {
                    $result = $client->searchWith($cql, $parse);
                } catch (RuntimeException $e) {
                    $errors[] = "{$line->slug} : {$e->getMessage()}";
                    $failed[$line->slug] = $line->id;

                    continue;
                }
                $truncated += $result['truncated'] ? 1 : 0;
                foreach ($result['records'] as $record) {
                    $found[$record->dedupKey()] ??= ['record' => $record, 'fetched_at' => $result['fetched_at']];
                }
            }
            if (! isset($failed[$line->slug])) {
                $records += $found;
            }
            if ($onLine !== null) {
                $onLine($line);
            }
        }

        $frozen = array_fill_keys($failed, true);
        $candidates = [];
        $judged = [];
        foreach ($records as ['record' => $record, 'fetched_at' => $fetchedAt]) {
            foreach ($this->matcher->bnfLines($record, $index, $aliases, $threshold) as $m) {
                $line = $index->line($m['line_id']);
                $judged[] = ['line' => $line?->slug, 'title' => $record->title, 'year' => $record->year, 'basis' => $m['basis'], 'score' => $m['score']];
                if (! isset($frozen[(string) $line?->slug])) {
                    $candidates[] = ['subject' => 'line', 'id' => $m['line_id'], 'record' => $record, 'provider' => 'bnf', 'basis' => $m['basis'],
                        'score' => $m['score'], 'reasons' => $m['reasons'], 'origin_url' => $record->url, 'fetched_at' => $fetchedAt];
                }
            }
        }

        return ['candidates' => $candidates, 'failed' => $failed, 'errors' => $errors, 'judged' => $judged,
            'stats' => ['lines' => count($lines), 'queries' => $queries, 'truncated' => $truncated, 'records' => count($records), 'accepted' => count($candidates)]];
    }

    /**
     * Notices de la BnF rattachées aux gares (« la gare de X » dans le titre).
     *
     * @param  list<int>  $stationIds
     * @param  (callable(int): void)|null  $onStation
     * @return array{candidates: list<array<string, mixed>>, failed: list<int>, errors: list<string>, stats: array<string, int>}
     */
    public function bnfStations(CatalogueSruClient $client, StationNames $stations, array $stationIds, ?callable $onStation = null): array
    {
        $parse = (new BnfBookParser)->parse(...);
        $errors = [];
        $failed = [];
        $queries = 0;
        $candidates = [];
        $seen = [];
        foreach ($stationIds as $id) {
            $term = $stations->queryTerm($id);
            if ($term !== null) {
                $queries++;
                try {
                    $result = $client->searchWith(self::cqlStation($term), $parse);
                    foreach ($result['records'] as $record) {
                        foreach ($this->matcher->bnfStations($record, $stations) as $sid) {
                            if ($sid === $id && ! isset($seen[$sid][$record->dedupKey()])) {
                                $seen[$sid][$record->dedupKey()] = true;
                                $candidates[] = ['subject' => 'station', 'id' => $sid, 'record' => $record, 'provider' => 'bnf', 'basis' => BibliographyMatcher::BASIS_TITLE,
                                    'score' => null, 'reasons' => [['code' => 'station_name', 'points' => 0]], 'origin_url' => $record->url, 'fetched_at' => $result['fetched_at']];
                            }
                        }
                    }
                } catch (RuntimeException $e) {
                    $errors[] = "station {$id} : {$e->getMessage()}";
                    $failed[] = $id;
                }
            }
            if ($onStation !== null) {
                $onStation($id);
            }
        }

        return ['candidates' => $candidates, 'failed' => $failed, 'errors' => $errors,
            'stats' => ['stations' => count($stationIds), 'queries' => $queries, 'accepted' => count($candidates)]];
    }

    /**
     * Références des articles de Wikipédia des lignes et des gares.
     *
     * @param  array<int, list<string>>  $lineTitles  titres d'article par ligne
     * @param  array<int, list<string>>  $stationTitles  titres d'article par gare
     * @return array{candidates: list<array<string, mixed>>, uncached: int, fetched: int, articles: int, references: int, read: array{lines: list<int>, stations: list<int>}}
     */
    public function wikipedia(StationArticleCache $cache, LinePlaceIndex $index, LineAliases $aliases, StationNames $stations, array $lineTitles, array $stationTitles, bool $fromCache, bool $refresh): array
    {
        $titles = [];
        foreach ([...array_values($lineTitles), ...array_values($stationTitles)] as $list) {
            array_push($titles, ...$list);
        }
        $res = $cache->pages(array_values(array_unique($titles)), $fromCache, $refresh);
        $extractor = new WikitextReferences;
        $candidates = [];
        $articles = 0;
        $references = 0;
        $parsed = [];
        // Sujets dont au moins un article a été lu : seuls leurs liens de Wikipédia sont remplacés (un article absent du cache ne retire rien).
        $read = ['line' => [], 'station' => []];
        foreach ([['line', $lineTitles], ['station', $stationTitles]] as [$subject, $bySubject]) {
            foreach ($bySubject as $id => $list) {
                foreach ($list as $title) {
                    $page = $res['pages'][$title] ?? null;
                    if ($page === null) {
                        continue;
                    }
                    $origin = WikimediaClient::permalink($page['title'], $page['revid']);
                    $parsed[$page['title']] ??= ['refs' => $extractor->extract($page['content'], $origin), 'origin' => $origin];
                    $articles++;
                    $read[$subject][$id] = $id;
                    foreach ($parsed[$page['title']]['refs'] as ['record' => $record, 'where' => $where]) {
                        $references++;
                        $match = $subject === 'line'
                            ? (($line = $index->line($id)) === null ? null : $this->matcher->articleLine($record, $where, $line, $index, $aliases->for($id)))
                            : $this->matcher->articleStation($record, $where, $id, $stations);
                        if ($match !== null) {
                            $candidates[] = ['subject' => $subject, 'id' => $id, 'record' => $record, 'provider' => 'wikipedia', 'basis' => $match['basis'],
                                'score' => null, 'reasons' => $match['reasons'], 'origin_url' => $parsed[$page['title']]['origin'], 'fetched_at' => null];
                        }
                    }
                }
            }
        }

        return ['candidates' => $candidates, 'uncached' => $res['uncached'], 'fetched' => $res['fetched'], 'articles' => $articles, 'references' => $references,
            'read' => ['lines' => array_values($read['line']), 'stations' => array_values($read['station'])]];
    }

    /**
     * Œuvres que Wikidata rattache aux éléments.
     *
     * @param  array<string, array{subject: string, id: int}>  $subjects  QID → ligne ou gare
     * @return array{candidates: list<array<string, mixed>>, error: ?string, stats: array<string, int>}
     */
    public function wikidata(WikidataBooks $client, array $subjects): array
    {
        try {
            $byQid = $client->forItems(array_keys($subjects));
        } catch (RuntimeException $e) {
            return ['candidates' => [], 'error' => $e->getMessage(), 'stats' => ['items' => count($subjects), 'works' => 0, 'accepted' => 0]];
        }
        $candidates = [];
        $works = 0;
        foreach ($byQid as $qid => $records) {
            foreach ($records as $record) {
                $works++;
                $s = $subjects[$qid];
                $candidates[] = ['subject' => $s['subject'], 'id' => $s['id'], 'record' => $record, 'provider' => 'wikidata', 'basis' => BibliographyMatcher::BASIS_WIKIDATA,
                    'score' => null, 'reasons' => [['code' => 'wikidata', 'points' => 0]], 'origin_url' => $record->url, 'fetched_at' => null];
            }
        }

        return ['candidates' => $candidates, 'error' => null, 'stats' => ['items' => count($subjects), 'works' => $works, 'accepted' => count($candidates)]];
    }

    /**
     * Les références de Wikipédia et de Wikidata qui ont un ISBN sont cherchées dans le catalogue de la BnF (une requête
     * pour quinze ISBN) : la notice trouvée, si son titre est celui de la référence (BibliographyMatcher::titlesAgree : un ISBN mal recopié ne change pas d'ouvrage),
     * remplace les données de la référence (titre, auteurs, éditeur, année de l'édition
     * de la BnF) et devient son lien, sous le même ISBN. Le rattachement, lui, garde la source qui l'a fait (article, Wikidata)
     * et son lien d'origine. Une requête qui échoue laisse les références telles quelles.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return array{candidates: list<array<string, mixed>>, isbns: int, found: int, errors: list<string>}
     */
    public function enrichWithBnf(CatalogueSruClient $client, array $candidates): array
    {
        $isbns = [];
        foreach ($candidates as $c) {
            /** @var BibliographyRecord $record */
            $record = $c['record'];
            if ($record->isbn !== null && $record->provider !== 'bnf') {
                $isbns[$record->isbn] = true;
            }
        }
        $notices = [];
        $errors = [];
        $parse = (new BnfBookParser)->parse(...);
        foreach (array_chunk(array_keys($isbns), 15) as $chunk) {
            $words = [];
            foreach ($chunk as $isbn) {
                $words[] = $isbn;
                if (($ten = Isbn::to10($isbn)) !== null) {
                    $words[] = $ten;
                }
            }
            try {
                $result = $client->searchWith(sprintf('bib.isbn any "%s"', implode(' ', $words)), $parse);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();

                continue;
            }
            foreach ($result['records'] as $notice) {
                foreach ($notice->isbns as $isbn) {
                    $notices[$isbn] ??= $notice;
                }
            }
        }
        $found = 0;
        foreach ($candidates as $i => $c) {
            $record = $c['record'];
            if ($record->isbn !== null && $record->provider !== 'bnf' && isset($notices[$record->isbn]) && BibliographyMatcher::titlesAgree($record->title, $notices[$record->isbn]->title)) {
                $candidates[$i]['record'] = $record->enrichedBy($notices[$record->isbn]);
                $found++;
            }
        }

        return ['candidates' => $candidates, 'isbns' => count($isbns), 'found' => $found, 'errors' => $errors];
    }

    /**
     * Enregistre les candidats : pour chaque source traitée, les liens des lignes et des gares traitées par cette
     * source sont remplacés par les nouveaux ; une référence qui n'a plus aucun lien disparaît.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, array{lines: list<int>, stations: list<int>}>  $processed  par source (bnf, wikipedia, wikidata) :
     *                                                                                  lignes et gares traitées, hors celles en erreur
     * @return array{entries: int, links: int, entries_before: int, entries_after: int, links_after: int}
     */
    public function store(array $candidates, array $processed): array
    {
        $candidates = $this->mergeSameWork($candidates);

        return DB::transaction(function () use ($candidates, $processed): array {
            $before = BibliographyEntry::query()->count();
            foreach ($processed as $provider => $subjects) {
                foreach (array_chunk($subjects['lines'], 1000) as $chunk) {
                    BibliographyLink::query()->where('provider', $provider)->whereIn('line_id', $chunk)->delete();
                }
                foreach (array_chunk($subjects['stations'], 1000) as $chunk) {
                    BibliographyLink::query()->where('provider', $provider)->whereIn('station_id', $chunk)->delete();
                }
            }
            $entries = [];
            $links = 0;
            $now = now();
            $sets = array_map(fn (array $s): array => ['line' => array_flip($s['lines']), 'station' => array_flip($s['stations'])], $processed);
            foreach ($candidates as $c) {
                $isLine = $c['subject'] === 'line';
                if (! isset($sets[$c['provider']][$c['subject']][$c['id']])) {
                    continue;
                }
                /** @var BibliographyRecord $record */
                $record = $c['record'];
                $entry = $entries[$record->dedupKey()] ??= $this->entry($record, $c['fetched_at'] ?? null);
                $column = $isLine ? 'line_id' : 'station_id';
                if (BibliographyLink::query()->where($column, $c['id'])->where('entry_id', $entry->id)->exists()) {
                    continue;
                }
                (new BibliographyLink)->forceFill([
                    'entry_id' => $entry->id, $column => $c['id'], 'provider' => $c['provider'], 'basis' => $c['basis'],
                    'score' => $c['score'], 'reasons' => $c['reasons'], 'origin_url' => $c['origin_url'], 'created_at' => $now, 'updated_at' => $now,
                ])->save();
                $links++;
            }
            BibliographyEntry::query()->whereDoesntHave('links')->delete();

            return ['entries' => count($entries), 'links' => $links, 'entries_before' => $before,
                'entries_after' => BibliographyEntry::query()->count(), 'links_after' => BibliographyLink::query()->count()];
        });
    }

    /**
     * Une même œuvre citée par deux sources, l'une avec son ISBN et l'autre sans (ou sous un autre nom d'auteur), n'est qu'une
     * référence : à titre et année égaux, tous les candidats prennent l'enregistrement le plus complet (ISBN, puis notice de la BnF).
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    public function mergeSameWork(array $candidates): array
    {
        $best = [];
        $rank = fn (BibliographyRecord $r): int => ($r->isbn !== null ? 2 : 0) + ($r->provider === 'bnf' ? 1 : 0);
        foreach ($candidates as $c) {
            $r = $c['record'];
            if ($r->year === null) {
                continue;
            }
            $key = BibliographyRecord::norm($r->title).'|'.$r->year;
            if (! isset($best[$key]) || $rank($r) > $rank($best[$key])) {
                $best[$key] = $r;
            }
        }
        foreach ($candidates as $i => $c) {
            $r = $c['record'];
            if ($r->year !== null) {
                $candidates[$i]['record'] = $best[BibliographyRecord::norm($r->title).'|'.$r->year];
            }
        }

        return $candidates;
    }

    /** Référence existante complétée, ou créée ; la notice de la BnF fait foi (titre, auteurs, éditeur, lien). */
    private function entry(BibliographyRecord $record, ?int $fetchedAt): BibliographyEntry
    {
        $entry = BibliographyEntry::query()->where('dedup_key', $record->dedupKey())->first() ?? new BibliographyEntry(['dedup_key' => $record->dedupKey()]);
        $bnf = $record->provider === 'bnf';
        if (! $entry->exists || ($bnf && $entry->provider !== 'bnf')) {
            $entry->forceFill([
                'kind' => $record->kind, 'title' => $record->title, 'authors' => $record->authors, 'provider' => $record->provider,
                'url' => $record->url, 'bnf_ark' => $record->bnfArk, 'fetched_at' => $fetchedAt === null ? now() : now()->setTimestamp($fetchedAt),
                'publisher' => $record->publisher, 'place' => $record->place, 'year' => $record->year, 'isbn' => $record->isbn, 'journal' => $record->journal,
            ]);
        } else {
            foreach (['publisher' => $record->publisher, 'place' => $record->place, 'year' => $record->year, 'isbn' => $record->isbn, 'journal' => $record->journal] as $field => $value) {
                $entry->{$field} ??= $value;
            }
            if (($entry->authors ?? []) === [] && $record->authors !== []) {
                $entry->authors = $record->authors;
            }
        }
        $entry->save();

        return $entry;
    }

    /**
     * Titres d'articles Wikipédia des lignes (titre de la ligne et des éléments Wikidata rattachés) et des gares.
     *
     * @return array{lines: array<int, list<string>>, stations: array<int, list<string>>}
     */
    public static function articleTitles(): array
    {
        $lines = [];
        foreach (DB::select('SELECT id, wikipedia_title AS title FROM lines WHERE wikipedia_title IS NOT NULL
            UNION SELECT line_id, frwiki_title FROM wikidata_lines WHERE line_id IS NOT NULL AND frwiki_title IS NOT NULL') as $r) {
            $lines[(int) $r->id][] = (string) $r->title;
        }
        $stations = [];
        foreach (DB::select("SELECT DISTINCT ss.station_id AS id, ss.properties->>'frwiki' AS title FROM station_sources ss
            WHERE ss.station_id IS NOT NULL AND ss.source = 'wikidata' AND ss.properties->>'frwiki' IS NOT NULL
            UNION SELECT ss.station_id, SUBSTRING(ss.properties->>'wikipedia' FROM 4) FROM station_sources ss
            WHERE ss.station_id IS NOT NULL AND ss.source = 'osm' AND ss.properties->>'wikipedia' LIKE 'fr:%'") as $r) {
            $stations[(int) $r->id][] = (string) $r->title;
        }

        return ['lines' => array_map(fn (array $t): array => array_values(array_unique($t)), $lines),
            'stations' => array_map(fn (array $t): array => array_values(array_unique($t)), $stations)];
    }

    /**
     * QID Wikidata des lignes (éléments rattachés) et des gares.
     *
     * @return array<string, array{subject: string, id: int}>
     */
    public static function wikidataSubjects(): array
    {
        $out = [];
        foreach (DB::select('SELECT qid, line_id FROM wikidata_lines WHERE line_id IS NOT NULL') as $r) {
            $out[(string) $r->qid] = ['subject' => 'line', 'id' => (int) $r->line_id];
        }
        foreach (DB::select('SELECT id, wikidata_id FROM stations WHERE wikidata_id IS NOT NULL') as $r) {
            $out[(string) $r->wikidata_id] ??= ['subject' => 'station', 'id' => (int) $r->id];
        }

        return $out;
    }
}
