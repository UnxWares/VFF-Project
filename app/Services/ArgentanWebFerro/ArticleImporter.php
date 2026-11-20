<?php

namespace App\Services\ArgentanWebFerro;

use App\Enums\ArgentanFactKind;
use App\Enums\FactDatePrecision;
use App\Enums\LineEventType;
use App\Enums\StationType;
use App\Services\PlaceNames;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Articles d'Argentan Web Ferroviaire → tables de source argentanwebferro_articles et
 * argentanwebferro_facts (jamais les tables du moteur).
 *
 * Pour chaque article de ligne (déclaré dans la curation) : faits extraits, corrections et
 * ajouts relus, ligne VFF visée, comparaison avec VFF. Les autres articles sont inventoriés
 * (titre, lien, dates) sans fait. Rejouable : upsert par empreinte, puis retrait des seuls faits
 * de cette source qui ne sont plus produits.
 */
final class ArticleImporter
{
    /** @var list<string> */
    public array $unknownRefs = [];

    /** @var list<string> */
    public array $orphanCorrections = [];

    public function __construct(
        private readonly ArgentanClient $client,
        private readonly Curation $curation,
        private readonly VffReader $reader,
    ) {}

    /**
     * @return array{articles: list<array<string, mixed>>, facts: list<array<string, mixed>>}
     */
    public function collect(): array
    {
        $refIds = $this->reader->lineIds($this->curation->lineRefs());
        $this->unknownRefs = array_values(array_diff($this->curation->lineRefs(), array_keys($refIds)));
        $allLineIds = array_values(array_unique($refIds));
        $stations = $this->reader->stationsByLine($allLineIds);
        $comparison = new VffComparison($this->reader->eventsByLine($allLineIds), $stations);
        $resolver = new LineResolver(array_map(fn (array $list) => array_column($list, 'name'), $stations));
        $places = $this->reader->placeIndex();
        $extractor = new ArticleFactExtractor(fn (string $name): bool => isset($places[implode(' ', PlaceNames::tokens($name))]));

        $categories = $this->client->categories();
        $items = [
            ...array_map(fn (array $p) => ['page', $p], $this->client->wordpress('pages')),
            ...array_map(fn (array $p) => ['post', $p], $this->client->wordpress('posts')),
        ];

        $articles = [];
        $facts = [];
        $usedCorrections = [];
        foreach ($items as [$type, $item]) {
            $slug = (string) $item['slug'];
            $curated = $this->curation->articles[$slug] ?? null;
            $lineIds = $curated === null ? [] : array_values(array_filter(array_map(fn (string $r) => $refIds[$r] ?? null, $curated['lines'])));
            $html = (string) ($item['content']['rendered'] ?? '');
            $article = [
                'wp_id' => (int) $item['id'], 'wp_type' => $type, 'slug' => $slug, 'url' => (string) $item['link'],
                'title' => self::plain((string) ($item['title']['rendered'] ?? '')),
                'published_at' => self::time($item['date'] ?? null), 'modified_at' => self::time($item['modified'] ?? null),
                'categories' => json_encode(array_values(array_map(fn ($id) => $categories[(int) $id] ?? (string) $id, $item['categories'] ?? [])), JSON_UNESCAPED_UNICODE),
                'is_line_article' => $curated !== null, 'line_ids' => json_encode($lineIds),
                'line_match' => $curated === null ? 'none' : ($lineIds === [] ? 'doubtful' : 'curated'),
                'line_match_note' => $curated['note'] ?? null, 'content_sha1' => sha1($html),
            ];
            $articles[] = $article;
            if ($curated === null) {
                continue;
            }

            $produced = [];
            foreach ($extractor->extract($html) as $fact) {
                $fingerprint = $fact->fingerprint($type, $article['wp_id']);
                $correction = $this->curation->facts[$fingerprint] ?? null;
                $review = 'auto';
                $note = null;
                if ($correction !== null) {
                    $usedCorrections[$fingerprint] = true;
                    [$fact, $review, $note] = $this->correct($fact, $correction);
                }
                $produced[] = [$fingerprint, $fact, $review, $note, $correction['line'] ?? null];
            }
            foreach ($this->curation->added[$slug] ?? [] as $added) {
                $fact = self::fromArray($added);
                $produced[] = [$fact->fingerprint($type, $article['wp_id']), $fact, 'curated', $added['note'] ?? 'ajout relu sur l\'article', $added['line'] ?? null];
            }

            foreach ($produced as [$fingerprint, $fact, $review, $note, $forcedLine]) {
                $resolution = $forcedLine !== null && isset($refIds[$forcedLine])
                    ? ['line_id' => $refIds[$forcedLine], 'match' => 'curated', 'note' => null]
                    : $resolver->resolve($fact, $lineIds);
                $compared = $review === 'rejected'
                    ? ['status' => null, 'detail' => null]
                    : $comparison->compare($fact, $resolution['line_id'] !== null ? [$resolution['line_id']] : $lineIds);
                $facts[] = [
                    'slug' => $slug, 'wp_type' => $type, 'wp_id' => $article['wp_id'], 'fingerprint' => $fingerprint,
                    'kind' => $fact->kind->value, 'line_id' => $resolution['line_id'], 'line_match' => $resolution['match'],
                    'event_type' => $fact->type?->value, 'event_date' => $fact->date(), 'year' => $fact->year, 'year_to' => $fact->yearTo,
                    'date_precision' => $fact->precision?->value,
                    'section_from' => self::cut($fact->from, 160), 'section_to' => self::cut($fact->to, 160),
                    'stop_name' => self::cut($fact->stopName, 160), 'stop_nature' => $fact->stopNature?->value, 'pk_m' => $fact->pkM,
                    'operator' => self::cut($fact->operator, 200), 'source_quote' => ArticleFactExtractor::quote($fact->quote),
                    'source_url' => $article['url'], 'article_modified_at' => $article['modified_at'],
                    'review' => $review, 'note' => self::joinNotes($note, $resolution['note']),
                    'vff_status' => $compared['status'], 'vff_detail' => $compared['detail'],
                ];
            }
        }
        $this->orphanCorrections = array_values(array_diff(array_keys($this->curation->facts), array_keys($usedCorrections)));

        return ['articles' => $articles, 'facts' => $facts];
    }

