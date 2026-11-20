<?php

namespace App\Services\StationPositioning;

/**
 * Calage retenu par PkConsensus : sens de parcours de l'axe (+1 : les PK croissent avec
 * l'abscisse, -1 : à rebours), points d'appui concordants (inliers) et écartés.
 *
 * Placement d'un PK :
 *   - entre deux gares d'appui concordantes : interpolation linéaire entre elles (le PK
 *     encadré absorbe l'écart de longueur entre le tracé et le kilométrage) ;
 *   - au-delà, ou avec une seule gare d'appui : PK = distance le long de la voie depuis la
 *     gare d'appui la plus proche (pente 1), jamais à plus de MAX_EXTRAPOLATION_M ;
 *   - sans gare d'appui (lieux-dits seuls) : interpolation entre lieux-dits, sinon pente 1
 *     depuis le plus proche, précision plus large.
 */
final readonly class PkFit
{
    /** Au-delà de cette distance (m de PK) d'un point d'appui, aucun placement. */
    public const MAX_EXTRAPOLATION_M = 15000;

    /** Précision de base (m) avec une gare d'appui, puis par mètre d'écart au point d'appui. */
    public const PRECISION_STATION_M = 100;

    public const PRECISION_PLACE_M = 400;

    public const PRECISION_SLOPE_EXTRAPOLATED = 0.03;

    public const PRECISION_SLOPE_BRACKETED = 0.01;

    /**
     * @param  list<PkAnchor>  $inliers  par PK croissant
     * @param  list<PkAnchor>  $outliers
     */
    public function __construct(
        public int $direction,
        public array $inliers,
        public array $outliers,
        public int $weight,
    ) {}

    /** @return list<PkAnchor> gares d'appui concordantes, par PK croissant */
    public function stations(): array
    {
        return array_values(array_filter($this->inliers, fn (PkAnchor $a) => $a->kind === AnchorKind::Station));
    }

    /**
     * Ordre : (1) entre deux gares d'appui ; (2) pente 1 depuis la gare d'appui la plus
     * proche, à au plus MAX_EXTRAPOLATION_M ; (3) entre deux points d'appui quelconques
     * (lieux-dits compris) ; (4) pente 1 depuis le point d'appui le plus proche.
     */
    public function place(int $pk): ?PkPlacement
    {
        $stations = $this->stations();

        return $this->between($stations, $pk, self::PRECISION_STATION_M)
            ?? $this->from($stations, $pk)
            ?? $this->between($this->inliers, $pk, self::PRECISION_PLACE_M)
            ?? $this->from($this->inliers, $pk);
    }

    /** @param  list<PkAnchor>  $set  par PK croissant */
    private function between(array $set, int $pk, int $base): ?PkPlacement
    {
        for ($i = 0; $i < count($set) - 1; $i++) {
            [$a, $b] = [$set[$i], $set[$i + 1]];
            if ($pk < $a->pk || $pk > $b->pk || $a->pk === $b->pk) {
                continue;
            }
            $gap = min($pk - $a->pk, $b->pk - $pk);

            return new PkPlacement(
                $a->measure + ($pk - $a->pk) * ($b->measure - $a->measure) / ($b->pk - $a->pk),
                $gap,
                $a->kind === AnchorKind::Station && $b->kind === AnchorKind::Station,
                (int) round($base + self::PRECISION_SLOPE_BRACKETED * $gap),
                sprintf('PK interpolé entre %s et %s', $a->label, $b->label),
            );
        }

        return null;
    }

    /** @param  list<PkAnchor>  $set */
    private function from(array $set, int $pk): ?PkPlacement
    {
        if ($set === []) {
            return null;
        }
        usort($set, fn (PkAnchor $a, PkAnchor $b) => [abs($a->pk - $pk), -$a->kind->weight()] <=> [abs($b->pk - $pk), -$b->kind->weight()]);
        $near = $set[0];
        $gap = abs($pk - $near->pk);
        if ($gap > self::MAX_EXTRAPOLATION_M) {
            return null;
        }
        $base = $near->kind === AnchorKind::Station ? self::PRECISION_STATION_M : self::PRECISION_PLACE_M;

        return new PkPlacement(
            $near->measure + $this->direction * ($pk - $near->pk),
            $gap,
            false,
            (int) round($base + self::PRECISION_SLOPE_EXTRAPOLATED * $gap),
            sprintf('PK = distance le long de la voie depuis %s (%s km)', $near->label, number_format($gap / 1000, 1, ',', '')),
        );
    }

    /** L'abscisse d'un point d'appui concorde-t-elle avec ce calage ? */
    public function agrees(PkAnchor $anchor): bool
    {
        $placed = $this->place($anchor->pk);

        return $placed !== null && abs($placed->measure - $anchor->measure)
            <= $anchor->kind->toleranceM() + PkConsensus::TOLERANCE_SLOPE * $placed->gapM;
    }
}
