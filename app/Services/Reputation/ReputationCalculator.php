<?php

namespace App\Services\Reputation;

use App\Enums\ContributionStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Compteurs de réputation des membres, recalculés à partir de ce qui s'est réellement passé :
 *
 *   - `reputation_applied`  : contributions du membre au statut « appliquée » ;
 *   - `reputation_rejected` : contributions du membre au statut « refusée » ;
 *   - `reputation_reviews`  : relectures faites, c'est-à-dire approbations données plus refus
 *     décidés (jamais sur sa propre contribution).
 *
 * Le calcul est rejouable : il repart toujours des tables `contributions` et
 * `contribution_approvals`, jamais de la valeur déjà stockée, donc le refaire donne le même
 * résultat et rattrape toute dérive. Les comptes concernés sont verrouillés pendant le calcul : deux
 * décisions simultanées sur le même membre se suivent au lieu de s'écraser. Les écritures passent
 * par le constructeur de requêtes (ni `updated_at` ni événement : ce n'est pas une modification du
 * profil) et n'ont lieu que si un chiffre change.
 *
 * Appelé après chaque décision de validation (approbation, refus, retrait, suppression de compte)
 * et par `vff:recalculate-reputation` pour tout refaire.
 *
 * @phpstan-type Counters array{applied: int, rejected: int, reviews: int}
 */
final class ReputationCalculator
{
    /** Comptes traités par lot dans {@see recalculateAll()}. */
    private const CHUNK = 500;

    /**
     * Recalcule les comptes donnés, tout de suite (à appeler après la validation de la
     * transaction qui a changé les contributions : `DB::afterCommit`).
     *
     * @param  iterable<int|null>  $userIds  les valeurs nulles (compte supprimé) sont ignorées
     * @return int nombre de comptes dont un chiffre a changé
     */
    public function recalculate(iterable $userIds): int
    {
        $ids = [];
        foreach ($userIds as $id) {
            if ($id !== null) {
                $ids[(int) $id] = (int) $id;
            }
        }
        if ($ids === []) {
            return 0;
        }
        sort($ids);

        return DB::transaction(fn (): int => $this->apply($ids, dryRun: false));
    }

    /**
     * Recalcule tous les comptes, par lots.
     *
     * @return array{checked: int, changed: int} comptes lus et comptes dont un chiffre change (ou a changé)
     */
    public function recalculateAll(bool $dryRun = false): array
    {
        $checked = 0;
        $changed = 0;

        User::query()->orderBy('id')->select('id')->chunkById(self::CHUNK, function ($users) use ($dryRun, &$checked, &$changed): void {
            $ids = $users->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $checked += count($ids);
            $changed += $dryRun
                ? $this->apply($ids, dryRun: true)
                : DB::transaction(fn (): int => $this->apply($ids, dryRun: false));
        });

        return ['checked' => $checked, 'changed' => $changed];
    }

    /**
     * Chiffres attendus des comptes donnés, d'après les contributions et les approbations.
     *
     * @param  list<int>  $ids
     * @return array<int, Counters>
     */
    public function countersFor(array $ids): array
    {
        $counters = [];
        foreach ($ids as $id) {
            $counters[$id] = ['applied' => 0, 'rejected' => 0, 'reviews' => 0];
        }

        $byStatus = DB::table('contributions')
            ->whereIn('user_id', $ids)
            ->whereIn('status', [ContributionStatus::Applied->value, ContributionStatus::Rejected->value])
            ->groupBy('user_id', 'status')
            ->selectRaw('user_id, status, COUNT(*) AS total')
            ->get();
        foreach ($byStatus as $row) {
            $key = $row->status === ContributionStatus::Applied->value ? 'applied' : 'rejected';
            $counters[(int) $row->user_id][$key] = (int) $row->total;
        }

        // Approbations données (une par contribution et par validateur, jamais sur la sienne : le
        // contrôle est dans ContributionController, la requête l'exclut aussi par prudence).
        $approvals = DB::table('contribution_approvals AS a')
            ->join('contributions AS c', 'c.id', '=', 'a.contribution_id')
            ->whereIn('a.user_id', $ids)
            // IS DISTINCT FROM : une contribution dont l'auteur a supprimé son compte (user_id NULL) compte aussi.
            ->whereRaw('c.user_id IS DISTINCT FROM a.user_id')
            ->groupBy('a.user_id')
            ->selectRaw('a.user_id, COUNT(*) AS total')
            ->get();
        foreach ($approvals as $row) {
            $counters[(int) $row->user_id]['reviews'] += (int) $row->total;
        }

        // Refus décidés par le validateur (`reviewed_by` n'est le relecteur d'une contribution
        // appliquée que pour son dernier approbateur, déjà compté ci-dessus).
        $refusals = DB::table('contributions')
            ->whereIn('reviewed_by', $ids)
            ->where('status', ContributionStatus::Rejected->value)
            ->whereRaw('user_id IS DISTINCT FROM reviewed_by')
            ->groupBy('reviewed_by')
            ->selectRaw('reviewed_by, COUNT(*) AS total')
            ->get();
        foreach ($refusals as $row) {
            $counters[(int) $row->reviewed_by]['reviews'] += (int) $row->total;
        }

        return $counters;
    }

    /**
     * @param  list<int>  $ids
     */
    private function apply(array $ids, bool $dryRun): int
    {
        // Lecture verrouillée dans l'ordre des identifiants (pas d'interblocage entre deux lots).
        $current = User::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->when(! $dryRun, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'reputation_applied', 'reputation_rejected', 'reputation_reviews'])
            ->keyBy('id');

        $expected = $this->countersFor($current->keys()->map(fn ($id): int => (int) $id)->all());

        $changed = 0;
        foreach ($expected as $id => $counters) {
            $user = $current[$id];
            $values = [
                'reputation_applied' => $counters['applied'],
                'reputation_rejected' => $counters['rejected'],
                'reputation_reviews' => $counters['reviews'],
            ];
            if ($values === [
                'reputation_applied' => $user->reputation_applied,
                'reputation_rejected' => $user->reputation_rejected,
                'reputation_reviews' => $user->reputation_reviews,
            ]) {
                continue;
            }
            $changed++;
            if (! $dryRun) {
                User::query()->whereKey($id)->toBase()->update($values);
            }
        }

        return $changed;
    }
}
