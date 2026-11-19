<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Envoi commun des notifications des contributions (ContributionNotifier) et des signalements
 * d'erreur (ErrorReportNotifier).
 *
 * L'envoi attend la validation de la transaction en cours (rien n'est annoncé si la décision est
 * annulée) et ne la fait jamais échouer : une erreur pendant l'envoi est signalée (report) sans
 * interrompre la décision.
 */
final readonly class SiteNotifier
{
    /**
     * Chaque validateur et administrateur, sauf le membre `$exceptUserId` (l'auteur de ce qui est
     * annoncé ne reçoit pas l'annonce de son propre envoi).
     */
    public function toReviewers(Notification $notification, ?int $exceptUserId = null): void
    {
        $reviewers = User::query()
            ->reviewers()
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->orderBy('id')
            ->get();

        $this->send($reviewers, $notification);
    }

    /** @param  iterable<User>|User|null  $recipients */
    public function send(iterable|User|null $recipients, Notification $notification): void
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

    /**
     * Une adresse électronique sans compte (personne qui a laissé son adresse dans un formulaire) :
     * même règles d'envoi, après la validation de la transaction et sans jamais la faire échouer.
     */
    public function toAddress(string $email, Notification $notification): void
    {
        DB::afterCommit(function () use ($email, $notification): void {
            try {
                NotificationFacade::route('mail', $email)->notify($notification);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
