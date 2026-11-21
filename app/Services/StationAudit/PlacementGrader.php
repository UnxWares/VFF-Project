<?php

namespace App\Services\StationAudit;

/**
 * Note de placement d'une gare à partir des positions de ses sources (fonction pure, testée).
 *
 * Familles indépendantes : SNCF (points de gares et de gares de voyageurs), OSM, BD TOPO (IGN),
 * Wikidata, OpenHistoricalMap, listes par PK (schémas Wikipédia et lignes-oubliées posés par
 * calage des PK), correction relue. Deux sources d'une même famille ne se confirment pas.
 *
 * Sources précises : SNCF, OSM, BD TOPO, OHM, et une position de liste dont la précision annoncée
 * (provenance.geom_precision_m) est d'au plus 50 m. Wikidata et les listes calées à plus de 50 m
 * sont grossières (médiane de l'écart Wikidata mesurée le 19/11 : 382 m) : elles ne contredisent
 * qu'au-delà de 1 km.
 *
 *   agree    : une source indépendante à moins de AGREE_M (20 m), aucune contradiction ;
 *   close    : la plus proche à moins de CLOSE_M (100 m), aucune contradiction ;
 *   isolated : aucune source indépendante, ou seulement des grossières à plus de 100 m et moins de 1 km ;
 *   disagree : une source précise à plus de DISAGREE_PRECISE_M (100 m), ou une grossière à plus de
 *              DISAGREE_COARSE_M (1 km) ;
 *   none     : aucune position.
 *
 * Position retenue mise en minorité (`outvoted`) : au moins deux autres familles, à plus de 100 m
 * de la position retenue, sont à moins de OUTVOTED_PAIR_M (100 m) l'une de l'autre (réglé sur le tirage de
 * 100 gares du 21/11 : à 50 m, Carteret, OSM et Wikidata à 71 m l'un de l'autre, échappait), et plus
 * nombreuses que les familles d'accord avec la position retenue. C'est la gare à revoir en premier
 * (Doulon, ligne 519000 : SNCF retenue, OSM et Wikidata d'accord à 465 m de là).
 *
 * Les positions recopiées (`derived`) sont montrées mais ne comptent pas.
 */
final class PlacementGrader
{
    public const AGREE_M = 20;

    public const CLOSE_M = 100;

    public const DISAGREE_PRECISE_M = 100;

    public const DISAGREE_COARSE_M = 1000;

    public const OUTVOTED_PAIR_M = 100;

    /** Précision annoncée en dessous de laquelle une position de liste (PK) compte comme précise. */
    public const PRECISE_LIST_M = 50;

    /** Écart à la voie au-delà duquel le contrôle `far_from_track` échoue. */
    public const TRACK_FAR_M = 50;

    private const FAMILIES = [
        'sncf' => 'sncf', 'sncf_voyageurs' => 'sncf',
        'osm' => 'osm', 'bdtopo' => 'bdtopo', 'wikidata' => 'wikidata', 'ohm' => 'ohm',
        'wikipedia_schema' => 'pk', 'lignes-oubliees' => 'pk', 'correction' => 'correction',
    ];

    private const PRECISE = ['sncf', 'osm', 'bdtopo', 'ohm', 'correction'];

    public static function family(?string $source): string
    {
        return self::FAMILIES[$source ?? ''] ?? (string) $source;
    }

    public static function isPrecise(string $source, ?int $precisionM = null): bool
    {
        $family = self::family($source);
        if ($family === 'pk') {
            return $precisionM !== null && $precisionM <= self::PRECISE_LIST_M;
        }

        return in_array($family, self::PRECISE, true);
    }

    /**
     * @param  list<SourcePosition>  $positions  toutes les positions des sources de la gare
     * @param  string|null  $retainedSource  source de la position retenue (stations.position_source)
     */
    public static function grade(array $positions, ?string $retainedSource): PlacementResult
    {
        $positions = array_values(array_filter($positions, fn (SourcePosition $p): bool => ! $p->derived));
        if ($positions === []) {
            return new PlacementResult(PlacementGrade::None, 0, null, null);
        }
        $retainedFamily = self::family($retainedSource);
        $families = array_values(array_unique(array_map(fn (SourcePosition $p): string => $p->family(), $positions)));
        // Sources indépendantes : celles d'une autre famille que la position retenue. Dans chaque
        // famille, la position la plus proche représente la famille (une famille d'accord sur un
        // point suffit, ses doublons éloignés sont souvent des homonymes).
        $best = [];
        foreach ($positions as $p) {
            if ($p->family() === $retainedFamily || $p->distanceM === null) {
                continue;
            }
            $f = $p->family();
            if (! isset($best[$f]) || $p->distanceM < $best[$f]->distanceM) {
                $best[$f] = $p;
            }
        }
        $count = count(array_unique([...$families, $retainedFamily]));
        if ($best === []) {
            return new PlacementResult(PlacementGrade::Isolated, $count, null, null);
        }

        $nearest = min(array_map(fn (SourcePosition $p): int => (int) $p->distanceM, $best));
        $precise = array_filter($best, fn (SourcePosition $p): bool => $p->precise());
        $farthestPrecise = $precise === [] ? null : max(array_map(fn (SourcePosition $p): int => (int) $p->distanceM, $precise));
        $coarseFar = array_filter($best, fn (SourcePosition $p): bool => ! $p->precise() && $p->distanceM > self::DISAGREE_COARSE_M);

        $grade = match (true) {
            $farthestPrecise !== null && $farthestPrecise > self::DISAGREE_PRECISE_M, $coarseFar !== [] => PlacementGrade::Disagree,
            $nearest <= self::AGREE_M => PlacementGrade::Agree,
            $nearest <= self::CLOSE_M => PlacementGrade::Close,
            default => PlacementGrade::Isolated,
        };

        return new PlacementResult($grade, $count, $nearest, $farthestPrecise, self::outvoted(array_values($best)));
    }

    /** @param list<SourcePosition> $best une position par famille indépendante */
    private static function outvoted(array $best): bool
    {
        $near = count(array_filter($best, fn (SourcePosition $p): bool => $p->distanceM <= self::AGREE_M));
        // Les listes par PK ne votent pas : leurs PK viennent souvent de la SNCF (Ponte-Nuovo, tirage du 21/11).
        $far = array_values(array_filter($best, fn (SourcePosition $p): bool => $p->distanceM > self::DISAGREE_PRECISE_M && $p->family() !== 'pk'));
        foreach ($far as $a) {
            $group = array_filter($far, fn (SourcePosition $b): bool => self::metres($a, $b) <= self::OUTVOTED_PAIR_M);
            if (count($group) >= 2 && count($group) > $near) {
                return true;
            }
        }

        return false;
    }

    /** Distance entre deux positions (m, formule de haversine). */
    public static function metres(SourcePosition $a, SourcePosition $b): float
    {
        $rad = M_PI / 180;
        $dLat = ($b->lat - $a->lat) * $rad;
        $dLon = ($b->lon - $a->lon) * $rad;
        $h = sin($dLat / 2) ** 2 + cos($a->lat * $rad) * cos($b->lat * $rad) * sin($dLon / 2) ** 2;

        return 2 * 6_371_008.8 * asin(min(1.0, sqrt($h)));
    }
}
