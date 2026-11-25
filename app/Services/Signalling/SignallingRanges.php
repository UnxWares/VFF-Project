<?php

namespace App\Services\Signalling;

use App\Services\LineStations;
use Illuminate\Support\Facades\DB;

/**
 * Tranches de signalisation d'une ligne à une ère (`line_signalling`), dans l'ordre des points
 * kilométriques : même lecture pour la fiche ligne, l'API REST et GraphQL.
 *
 * `signalling` est le code principal ; `signalling_systems` liste tous les systèmes de cantonnement
 * installés sur la tranche (deux sur les LGV en ETCS niveau 2 et TVM). Une tranche écrite avant
 * cette colonne (liste vide) reçoit la liste de son seul code. `rg_troncon` : rang du tronçon de SNCF Réseau (deux
 * tronçons d'une ligne peuvent partager des PK), null pour une tranche d'une autre source ou écrite avant la colonne.
 */
final class SignallingRanges
{
    /**
     * @param  bool  $withoutBlockSystems  retire du contrôle de vitesse les systèmes déjà nommés comme
     *                                     cantonnement (la TVM est les deux : une seule mention sur la fiche)
     * @return list<array{
     *     pk_start_m: int, pk_end_m: int, pk_start_label: string, pk_end_label: string,
     *     length_m: int, signalling: string, signalling_systems: list<string>,
     *     train_protection: list<string>, kvb_equipped: bool, source_label: ?string, note: ?string, rg_troncon: ?int
     * }>
     */
    public function forLine(int $lineId, int $eraId, bool $withoutBlockSystems = false): array
    {
        $rows = DB::table('line_signalling')
            ->where('line_id', $lineId)
            ->where('era_id', $eraId)
            ->orderBy('pk_start_m')
            ->orderBy('id')
            ->get(['pk_start_m', 'pk_end_m', 'signalling', 'signalling_systems', 'train_protection', 'kvb_equipped', 'source_label', 'note', 'rg_troncon'])
            ->all();

        return array_map(function (object $r) use ($withoutBlockSystems): array {
            $systems = self::systems((string) $r->signalling, $r->signalling_systems);
            $protection = self::list($r->train_protection);
            if ($withoutBlockSystems) {
                $protection = array_values(array_diff($protection, $systems));
            }

            return [
                'pk_start_m' => (int) $r->pk_start_m,
                'pk_end_m' => (int) $r->pk_end_m,
                'pk_start_label' => (string) LineStations::pkLabel((int) $r->pk_start_m),
                'pk_end_label' => (string) LineStations::pkLabel((int) $r->pk_end_m),
                'length_m' => (int) ($r->pk_end_m - $r->pk_start_m),
                'signalling' => (string) $r->signalling,
                'signalling_systems' => $systems,
                'train_protection' => $protection,
                'kvb_equipped' => (bool) $r->kvb_equipped,
                'source_label' => $r->source_label,
                'note' => $r->note,
                'rg_troncon' => $r->rg_troncon !== null ? (int) $r->rg_troncon : null,
            ];
        }, $rows);
    }

    /**
     * Liste des systèmes d'une tranche, code principal en premier.
     *
     * @return list<string>
     */
    public static function systems(string $signalling, mixed $stored): array
    {
        $list = self::list($stored);
        if ($signalling !== '' && ! in_array($signalling, $list, true)) {
            array_unshift($list, $signalling);
        }

        return $list;
    }

    /** @return list<string> */
    private static function list(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;

        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded), fn (string $c): bool => $c !== '')) : [];
    }
}
