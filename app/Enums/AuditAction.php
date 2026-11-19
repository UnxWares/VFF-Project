<?php

namespace App\Enums;

/** Actions consignées dans le journal d'audit (table audit_events). */
enum AuditAction: string
{
    /** Approbation d'une contribution, seuil d'application pas encore atteint. */
    case ContributionApproved = 'contribution_approved';
    /** Approbation qui atteint le seuil : la contribution est appliquée à l'encyclopédie. */
    case ContributionApplied = 'contribution_applied';
    case ContributionRejected = 'contribution_rejected';
    /** Retrait par un administrateur d'une contribution non publiée (contenu illicite, spam). */
    case ContributionRemoved = 'contribution_removed';
    /** Compte supprimé (par son titulaire ou pour inactivité), sans identifiant du compte. */
    case AccountDeleted = 'account_deleted';
    /** Passage de la tâche des comptes inactifs : nombre d'avertissements envoyés. */
    case InactivityWarningsSent = 'inactivity_warnings_sent';
    /** Information des membres avant un changement des CGU ou de la politique de confidentialité. */
    case MembersNotified = 'members_notified';
}
