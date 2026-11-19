<?php

namespace App\Enums;

/**
 * Rôle d'un système de signalisation dans l'historique (`line_signalling_events.role`).
 *
 * Le cantonnement espace les trains ; le contrôle de vitesse en limite la vitesse ou arrête un train qui
 * franchit un signal fermé. Le KVB est un contrôle de vitesse et n'est jamais un cantonnement. Sur les
 * lignes à grande vitesse, la signalisation en cabine (TVM, ETCS) fait les deux.
 */
enum SignallingRole: string
{
    /** Cantonnement : BAL, BAPR, block manuel, cantonnement téléphonique. */
    case Block = 'block';

    /** Signalisation en cabine : TVM et ETCS (cantonnement et contrôle à la fois). */
    case Cab = 'cab';

    /** Contrôle de vitesse : KVB. */
    case SpeedControl = 'speed_control';

    /** Rôle d'un code de système de l'historique ; null pour un code inconnu. */
    public static function forSystem(string $system): ?self
    {
        return match (true) {
            in_array($system, ['BAL', 'BAPR', 'BM', 'CT'], true) => self::Block,
            $system === 'TVM', str_starts_with($system, 'TVM-'), $system === 'ETCS', str_starts_with($system, 'ETCS_') => self::Cab,
            $system === 'KVB' => self::SpeedControl,
            default => null,
        };
    }
}
