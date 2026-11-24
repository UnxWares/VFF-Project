<?php

declare(strict_types=1);

namespace App\Services\Tracks\Osm;

use InvalidArgumentException;

/**
 * The OpenStreetMap objects of the detailed track layout, read from the OPL file written by
 * `bin/osm-track-detail` (osmium: node locations written into the ways, untagged nodes dropped; plain or gzip):
 *   - ways railway=rail|light_rail|narrow_gauge with their node ids and coordinates (flat [lon, lat, ...]) and
 *     the tags the layout reads (WAY_TAGS);
 *   - tagged nodes of the layout (NODE_KINDS) and every node that carries a PK (`railway:position:exact`,
 *     `railway:position`), with all their tags;
 *   - line codes of the route=railway relations, by member way.
 * A bounding box limits what is kept (runs on a few lines). `latest` is the newest object timestamp of the file,
 * the honest date of the data. OpenStreetMap data, ODbL.
 */
final class OsmTrackExtract
{
    public const TRACK_VALUES = ['rail', 'light_rail', 'narrow_gauge'];

    public const NODE_KINDS = ['switch', 'railway_crossing', 'buffer_stop', 'level_crossing', 'crossing', 'signal', 'milestone'];

    private const WAY_TAGS = [
        'railway', 'service', 'usage', 'ref', 'ref:FR:SNCF_Reseau', 'railway:track_ref', 'electrified', 'voltage',
        'maxspeed', 'gauge', 'name', 'railway:preferred_direction', 'highspeed',
    ];

    /** @var array<int, array{tags: array<string, string>, nodes: list<int>, coords: list<float>}> */
    public array $ways = [];

    /** @var array<int, array{tags: array<string, string>, lon: float, lat: float}> */
    public array $nodes = [];

    /** @var array<int, list<string>> */
    public array $relationCodes = [];

