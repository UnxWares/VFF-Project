<?php

namespace App\Services\Routebook;

use App\Services\PathfindingService;

/**
 * Découpage d'un itinéraire en morceaux de ligne (« legs ») pour le livret de parcours, et calage des PK de
 * ligne sur la distance parcourue. Fonctions pures (tests : tests/Unit/Routebook/RouteLegsTest.php).
 *
 * Un itinéraire est une suite d'arêtes gare à gare ; chaque arête est faite de portions de lignes dans l'ordre
 * de marche (`station_edges.line_ids` et `line_lengths`). Les portions sont regroupées par ligne comme pour
 * `GET /api/v1/route` (PathfindingService::legs : une portion de moins d'un kilomètre, raccordement ou
 * aiguillage, est fondue dans sa voisine), puis chaque morceau reçoit ses bornes en mètres parcourus depuis le
 * départ et, quand il commence ou finit hors d'une gare (changement de ligne en pleine voie), la position de
 * ce point sur l'arête qui le porte.
 */
final class RouteLegs
{
    /**
     * @param  list<list<array{0: int, 1: int}>>  $steps  arête k (gare k → gare k + 1) => [[ligne, longueur], …] dans l'ordre de marche
     * @return list<array{line_id: int, length_m: int, from_index: ?int, to_index: ?int, route_from_m: int, route_to_m: int,
     *     from_at: array{edge: int, offset_m: int}|null, to_at: array{edge: int, offset_m: int}|null}>
     *                                                                                                        `from_at` / `to_at` : arête et distance depuis sa gare de départ (sens de marche) d'un bout hors gare
     */
    public static function split(array $steps): array
    {
        $edgeStart = [];
        $edgeLength = [];
        $total = 0;
        foreach ($steps as $k => $parts) {
            $edgeStart[$k] = $total;
            $edgeLength[$k] = array_sum(array_map(fn (array $p): int => $p[1], $parts));
            $total += $edgeLength[$k];
        }

        $locate = function (int $routeM) use ($edgeStart, $edgeLength): array {
            $edge = 0;
            foreach ($edgeStart as $k => $start) {
                if ($start <= $routeM && $routeM <= $start + $edgeLength[$k]) {
                    $edge = $k;
                    break;
                }
            }

            return ['edge' => $edge, 'offset_m' => $routeM - $edgeStart[$edge]];
        };

        $legs = [];
        $cursor = 0;
        foreach (PathfindingService::legs($steps) as $leg) {
            $from = $cursor;
            $to = $cursor + $leg['length_m'];
            $legs[] = $leg + [
                'route_from_m' => $from,
                'route_to_m' => $to,
                'from_at' => $leg['from_index'] === null ? $locate($from) : null,
                'to_at' => $leg['to_index'] === null ? $locate($to) : null,
            ];
            $cursor = $to;
        }

        return $legs;
    }

    /**
     * Sens de marche sur la ligne d'un morceau : `up` (PK croissants), `down` (décroissants), null (PK inconnus ou égaux).
     */
    public static function direction(?int $pkFrom, ?int $pkTo): ?string
    {
        if ($pkFrom === null || $pkTo === null || $pkFrom === $pkTo) {
            return null;
        }

        return $pkTo > $pkFrom ? 'up' : 'down';
    }

    /**
     * Distance parcourue (m, depuis le départ de l'itinéraire) d'un PK de la ligne d'un morceau : linéaire entre les
     * PK de ses deux bouts et ses bornes parcourues (un écart entre PK et longueur mesurée, PK historiques ou
     * changements d'origine, est réparti sur le morceau ; les distances restent continues d'un morceau au suivant).
     */
    public static function routeMetres(int $pk, int $pkFrom, int $pkTo, int $routeFrom, int $routeTo): int
    {
        if ($pkFrom === $pkTo) {
            return $routeFrom;
        }
        $t = ($pk - $pkFrom) / ($pkTo - $pkFrom);

        return (int) round($routeFrom + $t * ($routeTo - $routeFrom));
    }

    /** Le PK est-il dans le morceau parcouru (bornes comprises, à `$tolerance` mètres près) ? */
    public static function covers(int $pk, int $pkFrom, int $pkTo, int $tolerance = 0): bool
    {
        return $pk >= min($pkFrom, $pkTo) - $tolerance && $pk <= max($pkFrom, $pkTo) + $tolerance;
    }
}
