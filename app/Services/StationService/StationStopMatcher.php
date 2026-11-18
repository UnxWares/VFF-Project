<?php

namespace App\Services\StationService;

use App\Enums\StopMatchMethod;
use App\Support\StationNames;

/**
 * Rapproche les arrêts GTFS des gares de VFF, dans cet ordre :
 *
 *   1. code UIC (7 premiers chiffres) : la gare de ce code la plus proche (à moins de 3 km,
 *      sinon signalée « uic_far »), et celles du même code à moins de 200 m de l'arrêt
 *      (gare et sa partie souterraine), signalées « uic_multiple » ;
 *   2. nom normalisé identique à moins de 2 km, ou nom contenu dans l'autre
 *      (mêmes mots) à moins de 800 m : la plus proche, « ambiguous » si une autre
 *      gare du même nom est aussi à moins de 2 km, « homonym » si une gare du
 *      même nom existe à plus de 30 km ;
 *   3. sur demande (trains seulement) : seule gare à moins de 150 m, aucune autre
 *      à moins de 400 m.
 */
final class StationStopMatcher
{
    public const UIC_RADIUS_M = 3000;

    public const UIC_COMPLEX_M = 200;

    public const NAME_RADIUS_M = 2000;

    public const PARTIAL_NAME_RADIUS_M = 800;

    public const POSITION_RADIUS_M = 150;

    public const POSITION_CLEAR_M = 400;

    public const HOMONYM_DISTANCE_M = 30000;

    /** Abréviations des noms de gares de VFF (« Nice - CP »), appliquées aux noms des flux. */
    private const ABBREVIATIONS = ['/\\b(?:gare des )?chemins de fer de provence\\b/' => 'cp'];

    /** Maille de l'index spatial (degrés, environ 2 km). */
    private const CELL = 0.02;

    /** @var array<int, StationCandidate> */
    private array $stations = [];

    /** @var array<string, list<int>> */
    private array $byUic = [];

    /** @var array<string, list<int>> */
    private array $byName = [];

    /** @var array<string, list<int>> */
    private array $grid = [];

    /** @param  iterable<StationCandidate>  $stations */
    public function __construct(iterable $stations)
    {
        foreach ($stations as $s) {
            $this->stations[$s->id] = $s;
            foreach ($s->uics as $uic) {
                $this->byUic[$uic][] = $s->id;
            }
            foreach ($s->names as $name) {
                $this->byName[$name][] = $s->id;
            }
            $this->grid[$this->cell($s->lat, $s->lon)][] = $s->id;
        }
    }

    public function match(GtfsStop $stop, bool $byPosition): ?StopMatch
    {
        return $this->byUic($stop)
            ?? ($stop->lat === null || $stop->lon === null ? null : $this->byName($stop, $stop->lat, $stop->lon)
                ?? ($byPosition ? $this->byPosition($stop->lat, $stop->lon) : null));
    }

    private function byUic(GtfsStop $stop): ?StopMatch
    {
        $ids = $stop->uic === null ? [] : array_values(array_unique($this->byUic[substr($stop->uic, 0, 7)] ?? []));
        if ($ids === []) {
            return null;
        }
        if ($stop->lat === null || $stop->lon === null) {
            return new StopMatch($ids, StopMatchMethod::Uic, null, count($ids) > 1 ? ['uic_multiple'] : []);
        }
        $distances = [];
        foreach ($ids as $id) {
            $distances[$id] = $this->distance($stop->lat, $stop->lon, $this->stations[$id]);
        }
        asort($distances);
        $nearest = (int) array_key_first($distances);
        $kept = [$nearest, ...array_keys(array_filter($distances, fn (int $d, int $id): bool => $id !== $nearest && $d <= self::UIC_COMPLEX_M, ARRAY_FILTER_USE_BOTH))];
        $flags = $distances[$nearest] > self::UIC_RADIUS_M ? ['uic_far'] : [];
        if (count($distances) > 1) {
            $flags[] = 'uic_multiple';
        }

        return new StopMatch($kept, StopMatchMethod::Uic, $distances[$nearest], $flags);
    }

