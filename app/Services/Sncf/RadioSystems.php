<?php

namespace App\Services\Sncf;

/**
 * Liaison radio sol-train du jeu SNCF Open Data « Lignes équipées de liaison radio sol-train » (SNCF Réseau,
 * ODbL), champ `rst`. Cinq libellés, publiés tels quels par SNCF Réseau (fautes comprises) :
 *
 * - `gsm-r` : « GSMR (Global System for Mobile Raywails) », réseau GSM ferroviaire dédié ;
 * - `gsm-gfu` : « Global System for Mobil- Groupe Fermé d'utilisateurs », GSM d'un opérateur public réservé à
 *   un groupe fermé d'utilisateurs (solution des lignes peu circulées) ;
 * - `rst` : « Sans transmission de données », radio sol-train analogique (phonie seulement) ;
 * - `rst-data` : « Avec transmission de données », radio sol-train analogique avec transmission de données ;
 * - `rst-id` : « Sans transmission de données avec identification », analogique avec identification du train.
 *
 * Analyse pure (aucun accès réseau ni base), utilisée par vff:import-sncf-radio.
 */
final class RadioSystems
{
    public const DATASET = 'lignes-equipees-de-liaison-radio-sol-train';

    public const LICENSE = 'ODbL 1.0';

    public const ATTRIBUTION = 'SNCF Réseau';

    /** Systèmes, du plus récent au plus ancien (ordre des listes de l'API). */
    public const SYSTEMS = ['gsm-r', 'gsm-gfu', 'rst-data', 'rst-id', 'rst'];

    /** Système d'un libellé SNCF, null s'il est inconnu (la portion est alors écartée et signalée). */
    public static function system(?string $label): ?string
    {
        $l = mb_strtolower(trim((string) $label));

        return match (true) {
            $l === '' => null,
            str_starts_with($l, 'gsmr'), str_starts_with($l, 'gsm-r') => 'gsm-r',
            str_contains($l, 'groupe fermé') => 'gsm-gfu',
            str_contains($l, 'avec identification') => 'rst-id',
            str_starts_with($l, 'avec transmission') => 'rst-data',
            str_starts_with($l, 'sans transmission') => 'rst',
            default => null,
        };
    }

    /**
     * Longueur couverte par système (km) pour chaque code de ligne. Un PK illisible compte 1 km, comme dans
     * TrackRegime::summarize.
     *
     * @param  iterable<array<string, mixed>>  $records
     * @return array<array-key, array<string, float>> code (entier si numérique) => [système => km], systèmes dans l'ordre de SYSTEMS
     */
    public static function summarize(iterable $records): array
    {
        $out = [];
        foreach ($records as $r) {
            $code = trim((string) ($r['code_ligne'] ?? ''));
            $system = self::system($r['rst'] ?? null);
            if ($code === '' || $system === null) {
                continue;
            }
            $from = TrackRegime::pkKm($r['pkd'] ?? null);
            $to = TrackRegime::pkKm($r['pkf'] ?? null);
            $out[$code][$system] = ($out[$code][$system] ?? 0.0) + ($from !== null && $to !== null ? abs($to - $from) : 1.0);
        }
        foreach ($out as $code => $systems) {
            $sorted = [];
            foreach (self::SYSTEMS as $s) {
                if (isset($systems[$s])) {
                    $sorted[$s] = round($systems[$s], 1);
                }
            }
            $out[$code] = $sorted;
        }
        ksort($out);

        return $out;
    }
}
