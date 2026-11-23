<?php

namespace App\Enums;

/**
 * Famille de cantonnement d'une tranche de signalisation (`line_signalling.signalling`), pour le
 * calque « Signalisation » de la carte : cinq teintes au lieu de quatorze codes (plus la commande centralisée, qui
 * n'est pas un cantonnement et se dessine en trait fin par-dessus).
 *
 * Le cantonnement espace les trains ; le contrôle de vitesse (KVB, KVBP) est une autre notion,
 * portée par `train_protection`, et n'entre dans aucune de ces familles. Sur les lignes à grande
 * vitesse, TVM et ETCS assurent à la fois le cantonnement (signalisation en cabine) et le contrôle.
 */
enum SignallingFamily: string
{
    /** Block automatique lumineux : BAL, BAL de voie banalisée, block automatique tram. */
    case AutomaticBlock = 'automatic_block';

    /** Block automatique à permissivité restreinte (BAPR), double voie ou voie banalisée. */
    case RestrictedBlock = 'restricted_block';

    /** Block manuel, de double voie (BM) ou de voie unique (BMVU). */
    case ManualBlock = 'manual_block';

    /** Cantonnement téléphonique (CT, CT-VU) et cantonnement assisté par informatique (CAPI). */
    case Telephone = 'telephone';

    /** Signalisation en cabine des lignes à grande vitesse : TVM 300, TVM 430, ETCS niveaux 1 et 2. */
    case Cab = 'cab';

    /**
     * Commande centralisée (CC : CCVU, CCR, PCD) : commande à distance des postes depuis un poste central. Ce n'est pas un
     * cantonnement : elle se combine avec l'un d'eux sans en remplacer aucun, d'où un trait fin posé par-dessus sur la
     * carte des époques passées (l'état actuel de SNCF Réseau ne la porte pas).
     */
    case CentralizedControl = 'centralized_control';

    /** Famille d'un code de cantonnement VFF ; null pour un code inconnu et pour `NONE` (« sans cantonnement » : rien à dessiner). */
    public static function fromCode(string $code): ?self
    {
        return match (true) {
            str_starts_with($code, 'BAL') => self::AutomaticBlock,
            str_starts_with($code, 'BAPR') => self::RestrictedBlock,
            $code === 'BM', str_starts_with($code, 'BMVU') => self::ManualBlock,
            str_starts_with($code, 'CT'), $code === 'CAPI' => self::Telephone,
            // TVM et ETCS sans version ni niveau : codes de l'historique daté (line_signalling_events).
            $code === 'TVM', $code === 'ETCS', str_starts_with($code, 'TVM-'), str_starts_with($code, 'ETCS_') => self::Cab,
            $code === 'CC' => self::CentralizedControl,
            default => null,
        };
    }

    /** Expression SQL qui ramène une colonne de code de cantonnement à sa famille (même règle que fromCode). */
    public static function sql(string $column): string
    {
        return "CASE
            WHEN {$column} LIKE 'BAL%' THEN '".self::AutomaticBlock->value."'
            WHEN {$column} LIKE 'BAPR%' THEN '".self::RestrictedBlock->value."'
            WHEN {$column} = 'BM' OR {$column} LIKE 'BMVU%' THEN '".self::ManualBlock->value."'
            WHEN {$column} LIKE 'CT%' OR {$column} = 'CAPI' THEN '".self::Telephone->value."'
            WHEN {$column} = 'CC' THEN '".self::CentralizedControl->value."'
            WHEN {$column} IN ('TVM', 'ETCS') OR {$column} LIKE 'TVM-%' OR {$column} LIKE 'ETCS\\_%' THEN '".self::Cab->value."'
        END";
    }
}
