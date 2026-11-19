<?php

namespace App\Services\Notifications;

use App\Enums\UserRole;
use App\Models\Contribution;
use App\Models\User;
use App\Notifications\ContributionApplied;
use App\Notifications\ContributionApproved;
use App\Notifications\ContributionAwaitingReview;
use App\Notifications\ContributionNotification;
use App\Notifications\ContributionRejected;
use App\Notifications\ContributionRemoved;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Prévient les membres concernés à chaque étape d'une contribution : les validateurs quand elle
 * arrive, son auteur quand elle est approuvée, publiée, refusée ou retirée.
 *
 * L'envoi attend la validation de la transaction en cours (rien n'est annoncé si la décision est
 * annulée) et ne la fait jamais échouer : une messagerie absente ou en panne est signalée
 * (report), la notification sur le site étant enregistrée avant le courriel.
 */
final readonly class ContributionNotifier
{
    /** Contribution envoyée : chaque validateur et administrateur, sauf son auteur. */
    public function submitted(Contribution $contribution): void
    {
        $reviewers = User::query()
            ->whereIn('role', [UserRole::Validator->value, UserRole::Admin->value])
            ->when($contribution->user_id !== null, fn ($query) => $query->whereKeyNot($contribution->user_id))
            ->orderBy('id')
            ->get();

        $this->send($reviewers, new ContributionAwaitingReview($contribution->id, ContributionNotification::subjectOf($contribution)));
    }

    /** Première approbation sur deux. */
    public function approved(Contribution $contribution): void
    {
        $this->send($contribution->user, new ContributionApproved($contribution->id, ContributionNotification::subjectOf($contribution)));
    }

    /** Seconde approbation : la contribution est appliquée. */
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

    /** @param  iterable<User>|User|null  $recipients */
    private function send(iterable|User|null $recipients, ContributionNotification $notification): void
    {
        $recipients = match (true) {
            $recipients === null => [],
            $recipients instanceof User => [$recipients],
            default => $recipients,
        };

        DB::afterCommit(function () use ($recipients, $notification): void {
            foreach ($recipients as $recipient) {
                try {
                    $recipient->notify($notification);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
