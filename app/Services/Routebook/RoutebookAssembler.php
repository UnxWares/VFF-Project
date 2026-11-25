<?php

namespace App\Services\Routebook;

/**
 * Assemblage du livret de parcours : les schémas des voies des portions de ligne parcourues (format du contrat de
 * `track-layout`), mis bout à bout dans le sens de marche. Fonctions pures (tests :
 * tests/Unit/Routebook/RoutebookAssemblerTest.php).
 *
 * Chaque objet garde ses champs (PK de sa ligne, provenance, précision) et reçoit :
 *   - `leg` (rang du morceau), `line_code` et `line_slug` (sa ligne) ;
 *   - `route_km` (point) ou `route_from_km` / `route_to_km` (étendue, dans l'ordre de marche) : kilomètres parcourus
 *     depuis le départ, au centre du point d'exploitation de départ (RouteLegs::routeMetres) ;
 *   - un identifiant préfixé du rang du morceau (`2:sw-14`), unique sur tout le parcours, références comprises.
 * Voies : `route_lane` est la rangée dans le sens de marche (la rangée `lane` du contrat, retournée sur un morceau
 * parcouru à PK décroissants, pour garder en haut la gauche du sens de marche).
 * Sens de marche : `travel` = `same` quand un signal vaut pour le sens de marche, `opposite` sinon ; un aiguillage
 * est `travel_facing` quand il est pris en pointe. Les déclivités sont données dans le sens de marche (`permille`,
 * positive en rampe) avec la valeur de la ligne (`line_permille`, sens des PK croissants).
 * Les listes sont triées par kilomètre parcouru. Un objet hors de la portion parcourue est écarté ; une étendue est
 * coupée à ses bornes. Couverture : mètres décrits par chaque source (`rinf_m`, `osm_m`, `vff_m`, voies) et portions
 * manquantes (`missing`) en kilomètres parcourus : `what` = une famille d'objets absente (`tracks`, `switches`…), ou
 * `all` quand la portion n'est décrite par aucune source (aucun objet, ou PK inconnus).
 */
final class RoutebookAssembler
{
    /** Kinds d'objets ponctuels du contrat. */
    private const POINT_LISTS = ['operational_points', 'switches', 'signals', 'crossings'];

