<?php

namespace App\Services\Notifications;

use App\Models\Contribution;
use App\Models\User;
use App\Notifications\ContributionApplied;
use App\Notifications\ContributionApproved;
use App\Notifications\ContributionAwaitingReview;
use App\Notifications\ContributionNotification;
use App\Notifications\ContributionRejected;
use App\Notifications\ContributionRemoved;

/**
 * Prévient les membres concernés à chaque étape d'une contribution : les validateurs quand elle
 * arrive, son auteur quand elle est approuvée, publiée, refusée ou retirée.
 *
 * L'envoi (SiteNotifier) attend la validation de la transaction en cours et ne la fait jamais
 * échouer : la notification du site est écrite pendant la requête, les courriels partent dans la
 * file d'attente (ContributionNotification).
 */
final readonly class ContributionNotifier
{
    public function __construct(private SiteNotifier $notifier) {}

    /** Contribution envoyée : chaque validateur et administrateur, sauf son auteur. */
    public function submitted(Contribution $contribution): void
    {
        $this->notifier->toReviewers(
            new ContributionAwaitingReview($contribution->id, ContributionNotification::subjectOf($contribution)),
            $contribution->user_id,
        );
    }

    /** Approbation qui ne suffit pas encore à publier : `$approvals` reçues sur les `$threshold` requises. */
    public function approved(Contribution $contribution, int $approvals, int $threshold): void
    {
        $this->send($contribution->user, new ContributionApproved(
            $contribution->id,
            $approvals,
            $threshold,
            ContributionNotification::subjectOf($contribution),
        ));
    }

    /** Dernière approbation requise : la contribution est appliquée. */
    public function applied(Contribution $contribution): void
    {
        $this->send($contribution->user, new ContributionApplied($contribution->id, ContributionNotification::subjectOf($contribution)));
    }

    /** Refus, avec le motif du validateur (review_notes) s'il en a donné un. */
    public function rejected(Contribution $contribution): void
    {
        $this->send($contribution->user, new ContributionRejected(
            $contribution->id,
            ContributionNotification::subjectOf($contribution),
            $contribution->review_notes,
        ));
    }

    /** Retrait par un administrateur (la contribution vient d'être supprimée). */
    public function removed(Contribution $contribution, string $reason): void
    {
        $this->send($contribution->user, new ContributionRemoved(
            $contribution->id,
            $contribution->created_at ?? now(),
            $reason,
            ContributionNotification::subjectOf($contribution),
        ));
    }

    private function send(?User $recipient, ContributionNotification $notification): void
    {
        $this->notifier->send($recipient, $notification);
    }
}
