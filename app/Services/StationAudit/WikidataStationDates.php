<?php

namespace App\Services\StationAudit;

use App\Services\Materials\SourceCache;
use App\Services\WikimediaClient;
use Illuminate\Support\Sleep;

/**
 * Dates de mise en service (P1619) et de fermeture (P3999) des gares dans Wikidata (CC0), avec
 * leur précision, leur rang, leurs qualificatifs et leurs références.
 *
 * Politesse : wbgetentities par lots de 50 éléments, réponses réduites aux déclarations utiles
 * (props=claims), une seconde de pause entre deux lots, User-Agent générique de VFF, maxlag et
 * Retry-After respectés (WikimediaClient). Cache local (SourceCache) dans
 * storage/app/data/gares-audit/ (dossier marqué `.nobackup`) : une relance ne redemande que les
 * éléments absents, `--from-cache` ne fait aucun appel.
 */
class WikidataStationDates
{
    public const API = 'https://www.wikidata.org/w/api.php';

    /** Propriétés lues : mise en service, fermeture. */
    public const PROPERTIES = ['P1619' => 'opening', 'P3999' => 'closure'];

    /** Qualificatif « s'applique à » (P518) : transport de voyageurs, trafic voyageurs ; fret. */
    private const PASSENGER = ['Q1433891', 'Q2072431', 'Q334166'];

    private const FREIGHT = ['Q2234623', 'Q1662079'];

    /** Réouverture (P518 ou P1480 vaut « réouverture »). */
    private const REOPENING = ['Q16571590'];

    /** Précision Wikidata → précision VFF. */
    private const PRECISION = [11 => 'day', 10 => 'month', 9 => 'year', 8 => 'decade', 7 => 'century'];

    private const BATCH = 50;

    private readonly SourceCache $cache;

    public function __construct(
        private readonly WikimediaClient $client,
        ?SourceCache $cache = null,
        private readonly int $pauseMs = 1000,
    ) {
        $this->cache = $cache ?? self::defaultCache();
    }

    public static function defaultCache(): SourceCache
    {
        return new SourceCache(storage_path('app/data/gares-audit'));
    }

    /**
     * Déclarations P1619 et P3999 des éléments, lues dans le cache ou demandées à Wikidata.
     *
     * @param  list<string>  $qids
     * @return array<string, array<string, mixed>|null> par identifiant (null : élément absent)
     */
    public function claims(array $qids, bool $offline = false, bool $refresh = false): array
    {
        if ($offline) {
            return array_intersect_key($this->cache->load('station-dates'), array_flip($qids));
        }

        return $this->cache->fill('station-dates', $qids, self::BATCH, function (array $chunk): array {
            Sleep::usleep($this->pauseMs * 1000);
            $json = $this->client->api([
                'action' => 'wbgetentities', 'ids' => implode('|', $chunk), 'props' => 'claims',
            ], self::API);
            $out = [];
            foreach ($json['entities'] ?? [] as $qid => $entity) {
                if (! isset($entity['missing'])) {
                    $out[(string) $qid] = array_intersect_key($entity['claims'] ?? [], self::PROPERTIES)
                        + ['_read_on' => now()->toDateString()];
                }
            }

            return $out;
        }, $refresh);
    }

    /**
     * Dates candidates d'un élément (déclarations dépréciées écartées).
     *
     * @param  array<string, mixed>  $claims
     * @return list<array{kind: string, date: string, precision: string, rank: string, source_ref: string,
     *     source_url: string, qualifiers: array<string, mixed>, references: list<array<string, string>>}>
     */
    public static function parse(string $qid, array $claims): array
    {
        $out = [];
        foreach (self::PROPERTIES as $property => $baseKind) {
            foreach ((array) ($claims[$property] ?? []) as $statement) {
                if (! is_array($statement) || ($statement['rank'] ?? 'normal') === 'deprecated') {
                    continue;
                }
                $time = self::time($statement['mainsnak']['datavalue']['value'] ?? null);
                if ($time === null) {
                    continue;
                }
                $qualifiers = self::qualifiers((array) ($statement['qualifiers'] ?? []));
                $out[] = [
                    'kind' => self::kind($baseKind, $qualifiers),
                    'date' => $time[0],
                    'precision' => $time[1],
                    'rank' => (string) ($statement['rank'] ?? 'normal'),
                    'source_ref' => $qid.'#'.$property.'#'.substr((string) ($statement['id'] ?? md5((string) json_encode($statement))), -36),
                    'source_url' => "https://www.wikidata.org/wiki/{$qid}#{$property}",
                    'qualifiers' => $qualifiers,
                    'references' => self::references((array) ($statement['references'] ?? [])),
                ];
            }
        }

        return $out;
    }

