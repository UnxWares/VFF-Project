<?php

namespace App\Services\Sncf;

/**
 * Régime d'exploitation des voies du jeu SNCF Open Data « Régime d'exploitation des lignes »
 * (SNCF Réseau, ODbL) : double voie, voie banalisée, voie unique et ses variantes. Le jeu ne dit rien
 * du trafic (voyageurs, fret) ni de l'ouverture : seulement le nombre de voies principales et leur
 * mode d'exploitation. Analyse pure (aucun accès réseau ni base), utilisée par
 * vff:measure-sncf-track-regime.
 */
final class TrackRegime
{
    public const DATASET = 'regime-dexploitation-des-lignes';

    public const LICENSE = 'ODbL 1.0';

    public const ATTRIBUTION = 'SNCF Réseau';

    /** Part de la longueur d'une ligne au-delà de laquelle son régime est dit uniforme. */
    public const UNIFORM_SHARE = 0.9;

    /**
     * Nombre de voies principales d'un régime SNCF, null si le libellé ne l'établit pas (« _Autre »).
     * « Voie banalisée » ne dit pas le nombre de voies (St-Gervais - Vallorcine ou Mignaloux - Bersac sont à voie
     * unique, St-André-le-Gaz - Chambéry à double voie) : null, comme « _Autre ».
     */
    public static function tracks(?string $regime): ?int
    {
        $regime = mb_strtolower(trim((string) $regime));

        return match (true) {
            $regime === '' || str_starts_with($regime, '_') => null,
            str_contains($regime, 'double voie') => 2,
            str_contains($regime, 'voie unique'), str_contains($regime, 'navette') => 1,
            default => null,
        };
    }

    /** Régime banalisé : exploitation dans les deux sens sur chaque voie, sans nombre de voies établi. */
    public static function isBanalised(?string $regime): bool
    {
        return str_contains(mb_strtolower((string) $regime), 'banalis');
    }

    /** PK SNCF (« 017+250 », « 001-250 ») en kilomètres, null s'il est écrit autrement (« D+841 »). */
    public static function pkKm(?string $pk): ?float
    {
        if ($pk === null || ! preg_match('/^(\d+)([+-])(\d{1,3})$/', trim($pk), $m)) {
            return null;
        }

        return (int) $m[1] + ($m[2] === '-' ? -1 : 1) * ((int) $m[3]) / 1000;
    }

    /**
     * Regroupe les enregistrements par code de ligne et retient le nombre de voies dominant (en longueur).
     * Un enregistrement dont le PK est illisible pèse 1 km : il compte sans dominer une vraie longueur.
     *
     * @param  iterable<array<string, mixed>>  $records
     * @return array<string, array{tracks: int, share: float, uniform: bool, regimes: list<string>, km: float, name: string, records: int}>
     */
    public static function summarize(iterable $records): array
    {
        $byCode = [];
        foreach ($records as $r) {
            $code = trim((string) ($r['code_ligne'] ?? ''));
            $n = self::tracks($r['exploitati'] ?? null);
            if ($code === '' || $n === null) {
                continue;
            }
            $from = self::pkKm($r['pkd'] ?? null);
            $to = self::pkKm($r['pkf'] ?? null);
            $km = $from !== null && $to !== null ? abs($to - $from) : 1.0;
            $byCode[$code]['name'] ??= (string) ($r['lib_ligne'] ?? '');
            $byCode[$code]['records'] = ($byCode[$code]['records'] ?? 0) + 1;
            $byCode[$code]['km'][$n] = ($byCode[$code]['km'][$n] ?? 0.0) + $km;
            $byCode[$code]['regimes'][(string) $r['exploitati']] = true;
        }

        $out = [];
        foreach ($byCode as $code => $d) {
            $total = array_sum($d['km']);
            arsort($d['km']);
            $tracks = (int) array_key_first($d['km']);
            $share = $total > 0 ? $d['km'][$tracks] / $total : 1.0;
            $regimes = array_keys($d['regimes']);
            sort($regimes);
            $out[(string) $code] = ['tracks' => $tracks, 'share' => round($share, 3), 'uniform' => $share >= self::UNIFORM_SHARE,
                'regimes' => $regimes, 'km' => round($total, 1), 'name' => $d['name'], 'records' => $d['records']];
        }
        ksort($out);

        return $out;
    }

    /**
     * Confronte le régime d'une ligne à ce que VFF sait déjà (nombre de voies de la fiche, Wikidata).
     *
     * @param  array{tracks: int, uniform: bool}  $summary
     * @return 'new'|'agree'|'contradiction'|'mixed'
     *                                               new : aucun nombre de voies connu ; agree : même nombre ; contradiction : nombre différent
     *                                               sur une ligne au régime uniforme ; mixed : régime partagé (le nombre dominant ne tranche pas)
     */
    public static function verdict(array $summary, ?int $known): string
    {
        if ($known === null) {
            return 'new';
        }
        if ($known === $summary['tracks']) {
            return 'agree';
        }

        return $summary['uniform'] ? 'contradiction' : 'mixed';
    }
}
