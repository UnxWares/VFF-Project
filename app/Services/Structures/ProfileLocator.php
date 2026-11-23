<?php

declare(strict_types=1);

namespace App\Services\Structures;

use Illuminate\Support\Facades\DB;

/**
 * Position d'un ouvrage le long d'une ligne, lue sur son profil en long (table `line_profile_points`,
 * un point tous les 100 m environ, PK calé sur les gares par PkCalibration) : chaque extrémité est
 * projetée sur la polyligne des points du profil (par morceau et par branche), la distance et le PK sont
 * interpolés entre les deux points qui l'encadrent. Rien n'est donné au-delà de StructureImporter::PROFILE_M
 * mètres du profil, ni de PK quand l'un des deux points n'en a pas.
 *
 * Les points de LINES_IN_MEMORY lignes au plus restent en mémoire.
 */
final class ProfileLocator
{
    private const LINES_IN_MEMORY = 64;

    /** @var array<int, list<array{coords: list<array{0: float, 1: float}>, distance: list<int>, pk: list<int|null>}>> */
    private array $paths = [];

    /**
     * Début et fin de l'ouvrage sur la ligne, dans le sens du profil (début = plus petite distance).
     *
     * Le PK est celui du milieu de l'ouvrage, étendu de part et d'autre de la longueur mesurée le long
     * du profil : entre deux gares, le calage peut étirer ou tasser le PK (gares mal placées, PK d'une
     * autre origine), et un ouvrage de 570 m ne doit pas couvrir 950 m de PK. Sans milieu calé, le PK
     * de chaque extrémité. `ends` dit combien d'extrémités sont sur le profil (0, 1 ou 2).
     *
     * @param  array{0: float, 1: float}  $start
     * @param  array{0: float, 1: float}  $end
     * @param  array{0: float, 1: float}|null  $mid  milieu de l'ouvrage (null : ouvrage ponctuel)
     * @return array{pk_start_m: ?int, pk_end_m: ?int, distance_from_m: ?int, distance_to_m: ?int, ends: int}
     */
    public function locate(int $lineId, array $start, array $end, ?array $mid = null): array
    {
        $paths = $this->paths($lineId);
        $a = self::at($paths, $start);
        $b = self::at($paths, $end);
        $m = $mid === null ? null : self::at($paths, $mid);
        if ($a !== null && $b !== null && $b['distance'] < $a['distance']) {
            [$a, $b] = [$b, $a];
        }
        $from = $a['distance'] ?? $b['distance'] ?? $m['distance'] ?? null;
        $to = $b['distance'] ?? $a['distance'] ?? $m['distance'] ?? null;

        if ($a !== null && $b !== null && ($m['pk'] ?? null) !== null) {
            $inside = max($a['distance'], min($b['distance'], $m['distance']));
            $pkStart = $m['pk'] - ($inside - $a['distance']);
            $pkEnd = $m['pk'] + ($b['distance'] - $inside);
        } else {
            $pks = array_values(array_filter([$a['pk'] ?? null, $b['pk'] ?? null, $m['pk'] ?? null], fn (?int $pk): bool => $pk !== null));
            $pkStart = $pks === [] ? null : min($pks);
            $pkEnd = $pks === [] ? null : max($pks);
        }

        return [
            'pk_start_m' => $pkStart,
            'pk_end_m' => $pkEnd,
            'distance_from_m' => $from,
            'distance_to_m' => $to,
            'ends' => ($a === null ? 0 : 1) + ($b === null ? 0 : 1),
        ];
    }

    /**
     * Distance et PK d'un point projeté sur le profil.
     *
     * @param  list<array{coords: list<array{0: float, 1: float}>, distance: list<int>, pk: list<int|null>}>  $paths
     * @param  array{0: float, 1: float}  $p
     * @return array{distance: int, pk: ?int}|null
     */
    public static function at(array $paths, array $p): ?array
    {
        $best = null;
        foreach ($paths as $path) {
            $proj = StructureGeometry::project($p, $path['coords']);
            if ($proj !== null && ($best === null || $proj['distance_m'] < $best[0]['distance_m'])) {
                $best = [$proj, $path];
            }
        }
        if ($best === null || $best[0]['distance_m'] > StructureImporter::PROFILE_M) {
            return null;
        }
        [$proj, $path] = $best;
        $i = $proj['segment'];
        $f = $proj['fraction'];
        $d = $path['distance'][$i] + ($path['distance'][$i + 1] - $path['distance'][$i]) * $f;
        $pk0 = $path['pk'][$i];
        $pk1 = $path['pk'][$i + 1];

        return [
            'distance' => (int) round($d),
            'pk' => $pk0 === null || $pk1 === null ? null : (int) round($pk0 + ($pk1 - $pk0) * $f),
        ];
    }

    /** @return list<array{coords: list<array{0: float, 1: float}>, distance: list<int>, pk: list<int|null>}> */
    private function paths(int $lineId): array
    {
        if (isset($this->paths[$lineId])) {
            return $this->paths[$lineId];
        }
        if (count($this->paths) >= self::LINES_IN_MEMORY) {
            unset($this->paths[array_key_first($this->paths)]);
        }
        $rows = DB::table('line_profile_points')
            ->where('line_id', $lineId)->whereNotNull('geom')
            ->orderBy('line_profile_id')->orderBy('seq')
            ->get(['line_profile_id', 'piece', 'branch', 'distance_m', 'pk_m', DB::raw('ST_X(geom) AS lon'), DB::raw('ST_Y(geom) AS lat')]);
        $paths = [];
        foreach ($rows as $r) {
            $key = $r->line_profile_id.':'.$r->piece.':'.($r->branch ?? '');
            $paths[$key]['coords'][] = [(float) $r->lon, (float) $r->lat];
            $paths[$key]['distance'][] = (int) $r->distance_m;
            $paths[$key]['pk'][] = $r->pk_m === null ? null : (int) $r->pk_m;
        }

        return $this->paths[$lineId] = array_values(array_filter($paths, fn (array $p): bool => count($p['coords']) > 1));
    }
}
