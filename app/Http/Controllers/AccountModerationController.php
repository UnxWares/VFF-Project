<?php

namespace App\Http\Controllers;

use App\Enums\SuspensionOutcome;
use App\Http\Requests\SuspendAccountRequest;
use App\Models\User;
use App\Services\Account\AccountSuspension;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comptes dans l'espace de modération (/validate/accounts), réservés aux administrateurs : liste
 * des comptes avec recherche, suspension avec motif et fin facultative, levée avant terme.
 * La suspension s'applique par EnsureAccountNotSuspended ; la décision est consignée au journal
 * d'audit et annoncée au membre (AccountSuspension).
 */
class AccountModerationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filter = $request->query('filter') === 'suspended' ? 'suspended' : 'all';
        $search = trim((string) $request->query('q', ''));

        $accounts = User::query()
            ->with('suspendedBy:id,name')
            ->withCount('contributions')
            ->when($filter === 'suspended', fn ($q) => $q->suspended())
            ->when($search !== '', function ($q) use ($search): void {
                // Les jokers de LIKE saisis par l'administrateur sont des caractères ordinaires.
                $like = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(email) LIKE ?', [$like]));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $actor = $request->user();

        return Inertia::render('Validate/Accounts', [
            'filter' => $filter,
            'search' => $search,
            'counts' => ['suspended' => User::query()->suspended()->count()],
            'accounts' => $accounts->through(function (User $u) use ($actor): array {
                $decision = Gate::forUser($actor)->inspect('suspend', $u);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->role->value,
                    // ISO 8601 : le navigateur formate la date dans la langue de la page.
                    'created_at' => $u->created_at?->toIso8601String(),
                    'contributions' => $u->contributions_count,
                    'suspension' => $u->isSuspended() ? [
                        'since' => $u->suspended_at?->toIso8601String(),
                        'until' => $u->suspended_until?->toIso8601String(),
                        'reason' => $u->suspension_reason,
                        'by' => $u->suspendedBy?->name,
                    ] : null,
                    'can_suspend' => $decision->allowed(),
                    // Pourquoi on ne peut pas suspendre ce compte (soi-même, dernier administrateur).
                    'blocked' => $decision->allowed() ? null : $decision->message(),
                ];
            }),
        ]);
    }

    public function suspend(SuspendAccountRequest $request, User $user, AccountSuspension $suspension): RedirectResponse
    {
        $outcome = $suspension->suspend($user, $request->user(), $request->reason(), $request->until());

        return $this->back($outcome, 'suspended', $user);
    }

    public function lift(Request $request, User $user, AccountSuspension $suspension): RedirectResponse
    {
        Gate::authorize('lift', $user);

        $outcome = $suspension->lift($user, $request->user());

        return $this->back($outcome, 'lifted', $user);
    }

    private function back(SuspensionOutcome $outcome, string $done, User $user): RedirectResponse
    {
        return match ($outcome) {
            SuspensionOutcome::Done => back()->with('success', __("moderation.flash.{$done}", ['name' => $user->name])),
            SuspensionOutcome::AlreadySuspended => back()->with('error', __('moderation.errors.already_suspended')),
            SuspensionOutcome::NotSuspended => back()->with('error', __('moderation.errors.not_suspended')),
            SuspensionOutcome::LastAdmin => back()->with('error', __('moderation.errors.last_admin')),
            SuspensionOutcome::ActorSuspended => abort(403, __('moderation.errors.suspended')),
        };
    }
}
