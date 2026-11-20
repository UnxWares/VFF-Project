<?php

namespace App\Services\Contributions;

/** Issue d'une relecture (ContributionReview) : décision enregistrée ou raison du refus de la traiter. */
enum ReviewOutcome: string
{
    /** Approbation enregistrée, le nombre requis n'est pas encore atteint. */
    case Approved = 'approved';
    /** Dernière approbation requise : la contribution est appliquée à l'encyclopédie. */
    case Applied = 'applied';
    /** Contribution refusée, avec son motif. */
    case Rejected = 'rejected';
    /** Le validateur est l'auteur de la contribution. */
    case Own = 'own';
    /** Contribution déjà refusée ou publiée. */
    case Closed = 'closed';
    /** Le validateur a déjà approuvé cette contribution. */
    case AlreadyApproved = 'already_approved';
    /** L'application a échoué (rien n'est écrit, l'approbation est annulée). */
    case Failed = 'failed';

    /** Vrai quand la décision a été enregistrée. */
    public function isRecorded(): bool
    {
        return match ($this) {
            self::Approved, self::Applied, self::Rejected => true,
            self::Own, self::Closed, self::AlreadyApproved, self::Failed => false,
        };
    }
}
