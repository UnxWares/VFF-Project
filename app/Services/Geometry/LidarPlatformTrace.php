<?php

namespace App\Services\Geometry;

use InvalidArgumentException;

/**
 * Source de géométrie « plateforme ferroviaire vue dans le relief » (MNT LiDAR HD de l'IGN, 50 cm).
 *
 * Lit le GeoJSON produit par `ml/lidar-plateformes` (`run.py detect`) : un LineString par morceau
 * de ligne, un point tous les 5 m, et pour chaque point (`point_properties`) :
 *   - `found` : plateforme trouvée (signature de remblai, tranchée ou banquette assez nette) ;
 *   - `error_p90_m` : erreur planimétrique estimée (p90, m), calibrée sur des voies connues ;
 *     null quand le point n'est pas trouvé ;
 *   - `score`, `prominence`, `width_m`, `kind` (plat, remblai, tranchee, banquette).
 *
 * Elle ne remplace rien : elle propose des tronçons fiables, avec leur erreur, à la fusion
 * des sources (même contrat que `source_traces` : source `lidar-hd`, couche `plateforme`).
 */
final class LidarPlatformTrace
{
    public const SOURCE = 'lidar-hd';

    public const LAYER = 'plateforme';

    /**
     * @param  list<array{line_id: int, part: int, step_m: float, coordinates: list<array{0: float, 1: float}>, found: list<bool>, error: list<float|null>, kind: list<string>}>  $parts
     */
    private function __construct(private readonly array $parts) {}

    public static function fromFile(string $path): self
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new InvalidArgumentException("Fichier illisible : {$path}");
        }

        return self::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $collection
     */
    public static function fromArray(array $collection): self
    {
        if (($collection['type'] ?? null) !== 'FeatureCollection' || ! is_array($collection['features'] ?? null)) {
            throw new InvalidArgumentException('FeatureCollection attendue');
        }
        $parts = [];
        foreach ($collection['features'] as $i => $feature) {
            $coords = $feature['geometry']['coordinates'] ?? null;
            $props = $feature['properties'] ?? [];
            $points = $feature['point_properties'] ?? null;
            if (($feature['geometry']['type'] ?? null) !== 'LineString' || ! is_array($coords) || ! is_array($points)) {
                throw new InvalidArgumentException("Morceau {$i} : LineString et point_properties attendus");
            }
            $n = count($coords);
            foreach (['found', 'error_p90_m'] as $key) {
                if (! is_array($points[$key] ?? null) || count($points[$key]) !== $n) {
                    throw new InvalidArgumentException("Morceau {$i} : {$key} doit avoir {$n} valeurs");
                }
            }
            $parts[] = [
                'line_id' => (int) ($props['line_id'] ?? 0),
                'part' => (int) ($props['part'] ?? $i),
                'step_m' => (float) ($props['step_m'] ?? 5.0),
                'coordinates' => array_map(static fn (array $c): array => [(float) $c[0], (float) $c[1]], $coords),
                'found' => array_map(static fn (mixed $f): bool => (bool) $f, $points['found']),
                'error' => array_map(static fn (mixed $e): ?float => $e === null ? null : (float) $e, $points['error_p90_m']),
                'kind' => array_map(static fn (mixed $k): string => (string) $k, $points['kind'] ?? array_fill(0, $n, '')),
            ];
        }

        return new self($parts);
    }

    /** Nombre de points (tous morceaux). */
    public function pointCount(): int
    {
        return array_sum(array_map(static fn (array $p): int => count($p['coordinates']), $this->parts));
    }

    /** Part des points où la plateforme est trouvée, avec une erreur estimée au plus égale à $maxError. */
    public function coverage(float $maxError = INF): float
    {
        $total = $this->pointCount();
        if ($total === 0) {
            return 0.0;
        }
        $ok = 0;
        foreach ($this->parts as $p) {
            foreach ($p['found'] as $k => $found) {
                $ok += $found && $p['error'][$k] !== null && $p['error'][$k] <= $maxError ? 1 : 0;
            }
        }

        return $ok / $total;
    }

    /**
     * Tronçons continus fiables : points trouvés dont l'erreur estimée est au plus $maxError m,
     * au moins $minPoints points à la suite (40 m par défaut, au pas de 5 m).
     *
     * @return list<array{line_id: int, part: int, from: int, to: int, length_m: float, error_p90_m: float, coordinates: list<array{0: float, 1: float}>}>
     */
    public function reliableRuns(float $maxError = 2.5, int $minPoints = 8): array
    {
        $runs = [];
        foreach ($this->parts as $p) {
            $start = null;
            $n = count($p['coordinates']);
            for ($k = 0; $k <= $n; $k++) {
                $good = $k < $n && $p['found'][$k] && $p['error'][$k] !== null && $p['error'][$k] <= $maxError;
                if ($good && $start === null) {
                    $start = $k;
                } elseif (! $good && $start !== null) {
                    if ($k - $start >= $minPoints) {
                        $runs[] = $this->run($p, $start, $k - 1);
                    }
                    $start = null;
                }
            }
        }

        return $runs;
    }

    /**
     * Lignes au format de `source_traces` (sans écriture) : une par tronçon fiable.
     *
     * @return list<array{source: string, source_ref: string, layer: string, line_id: int, match_method: string, match_score: float, length_m: int, properties: array{error_p90_m: float, from: int, to: int, part: int}, wkt: string}>
     */
    public function sourceTraceRows(float $maxError = 2.5, int $minPoints = 8): array
    {
        return array_map(static fn (array $r): array => [
            'source' => self::SOURCE,
            'source_ref' => "{$r['line_id']}-{$r['part']}-{$r['from']}",
            'layer' => self::LAYER,
            'line_id' => $r['line_id'],
            'match_method' => 'corridor',
            'match_score' => round(max(0.0, 1.0 - $r['error_p90_m'] / 10.0), 3),
            'length_m' => (int) round($r['length_m']),
            'properties' => ['error_p90_m' => $r['error_p90_m'], 'from' => $r['from'], 'to' => $r['to'], 'part' => $r['part']],
            'wkt' => 'MULTILINESTRING(('.implode(',', array_map(
                static fn (array $c): string => sprintf('%.7F %.7F', $c[0], $c[1]),
                $r['coordinates'],
            )).'))',
        ], $this->reliableRuns($maxError, $minPoints));
    }

    /**
     * @param  array{line_id: int, part: int, step_m: float, coordinates: list<array{0: float, 1: float}>, found: list<bool>, error: list<float|null>, kind: list<string>}  $p
     * @return array{line_id: int, part: int, from: int, to: int, length_m: float, error_p90_m: float, coordinates: list<array{0: float, 1: float}>}
     */
    private function run(array $p, int $from, int $to): array
    {
        $errors = array_filter(array_slice($p['error'], $from, $to - $from + 1), static fn (?float $e): bool => $e !== null);

        return [
            'line_id' => $p['line_id'],
            'part' => $p['part'],
            'from' => $from,
            'to' => $to,
            'length_m' => ($to - $from) * $p['step_m'],
            'error_p90_m' => $errors === [] ? INF : max($errors),
            'coordinates' => array_slice($p['coordinates'], $from, $to - $from + 1),
        ];
    }
}
