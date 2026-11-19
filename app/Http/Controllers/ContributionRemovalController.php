<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ContributionStatus;
use App\Http\Requests\RemoveContributionRequest;
use App\Models\Contribution;
use App\Notifications\ContributionRemoved;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Retrait d'une contribution non publiée par un administrateur (CGU, « Validation et
 * modération ») : la contribution est supprimée, son auteur reçoit le motif par courriel et la
 * décision entre au journal d'audit. Une contribution déjà appliquée ne se retire pas : ses
 * données sont dans l'encyclopédie et se corrigent par une nouvelle contribution.
 */
class ContributionRemovalController extends Controller
{
    public function __invoke(RemoveContributionRequest $request, Contribution $contribution, AuditLog $audit): RedirectResponse
    {
        // Contrôle et suppression dans la même transaction, la ligne verrouillée : une approbation
        // concurrente (qui modifie la contribution) attend la fin du retrait, ou le retrait voit
        // le statut qu'elle vient d'écrire. Jamais de retrait d'une contribution tout juste publiée.
        $removed = DB::transaction(function () use ($contribution, $request, $audit): ?Contribution {
            $locked = Contribution::query()->whereKey($contribution->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === ContributionStatus::Applied) {
                return null;
            }

            $audit->record(AuditAction::ContributionRemoved, $request->user(), context: [
                'removed_contribution_id' => $locked->id,
                'type' => $locked->type,
                'status' => $locked->status->value,
            ]);
            $locked->delete();

            return $locked;
        });

        if ($removed === null) {
            return back()->with('error', __('account.errors.removal_published'));
        }

        $author = $removed->user;
        $notification = new ContributionRemoved($removed->id, $removed->created_at ?? now(), $request->validated('reason'));

        // Après la transaction : un courriel en échec n'annule pas le retrait (il est signalé).
        try {
            $author?->notifyNow($notification);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', __('account.flash.contribution_removed'));
    }
}