    /**
     * @param  list<array{index: int, line: array{code: ?string, slug: string, name: string}, direction: ?string,
     *     pk_from_m: ?int, pk_to_m: ?int, route_from_m: int, route_to_m: int}>  $legs
     * @param  array<int, array<string, mixed>|null>  $layouts  rang du morceau => portion (null : PK inconnus, pas de schéma)
     * @return array{sources: list<array<string, mixed>>, operational_points: list<array<string, mixed>>, tracks: list<array<string, mixed>>,
     *     switches: list<array<string, mixed>>, signals: list<array<string, mixed>>, crossings: list<array<string, mixed>>,
     *     strip: array{speeds: list<array<string, mixed>>, gradients: list<array<string, mixed>>},
     *     coverage: array{total_m: int, rinf_m: int, osm_m: int, vff_m: int, missing: list<array<string, mixed>>}}
     */
    public static function assemble(array $legs, array $layouts): array
    {
        $out = ['sources' => [], 'operational_points' => [], 'tracks' => [], 'switches' => [], 'signals' => [],
            'crossings' => [], 'strip' => ['speeds' => [], 'gradients' => []]];
        $coverage = ['total_m' => 0, 'rinf_m' => 0, 'osm_m' => 0, 'vff_m' => 0, 'missing' => []];
        $sources = [];

        foreach ($legs as $leg) {
            $length = $leg['route_to_m'] - $leg['route_from_m'];
            $coverage['total_m'] += $length;
            $layout = $layouts[$leg['index']] ?? null;
            if ($layout === null || $leg['pk_from_m'] === null || $leg['pk_to_m'] === null) {
                $coverage['missing'][] = self::missing($leg, $leg['route_from_m'], $leg['route_to_m'], 'all')
                    + ['pk_from_m' => $leg['pk_from_m'], 'pk_to_m' => $leg['pk_to_m']];

                continue;
            }
            foreach ($layout['sources'] ?? [] as $source) {
                $sources[$source['key']] ??= $source;
            }
            $pkFrom = $leg['pk_from_m'];
            $pkTo = $leg['pk_to_m'];
            $route = fn (int $pk): int => RouteLegs::routeMetres($pk, $pkFrom, $pkTo, $leg['route_from_m'], $leg['route_to_m']);
            $tag = fn (array $o): array => ['leg' => $leg['index'], 'line_code' => $leg['line']['code'], 'line_slug' => $leg['line']['slug']] + $o;
            $id = fn (?string $ref): ?string => $ref === null ? null : $leg['index'].':'.$ref;

            foreach (self::POINT_LISTS as $list) {
                foreach ($layout[$list] ?? [] as $o) {
                    if (! RouteLegs::covers((int) $o['pk_m'], $pkFrom, $pkTo)) {
                        continue;
                    }
                    $o['id'] = $id((string) $o['id']);
                    $o['route_km'] = self::km($route((int) $o['pk_m']));
                    if ($list === 'switches') {
                        $o['from_track'] = $id($o['from_track'] ?? null);
                        $o['to_track'] = $id($o['to_track'] ?? null);
                        $o['travel_facing'] = in_array($o['facing'] ?? null, ['up', 'down'], true) && $leg['direction'] !== null
                            ? $o['facing'] === $leg['direction'] : null;
                    }
                    if ($list === 'signals') {
                        $o['track'] = $id($o['track'] ?? null);
                        $o['travel'] = in_array($o['direction'] ?? null, ['up', 'down'], true) && $leg['direction'] !== null
                            ? ($o['direction'] === $leg['direction'] ? 'same' : 'opposite') : null;
                    }
                    $out[$list][] = $tag($o);
                }
            }

            $lanes = array_map(fn (array $t): int => (int) ($t['lane'] ?? 0), $layout['tracks'] ?? []);
            $mirror = $lanes === [] ? 0 : min($lanes) + max($lanes);
            foreach ($layout['tracks'] ?? [] as $t) {
                $range = self::clip((int) $t['pk_from_m'], (int) $t['pk_to_m'], $pkFrom, $pkTo);
                if ($range === null) {
                    continue;
                }
                $t['id'] = $id((string) $t['id']);
                [$t['pk_from_m'], $t['pk_to_m']] = $range;
                $t['length_m'] = $range[1] - $range[0];
                $t['route_lane'] = $leg['direction'] === 'down' ? $mirror - (int) ($t['lane'] ?? 0) : (int) ($t['lane'] ?? 0);
                $out['tracks'][] = $tag($t + self::routeRange($range, $route));
            }
            foreach ($layout['strip']['speeds'] ?? [] as $s) {
                $range = self::clip((int) $s['pk_from_m'], (int) $s['pk_to_m'], $pkFrom, $pkTo);
                if ($range !== null) {
                    [$s['pk_from_m'], $s['pk_to_m']] = $range;
                    $out['strip']['speeds'][] = $tag($s + self::routeRange($range, $route));
                }
            }
            foreach ($layout['strip']['gradients'] ?? [] as $g) {
                $range = self::clip((int) $g['pk_from_m'], (int) $g['pk_to_m'], $pkFrom, $pkTo);
                if ($range === null) {
                    continue;
                }
                [$g['pk_from_m'], $g['pk_to_m']] = $range;
                $g['line_permille'] = $g['permille'];
                $g['permille'] = $leg['direction'] === 'down' ? -1 * $g['permille'] : $g['permille'];
                $out['strip']['gradients'][] = $tag($g + self::routeRange($range, $route));
            }

            $c = $layout['coverage'] ?? [];
            foreach (['rinf_m', 'osm_m', 'vff_m'] as $key) {
                $coverage[$key] += (int) ($c[$key] ?? 0);
            }
            // Portion qu'aucune source ne décrit (aucun objet) : manquante en entier, plutôt que famille par famille.
            $missing = self::isEmpty($layout)
                ? [['pk_from_m' => min($pkFrom, $pkTo), 'pk_to_m' => max($pkFrom, $pkTo), 'what' => 'all']]
                : ($c['missing'] ?? []);
            foreach ($missing as $m) {
                $range = self::clip((int) $m['pk_from_m'], (int) $m['pk_to_m'], $pkFrom, $pkTo);
                if ($range !== null) {
                    $r = self::routeRange($range, $route);
                    $coverage['missing'][] = self::missing($leg, (int) round($r['route_from_km'] * 1000), (int) round($r['route_to_km'] * 1000), (string) $m['what'])
                        + ['pk_from_m' => $range[0], 'pk_to_m' => $range[1]];
                }
            }
        }

        $byRoute = fn (array $a, array $b): int => [$a['route_km'], $a['leg']] <=> [$b['route_km'], $b['leg']];
        $byRange = fn (array $a, array $b): int => [$a['route_from_km'], $a['leg'], $a['route_to_km']] <=> [$b['route_from_km'], $b['leg'], $b['route_to_km']];
        foreach (self::POINT_LISTS as $list) {
            usort($out[$list], $byRoute);
        }
        $out['operational_points'] = self::dedupeOperationalPoints($out['operational_points']);
        usort($out['tracks'], $byRange);
        usort($out['strip']['speeds'], $byRange);
        usort($out['strip']['gradients'], $byRange);

        $out['sources'] = array_values($sources);
        $out['coverage'] = $coverage;

        return $out;
    }