    /**
     * Valeur temporelle Wikidata → [date ISO, précision]. Avant le XVIIIe siècle, calendrier
     * julien ou précision plus grossière que le siècle : écartée (aucune gare).
     *
     * @return array{0: string, 1: string}|null
     */
    public static function time(mixed $value): ?array
    {
        if (! is_array($value) || ! isset($value['time'], $value['precision'])) {
            return null;
        }
        $precision = self::PRECISION[(int) $value['precision']] ?? null;
        if ($precision === null || ! preg_match('/^\+(\d{4})-(\d{2})-(\d{2})T/', (string) $value['time'], $m) || (int) $m[1] < 1700) {
            return null;
        }
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        $date = match ($precision) {
            'day' => sprintf('%04d-%02d-%02d', $y, max(1, $mo), max(1, $d)),
            'month' => sprintf('%04d-%02d-01', $y, max(1, $mo)),
            'year' => sprintf('%04d-01-01', $y),
            'decade' => sprintf('%04d-01-01', intdiv($y, 10) * 10),
            // Siècle à la manière de Wikidata (année / 100 arrondie au-dessus : +1850 et +1900 sont le XIXe) :
            // rangé à sa première année, 1801-01-01.
            'century' => sprintf('%04d-01-01', intdiv($y - 1, 100) * 100 + 1),
        };
        if ($precision === 'day' && ! checkdate(max(1, $mo), max(1, $d), $y)) {
            return null;
        }

        return [$date, $precision];
    }

    /**
     * Qualificatifs utiles : s'applique à (P518), circonstances (P1480, ex. « vers »), au plus tôt
     * (P1319), au plus tard (P1326), sujet de la déclaration (P805).
     *
     * @param  array<string, mixed>  $qualifiers
     * @return array<string, mixed>
     */
    private static function qualifiers(array $qualifiers): array
    {
        $ids = fn (string $p): array => array_values(array_filter(array_map(
            fn ($q) => $q['datavalue']['value']['id'] ?? null, (array) ($qualifiers[$p] ?? []))));
        $times = fn (string $p): ?string => ($t = self::time($qualifiers[$p][0]['datavalue']['value'] ?? null)) ? $t[0] : null;

        return array_filter([
            'applies_to' => $ids('P518'),
            'circumstances' => $ids('P1480'),
            'earliest' => $times('P1319'),
            'latest' => $times('P1326'),
            'subject' => $ids('P805'),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** @param array<string, mixed> $qualifiers */
    private static function kind(string $base, array $qualifiers): string
    {
        $parts = [...($qualifiers['applies_to'] ?? []), ...($qualifiers['circumstances'] ?? [])];

        return match (true) {
            $base === 'opening' && array_intersect($parts, self::REOPENING) !== [] => 'reopening',
            $base === 'closure' && array_intersect($parts, self::PASSENGER) !== [] => 'closure_passenger',
            $base === 'closure' && array_intersect($parts, self::FREIGHT) !== [] => 'closure_freight',
            default => $base,
        };
    }

    /**
     * Références d'une déclaration : affirmé dans (P248), adresse (P854), importé de (P143).
     *
     * @param  list<array<string, mixed>>|array<string, mixed>  $references
     * @return list<array<string, string>>
     */
    private static function references(array $references): array
    {
        $out = [];
        foreach ($references as $ref) {
            $snaks = (array) ($ref['snaks'] ?? []);
            $item = array_filter([
                'stated_in' => $snaks['P248'][0]['datavalue']['value']['id'] ?? null,
                'url' => $snaks['P854'][0]['datavalue']['value'] ?? null,
                'imported_from' => $snaks['P143'][0]['datavalue']['value']['id'] ?? null,
            ], fn ($v) => is_string($v) && $v !== '');
            if ($item !== []) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
