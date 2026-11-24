<?php

namespace App\Services\TrackLayout;

use App\Models\Line;
use App\Services\LineStations;

/**
 * Schéma des voies d'une ligne (`GET /api/v1/lines/{line}/track-layout`, GraphQL `Line.track_layout`, outil MCP
 * `line_track_layout` ; contrat agents-commun/contrat-schema-voies.md), à la manière d'un « routebook » : voies
 * numérotées, voies d'évitement et de service, aiguillages, traversées, signaux, points d'exploitation, bande des
 * vitesses et des déclivités, chaque objet avec sa provenance. Réseau d'aujourd'hui (ère courante) seulement.
 *
 * SOURCES, par ordre de préférence (TrackLayoutSource, clé = `source` des objets) :
 * 1. `rinf` : RINF de l'ERA (RinfTrackLayoutSource, tables rinf_*, vff:import-rinf) ;
 * 2. `osm` : OpenStreetMap (géométrie des voies et aiguillages là où le RINF manque), branché par l'agent
 *    « osm-voies » : une classe TrackLayoutSource de clé `osm`, étiquetée TAG dans le conteneur
 *    (`$this->app->tag([OsmTrackLayoutSource::class], TrackLayout::TAG)` dans AppServiceProvider) ;
 * 3. `vff` : données déjà dans VFF (PK sourcés des gares, vitesses de SNCF Réseau, déclivités du profil en long,
 *    passages à niveau de SNCF Réseau), VffTrackLayoutSource.
 *
 * RÈGLES DE FUSION (une source plus loin dans l'ordre ne fait que compléter) :
 * - voies : gardées seulement hors des portions `tracks` couvertes par une source préférée ;
 * - aiguillages, signaux, traversées : gardés hors des portions couvertes de leur famille par une source préférée,
 *   et sauf doublon (même famille, même référence ou référence absente, à DEDUPE_M près) ;
 * - points d'exploitation : gardés sauf s'ils doublent un point déjà retenu (même gare de VFF, ou à OP_MERGE_M près) ;
 * - vitesses et déclivités : rognées aux trous de la bande déjà remplie.
 * Puis les rangées des voies (LaneAssigner), le tri par PK, la couverture (`coverage`) et les sources utilisées.
 *
 * Paramètres : portion [from, to] en mètres (objets qui la touchent ; bornes nulles : toute la ligne), familles
 * à servir (`kinds`). Une ligne sans donnée rend 200, des listes vides et la couverture (jamais une erreur).
 */
final class TrackLayout
{
    /** Étiquette du conteneur des sources supplémentaires (OSM). */
    public const TAG = 'vff.track-layout.sources';

    /** Ordre de préférence des sources, par clé ; une clé inconnue passe après. */
    public const PRIORITY = ['rinf', 'osm', 'vff'];

    /** Familles que l'on peut demander (`kinds`). */
    public const KINDS = ['operational_points', 'tracks', 'switches', 'signals', 'crossings', 'speeds', 'gradients'];

    /** Familles dont les trous sont listés dans `coverage.missing`. */
    public const COVERAGE_FAMILIES = ['tracks', 'switches', 'signals', 'speeds', 'gradients'];

    /** Deux objets ponctuels d'une même famille à moins de cette distance (m) et de même référence : doublon. */
    public const DEDUPE_M = 30;

    /** Deux points d'exploitation à moins de cette distance (m) : le second double le premier. */
    public const OP_MERGE_M = 150;

    /** Version de la forme de la réponse (à changer avec elle). */
    public const FORMAT = 1;

    /** @var list<TrackLayoutSource> */
    private array $sources;

    /** @param  iterable<TrackLayoutSource>  $sources */
    public function __construct(iterable $sources)
    {
        $list = [];
        foreach ($sources as $source) {
            $list[$source->key()] = $source;
        }
        uksort($list, fn (string $a, string $b): int => self::rank($a) <=> self::rank($b) ?: strcmp($a, $b));
        $this->sources = array_values($list);
    }

    /** @return list<string> clés des sources branchées, dans l'ordre de préférence */
    public function sourceKeys(): array
    {
        return array_map(fn (TrackLayoutSource $s): string => $s->key(), $this->sources);
    }

