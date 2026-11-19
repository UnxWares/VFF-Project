<?php

namespace App\Services\Signalling;

/**
 * Faits d'historique de signalisation « en vigueur » à une date : ce que la carte par ère dessine et ce que
 * l'API renvoie avec `era`. Logique pure (aucun accès à la base), testée à part.
 *
 * Un fait est en vigueur s'il est daté au plus tard ce jour-là et qu'aucun fait plus récent du même groupe ne
 * le recouvre :
 *   - groupes : le cantonnement (`block` : BAL, BAPR, block manuel, cantonnement téléphonique : une portion n'a
 *     qu'un mode de cantonnement à la fois, le plus récent remplace l'autre), la TVM, l'ETCS (signalisation en
 *     cabine : l'ETCS s'ajoute souvent à la TVM sans la remplacer, d'où deux groupes), le KVB ;
 *   - recouvrement : le fait récent porte sur toute la ligne, ou sa portion placée (PK) contient celle du fait
 *     ancien, à 200 m près. Un fait récent sans portion connue ne remplace rien : on ne sait pas où il s'applique.
 * Un remplacement que l'article ne date pas reste donc invisible : le fait ancien est montré tel quel.
 */
final class SignallingHistoryState
{
    /** Tolérance (m) sur les bornes de deux portions placées. */
    private const TOLERANCE_M = 200;

    /**
     * @param  list<array<string, mixed>>  $facts  faits de LineSignallingHistory::facts (sûrs seulement) : system, role,
     *                                             date, scope, section
     * @param  string  $until  date AAAA-MM-JJ (fin de l'ère, ou aujourd'hui pour l'ère courante)
     * @return list<array<string, mixed>> faits en vigueur, du plus ancien au plus récent
     */
    public static function inForce(array $facts, string $until): array
    {
        $dated = array_values(array_filter($facts, fn (array $f): bool => $f['date'] <= $until));
        $kept = array_filter($dated, function (array $old) use ($dated): bool {
            foreach ($dated as $new) {
                if ($new['date'] > $old['date'] && self::group($new) === self::group($old) && self::covers($new, $old)) {
                    return false;
                }
            }

            return true;
        });
        $kept = array_values($kept);
        usort($kept, fn (array $a, array $b): int => [$a['date'], $a['system']] <=> [$b['date'], $b['system']]);

        return $kept;
    }

    /** Dernier jour d'une ère passée (31 décembre de l'année), ou aujourd'hui pour l'ère courante. */
    public static function until(int $year, bool $current, ?\DateTimeInterface $today = null): string
    {
        return $current ? ($today ?? new \DateTimeImmutable)->format('Y-m-d') : sprintf('%04d-12-31', $year);
    }

    /** @param  array{system: string, role: string}  $fact */
    public static function group(array $fact): string
    {
        return match ($fact['role']) {
            'block' => 'block',
            'cab' => SignallingSystems::base($fact['system']),
            default => $fact['system'],
        };
    }

    /**
     * Le fait récent recouvre-t-il le fait ancien ?
     *
     * @param  array{scope: string, section: array<string, mixed>|null}  $new
     * @param  array{scope: string, section: array<string, mixed>|null}  $old
     */
    public static function covers(array $new, array $old): bool
    {
        if ($new['scope'] === 'whole_line') {
            return true;
        }
        [$ns, $ne] = self::range($new);
        [$os, $oe] = self::range($old);
        if ($ns === null || $os === null) {
            return false;
        }

        return $ns <= $os + self::TOLERANCE_M && $ne >= $oe - self::TOLERANCE_M;
    }

    /**
     * PK de la portion placée, ou [null, null].
     *
     * @param  array{section: array<string, mixed>|null}  $fact
     * @return array{0: ?int, 1: ?int}
     */
    private static function range(array $fact): array
    {
        $s = $fact['section'];
        if (! is_array($s) || ! isset($s['pk_start_m'], $s['pk_end_m'])) {
            return [null, null];
        }

        return [(int) $s['pk_start_m'], (int) $s['pk_end_m']];
    }
}