    /**
     * Nombre d'objets du livret (points d'exploitation, voies, aiguillages, signaux, traversées).
     *
     * @param  array<string, mixed>  $book
     */
    public static function objectCount(array $book): int
    {
        return count($book['operational_points']) + count($book['tracks']) + count($book['switches'])
            + count($book['signals']) + count($book['crossings']);
    }

    /** @param  array<string, mixed>  $layout */
    private static function isEmpty(array $layout): bool
    {
        foreach (self::POINT_LISTS as $list) {
            if (($layout[$list] ?? []) !== []) {
                return false;
            }
        }

        return ($layout['tracks'] ?? []) === [] && ($layout['strip']['speeds'] ?? []) === [] && ($layout['strip']['gradients'] ?? []) === [];
    }

    /**
     * Une gare de changement de ligne figure dans les deux portions (fin de l'une, début de l'autre) : elle n'est
     * gardée qu'une fois, avec les lignes des deux côtés (`lines`).
     *
     * @param  list<array<string, mixed>>  $points  triés par kilomètre parcouru
     * @return list<array<string, mixed>>
     */
    private static function dedupeOperationalPoints(array $points): array
    {
        $out = [];
        foreach ($points as $p) {
            $last = count($out) - 1;
            if ($last >= 0 && ($p['station_slug'] ?? null) !== null && ($out[$last]['station_slug'] ?? null) === $p['station_slug']
                && $out[$last]['leg'] !== $p['leg'] && abs($out[$last]['route_km'] - $p['route_km']) <= 1.0) {
                $out[$last]['lines'] = array_values(array_unique([...($out[$last]['lines'] ?? [$out[$last]['line_slug']]), $p['line_slug']]));

                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    /**
     * Étendue [PK a, PK b] coupée à la portion parcourue, ou null si elle est dehors.
     *
     * @return array{0: int, 1: int}|null
     */
    private static function clip(int $a, int $b, int $pkFrom, int $pkTo): ?array
    {
        $lo = max(min($a, $b), min($pkFrom, $pkTo));
        $hi = min(max($a, $b), max($pkFrom, $pkTo));

        return $hi > $lo ? [$lo, $hi] : null;
    }

    /**
     * @param  array{0: int, 1: int}  $range
     * @param  callable(int): int  $route
     * @return array{route_from_km: float, route_to_km: float}
     */
    private static function routeRange(array $range, callable $route): array
    {
        $a = $route($range[0]);
        $b = $route($range[1]);

        return ['route_from_km' => self::km(min($a, $b)), 'route_to_km' => self::km(max($a, $b))];
    }

    /**
     * @param  array{index: int, line: array{code: ?string, slug: string, name: string}}  $leg
     * @return array{leg: int, line_code: ?string, line_slug: string, route_from_km: float, route_to_km: float, what: string}
     */
    private static function missing(array $leg, int $fromM, int $toM, string $what): array
    {
        return ['leg' => $leg['index'], 'line_code' => $leg['line']['code'], 'line_slug' => $leg['line']['slug'],
            'route_from_km' => self::km($fromM), 'route_to_km' => self::km($toM), 'what' => $what];
    }

    private static function km(int $metres): float
    {
        return round($metres / 1000, 3);
    }
}