    /**
     * Schéma de la ligne, au format du contrat.
     *
     * @param  list<string>|null  $kinds  familles à servir (null : toutes)
     * @return array<string, mixed>
     */
    public function forLine(Line $line, ?int $fromM = null, ?int $toM = null, ?array $kinds = null): array
    {
        if ($fromM !== null && $toM !== null && $fromM > $toM) {
            [$fromM, $toM] = [$toM, $fromM];
        }
        $kinds = $kinds === null ? self::KINDS : array_values(array_intersect(self::KINDS, $kinds));

        $parts = [];
        foreach ($this->sources as $source) {
            $part = $source->part($line, $fromM, $toM);
            if (! $part->isEmpty()) {
                $parts[$source->key()] = $part;
            }
        }

        $merged = array_fill_keys(TrackLayoutPart::FAMILIES, []);
        $covered = array_fill_keys(TrackLayoutPart::FAMILIES, []);
        $coveredBy = [];
        foreach ($parts as $key => $part) {
            foreach (TrackLayoutPart::FAMILIES as $family) {
                $items = $part->family($family);
                $merged[$family] = [...$merged[$family], ...match ($family) {
                    'operational_points' => self::newPoints($merged[$family], $items),
                    'tracks' => array_values(array_filter($items, fn (array $t): bool => ! self::overlapsAny($covered['tracks'], (int) $t['pk_from_m'], (int) $t['pk_to_m']))),
                    'switches', 'signals', 'crossings' => self::newPunctual($merged[$family], $covered[$family], $items),
                    default => self::clipRanges($items, $covered[$family]),
                }];
                $own = $part->covered[$family] ?? [];
                if (in_array($family, ['speeds', 'gradients'], true)) {
                    $own = [...$own, ...array_map(fn (array $r): array => [(int) $r['pk_from_m'], (int) $r['pk_to_m']], $items)];
                }
                $covered[$family] = self::union([...$covered[$family], ...$own]);
                $coveredBy[$key][$family] = self::union($own);
            }
        }

        $extent = self::extent($merged, $covered);
        $start = $fromM !== null ? max($fromM, $extent[0] ?? $fromM) : $extent[0];
        $end = $toM !== null ? min($toM, $extent[1] ?? $toM) : $extent[1];
        if ($start !== null && $end !== null && $start > $end) {
            $start = $end = null;
        }

        $in = fn (int $a, int $b): bool => $start === null || $end === null || ($a <= $end && $b >= $start);
        $points = array_values(array_filter($merged['operational_points'], fn (array $o): bool => $o['pk_m'] !== null && $in((int) $o['pk_m'], (int) $o['pk_m'])));
        $tracks = LaneAssigner::assign(array_values(array_filter($merged['tracks'], fn (array $t): bool => $in((int) $t['pk_from_m'], (int) $t['pk_to_m']))));
        $pointFilter = fn (array $list): array => self::sortByPk(array_values(array_filter($list, fn (array $o): bool => $in((int) $o['pk_m'], (int) $o['pk_m']))));
        $rangeFilter = fn (array $list): array => self::sortRanges(self::clipTo(array_values(array_filter($list, fn (array $r): bool => $in((int) $r['pk_from_m'], (int) $r['pk_to_m']))), $start, $end));

        $used = array_values(array_filter(array_map(fn (TrackLayoutPart $p): ?array => $p->source, $parts)));

        $out = [
            'line' => ['code' => $line->code, 'slug' => $line->slug, 'name' => $line->name],
            'pk' => ['start_m' => $start, 'end_m' => $end,
                'start_label' => LineStations::pkLabel($start), 'end_label' => LineStations::pkLabel($end)],
            'sources' => $used,
            'operational_points' => self::sortByPk($points),
            'tracks' => self::sortTracks($tracks),
            'switches' => $pointFilter($merged['switches']),
            'signals' => $pointFilter($merged['signals']),
            'crossings' => $pointFilter($merged['crossings']),
            'strip' => ['speeds' => $rangeFilter($merged['speeds']), 'gradients' => $rangeFilter($merged['gradients'])],
            'coverage' => self::coverage($start, $end, $covered, $coveredBy),
        ];
        foreach (['operational_points', 'tracks', 'switches', 'signals', 'crossings'] as $family) {
            if (! in_array($family, $kinds, true)) {
                $out[$family] = [];
            }
        }
        foreach (['speeds', 'gradients'] as $family) {
            if (! in_array($family, $kinds, true)) {
                $out['strip'][$family] = [];
            }
        }

        return $out;
    }

