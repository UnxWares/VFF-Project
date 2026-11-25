<?php

declare(strict_types=1);

namespace App\Services\TrackLayout;

use App\Models\Line;
use App\Models\Station;
use App\Services\LineStations;
use App\Services\Rinf\RinfDataset;
use App\Services\Sncf\StationPlatforms;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track layout of a station (`GET /api/v1/stations/{station}/track-layout`, GraphQL `Station.track_layout`, MCP
 * `station_track_layout`): for every line through the station that has detailed tracks around it, the line track
 * layout (TrackLayout::forLine, same contract, same sources and merge rules) over a window around the station, and
 * the platform edges of the station with their length and height.
 *
 * WINDOW, per line: the station's own tracks on that line (OpenStreetMap tracks attached to the station that are not
 * running tracks, RINF tracks of the station's operational points centred on their point, over their declared
 * length) and the station kilometre point, widened by MARGIN_M on each side (the switches of the station throats),
 * at least MIN_HALF_M and at most MAX_HALF_M from the station kilometre point. A line enters the response only when
 * something places the station on it (its station_lines kilometre point, a RINF section ending at one of the
 * station's operational points, or OpenStreetMap tracks attached to the station) and its window holds tracks.
 *
 * PLATFORMS: one entry per platform edge (a platform along one track; an island platform has two). The RINF gives
 * the edges of the station's operational points with their usable length and their height; SNCF Réseau's « Liste des
 * quais » gives, per line and track, the kilometre points of each platform (ODbL). An edge of both sources is one
 * entry: RINF length and height, SNCF kilometre points (`precision` exact). An edge only the RINF knows is placed
 * around the station kilometre point on its track (`precision` approximate, `pk_source` vff); one only SNCF Réseau
 * knows keeps its kilometre points and length, without height. `track` is the id of the drawn track of the line
 * layout the edge borders, when one is found (same RINF track, or same track number near the station).
 */
final class StationTrackLayout
{
    /** Margin (m) added on each side of the station's own tracks: the switches of its throats. */
    public const MARGIN_M = 200;

    /** Smallest half-window (m) around the station kilometre point. */
    public const MIN_HALF_M = 500;

    /** Largest half-window (m) around the station kilometre point. */
    public const MAX_HALF_M = 2500;

    /** Distance (m) from the station kilometre point within which a track of the same number borders a platform. */
    public const PLATFORM_REACH_M = 1500;

    /** Kinds of track that never border a platform: crossovers, yard tracks, spurs. */
    public const NO_PLATFORM_KINDS = ['crossover', 'yard', 'spur'];

    /** Fields of a platform edge, in response order. */
    public const PLATFORM_FIELDS = ['id', 'ref', 'track_ref', 'line_code', 'track', 'pk_from_m', 'pk_to_m', 'length_m', 'height_mm',
        'source', 'pk_source', 'precision'];

    /** Families counted in `counts` (besides `lines` and `platforms`). */
    public const COUNTED = ['tracks', 'switches', 'signals', 'crossings', 'buffer_stops'];

    /** Seconds a station layout stays in the cache (the sources change at their imports only). */
    public const CACHE_TTL = 3600;

    /**
     * Version of the rules behind a station layout, in its cache key: an entry made by older rules is not read (2:
     * platform tracks kept and ordered around their platforms, 25/11/2026).
     */
    public const RULES = 2;

    public function __construct(private readonly TrackLayout $layout) {}

    /**
     * Station layout, kept CACHE_TTL seconds (the station sheet asks for it at each visit; a line layout costs a
     * few hundred milliseconds on a long line).
     *
     * @param  list<string>|null  $kinds  families of each line layout (null: all)
     * @param  string|null  $only  one line only (slug or code)
     * @return array<string, mixed>
     */
    public function forStation(Station $station, ?array $kinds = null, ?string $only = null): array
    {
        // The whole layout is kept once per station and line asked: the light overview of the station sheet and the
        // full page read the same entry.
        $key = 'station-track-layout:'.TrackLayout::FORMAT.'.'.self::RULES.':'.$station->id.':'.($only ?? '*');
        $layout = Cache::remember($key, self::CACHE_TTL, fn (): array => $this->build($station, $only));
        if ($kinds !== null) {
            $kinds = array_values(array_intersect(TrackLayout::KINDS, $kinds));
            foreach ($layout['lines'] as &$block) {
                $block['track_layout'] = TrackLayout::onlyKinds($block['track_layout'], $kinds);
            }
            unset($block);
        }

        return $layout;
    }

    /** @return array<string, mixed> */
    private function build(Station $station, ?string $only): array
    {
        $stationId = (int) $station->id;
        $rinf = RinfTrackLayoutSource::tablesReady();

        // RINF operational points of the station (sure matches only, like `station_slug` of the line layout).
        $ops = $rinf ? DB::table('rinf_operational_points')->where('station_id', $stationId)
            ->whereIn('station_match', RinfTrackLayoutSource::SURE_MATCHES)->orderBy('uopid')->get(['uopid', 'name'])->all() : [];
        $uopids = array_map(fn (object $o): string => (string) $o->uopid, $ops);

        /** @var array<string, array<string, list<int>>> $rinfPks line code => operational point => its PK on the line */
        $rinfPks = [];
        /** @var array<string, int> $halfLength operational point => half the longest declared track */
        $halfLength = [];
        /** @var array<string, list<string>> $opLines operational point => codes of the lines its sections belong to */
        $opLines = [];
        if ($uopids !== []) {
            $sections = DB::table('rinf_sections')->where('nature', 'regular')
                ->where(fn ($q) => $q->whereIn('op_start_uopid', $uopids)->orWhereIn('op_end_uopid', $uopids))
                ->get(['line_code', 'op_start_uopid', 'op_end_uopid', 'pk_start_m', 'pk_end_m']);
            foreach ($sections as $s) {
                foreach ([[$s->op_start_uopid, $s->pk_start_m], [$s->op_end_uopid, $s->pk_end_m]] as [$uopid, $pk]) {
                    if (in_array($uopid, $uopids, true) && $pk !== null) {
                        $rinfPks[(string) $s->line_code][(string) $uopid][] = (int) $pk;
                        $opLines[(string) $uopid][] = (string) $s->line_code;
                    }
                }
            }
            foreach (DB::table('rinf_tracks')->whereIn('op_uopid', $uopids)->groupBy('op_uopid')
                ->selectRaw('op_uopid, max(length_m) as longest')->get() as $r) {
                $halfLength[(string) $r->op_uopid] = intdiv((int) ($r->longest ?: RinfTrackLayoutSource::DEFAULT_OP_TRACK_M), 2);
            }
        }

        // OpenStreetMap tracks attached to the station, running tracks aside (they run on beyond it).
        /** @var array<int, array{0: int, 1: int}> $osmExtent */
        $osmExtent = [];
        if (Schema::hasTable('osm_tracks')) {
            foreach (DB::table('osm_tracks')->where('station_id', $stationId)->where('kind', '<>', 'running')
                ->whereNotNull('pk_from_m')->whereNotNull('pk_to_m')
                ->whereRaw('(length_m IS NULL OR pk_to_m - pk_from_m <= GREATEST(? * length_m, length_m + ?))',
                    [OsmTrackLayoutSource::SPAN_RATIO, OsmTrackLayoutSource::SPAN_SLACK_M])
                ->groupBy('line_id')->selectRaw('line_id, min(pk_from_m) as lo, max(pk_to_m) as hi')->get() as $r) {
                $osmExtent[(int) $r->line_id] = [(int) $r->lo, (int) $r->hi];
            }
        }

        $stationLines = DB::table('station_lines')->where('station_id', $stationId)->get(['line_id', 'pk_m', 'pk_source'])->keyBy('line_id');
        $lineIds = array_values(array_unique([...$stationLines->keys()->map(fn ($id): int => (int) $id)->all(), ...array_keys($osmExtent)]));
        $codes = array_keys($rinfPks);
        $lines = $lineIds === [] && $codes === [] ? collect() : Line::query()
            ->where(fn ($q) => $q->whereIn('id', $lineIds)->when($codes !== [], fn ($q) => $q->orWhereIn('code', $codes)))
            ->orderBy('code')->orderBy('id')->get(['id', 'code', 'slug', 'name']);
        if ($only !== null) {
            $lines = $lines->filter(fn (Line $l): bool => $l->slug === $only || ($l->code !== null && $l->code === $only))->values();
        }

        $blocks = [];
        foreach ($lines as $line) {
            $link = $stationLines->get($line->id);
            $byOp = $line->code !== null ? ($rinfPks[(string) $line->code] ?? []) : [];
            $own = array_merge(...array_values($byOp ?: [[]]));
            // Nothing detailed places the station on this line (a closed line of its past): no layout to read.
            if ($own === [] && ! isset($osmExtent[(int) $line->id])) {
                continue;
            }
            $stationPk = $link !== null && $link->pk_m !== null ? (int) $link->pk_m : ($own !== [] ? (int) round(array_sum($own) / count($own)) : null);
            $pkSource = $link !== null && $link->pk_m !== null ? (string) $link->pk_source : ($own !== [] ? 'rinf' : null);
            $extents = [];
            foreach ($byOp as $uopid => $pks) {
                $half = $halfLength[$uopid] ?? 0;
                foreach ($pks as $pk) {
                    $extents[] = [$pk - $half, $pk + $half];
                }
            }
            if (isset($osmExtent[(int) $line->id])) {
                $extents[] = $osmExtent[(int) $line->id];
            }
            [$from, $to] = self::window($stationPk, $extents);
            // Every family is read: the platforms are matched to the drawn tracks, and forStation empties the families
            // not asked for (TrackLayout::onlyKinds).
            $layout = $this->layout->forLine($line, $from, $to);
            if (($layout['counts']['tracks'] ?? 0) === 0) {
                continue;
            }
            $blocks[] = [
                'line' => ['code' => $line->code, 'slug' => $line->slug, 'name' => $line->name],
                'station_pk_m' => $stationPk,
                'station_pk_label' => LineStations::pkLabel($stationPk),
                'station_pk_source' => $pkSource,
                'window' => ['from_m' => $from, 'to_m' => $to],
                'track_layout' => $layout,
            ];
        }
        // The station's main line first: the one with the most tracks in its window.
        usort($blocks, fn (array $a, array $b): int => [$b['track_layout']['counts']['tracks'], (string) $a['line']['code']]
            <=> [$a['track_layout']['counts']['tracks'], (string) $b['line']['code']]);

        [$platforms, $platformSources] = $this->platforms($stationId, $ops, $opLines, $blocks, $only !== null);

        $sources = [];
        foreach ($blocks as $b) {
            foreach ($b['track_layout']['sources'] as $s) {
                $sources[$s['key']] ??= $s;
            }
        }
        foreach ($platformSources as $s) {
            $sources[$s['key']] ??= $s;
        }
        uksort($sources, fn (string $a, string $b): int => self::sourceRank($a) <=> self::sourceRank($b) ?: strcmp($a, $b));

        $counts = ['lines' => count($blocks)];
        foreach (self::COUNTED as $family) {
            $counts[$family] = array_sum(array_map(fn (array $b): int => (int) $b['track_layout']['counts'][$family], $blocks));
        }
        $counts['platforms'] = count($platforms);

        return [
            'station' => ['slug' => $station->slug, 'name' => $station->name, 'uic_code' => $station->uic_code],
            'operational_points' => array_map(fn (object $o): array => ['uopid' => (string) $o->uopid, 'name' => (string) $o->name, 'source' => 'rinf'], $ops),
            'lines' => $blocks,
            'platforms' => $platforms,
            'sources' => array_values($sources),
            'counts' => $counts,
        ];
    }

    /**
     * Window of a line around a station: its own tracks and its kilometre point, widened by MARGIN_M, kept between
     * MIN_HALF_M and MAX_HALF_M from the station kilometre point (or the middle of its tracks without one).
     *
     * @param  list<array{0: int, 1: int}>  $extents
     * @return array{0: int, 1: int}
     */
    public static function window(?int $stationPk, array $extents): array
    {
        $los = array_map(fn (array $e): int => min($e), $extents);
        $his = array_map(fn (array $e): int => max($e), $extents);
        if ($stationPk !== null) {
            $los[] = $his[] = $stationPk;
        }
        if ($los === []) {
            return [0, 0];
        }
        $lo = min($los) - self::MARGIN_M;
        $hi = max($his) + self::MARGIN_M;
        $center = $stationPk ?? intdiv($lo + $hi, 2);

        return [
            min($center - self::MIN_HALF_M, max($center - self::MAX_HALF_M, $lo)),
            max($center + self::MIN_HALF_M, min($center + self::MAX_HALF_M, $hi)),
        ];
    }

    /**
     * Short reference of the track a platform edge borders: « Voie Z de Poitiers » and « Z » give Z, « Voie V1 » gives
     * V1, « Voie 43 Poitiers » gives 43, a SNCF track code « 570000-3-V1 » gives V1.
     */
    public static function platformTrackRef(string $ref): string
    {
        if (preg_match('/^\d{6}-\d+-(.+)$/', trim($ref), $m) === 1) {
            return strtoupper($m[1]);
        }
        $short = RinfDataset::shortTrackRef($ref);
        if (preg_match('/\s/', $short) !== 1) {
            return $short;
        }
        $number = TrackLayout::trackNumber($ref);

        return $number !== '' ? $number : $short;
    }

    /**
     * Platform edges of the station, matched to the drawn tracks of the line layouts (see the class).
     *
     * @param  list<object>  $ops
     * @param  array<string, list<string>>  $opLines
     * @param  list<array<string, mixed>>  $blocks
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} edges, sources used
     */
    private function platforms(int $stationId, array $ops, array $opLines, array $blocks, bool $onlyServedLines): array
    {
        $edges = [];
        $sources = [];
        if ($ops !== []) {
            $rows = DB::table('rinf_platforms as p')->join('rinf_tracks as t', 't.uri', '=', 'p.track_uri')
                ->whereIn('p.op_uopid', array_map(fn (object $o): string => (string) $o->uopid, $ops))
                ->orderBy('p.ref')->orderBy('p.uri')
                ->get(['p.uri', 'p.ref', 'p.length_m', 'p.height_mm', 'p.op_uopid', 'p.dataset', 'p.fetched_at', 't.uri as track_uri', 't.ref as track_ref',
                    't.rinf_class']);
            // Other numbers of a track, from the name of its island platform (« 3 » of « Quai V1/V1BIS » is V1BIS).
            $aliases = PlatformNames::aliases($rows->map(fn (object $r): array => ['platform' => PlatformNames::key((string) $r->op_uopid, (string) $r->ref),
                'ref' => (string) $r->ref, 'track' => (string) $r->track_uri, 'number' => TrackLayout::trackNumber(self::platformTrackRef((string) $r->track_ref))])->all());
            foreach ($rows as $r) {
                $trackRef = self::platformTrackRef((string) $r->track_ref);
                $edges[] = [
                    'id' => 'rinf:pe:'.substr(md5((string) $r->uri), 0, 12), 'ref' => (string) $r->ref,
                    'track_ref' => $trackRef, 'line_code' => null, 'track' => null,
                    'pk_from_m' => null, 'pk_to_m' => null,
                    'length_m' => $r->length_m === null ? null : (int) $r->length_m, 'height_mm' => $r->height_mm === null ? null : (int) $r->height_mm,
                    'source' => 'rinf', 'pk_source' => null, 'precision' => null,
                    '_rinf_track' => 'rinf:t:'.substr(md5((string) $r->track_uri), 0, 12),
                    '_numbers' => array_values(array_unique(array_filter([TrackLayout::trackNumber($trackRef), ...$aliases[(string) $r->track_uri] ?? []]))),
                    '_running' => $r->rinf_class === 'running', '_op' => (string) $r->op_uopid,
                    '_lines' => array_values(array_unique($opLines[(string) $r->op_uopid] ?? [])),
                ];
            }
            if ($rows->isNotEmpty()) {
                $sources[] = RinfDataset::sourceMeta((string) $rows->pluck('dataset')->unique()->implode(' ; '),
                    ($f = $rows->max('fetched_at')) === null ? null : substr((string) $f, 0, 10));
            }
        }

        $sncf = Schema::hasTable('station_platforms') ? DB::table('station_platforms')->where('station_id', $stationId)
            ->where('source', StationPlatforms::SOURCE)->orderBy('code_ligne')->orderBy('code_voie')->orderBy('id')
            ->get(['id', 'code_ligne', 'code_voie', 'label', 'length_m', 'pk_start_m', 'pk_end_m', 'dataset_modified'])->all() : [];

        $byLine = [];
        foreach ($blocks as $b) {
            $byLine[(string) $b['line']['code']] = $b;
        }
        $usedSncf = [];
        foreach ($edges as &$e) {
            // Line of the edge: one of the lines of its operational point's sections, where a drawn track borders it.
            foreach ([...$e['_lines'], ...array_keys($byLine)] as $code) {
                if (! isset($byLine[$code])) {
                    continue;
                }
                $track = self::borderingTrack($byLine[$code], $e['_rinf_track'], $e['_numbers']);
                if ($track !== null || in_array($code, $e['_lines'], true)) {
                    $e['line_code'] = $code;
                    $e['track'] = $track['id'] ?? null;
                    break;
                }
            }
            $e['line_code'] ??= $e['_lines'][0] ?? null;
        }
        unset($e);
        self::singleTrackEdges($edges, $byLine);

        foreach ($edges as &$e) {
            // Kilometre points declared by SNCF Réseau for the same line and track.
            foreach ($sncf as $i => $s) {
                if (! isset($usedSncf[$i]) && $s->code_ligne === $e['line_code'] && $s->code_voie !== null
                    && TrackLayout::sameNumber(TrackLayout::trackNumber(self::platformTrackRef((string) $s->code_voie)), TrackLayout::trackNumber($e['track_ref']))
                    && self::plausible($s, $byLine[(string) $s->code_ligne] ?? null)) {
                    $usedSncf[$i] = true;
                    $e['pk_from_m'] = min((int) $s->pk_start_m, (int) $s->pk_end_m);
                    $e['pk_to_m'] = max((int) $s->pk_start_m, (int) $s->pk_end_m);
                    $e['pk_source'] = 'sncf';
                    $e['precision'] = 'exact';
                    break;
                }
            }
            if ($e['pk_from_m'] === null && $e['track'] !== null && $e['length_m'] !== null && isset($byLine[(string) $e['line_code']])) {
                [$e['pk_from_m'], $e['pk_to_m']] = self::around($byLine[(string) $e['line_code']], $e['track'], $e['length_m']);
                $e['pk_source'] = 'vff';
                $e['precision'] = 'approximate';
            }
        }
        unset($e);

        // Edges only SNCF Réseau knows.
        foreach ($sncf as $i => $s) {
            $block = $byLine[(string) $s->code_ligne] ?? null;
            if (isset($usedSncf[$i]) || $s->code_voie === null || ! self::plausible($s, $block)) {
                continue;
            }
            $trackRef = self::platformTrackRef((string) $s->code_voie);
            $track = $block === null ? null : self::borderingTrack($block, null, [TrackLayout::trackNumber($trackRef)]);
            $edges[] = [
                'id' => 'sncf:quai:'.$s->id, 'ref' => trim((string) $s->label), 'track_ref' => $trackRef, 'line_code' => (string) $s->code_ligne,
                'track' => $track['id'] ?? null, 'pk_from_m' => min((int) $s->pk_start_m, (int) $s->pk_end_m), 'pk_to_m' => max((int) $s->pk_start_m, (int) $s->pk_end_m),
                'length_m' => $s->length_m === null ? null : (int) $s->length_m, 'height_mm' => null,
                'source' => 'sncf', 'pk_source' => 'sncf', 'precision' => 'exact',
            ];
        }
        if (array_filter($edges, fn (array $e): bool => $e['pk_source'] === 'sncf') !== []) {
            $modified = collect($sncf)->max('dataset_modified');
            $sources[] = ['key' => 'sncf', 'label' => 'SNCF Réseau', 'licence' => StationPlatforms::LICENSE,
                'dataset' => 'Liste des quais (SNCF Open Data)'.($modified !== null ? ', '.substr((string) $modified, 0, 10) : ''),
                'fetched_at' => null, 'url' => 'https://data.sncf.com/explore/dataset/liste-des-quais/', 'attribution' => StationPlatforms::MENTION,
                'licence_url' => 'https://opendatacommons.org/licenses/odbl/1-0/'];
        }

        // One line asked: only its edges.
        if ($onlyServedLines) {
            $edges = array_values(array_filter($edges, fn (array $e): bool => isset($byLine[(string) $e['line_code']])));
        }
        usort($edges, fn (array $a, array $b): int => [(string) $a['line_code'], self::naturalKey($a['ref']), self::naturalKey($a['track_ref'])]
            <=> [(string) $b['line_code'], self::naturalKey($b['ref']), self::naturalKey($b['track_ref'])]);
        $fields = array_fill_keys(self::PLATFORM_FIELDS, null);

        return [array_map(fn (array $e): array => array_intersect_key(array_replace($fields, $e), $fields), $edges), $sources];
    }

    /**
     * Drawn track of a line layout that a platform edge borders: the same RINF track (or the track that replaced it),
     * otherwise the track of one of its numbers (TrackLayout::sameNumber: « VD » and « D », or the number the name of
     * its island platform gives it) nearest the station kilometre point, within PLATFORM_REACH_M, the first number
     * first; never a crossover, a yard track or a spur (Paris-Austerlitz has a yard track 3 beside the platform track 3).
     *
     * @param  array<string, mixed>  $block
     * @param  list<string>  $numbers  track numbers of the edge, its own first
     * @return array<string, mixed>|null
     */
    private static function borderingTrack(array $block, ?string $rinfTrack, array $numbers): ?array
    {
        $tracks = array_values(array_filter($block['track_layout']['tracks'], fn (array $t): bool => ! in_array($t['kind'], self::NO_PLATFORM_KINDS, true)));
        $pk = $block['station_pk_m'] ?? intdiv($block['window']['from_m'] + $block['window']['to_m'], 2);
        if ($rinfTrack !== null) {
            // Several pieces of OpenStreetMap may carry the RINF track (Paris-Nord: a 4 m stub before the buffer stop,
            // the platform part, the throat): the one at the station kilometre point, otherwise the nearest, the
            // longest first.
            $same = array_values(array_filter($tracks, fn (array $t): bool => $t['id'] === $rinfTrack || ($t['rinf_id'] ?? null) === $rinfTrack));
            usort($same, fn (array $a, array $b): int => [max(0, (int) $a['pk_from_m'] - $pk, $pk - (int) $a['pk_to_m']), (int) $b['pk_to_m'] - (int) $b['pk_from_m']]
                <=> [max(0, (int) $b['pk_from_m'] - $pk, $pk - (int) $b['pk_to_m']), (int) $a['pk_to_m'] - (int) $a['pk_from_m']]);
            if ($same !== []) {
                return $same[0];
            }
        }
        foreach (array_filter($numbers, fn (string $n): bool => $n !== '') as $number) {
            $best = null;
            $bestDistance = null;
            foreach ($tracks as $t) {
                if (! TrackLayout::sameNumber($number, TrackLayout::trackNumber((string) ($t['ref'] ?? '')))) {
                    continue;
                }
                $distance = max(0, (int) $t['pk_from_m'] - $pk, $pk - (int) $t['pk_to_m']);
                if ($distance <= self::PLATFORM_REACH_M && ($bestDistance === null || $distance < $bestDistance)) {
                    [$best, $bestDistance] = [$t, $distance];
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /**
     * On a single-track line, the through track of a station bears the station's own name for it: at Cognac the RINF
     * names « D » (« Quai VD ») the track that the line and OpenStreetMap call the single track (VU, U). A platform
     * edge of a RINF main track (`running` class) left without a drawn track borders the single track when it is the
     * only such track of its operational point, the line layout has a single track (number U) at the station and no
     * other edge of the point borders it already.
     *
     * @param  list<array<string, mixed>>  $edges
     * @param  array<string, array<string, mixed>>  $byLine
     */
    private static function singleTrackEdges(array &$edges, array $byLine): void
    {
        $left = [];
        $taken = [];
        foreach ($edges as $i => $e) {
            if ($e['track'] === null && $e['_running'] && isset($byLine[(string) $e['line_code']])) {
                $left[$e['_op'].'|'.$e['line_code']][$e['_rinf_track']][] = $i;
            } elseif ($e['track'] !== null) {
                $taken[$e['_op'].'|'.$e['line_code']][$e['track']] = true;
            }
        }
        foreach ($left as $key => $tracks) {
            if (count($tracks) !== 1) {
                continue;
            }
            $block = $byLine[explode('|', $key)[1]];
            $pk = (int) ($block['station_pk_m'] ?? intdiv($block['window']['from_m'] + $block['window']['to_m'], 2));
            $at = fn (array $t): bool => $t['kind'] === 'running' && (int) $t['pk_from_m'] <= $pk && $pk <= (int) $t['pk_to_m'];
            $number = fn (array $t): string => TrackLayout::trackNumber((string) ($t['ref'] ?? ''));
            // Pieces of the single track at the station (a RINF track is cut at each section end), the one the station
            // lies most inside first.
            $single = array_values(array_filter($block['track_layout']['tracks'], fn (array $t): bool => $at($t) && $number($t) === 'U'));
            usort($single, fn (array $a, array $b): int => min($pk - (int) $b['pk_from_m'], (int) $b['pk_to_m'] - $pk)
                <=> min($pk - (int) $a['pk_from_m'], (int) $a['pk_to_m'] - $pk));
            $double = array_filter($block['track_layout']['tracks'], fn (array $t): bool => $at($t) && in_array($number($t), ['1', '2'], true));
            $busy = array_filter($single, fn (array $t): bool => isset($taken[$key][$t['id']]));
            if ($single === [] || $double !== [] || $busy !== []) {
                continue;
            }
            foreach (reset($tracks) as $i) {
                $edges[$i]['track'] = $single[0]['id'];
            }
        }
    }

    /**
     * Approximate place of a platform of known length on its track: centred on the station kilometre point, moved
     * inside the track when it would overhang one of its ends, the whole track when the platform is longer. The track
     * runs on through the pieces of the same reference it touches (a RINF line track is cut at each section end, at
     * Cognac on the station kilometre point itself).
     *
     * @param  array<string, mixed>  $block
     * @return array{0: int, 1: int}
     */
    private static function around(array $block, string $trackId, int $length): array
    {
        $track = null;
        foreach ($block['track_layout']['tracks'] as $t) {
            if ($t['id'] === $trackId) {
                $track = $t;
                break;
            }
        }
        $pk = (int) ($block['station_pk_m'] ?? intdiv($block['window']['from_m'] + $block['window']['to_m'], 2));
        $from = $pk - intdiv($length, 2);
        if ($track === null) {
            return [$from, $from + $length];
        }
        [$a, $b] = [(int) $track['pk_from_m'], (int) $track['pk_to_m']];
        $same = array_filter($block['track_layout']['tracks'], fn (array $t): bool => $t['ref'] !== null && $t['ref'] === $track['ref']
            && $t['source'] === $track['source'] && $t['kind'] === $track['kind']);
        usort($same, fn (array $x, array $y): int => (int) $x['pk_from_m'] <=> (int) $y['pk_from_m']);
        foreach ([$same, array_reverse($same)] as $k => $list) {
            foreach ($list as $t) {
                if ($k === 0 && (int) $t['pk_from_m'] <= $b && (int) $t['pk_to_m'] > $b) {
                    $b = (int) $t['pk_to_m'];
                } elseif ($k === 1 && (int) $t['pk_to_m'] >= $a && (int) $t['pk_from_m'] < $a) {
                    $a = (int) $t['pk_from_m'];
                }
            }
        }
        if ($b - $a <= $length) {
            return [$a, $b];
        }
        $from = max($a, min($from, $b - $length));

        return [$from, $from + $length];
    }

    /**
     * A SNCF platform record with both kilometre points, over an extent compatible with its length and, on a line of
     * the response, within PLATFORM_REACH_M of the station (Brétigny has two « Quai 4 » records at PK 0+000).
     *
     * @param  array<string, mixed>|null  $block
     */
    private static function plausible(object $s, ?array $block): bool
    {
        if ($s->pk_start_m === null || $s->pk_end_m === null
            || ! StationPlatforms::plausibleExtent($s->length_m === null ? null : (int) $s->length_m, abs((int) $s->pk_end_m - (int) $s->pk_start_m))) {
            return false;
        }
        if ($block === null) {
            return true;
        }
        $pk = (int) ($block['station_pk_m'] ?? intdiv($block['window']['from_m'] + $block['window']['to_m'], 2));

        return min((int) $s->pk_start_m, (int) $s->pk_end_m) <= $pk + self::PLATFORM_REACH_M
            && max((int) $s->pk_start_m, (int) $s->pk_end_m) >= $pk - self::PLATFORM_REACH_M;
    }

    /** Sort key that puts « Quai 2 » before « Quai 10 ». */
    private static function naturalKey(?string $s): string
    {
        return (string) preg_replace_callback('/\d+/', fn (array $m): string => str_pad($m[0], 6, '0', STR_PAD_LEFT), mb_strtoupper((string) $s));
    }

    private static function sourceRank(string $key): int
    {
        $order = [...TrackLayout::PRIORITY, 'sncf'];
        $i = array_search($key, $order, true);

        return $i === false ? count($order) : (int) $i;
    }
}
