<?php

namespace App\Services\Signalling;

/**
 * Découpe d'une tranche de cantonnement aux PK des portions KVB du jeu SNCF Réseau `lignes-equipees-de-kvb`. Analyse pure.
 *
 * Avant, une tranche recevait le KVB dès qu'une portion la touchait, même en partie, ou seulement à un bout (975900,
 * tronçon 1 : tranche BAL de 9+906 à 12+985, KVB de 10+380 à 12+985 : les 474 premiers mètres étaient dits équipés).
 * La tranche est maintenant coupée au PK de début et de fin du KVB : morceaux avec KVB, morceaux sans.
 */
final class KvbPieces
{
    /**
     * Écart de PK sous lequel deux bornes sont la même : les deux jeux de SNCF Réseau arrondissent parfois au mètre près
     * (892000 : cantonnement jusqu'à 211+701, KVB jusqu'à 211+700). Un morceau plus court ne serait que du bruit.
     */
    public const MIN_PIECE_M = 10;

    /**
     * Morceaux d'une tranche [from, to] dans l'ordre des PK, chacun avec ou sans KVB ; liste vide si aucune portion KVB
     * ne la couvre (la tranche reste telle quelle, sans KVB).
     *
     * @param  list<array{0: int, 1: int}>  $portions  portions KVB de la même ligne et du même tronçon (PK en mètres)
     * @return list<array{from: int, to: int, kvb: bool}>
     */
    public static function split(int $from, int $to, array $portions): array
    {
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        // Tranche plus courte que l'écart toléré : équipée si une portion la contient ou la recouvre en partie.
        if ($to - $from < self::MIN_PIECE_M) {
            foreach ($portions as [$a, $b]) {
                [$a, $b] = [min($a, $b), max($a, $b)];
                if (($a <= $from && $b >= $to) || min($b, $to) - max($a, $from) > 0) {
                    return [['from' => $from, 'to' => $to, 'kvb' => true]];
                }
            }

            return [];
        }

        // Portions ramenées à la tranche, réunies quand elles se touchent ou sont séparées de moins de MIN_PIECE_M.
        $clipped = [];
        foreach ($portions as [$start, $end]) {
            $a = max($from, min($start, $end));
            $b = min($to, max($start, $end));
            if ($b > $a) {
                $clipped[] = [$a, $b];
            }
        }
        if ($clipped === []) {
            return [];
        }
        usort($clipped, fn (array $x, array $y): int => $x[0] <=> $y[0]);
        $union = [];
        foreach ($clipped as [$a, $b]) {
            $last = count($union) - 1;
            if ($last >= 0 && $a - $union[$last][1] < self::MIN_PIECE_M) {
                $union[$last][1] = max($union[$last][1], $b);
            } else {
                $union[] = [$a, $b];
            }
        }

        // Bornes à moins de MIN_PIECE_M d'un bout de la tranche : collées à ce bout ; morceaux KVB trop courts : écartés.
        $covered = [];
        foreach ($union as [$a, $b]) {
            if ($a - $from < self::MIN_PIECE_M) {
                $a = $from;
            }
            if ($to - $b < self::MIN_PIECE_M) {
                $b = $to;
            }
            if ($b - $a >= self::MIN_PIECE_M) {
                $covered[] = [$a, $b];
            }
        }
        if ($covered === []) {
            return [];
        }

        $pieces = [];
        $cursor = $from;
        foreach ($covered as [$a, $b]) {
            if ($a > $cursor) {
                $pieces[] = ['from' => $cursor, 'to' => $a, 'kvb' => false];
            }
            $pieces[] = ['from' => $a, 'to' => $b, 'kvb' => true];
            $cursor = $b;
        }
        if ($cursor < $to) {
            $pieces[] = ['from' => $cursor, 'to' => $to, 'kvb' => false];
        }

        return $pieces;
    }
}