    /** Réponse vide conforme au contrat (ligne inconnue des sources). */
    public static function emptyFor(Line $line): array
    {
        return (new self([]))->forLine($line);
    }

    private static function rank(string $key): int
    {
        $i = array_search($key, self::PRIORITY, true);

        return $i === false ? count(self::PRIORITY) : (int) $i;
    }

    /**
     * Points d'exploitation d'une source moins préférée qui ne doublent pas un point retenu.
     *
     * @param  list<array<string, mixed>>  $kept
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function newPoints(array $kept, array $items): array
    {
        $out = [];
        foreach ($items as $o) {
            $dup = false;
            $slug = $o['station_slug'] ?? null;
            foreach ($kept as $k) {
                $sameStation = $slug !== null && $slug === ($k['station_slug'] ?? null);
                $near = $o['pk_m'] !== null && $k['pk_m'] !== null && abs((int) $o['pk_m'] - (int) $k['pk_m']) <= self::OP_MERGE_M;
                if ($sameStation || $near) {
                    $dup = true;
                    break;
                }
            }
            if (! $dup) {
                $out[] = $o;
            }
        }

        return $out;
    }

    /**
     * Objets ponctuels (aiguillages, signaux, traversées) d'une source moins préférée : hors des portions déjà
     * couvertes pour leur famille et sans doublon.
     *
     * @param  list<array<string, mixed>>  $kept
     * @param  list<array{0: int, 1: int}>  $covered
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function newPunctual(array $kept, array $covered, array $items): array
    {
        $out = [];
        foreach ($items as $o) {
            $pk = (int) $o['pk_m'];
            if (self::overlapsAny($covered, $pk, $pk)) {
                continue;
            }
            foreach ([...$kept, ...$out] as $k) {
                if (abs((int) $k['pk_m'] - $pk) <= self::DEDUPE_M && (($k['ref'] ?? null) === null || ($o['ref'] ?? null) === null || $k['ref'] === $o['ref'])) {
                    continue 2;
                }
            }
            $out[] = $o;
        }

        return $out;
    }

    /**
     * Portions de bande (vitesses, déclivités) rognées aux trous de la bande déjà remplie.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<array{0: int, 1: int}>  $covered
     * @return list<array<string, mixed>>
     */
    private static function clipRanges(array $items, array $covered): array
    {
        $out = [];
        foreach ($items as $r) {
            foreach (self::subtract([(int) $r['pk_from_m'], (int) $r['pk_to_m']], $covered) as [$a, $b]) {
                if ($b > $a) {
                    $out[] = ['pk_from_m' => $a, 'pk_to_m' => $b] + $r;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $ranges
     */
    private static function overlapsAny(array $ranges, int $from, int $to): bool
    {
        foreach ($ranges as [$a, $b]) {
            if ($from < $b && $a < $to || ($from === $to && $from >= $a && $from <= $b)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Réunion de portions [début, fin].
     *
     * @param  list<array{0: int, 1: int}>  $ranges
     * @return list<array{0: int, 1: int}>
     */
    public static function union(array $ranges): array
    {
        $ranges = array_map(fn (array $r): array => [min($r[0], $r[1]), max($r[0], $r[1])], $ranges);
        usort($ranges, fn (array $a, array $b): int => $a <=> $b);
        $out = [];
        foreach ($ranges as $r) {
            $last = count($out) - 1;
            if ($last >= 0 && $r[0] <= $out[$last][1]) {
                $out[$last][1] = max($out[$last][1], $r[1]);
            } else {
                $out[] = $r;
            }
        }

        return $out;
    }

    /**
     * Ce qui reste d'une portion hors des portions données.
     *
     * @param  array{0: int, 1: int}  $range
     * @param  list<array{0: int, 1: int}>  $minus
     * @return list<array{0: int, 1: int}>
     */
    public static function subtract(array $range, array $minus): array
    {
        $parts = [[min($range), max($range)]];
        foreach (self::union($minus) as [$a, $b]) {
            $next = [];
            foreach ($parts as [$x, $y]) {
                if ($b <= $x || $a >= $y) {
                    $next[] = [$x, $y];

                    continue;
                }
                if ($a > $x) {
                    $next[] = [$x, $a];
                }
                if ($b < $y) {
                    $next[] = [$b, $y];
                }
            }
            $parts = $next;
        }

        return $parts;
    }

    /**
     * PK extrêmes de tout ce que les sources décrivent.
     *
     * @param  array<string, list<array<string, mixed>>>  $merged
     * @param  array<string, list<array{0: int, 1: int}>>  $covered
     * @return array{0: ?int, 1: ?int}
     */
    private static function extent(array $merged, array $covered): array
    {
        $pks = [];
        foreach ($merged as $items) {
            foreach ($items as $o) {
                foreach (['pk_m', 'pk_from_m', 'pk_to_m'] as $k) {
                    if (isset($o[$k])) {
                        $pks[] = (int) $o[$k];
                    }
                }
            }
        }
        foreach ($covered as $ranges) {
            foreach ($ranges as [$a, $b]) {
                $pks[] = $a;
                $pks[] = $b;
            }
        }

        return $pks === [] ? [null, null] : [min($pks), max($pks)];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function clipTo(array $items, ?int $start, ?int $end): array
    {
        if ($start === null || $end === null) {
            return $items;
        }

        return array_map(fn (array $r): array => ['pk_from_m' => max($start, (int) $r['pk_from_m']), 'pk_to_m' => min($end, (int) $r['pk_to_m'])] + $r, $items);
    }

    /**
     * Couverture de la portion servie : longueur décrite par le RINF et par OSM (voies), et trous par famille.
     *
     * @param  array<string, list<array{0: int, 1: int}>>  $covered
     * @param  array<string, array<string, list<array{0: int, 1: int}>>>  $coveredBy
     * @return array{total_m: int, rinf_m: int, osm_m: int, vff_m: int, missing: list<array{pk_from_m: int, pk_to_m: int, what: string}>}
     */
    private static function coverage(?int $start, ?int $end, array $covered, array $coveredBy): array
    {
        if ($start === null || $end === null) {
            return ['total_m' => 0, 'rinf_m' => 0, 'osm_m' => 0, 'vff_m' => 0, 'missing' => []];
        }
        $length = function (array $ranges) use ($start, $end): int {
            $sum = 0;
            foreach (self::union($ranges) as [$a, $b]) {
                $sum += max(0, min($b, $end) - max($a, $start));
            }

            return $sum;
        };
        $missing = [];
        foreach (self::COVERAGE_FAMILIES as $family) {
            foreach (self::subtract([$start, $end], $covered[$family]) as [$a, $b]) {
                if ($b > $a) {
                    $missing[] = ['pk_from_m' => $a, 'pk_to_m' => $b, 'what' => $family];
                }
            }
        }

        return [
            'total_m' => $end - $start,
            'rinf_m' => $length($coveredBy['rinf']['tracks'] ?? []),
            'osm_m' => $length($coveredBy['osm']['tracks'] ?? []),
            'vff_m' => $length($coveredBy['vff']['tracks'] ?? []),
            'missing' => $missing,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sortByPk(array $items): array
    {
        usort($items, fn (array $a, array $b): int => [(int) $a['pk_m'], (string) $a['id']] <=> [(int) $b['pk_m'], (string) $b['id']]);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sortRanges(array $items): array
    {
        usort($items, fn (array $a, array $b): int => [(int) $a['pk_from_m'], (int) $a['pk_to_m']] <=> [(int) $b['pk_from_m'], (int) $b['pk_to_m']]);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sortTracks(array $items): array
    {
        usort($items, fn (array $a, array $b): int => [(int) $a['pk_from_m'], (int) $a['lane'], (string) $a['id']] <=> [(int) $b['pk_from_m'], (int) $b['lane'], (string) $b['id']]);

        return $items;
    }
}
