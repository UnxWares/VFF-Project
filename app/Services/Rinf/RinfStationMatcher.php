<?php

namespace App\Services\Rinf;

use App\Support\Search\SearchText;

/**
 * Rattachement des points d'exploitation du RINF aux gares de VFF, sans écriture : par le nom (clé de recherche de
 * VFF, SearchText::key, « St-Michel-sur-Charente » = « Saint-Michel-sur-Charente ») et la distance.
 *
 * - `name_distance` : même nom (clé ou cœur de clé égaux, ou l'un commence l'autre mot pour mot : « Les Aubrais »
 *   et « Les Aubrais-Orléans ») à NAME_RADIUS_M au plus ; un nom égal l'emporte sur un nom prolongé (« Châteaubriant »
 *   et « Châteaubriant Tram-Train », deux gares à 50 m l'une de l'autre, « Paris-Nord-Surface » et « Paris-Nord »),
 *   puis la plus proche ;
 * - `name` : point sans position, nom égal à celui d'une seule gare de VFF ;
 * - `distance` : point de type gare ou halte sans nom reconnu, gare la plus proche à DISTANCE_RADIUS_M au plus.
 * Les bifurcations, faisceaux, installations terminales et changements techniques ne sont rattachés que par le nom.
 */
final class RinfStationMatcher
{
    public const NAME_RADIUS_M = 3000;

    public const DISTANCE_RADIUS_M = 150;

    /** Taille d'une case de la grille de recherche, en degrés (environ 5,5 km en latitude). */
    private const CELL = 0.05;

    /** @var array<string, list<int>> case => indices de gares */
    private array $grid = [];

    /** @var list<array{id: int, slug: string, key: string, core: string, lat: float, lon: float}> */
    private array $stations = [];

    /** @var array<string, list<int>> clé => indices de gares */
    private array $byKey = [];

    /** @param  iterable<object{id: int|string, slug: string, name: string, lat: float|string, lon: float|string}>  $stations */
    public function __construct(iterable $stations)
    {
        foreach ($stations as $s) {
            $key = SearchText::key((string) $s->name);
            $i = count($this->stations);
            $this->stations[] = ['id' => (int) $s->id, 'slug' => (string) $s->slug, 'key' => $key, 'core' => SearchText::core($key),
                'lat' => (float) $s->lat, 'lon' => (float) $s->lon];
            $this->grid[self::cell((float) $s->lat, (float) $s->lon)][] = $i;
            $this->byKey[$key][] = $i;
        }
    }

    /**
     * @param  array{name: string, kind: string, lat: ?float, lon: ?float}  $op
     * @return array{station_id: int, station_match: string, station_distance_m: ?int}|null
     */
    public function match(array $op): ?array
    {
        $key = SearchText::key($op['name']);
        if ($op['lat'] === null || $op['lon'] === null) {
            $same = $this->byKey[$key] ?? [];

            return count($same) === 1 ? ['station_id' => $this->stations[$same[0]]['id'], 'station_match' => 'name', 'station_distance_m' => null] : null;
        }
        $same = null;
        $prolonged = null;
        $nearest = null;
        foreach ($this->around($op['lat'], $op['lon']) as $i) {
            $s = $this->stations[$i];
            $d = self::distance($op['lat'], $op['lon'], $s['lat'], $s['lon']);
            if ($d <= self::NAME_RADIUS_M && self::sameName($key, $s['key'], $s['core'])) {
                // Nom égal (clé ou cœur de clé) avant nom prolongé mot pour mot.
                if (self::equalName($key, $s['key'], $s['core'])) {
                    $same = $same === null || $d < $same[1] ? [$i, $d] : $same;
                } else {
                    $prolonged = $prolonged === null || $d < $prolonged[1] ? [$i, $d] : $prolonged;
                }
            }
            if ($d <= self::DISTANCE_RADIUS_M && ($nearest === null || $d < $nearest[1])) {
                $nearest = [$i, $d];
            }
        }
        $best = $same ?? $prolonged;
        if ($best !== null) {
            return ['station_id' => $this->stations[$best[0]]['id'], 'station_match' => 'name_distance', 'station_distance_m' => (int) round($best[1])];
        }
        if ($nearest !== null && in_array($op['kind'], ['station', 'halt'], true)) {
            return ['station_id' => $this->stations[$nearest[0]]['id'], 'station_match' => 'distance', 'station_distance_m' => (int) round($nearest[1])];
        }

        return null;
    }

    /** Noms égaux au sens strict : clés égales, ou cœurs de clé égaux. */
    public static function equalName(string $a, string $b, ?string $bCore = null): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        $aCore = SearchText::core($a);

        return $a === $b || ($aCore !== '' && $aCore === ($bCore ?? SearchText::core($b)));
    }

    /** Noms égaux pour le rattachement : clés ou cœurs égaux, ou l'un commence l'autre mot pour mot. */
    public static function sameName(string $a, string $b, ?string $bCore = null): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        $aCore = SearchText::core($a);
        $bCore ??= SearchText::core($b);

        return $a === $b || ($aCore !== '' && $aCore === $bCore) || str_starts_with($a.' ', $b.' ') || str_starts_with($b.' ', $a.' ');
    }

    /** @return list<int> */
    private function around(float $lat, float $lon): array
    {
        $out = [];
        $cy = (int) floor($lat / self::CELL);
        $cx = (int) floor($lon / self::CELL);
        for ($y = $cy - 1; $y <= $cy + 1; $y++) {
            for ($x = $cx - 1; $x <= $cx + 1; $x++) {
                foreach ($this->grid[$y.':'.$x] ?? [] as $i) {
                    $out[] = $i;
                }
            }
        }

        return $out;
    }

    private static function cell(float $lat, float $lon): string
    {
        return ((int) floor($lat / self::CELL)).':'.((int) floor($lon / self::CELL));
    }

    /** Distance en mètres (équirectangulaire, assez juste sous quelques kilomètres). */
    public static function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $x = deg2rad($lon2 - $lon1) * cos(deg2rad(($lat1 + $lat2) / 2));
        $y = deg2rad($lat2 - $lat1);

        return sqrt($x * $x + $y * $y) * 6_371_000;
    }
}
