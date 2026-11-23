<?php

namespace App\Services\Signalling;

/**
 * Cantonnement déduit du régime d'exploitation pour les tranches « _Autre » du jeu SNCF Réseau (voir
 * `database/data/signalling_regime_systems.php`, qui cite la source de chaque règle). Analyse pure.
 */
final class SignallingRegimeRules
{
    /** Libellé SNCF de la tranche dont le système n'est pas dit. */
    public const OTHER_LABEL = '_Autre';

    /** @var array<string, array{system: string, source: string}>|null */
    private static ?array $rules = null;

    /** @return array<string, array{system: string, source: string}> */
    public static function rules(): array
    {
        return self::$rules ??= (array) require __DIR__.'/../../../database/data/signalling_regime_systems.php';
    }

    /**
     * Portions d'une tranche « _Autre » dont le régime donne un système : intersection avec les portions de régime de la
     * même ligne, sans recouvrement (la première portion l'emporte), dans l'ordre des PK.
     *
     * @param  list<array{from: int, to: int, regime: string}>  $regimes  portions de régime de la ligne (PK en mètres)
     * @return list<array{from: int, to: int, system: string, source: string}>
     */
    public static function pieces(int $from, int $to, array $regimes): array
    {
        $rules = self::rules();
        $pieces = [];
        foreach ($regimes as $r) {
            $rule = $rules[trim($r['regime'])] ?? null;
            if ($rule === null) {
                continue;
            }
            $a = max($from, min($r['from'], $r['to']));
            $b = min($to, max($r['from'], $r['to']));
            foreach ($pieces as $p) {
                // Retire de la portion ce que couvre déjà une portion retenue (cas rare : régimes qui se chevauchent).
                if ($a < $p['to'] && $b > $p['from']) {
                    if ($a >= $p['from']) {
                        $a = $p['to'];
                    } elseif ($b <= $p['to']) {
                        $b = $p['from'];
                    }
                }
            }
            if ($b - $a > 0) {
                $pieces[] = ['from' => $a, 'to' => $b, 'system' => $rule['system'], 'source' => $rule['source']];
            }
        }
        usort($pieces, fn (array $x, array $y): int => $x['from'] <=> $y['from']);

        return $pieces;
    }
}