    public ?string $latest = null;

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox  [min lon, min lat, max lon, max lat]
     */
    public static function fromFile(string $path, ?array $bbox = null): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Unreadable file: {$path}");
        }
        $fh = fopen(str_ends_with($path, '.gz') ? 'compress.zlib://'.$path : $path, 'r');
        if ($fh === false) {
            throw new InvalidArgumentException("Unreadable file: {$path}");
        }
        $extract = new self;
        try {
            while (($line = fgets($fh)) !== false) {
                $extract->read(rtrim($line, "\r\n"), $bbox);
            }
        } finally {
            fclose($fh);
        }

        return $extract;
    }

    /**
     * @param  list<string>  $lines
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     */
    public static function fromLines(array $lines, ?array $bbox = null): self
    {
        $extract = new self;
        foreach ($lines as $line) {
            $extract->read($line, $bbox);
        }

        return $extract;
    }

    /**
     * Line codes of a way: its `ref` (one or several codes separated by « ; »), the first field of
     * `ref:FR:SNCF_Reseau` and the codes of its route relations, normalised by lineCode().
     *
     * @return list<string>
     */
    public function wayCodes(int $wayId): array
    {
        $tags = $this->ways[$wayId]['tags'] ?? [];
        $codes = [];
        foreach (explode(';', $tags['ref'] ?? '') as $ref) {
            $codes[] = self::lineCode($ref);
        }
        $codes[] = self::lineCode(explode(',', $tags['ref:FR:SNCF_Reseau'] ?? '')[0]);
        foreach ($this->relationCodes[$wayId] ?? [] as $code) {
            $codes[] = $code;
        }

        return array_values(array_unique(array_filter($codes)));
    }

    /** SNCF Reseau line code on six digits (« 1000 » → « 001000 », « 676 000 » → « 676000 »), or null. */
    public static function lineCode(string $value): ?string
    {
        $v = str_replace([' ', "\u{a0}"], '', trim($value));

        return preg_match('/^\d{4,6}$/', $v) === 1 ? str_pad($v, 6, '0', STR_PAD_LEFT) : null;
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     */
    private function read(string $line, ?array $bbox): void
    {
        $type = $line[0] ?? '';
        if ($type !== 'n' && $type !== 'w' && $type !== 'r') {
            return;
        }
        $fields = explode(' ', $line);
        $id = (int) substr($fields[0], 1);
        $tags = [];
        $x = $y = null;
        $refs = '';
        foreach ($fields as $f) {
            $k = $f[0] ?? '';
            if ($k === 'T') {
                $tags = self::tags(substr($f, 1));
            } elseif ($k === 'x') {
                $x = $f === 'x' ? null : (float) substr($f, 1);
            } elseif ($k === 'y') {
                $y = $f === 'y' ? null : (float) substr($f, 1);
            } elseif ($k === 'N' || $k === 'M') {
                $refs = substr($f, 1);
            } elseif ($k === 't' && strlen($f) > 1) {
                $t = substr($f, 1);
                if ($this->latest === null || strcmp($t, $this->latest) > 0) {
                    $this->latest = $t;
                }
            }
        }

        match ($type) {
            'n' => $this->readNode($id, $tags, $x, $y, $bbox),
            'w' => $this->readWay($id, $tags, $refs, $bbox),
            default => $this->readRelation($tags, $refs),
        };
    }

    /**
     * @param  array<string, string>  $tags
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     */
    private function readNode(int $id, array $tags, ?float $x, ?float $y, ?array $bbox): void
    {
        if ($x === null || $y === null) {
            return;
        }
        $kind = $tags['railway'] ?? null;
        if (! in_array($kind, self::NODE_KINDS, true) && ! isset($tags['railway:position:exact']) && ! isset($tags['railway:position'])) {
            return;
        }
        if ($bbox !== null && ($x < $bbox[0] || $y < $bbox[1] || $x > $bbox[2] || $y > $bbox[3])) {
            return;
        }
        $this->nodes[$id] = ['tags' => $tags, 'lon' => $x, 'lat' => $y];
    }

    /**
     * @param  array<string, string>  $tags
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     */
    private function readWay(int $id, array $tags, string $refs, ?array $bbox): void
    {
        if (! in_array($tags['railway'] ?? null, self::TRACK_VALUES, true) || $refs === '') {
            return;
        }
        $nodes = [];
        $coords = [];
        $inside = $bbox === null;
        foreach (explode(',', $refs) as $ref) {
            // n123x4.56y7.89 ; a node without location (cut by the extract border) is left out.
            $xp = strpos($ref, 'x');
            $yp = strpos($ref, 'y');
            if ($xp === false || $yp === false || $yp <= $xp + 1 || $yp === strlen($ref) - 1) {
                continue;
            }
            $lon = (float) substr($ref, $xp + 1, $yp - $xp - 1);
            $lat = (float) substr($ref, $yp + 1);
            $nodes[] = (int) substr($ref, 1, $xp - 1);
            $coords[] = $lon;
            $coords[] = $lat;
            if (! $inside && $lon >= $bbox[0] && $lat >= $bbox[1] && $lon <= $bbox[2] && $lat <= $bbox[3]) {
                $inside = true;
            }
        }
        if (count($nodes) < 2 || ! $inside) {
            return;
        }
        $this->ways[$id] = [
            'tags' => array_intersect_key($tags, array_flip(self::WAY_TAGS)),
            'nodes' => $nodes,
            'coords' => $coords,
        ];
    }

    /** @param  array<string, string>  $tags */
    private function readRelation(array $tags, string $refs): void
    {
        if (($tags['route'] ?? null) !== 'railway' || $refs === '') {
            return;
        }
        $code = self::lineCode($tags['ref'] ?? '') ?? self::lineCode(explode(',', $tags['ref:FR:SNCF_Reseau'] ?? '')[0]);
        if ($code === null) {
            return;
        }
        foreach (explode(',', $refs) as $member) {
            if (($member[0] ?? '') !== 'w') {
                continue;
            }
            $wayId = (int) substr($member, 1, (strpos($member, '@') ?: strlen($member)) - 1);
            if (! in_array($code, $this->relationCodes[$wayId] ?? [], true)) {
                $this->relationCodes[$wayId][] = $code;
            }
        }
    }

    /**
     * OPL tags « k=v,k2=v2 » ; special characters are escaped as %hex% (code point).
     *
     * @return array<string, string>
     */
    public static function tags(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $tags = [];
        foreach (explode(',', $raw) as $pair) {
            $eq = strpos($pair, '=');
            if ($eq === false) {
                continue;
            }
            $tags[self::unescape(substr($pair, 0, $eq))] = self::unescape(substr($pair, $eq + 1));
        }

        return $tags;
    }

    private static function unescape(string $s): string
    {
        if (! str_contains($s, '%')) {
            return $s;
        }

        return (string) preg_replace_callback('/%([0-9a-fA-F]{1,6})%/', fn (array $m): string => mb_chr((int) hexdec($m[1]), 'UTF-8') ?: '', $s);
    }
}