    private function byName(GtfsStop $stop, float $lat, float $lon): ?StopMatch
    {
        $name = StationNames::normalize($stop->name);
        if ($name === null) {
            return null;
        }
        $name = trim((string) preg_replace(array_keys(self::ABBREVIATIONS), array_values(self::ABBREVIATIONS), $name));
        $words = explode(' ', $name);
        $best = [];
        foreach ($this->near($lat, $lon, self::NAME_RADIUS_M) as $id => $distance) {
            $score = $this->nameScore($name, $words, $this->stations[$id]->names);
            if ($score === 2 || ($score === 1 && $distance <= self::PARTIAL_NAME_RADIUS_M)) {
                $best[] = [$score, $distance, $id];
            }
        }
        if ($best === []) {
            return null;
        }
        usort($best, fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
        [$score, $distance, $id] = $best[0];
        $flags = [];
        if (count(array_filter($best, fn (array $b): bool => $b[0] === $score)) > 1) {
            $flags[] = 'ambiguous';
        }
        if ($this->hasFarNamesake($name, $lat, $lon)) {
            $flags[] = 'homonym';
        }

        return new StopMatch([$id], StopMatchMethod::Name, $distance, $flags);
    }

    private function byPosition(float $lat, float $lon): ?StopMatch
    {
        $near = $this->near($lat, $lon, self::POSITION_CLEAR_M);
        if (count($near) !== 1) {
            return null;
        }
        $distance = reset($near);
        $id = (int) key($near);

        return $distance <= self::POSITION_RADIUS_M ? new StopMatch([$id], StopMatchMethod::Position, $distance) : null;
    }

    /**
     * 2 : même nom ; 1 : tous les mots de l'un dans l'autre ; 0 sinon.
     *
     * @param  list<string>  $words
     * @param  list<string>  $candidates
     */
    private function nameScore(string $name, array $words, array $candidates): int
    {
        $score = 0;
        foreach ($candidates as $candidate) {
            if ($candidate === $name) {
                return 2;
            }
            $other = explode(' ', $candidate);
            if (array_diff($words, $other) === [] || array_diff($other, $words) === []) {
                $score = 1;
            }
        }

        return $score;
    }

    private function hasFarNamesake(string $name, float $lat, float $lon): bool
    {
        foreach ($this->byName[$name] ?? [] as $id) {
            if ($this->distance($lat, $lon, $this->stations[$id]) > self::HOMONYM_DISTANCE_M) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, int> gares à moins de $radius mètres => distance, de la plus proche à la plus lointaine */
    private function near(float $lat, float $lon, int $radius): array
    {
        $out = [];
        $spanY = (int) ceil($radius / 111000 / self::CELL) + 1;
        $spanX = (int) ceil($radius / (111000 * max(0.1, cos(deg2rad($lat)))) / self::CELL) + 1;
        [$cy, $cx] = array_map('intval', explode(':', $this->cell($lat, $lon)));
        for ($y = $cy - $spanY; $y <= $cy + $spanY; $y++) {
            for ($x = $cx - $spanX; $x <= $cx + $spanX; $x++) {
                foreach ($this->grid["{$y}:{$x}"] ?? [] as $id) {
                    $d = $this->distance($lat, $lon, $this->stations[$id]);
                    if ($d <= $radius) {
                        $out[$id] = $d;
                    }
                }
            }
        }
        asort($out);

        return $out;
    }

    private function cell(float $lat, float $lon): string
    {
        return (int) floor($lat / self::CELL).':'.(int) floor($lon / self::CELL);
    }

    /** Distance en mètres (formule de haversine). */
    private function distance(float $lat, float $lon, StationCandidate $s): int
    {
        $dLat = deg2rad($s->lat - $lat);
        $dLon = deg2rad($s->lon - $lon);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($s->lat)) * sin($dLon / 2) ** 2;

        return (int) round(6371000 * 2 * asin(min(1.0, sqrt($a))));
    }
}