    /**
     * Écrit l'inventaire et les faits (tables de source seulement).
     *
     * @param  array{articles: list<array<string, mixed>>, facts: list<array<string, mixed>>}  $data
     * @return array{articles: int, facts: int, removed: int}
     */
    public function persist(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $now = now();
            $ids = [];
            foreach ($data['articles'] as $a) {
                DB::table('argentanwebferro_articles')->upsert([[...$a, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now]],
                    ['wp_type', 'wp_id'], [...array_keys($a), 'fetched_at', 'updated_at']);
            }
            foreach (DB::table('argentanwebferro_articles')->get(['id', 'wp_type', 'wp_id']) as $r) {
                $ids["{$r->wp_type}:{$r->wp_id}"] = (int) $r->id;
            }
            $rows = array_map(function (array $f) use ($ids, $now): array {
                $row = $f;
                $row['article_id'] = $ids["{$f['wp_type']}:{$f['wp_id']}"];
                unset($row['slug'], $row['wp_type'], $row['wp_id']);

                return [...$row, 'created_at' => $now, 'updated_at' => $now];
            }, $data['facts']);
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('argentanwebferro_facts')->upsert($chunk, ['fingerprint'],
                    array_values(array_diff(array_keys($chunk[0]), ['fingerprint', 'created_at'])));
            }
            $removed = DB::table('argentanwebferro_facts')->whereNotIn('fingerprint', array_column($rows, 'fingerprint') ?: [''])->delete();

            return ['articles' => count($data['articles']), 'facts' => count($rows), 'removed' => $removed];
        });
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{0: ExtractedFact, 1: string, 2: ?string}
     */
    private function correct(ExtractedFact $fact, array $c): array
    {
        if (isset($c['reject'])) {
            return [$fact, 'rejected', (string) $c['reject']];
        }
        $changes = array_filter([
            'type' => isset($c['type']) ? LineEventType::from($c['type']) : null,
            'from' => $c['from'] ?? null, 'to' => $c['to'] ?? null,
            'year' => $c['year'] ?? null, 'month' => $c['month'] ?? null, 'day' => $c['day'] ?? null,
            'yearTo' => $c['year_to'] ?? null,
            'precision' => isset($c['precision']) ? FactDatePrecision::from($c['precision']) : null,
            'stopNature' => isset($c['stop_nature']) ? StationType::from($c['stop_nature']) : null,
            'stopName' => $c['stop_name'] ?? null,
        ], fn ($v) => $v !== null);
        foreach (['from' => 'from', 'to' => 'to', 'month' => 'month', 'day' => 'day', 'yearTo' => 'year_to', 'year' => 'year', 'precision' => 'precision'] as $nullable => $key) {
            if (array_key_exists($key, $c) && $c[$key] === null) {
                $changes[$nullable] = null;
            }
        }

        return [$fact->with($changes), 'curated', $c['note'] ?? 'corrigé à la relecture'];
    }

    /** @param  array<string, mixed>  $a */
    public static function fromArray(array $a): ExtractedFact
    {
        return new ExtractedFact(
            kind: ArgentanFactKind::from($a['kind']),
            quote: (string) $a['quote'],
            type: isset($a['type']) ? LineEventType::from($a['type']) : null,
            year: $a['year'] ?? null, month: $a['month'] ?? null, day: $a['day'] ?? null, yearTo: $a['year_to'] ?? null,
            precision: isset($a['precision']) ? FactDatePrecision::from($a['precision']) : null,
            from: $a['from'] ?? null, to: $a['to'] ?? null,
            stopName: $a['stop_name'] ?? null,
            stopNature: isset($a['stop_nature']) ? StationType::from($a['stop_nature']) : null,
            pkM: $a['pk_m'] ?? null, operator: $a['operator'] ?? null,
        );
    }

    private static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\x{2013}\x{2014}]/u', '-', $text));
    }

    private static function time(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->toDateTimeString() : null;
    }

    private static function cut(?string $value, int $max): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $max);
    }

    private static function joinNotes(?string ...$notes): ?string
    {
        $notes = array_filter($notes, fn (?string $n) => $n !== null && $n !== '');

        return $notes === [] ? null : implode(' ; ', $notes);
    }
}
