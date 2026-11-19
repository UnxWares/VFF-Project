<?php

namespace App\Services\Contributions;

use App\Enums\AuditAction;
use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\Era;
use App\Models\Line;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\Notifications\ContributionNotifier;
use App\Services\Reputation\ReputationCalculator;
use App\Services\TraceContributionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Relecture d'une contribution par un validateur, commune à l'espace de validation du site
 * (ContributionController) et à l'API GraphQL (`validateContribution`).
 *
 * Règles : deux approbations indépendantes publient la contribution ; un validateur ne relit pas sa
 * propre contribution et n'approuve pas deux fois ; une contribution refusée ou publiée ne se relit
 * plus ; un refus porte un motif, transmis à l'auteur. Chaque décision recalcule la réputation de
 * l'auteur et du validateur, prévient l'auteur et entre au journal d'audit ; la publication déclenche
 * les webhooks `contribution.applied` (WebhookServiceProvider, à la mise à jour de la contribution).
 *
 * Le rôle de validateur et la suspension du compte sont vérifiés par l'appelant (contrôleur et
 * middleware `not.suspended` sur le site, ContributionApiAccess dans l'API).
 */
final readonly class ContributionReview
{
    /** Nombre d'approbations indépendantes requises avant application. */
    public const APPROVAL_THRESHOLD = 2;

    /** Longueur du motif de refus, en caractères. */
    public const REASON_MIN = 5;

    public const REASON_MAX = 1000;

    public function __construct(
        private TraceContributionService $traces,
        private AuditLog $audit,
        private ContributionNotifier $notifier,
        private ReputationCalculator $reputation,
    ) {}

    /** @return list<string> règles de validation du motif d'un refus */
    public static function reasonRules(): array
    {
        return ['required', 'string', 'min:'.self::REASON_MIN, 'max:'.self::REASON_MAX];
    }

    /** @return array<string, string> messages du motif d'un refus, pour le champ `$field` */
    public static function reasonMessages(string $field): array
    {
        return [
            "$field.required" => __('editor.validate.errors.reason_required', ['min' => self::REASON_MIN]),
            "$field.min" => __('editor.validate.errors.reason_required', ['min' => self::REASON_MIN]),
            "$field.max" => __('editor.validate.errors.reason_max', ['max' => self::REASON_MAX]),
        ];
    }

    /**
     * Approuve une contribution. À la deuxième approbation indépendante, elle est appliquée.
     *
     * @throws ModelNotFoundException contribution inconnue ou retirée
     */
    public function approve(User $reviewer, int $id): ReviewResult
    {
        $contribution = Contribution::findOrFail($id);

        if ((int) $contribution->user_id === (int) $reviewer->id) {
            return new ReviewResult(ReviewOutcome::Own, $contribution);
        }
        if (! $contribution->status->isOpen()) {
            return new ReviewResult(ReviewOutcome::Closed, $contribution);
        }

        try {
            return $this->approveInTransaction($contribution, $reviewer);
        } catch (\RuntimeException $e) {
            // L'erreur complète part au journal ; l'appelant ne reçoit qu'une raison stable, jamais le
            // message brut (une erreur SQL citerait la requête et ses valeurs).
            report($e);

            return new ReviewResult(ReviewOutcome::Failed, $contribution->fresh(),
                failure: $e instanceof ContributionNotPublishable ? $e->failure : PublicationFailure::InternalError);
        }
    }

    /**
     * Refuse une contribution, avec un motif déjà validé (reasonRules) et transmis à l'auteur.
     *
     * Contrôle et refus sur la ligne verrouillée : une approbation concurrente qui vient de publier
     * la contribution n'est pas écrasée par un refus, et une contribution déjà close (refusée ou
     * publiée) n'est ni refusée de nouveau ni annoncée une seconde fois à son auteur.
     *
     * @throws ModelNotFoundException contribution inconnue ou retirée
     */
    public function reject(User $reviewer, int $id, string $reason): ReviewResult
    {
        return DB::transaction(function () use ($reviewer, $id, $reason): ReviewResult {
            $contribution = Contribution::query()->lockForUpdate()->findOrFail($id);
            if ((int) $contribution->user_id === (int) $reviewer->id) {
                return new ReviewResult(ReviewOutcome::Own, $contribution);
            }
            if (! $contribution->status->isOpen()) {
                return new ReviewResult(ReviewOutcome::Closed, $contribution);
            }

            $previous = $contribution->status->value;
            $contribution->update([
                'status' => ContributionStatus::Rejected->value,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ]);
            // Le motif reste dans la contribution (review_notes) : le journal note seulement sa présence.
            $this->audit->record(AuditAction::ContributionRejected, $reviewer, $contribution, [
                'type' => $contribution->type,
                'previous_status' => $previous,
                'with_reason' => true,
            ]);
            $this->notifier->rejected($contribution);
            // Réputation : un refus de plus pour l'auteur, une relecture de plus pour le validateur.
            DB::afterCommit(fn () => $this->reputation->recalculate([$reviewer->id, $contribution->user_id]));

            return new ReviewResult(ReviewOutcome::Rejected, $contribution, $this->approvalCount($contribution->id));
        });
    }

    /** Nombre d'approbations reçues par une contribution. */
    public function approvalCount(int $contributionId): int
    {
        return DB::table('contribution_approvals')->where('contribution_id', $contributionId)->count();
    }

    private function approveInTransaction(Contribution $contribution, User $reviewer): ReviewResult
    {
        return DB::transaction(function () use ($contribution, $reviewer): ReviewResult {
            // Relue verrouillée : un refus, un retrait ou une autre approbation concurrents attendent
            // la fin de celle-ci, ou elle voit leur résultat (contribution fermée ou disparue).
            $contribution = Contribution::query()->whereKey($contribution->id)->lockForUpdate()->first();
            if ($contribution === null || ! $contribution->status->isOpen()) {
                return new ReviewResult(ReviewOutcome::Closed, $contribution);
            }

            // Refuse les double-approbations du même validateur
            $existing = DB::table('contribution_approvals')
                ->where('contribution_id', $contribution->id)
                ->where('user_id', $reviewer->id)
                ->exists();
            if ($existing) {
                return new ReviewResult(ReviewOutcome::AlreadyApproved, $contribution, $this->approvalCount($contribution->id));
            }

            DB::table('contribution_approvals')->insert([
                'contribution_id' => $contribution->id,
                'user_id' => $reviewer->id,
                'approved_at' => now(),
            ]);
            // Réputation : une relecture de plus pour le validateur et, si la contribution est
            // appliquée, une contribution acceptée de plus pour son auteur (après la validation de la transaction).
            DB::afterCommit(fn () => $this->reputation->recalculate([$reviewer->id, $contribution->user_id]));

            $count = $this->approvalCount($contribution->id);
            $threshold = self::APPROVAL_THRESHOLD;

            if ($count >= $threshold) {
                $update = [
                    'status' => ContributionStatus::Applied->value,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                ];
                if ($contribution->type === TraceContributionService::TYPE) {
                    $result = $this->traces->apply($contribution);
                    $update['payload'] = [...$contribution->payload, 'applied' => $result + ['at' => now()->toIso8601String()]];
                    $update['target_type'] = Line::class;
                    $update['target_id'] = $result['line_id'];
                } else {
                    $this->applySegment($contribution);
                }
                $contribution->update($update);
                $this->audit->record(AuditAction::ContributionApplied, $reviewer, $contribution, [
                    'type' => $contribution->type,
                    'approvals' => $count,
                    'threshold' => $threshold,
                ]);
                // Envoyée après la validation de la transaction (rien si l'application échoue).
                $this->notifier->applied($contribution);

                return new ReviewResult(ReviewOutcome::Applied, $contribution, $count, $threshold);
            }

            $contribution->update(['status' => ContributionStatus::Approved->value]);
            $this->audit->record(AuditAction::ContributionApproved, $reviewer, $contribution, [
                'type' => $contribution->type,
                'approvals' => $count,
                'threshold' => $threshold,
            ]);
            $this->notifier->approved($contribution, $count, $threshold);

            return new ReviewResult(ReviewOutcome::Approved, $contribution, $count, $threshold);
        });
    }

    /**
     * Applique une modification par PK à la géométrie : trouve la sous-ligne via PostGIS
     * (ST_LineSubstring sur le merge) et met à jour les tronçons qui la rencontrent.
     *
     * @throws ContributionNotPublishable la ligne n'a pas de géométrie à l'ère visée
     */
    private function applySegment(Contribution $contribution): void
    {
        $p = $contribution->payload;
        $era = Era::where('year', $p['era_year'])->firstOrFail();
        $line = Line::findOrFail($contribution->target_id);
        $changes = $p['changes'] ?? [];

        // 1. Calcule la longueur totale de la ligne pour l'era
        $total = DB::selectOne(
            'SELECT ST_Length(ST_LineMerge(ST_Collect(geom))::geography) AS total_m
             FROM line_segments WHERE line_id = ? AND era_id = ?',
            [$line->id, $era->id],
        );
        if (! $total || ! $total->total_m) {
            throw new ContributionNotPublishable(PublicationFailure::NoGeometry, "Pas de géométrie pour {$line->code} ère {$era->year}");
        }

        $totalM = (float) $total->total_m;
        $startFrac = max(0, min(1, $p['pk_start_m'] / $totalM));
        $endFrac = max(0, min(1, $p['pk_end_m'] / $totalM));

        // 2. Identifie les segments OSM qui intersectent la sous-portion
        $affected = DB::select(
            'WITH merged AS (
                SELECT ST_LineMerge(ST_Collect(geom)) AS g
                FROM line_segments WHERE line_id = ? AND era_id = ?
             ),
             slice AS (
                SELECT ST_LineSubstring(g, ?, ?) AS g FROM merged
             )
             SELECT ls.id
             FROM line_segments ls, slice s
             WHERE ls.line_id = ? AND ls.era_id = ?
               AND ST_Intersects(ls.geom, s.g)',
            [$line->id, $era->id, $startFrac, $endFrac, $line->id, $era->id],
        );

        $ids = array_column($affected, 'id');
        if (empty($ids)) {
            return;
        }

        // 3. Applique les changements aux segments affectés (liste blanche de colonnes)
        $allowed = array_intersect_key($changes, array_flip(ContributionSubmissions::SEGMENT_FIELDS));
        if (! empty($allowed)) {
            DB::table('line_segments')->whereIn('id', $ids)->update($allowed);
        }
    }
}
