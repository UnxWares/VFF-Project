<?php

namespace App\Enums;

/** État d'un signalement d'erreur (table error_reports). */
enum ErrorReportStatus: string
{
    /** Reçu, pas encore examiné par l'équipe. */
    case Open = 'open';
    /** Examiné : l'erreur est corrigée (ou la fiche déjà juste), avec une note de l'équipe. */
    case Resolved = 'resolved';
    /** Examiné et écarté (signalement infondé, hors sujet, abusif). */
    case Rejected = 'rejected';

    /** Un signalement traité ou rejeté ne revient plus dans la liste des signalements ouverts. */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
