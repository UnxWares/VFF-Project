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
    /**
     * Compte administrateur créé ou promu en console (vff:make-admin). Contexte : identifiant du
     * compte, création ou promotion, rôle précédent ; jamais l'adresse ni le mot de passe.
     */
    case AdminGranted = 'admin_granted';
    /** Dernier administrateur inactif gardé malgré la règle des 3 ans, signalé à l'adresse de contact. */
    case LastAdminInactive = 'last_admin_inactive';
    /** Information des membres avant un changement des CGU ou de la politique de confidentialité. */
    case MembersNotified = 'members_notified';
    /** Signalement d'erreur reçu depuis une fiche (contexte : numéro et sorte de fiche, jamais le texte). */
    case ErrorReportReceived = 'error_report_received';
    /** Signalement examiné et traité par un validateur ou un administrateur. */
    case ErrorReportResolved = 'error_report_resolved';
    /** Signalement examiné et écarté. */
    case ErrorReportRejected = 'error_report_rejected';
    /**
     * Compte suspendu par un administrateur. Contexte : `account_id` (retiré à la suppression du
     * compte, AccountDeletion), `with_end_date`, `ends_at` ; jamais le motif ni le nom.
     */
    case AccountSuspended = 'account_suspended';
    /** Suspension levée à la main par un administrateur (contexte : `account_id`). */
    case AccountSuspensionLifted = 'account_suspension_lifted';
    /**
     * Jeton d'API personnel créé par son titulaire (acteur). Contexte : `token_id` ; jamais le nom
     * du jeton ni son secret.
     */
    case ApiTokenCreated = 'api_token_created';
    /** Jeton d'API révoqué par son titulaire (acteur). Contexte : `token_id`. */
    case ApiTokenRevoked = 'api_token_revoked';
    /**
     * Profil « partenaire » de l'API accordé en console (vff:api-partner). Contexte : `account_id`
     * (retiré à la suppression du compte, AccountDeletion).
     */
    case ApiPartnerGranted = 'api_partner_granted';
    /** Profil « partenaire » de l'API retiré en console. Contexte : `account_id`. */
    case ApiPartnerRevoked = 'api_partner_revoked';
}
