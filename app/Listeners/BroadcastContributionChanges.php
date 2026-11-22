<?php

namespace App\Listeners;

use App\Enums\ContributionRealtimeEvent;
use App\Enums\ContributionStatus;
use App\Events\ContributionChanged;
use App\Models\Contribution;
use App\Support\Realtime;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Throwable;

/**
 * Diffuse en temps réel les changements d'état d'une contribution, branché par RealtimeServiceProvider
 * sur les événements du modèle (quel que soit le chemin : site, GraphQL, éditeur de tracé) :
 *
 *   - création : `contribution.submitted` ;
 *   - passage à « approuvée », « appliquée » ou « refusée » : `contribution.approved`,
 *     `contribution.applied` ou `contribution.rejected`.
 *
 * Deux sorties : l'événement ContributionChanged (canaux Laravel Echo) et les abonnements GraphQL
 * (`reviewQueueChanged`, `myContributionChanged`, `contributionApplied`). Rien ne part tant que le
 * temps réel est désactivé (App\Support\Realtime), et rien avant la validation de la transaction en
 * cours : une publication annulée n'est jamais annoncée.
 */
final readonly class BroadcastContributionChanges
{
    public function __construct(private Dispatcher $events) {}

    public function created(Contribution $contribution): void
    {
        $kind = $contribution->status === ContributionStatus::Pending
            ? ContributionRealtimeEvent::Submitted
            : ContributionRealtimeEvent::forStatus($contribution->status);
        $this->announce($contribution, $kind);
    }

    public function updated(Contribution $contribution): void
    {
        if (! $contribution->wasChanged('status')) {
            return;
        }
        $this->announce($contribution, ContributionRealtimeEvent::forStatus($contribution->status));
    }

    private function announce(Contribution $contribution, ?ContributionRealtimeEvent $kind): void
    {
        if ($kind === null || ! Realtime::enabled()) {
            return;
        }

        DB::afterCommit(function () use ($contribution, $kind): void {
            // Serveur temps réel injoignable : l'erreur va au journal, la contribution reste enregistrée.
            try {
                $event = ContributionChanged::for($contribution, $kind);
                $this->events->dispatch($event);

                // Racine des abonnements GraphQL : la charge utile publique et l'auteur, qui choisit le
                // sujet de `myContributionChanged` (jamais renvoyé au client).
                $root = ['payload' => $event->payload, 'author_id' => $event->authorId];
                Subscription::broadcast('reviewQueueChanged', $root);
                if ($event->authorId !== null) {
                    Subscription::broadcast('myContributionChanged', $root);
                }
                if ($kind->isPublic()) {
                    Subscription::broadcast('contributionApplied', $root);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
