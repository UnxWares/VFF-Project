<?php

namespace App\Enums;

/** Sorte de notification, gardée dans `notifications.data.kind` (stable : ne pas renommer). */
enum NotificationKind: string
{
    /** Pour les validateurs : une contribution vient d'être envoyée et attend leur relecture. */
    case AwaitingReview = 'contribution_awaiting_review';
    /** Pour l'auteur : première approbation, il en faut une seconde avant la publication. */
    case Approved = 'contribution_approved';
    /** Pour l'auteur : seconde approbation, la contribution est appliquée à l'encyclopédie. */
    case Applied = 'contribution_applied';
    /** Pour l'auteur : contribution refusée par un validateur (avec le motif s'il en a donné un). */
    case Rejected = 'contribution_rejected';
    /** Pour l'auteur : contribution non publiée retirée par un administrateur, avec le motif. */
    case Removed = 'contribution_removed';
}
