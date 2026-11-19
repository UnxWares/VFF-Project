<?php

namespace App\Services\Account;

use App\Enums\AuditAction;
use App\Enums\SuspensionOutcome;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AccountSuspended;
use App\Notifications\AccountSuspensionLifted;
use App\Services\AuditLog;
use App\Services\Notifications\SiteNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Suspension d'un compte par un administrateur, et levée avant terme.
 *
 * Un compte suspendu ne peut plus proposer, relire ni signaler (EnsureAccountNotSuspended) mais
 * garde l'accès à ses données (export, suppression). La décision est consignée au journal d'audit
 * (sans motif ni nom) et annoncée au membre sur le site et par courriel, avec le motif.
 *
 * Les administrateurs sont verrouillés dans l'ordre de leur identifiant avant toute décision : deux
 * administrateurs qui se suspendent l'un l'autre au même instant ne peuvent pas, ensemble,
 * laisser le site sans administrateur en mesure d'agir.
 */
final readonly class AccountSuspension
{
    public function __construct(private AuditLog $audit, private SiteNotifier $notifier) {}

    /**
     * @param  Carbon|null  $until  dernier jour de la suspension (fin de journée), sinon jusqu'à la levée
     */
    public function suspend(User $account, User $admin, string $reason, ?Carbon $until): SuspensionOutcome
    {
        $reason = trim($reason);

        $outcome = DB::transaction(function () use ($account, $admin, $reason, $until): SuspensionOutcome {
            $this->lockAdministrators();
            $target = User::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $actor = $admin->fresh() ?? $admin;

            if ($actor->isSuspended()) {
                return SuspensionOutcome::ActorSuspended;
            }
            if ($target->isSuspended()) {
                return SuspensionOutcome::AlreadySuspended;
            }
            if ($target->isLastAdmin()) {
                return SuspensionOutcome::LastAdmin;
            }

            $target->forceFill([
                'suspended_at' => now(),
                'suspended_until' => $until,
                'suspension_reason' => $reason,
                'suspended_by' => $actor->id,
            ])->save();

            $this->audit->record(AuditAction::AccountSuspended, $actor, context: [
                'account_id' => $target->id,
                'with_end_date' => $until !== null,
                'ends_at' => $until?->toDateString(),
            ]);
            $this->notifier->send($target, new AccountSuspended($reason, $until));

            return SuspensionOutcome::Done;
        });

        if ($outcome === SuspensionOutcome::Done) {
            $account->refresh();
        }

        return $outcome;
    }

    public function lift(User $account, User $admin): SuspensionOutcome
    {
        $outcome = DB::transaction(function () use ($account, $admin): SuspensionOutcome {
            $this->lockAdministrators();
            $target = User::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $actor = $admin->fresh() ?? $admin;

            if ($actor->isSuspended()) {
                return SuspensionOutcome::ActorSuspended;
            }
            // Une suspension échue mais jamais levée à la main (colonnes encore remplies) se
            // nettoie ici sans annonce : le compte est déjà rétabli.
            if ($target->suspended_at === null) {
                return SuspensionOutcome::NotSuspended;
            }
            $wasActive = $target->isSuspended();

            $target->forceFill([
                'suspended_at' => null,
                'suspended_until' => null,
                'suspension_reason' => null,
                'suspended_by' => null,
            ])->save();

            if (! $wasActive) {
                return SuspensionOutcome::NotSuspended;
            }

            $this->audit->record(AuditAction::AccountSuspensionLifted, $actor, context: ['account_id' => $target->id]);
            $this->notifier->send($target, new AccountSuspensionLifted);

            return SuspensionOutcome::Done;
        });

        if ($outcome === SuspensionOutcome::Done) {
            $account->refresh();
        }

        return $outcome;
    }

    /** Verrouille les administrateurs, dans l'ordre de leur identifiant (pas d'interblocage). */
    private function lockAdministrators(): void
    {
        User::query()->where('role', UserRole::Admin->value)->orderBy('id')->lockForUpdate()->get(['id']);
    }
}
