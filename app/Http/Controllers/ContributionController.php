<?php

namespace App\Http\Controllers;

use App\Enums\ContributionStatus;
use App\Enums\SegmentStatus;
use App\Enums\TrafficType;
use App\Http\Requests\RejectContributionRequest;
use App\Http\Requests\SubmitSegmentContributionRequest;
use App\Models\Contribution;
use App\Models\Era;
use App\Models\Line;
use App\Models\Material;
use App\Models\User;
use App\Services\Contributions\ContributionReview;
use App\Services\Contributions\ContributionSubmissions;
use App\Services\Contributions\EventContributions;
use App\Services\Contributions\LineMaterialContributions;
use App\Services\Contributions\MaterialSeriesContributions;
use App\Services\Contributions\MediaContributions;
use App\Services\Contributions\PublicationFailure;
use App\Services\Contributions\ReviewOutcome;
use App\Services\Contributions\ReviewResult;
use App\Services\Reputation\ReputationLevels;
use App\Services\TraceContributionService;
use App\Support\Realtime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContributionController extends Controller
{
    /** Nombre d'approbations indépendantes requises avant application (règle de ContributionReview). */
    public const APPROVAL_THRESHOLD = ContributionReview::APPROVAL_THRESHOLD;

    /**
     * Formulaire d'édition d'un segment de ligne par PK.
     * Pré-rempli avec le code ligne si fourni en query.
     */
    public function editSegment(Request $request): Response
    {
        $lineCode = $request->query('line');
        $line = $lineCode
            ? Line::query()
                ->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [preg_replace('/\s+/', '', $lineCode)])
                ->first()
            : null;

        return Inertia::render('Contribute/EditSegment', [
            'preselectedLine' => $line ? [
                'slug' => $line->slug,
                'code' => $line->code,
                'name' => $line->name,
            ] : null,
            'eras' => Era::orderBy('year')->get(['year', 'is_current']),
            'statuses' => array_map(fn ($c) => $c->value, SegmentStatus::cases()),
            'trafficTypes' => array_map(fn ($c) => $c->value, TrafficType::cases()),
        ]);
    }

    public function submit(SubmitSegmentContributionRequest $request, ContributionSubmissions $submissions): RedirectResponse
    {
        $contribution = $submissions->submitSegment($request->user(), $request->validated());

        return redirect()->route('contribute.thanks', ['id' => $contribution->id]);
    }

    public function thanks(int $id): Response
    {
        $contribution = Contribution::findOrFail($id);
        abort_unless((int) $contribution->user_id === (int) Auth::id() || $this->isValidator(), 404);

        return Inertia::render('Contribute/Thanks', [
            'contribution' => [
                'id' => $contribution->id,
                'type' => $contribution->type,
                'payload' => MediaContributions::withoutPrivatePath($contribution->payload ?? []),
            ],
        ]);
    }

    /**
     * Page "Mes contributions" - historique du user connecté.
     */
    public function mine(): Response
    {
        $mine = Contribution::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->limit(50)
            ->get(['id', 'type', 'status', 'payload', 'created_at', 'reviewed_at', 'review_notes']);

        $approvalCounts = DB::table('contribution_approvals')
            ->whereIn('contribution_id', $mine->pluck('id'))
            ->selectRaw('contribution_id, COUNT(*) AS c')
            ->groupBy('contribution_id')
            ->pluck('c', 'contribution_id');

        return Inertia::render('Contribute/Mine', [
            'contributions' => $mine->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'status' => $c->status->value,
                'payload' => MediaContributions::withoutPrivatePath($c->payload ?? []),
                // ISO 8601 (« 2026-11-18T10:21:00+01:00 ») : le navigateur formate selon la langue de la page.
                'created_at' => $c->created_at->toIso8601String(),
                'reviewed_at' => $c->reviewed_at?->toIso8601String(),
                'review_notes' => $c->review_notes,
                'approval_count' => (int) ($approvalCounts[$c->id] ?? 0),
            ]),
        ]);
    }

    /**
     * Dashboard validateurs - réservé aux rôles validator / admin.
     * Liste les contributions en attente (pending) ET partiellement approuvées
     * (approved = 1 approbation sur 2) : segments PK, tracés, événements datés, médias, matériel sur
     * une ligne et séries de matériel.
     */
    public function validateList(TraceContributionService $traces, EventContributions $events, MediaContributions $media, LineMaterialContributions $lineMaterials, MaterialSeriesContributions $series, ReputationLevels $levels): Response
    {
        $this->ensureValidator();
        $me = Auth::id();

        $pending = Contribution::query()
            ->whereIn('status', [ContributionStatus::Pending->value, ContributionStatus::Approved->value])
            ->whereIn('type', ['line_segment', TraceContributionService::TYPE, EventContributions::TYPE, MediaContributions::TYPE, LineMaterialContributions::TYPE, MaterialSeriesContributions::TYPE])
            ->with(['user:id,name,reputation_applied,reputation_rejected,reputation_reviews,suspended_at,suspended_until', 'target'])
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $approvals = DB::table('contribution_approvals')
            ->whereIn('contribution_id', $pending->pluck('id'))
            ->get(['contribution_id', 'user_id'])
            ->groupBy('contribution_id');

        return Inertia::render('Validate/Index', [
            'threshold' => self::APPROVAL_THRESHOLD,
            // Réglages de Laravel Echo (canal privé `review-queue`), null tant que le temps réel est désactivé.
            'realtime' => Realtime::clientConfig(),
            'contributions' => $pending->map(function ($c) use ($approvals, $me, $traces, $events, $media, $lineMaterials, $series, $levels) {
                $mine = $approvals->get($c->id, collect());
                $item = [
                    'id' => $c->id,
                    'type' => $c->type,
                    'status' => $c->status->value,
                    'created_at' => $c->created_at->diffForHumans(),
                    // Compte supprimé : la contribution reste, sans auteur (« Contributeur anonyme »,
                    // sans réputation). Compte suspendu : les chiffres, mais pas de niveau.
                    'user' => $c->user_id === null ? [
                        'name' => __('account.anonymous_contributor'),
                        'reputation' => null,
                    ] : [
                        'name' => $c->user->name,
                        'reputation' => $levels->forUser($c->user)->forReviewer(),
                    ],
                    'is_mine' => (int) $c->user_id === (int) $me,
                    'approval_count' => $mine->count(),
                    'approved_by_me' => $mine->contains('user_id', $me),
                    'payload' => MediaContributions::withoutPrivatePath($c->payload ?? []),
                    'target' => $c->target instanceof Line ? [
                        'slug' => $c->target->slug,
                        'name' => $c->target->name,
                        'code' => $c->target->code,
                        'network' => $c->target->network,
                    ] : null,
                    // Série corrigée, ou série d'une image : sa fiche actuelle (une correction garde aussi les
                    // valeurs « avant »).
                    'material' => $c->target instanceof Material ? [
                        'slug' => $c->target->slug,
                        'name' => $c->target->name,
                        'type' => $c->target->getRawOriginal('type'),
                    ] : null,
                ];
                if ($c->type === 'line_segment') {
                    // « Avant » vivant : valeurs actuelles de la portion visée (peuvent avoir changé depuis la proposition).
                    $item['current_values'] = $this->currentPortionValues($c);
                }
                if ($c->type === EventContributions::TYPE) {
                    // Événements déjà connus de la même famille sur la ligne : doublon ou contradiction visibles.
                    $item['similar_events'] = $events->similarEvents($c);
                }
                if ($c->type === LineMaterialContributions::TYPE) {
                    // Liens déjà connus du couple et règle relue maintenant avec la phrase citée.
                    $item['material_context'] = $lineMaterials->reviewContext($c);
                }
                if ($c->type === MaterialSeriesContributions::TYPE) {
                    // « Avant » vivant (champs changés depuis l'envoi) et liens que la correction rendrait contraires aux règles.
                    $item['series_context'] = $series->reviewContext($c);
                }
                if ($c->type === MediaContributions::TYPE) {
                    // Fichier déposé : lu par une adresse réservée à l'auteur et aux validateurs, jamais publique.
                    $item['file_url'] = $media->pendingFile($c) !== null ? route('contribute.media.file', ['contribution' => $c->id]) : null;
                }
                if ($c->type === TraceContributionService::TYPE) {
                    $portions = $c->payload['portions'] ?? [];
                    $lineId = $c->payload['line_id'] ?? null;
                    // « Avant » vivant : géométrie actuelle de la ligne (peut avoir bougé depuis la proposition).
                    $item['current'] = $lineId ? $traces->lineGeometry((int) $lineId, 0.00002) : null;
                    $item['nearby'] = $traces->nearbyLines($portions, $lineId ? (int) $lineId : null);
                }

                return $item;
            }),
        ]);
    }

    /**
     * Valeurs actuelles de la portion visée par une modification par PK : pour chaque attribut proposé,
     * les valeurs distinctes des tronçons que l'application modifierait (mêmes tronçons que
     * applyContribution : ceux qui rencontrent la sous-ligne entre les deux PK, à l'ère visée).
     * Null si la portion n'a pas de géométrie exploitable (ère sans tronçon, ligne en plusieurs
     * morceaux non jointifs) : le front n'affiche alors que la valeur proposée.
     *
     * @return array<string, list<bool|int|string>>|null
     */
    private function currentPortionValues(Contribution $contribution): ?array
    {
        $p = $contribution->payload;
        $fields = array_values(array_intersect(array_keys($p['changes'] ?? []), ContributionSubmissions::SEGMENT_FIELDS));
        $era = Era::where('year', $p['era_year'] ?? null)->first();
        if ($fields === [] || ! $era || ! $contribution->target_id || ! isset($p['pk_start_m'], $p['pk_end_m'])) {
            return null;
        }

        // Fractions bornées à [0, 1] sur la longueur de la ligne fusionnée ; une ligne non fusionnable
        // en un seul tracé ou de longueur nulle donne une sous-ligne vide (donc aucun tronçon).
        $rows = DB::select(
            'WITH merged AS (
                SELECT ST_LineMerge(ST_Collect(geom)) AS g
                FROM line_segments WHERE line_id = ? AND era_id = ?
             ),
             slice AS (
                SELECT CASE WHEN GeometryType(g) = \'LINESTRING\' AND ST_Length(g::geography) > 0
                    THEN ST_LineSubstring(
                        g,
                        LEAST(1, GREATEST(0, ?::float8 / ST_Length(g::geography))),
                        LEAST(1, GREATEST(0, ?::float8 / ST_Length(g::geography)))
                    ) END AS g
                FROM merged
             )
             SELECT ls.status, ls.traffic_type, ls.electrified, ls.voltage_v, ls.max_speed_kmh, ls.is_heritage
             FROM line_segments ls, slice s
             WHERE ls.line_id = ? AND ls.era_id = ?
               AND ST_Intersects(ls.geom, s.g)
             ORDER BY ls.id',
            [$contribution->target_id, $era->id, $p['pk_start_m'], $p['pk_end_m'], $contribution->target_id, $era->id],
        );
        if ($rows === []) {
            return null;
        }

        $current = [];
        foreach ($fields as $field) {
            $values = [];
            foreach ($rows as $row) {
                $v = $row->{$field};
                if ($v === null) {
                    continue;
                }
                $values[] = match ($field) {
                    'electrified', 'is_heritage' => (bool) $v,
                    'voltage_v', 'max_speed_kmh' => (int) $v,
                    default => (string) $v,
                };
            }
            $current[$field] = array_values(array_unique($values, SORT_REGULAR));
        }

        return $current;
    }

    public function approve(int $id, ContributionReview $review): RedirectResponse
    {
        $this->ensureValidator();

        return $this->flash($review->approve($this->reviewer(), $id));
    }

    public function reject(RejectContributionRequest $request, int $id, ContributionReview $review): RedirectResponse
    {
        // Une contribution retirée n'existe plus (404).
        return $this->flash($review->reject($this->reviewer(), $id, $request->reason()), rejecting: true);
    }

    /** Message flash de l'espace de validation pour l'issue d'une relecture. */
    private function flash(ReviewResult $result, bool $rejecting = false): RedirectResponse
    {
        $counts = ['count' => $result->approvals, 'threshold' => $result->threshold];

        return match ($result->outcome) {
            ReviewOutcome::Applied => back()->with('success', __('editor.validate.flash.applied', $counts)),
            ReviewOutcome::Approved => back()->with('success', __('editor.validate.flash.partial', $counts)),
            ReviewOutcome::Rejected => back()->with('success', __('editor.validate.flash.rejected')),
            ReviewOutcome::Own => back()->with('error', __($rejecting ? 'editor.validate.flash.own_reject' : 'editor.validate.flash.own')),
            ReviewOutcome::Closed => back()->with('error', __('editor.validate.flash.closed')),
            ReviewOutcome::AlreadyApproved => back()->with('error', __('editor.validate.flash.already')),
            // Raison stable, jamais le message brut de l'erreur (journal du site seulement).
            ReviewOutcome::Failed => back()->with('error', trim(__('editor.validate.flash.failed', [
                'message' => __('editor.validate.flash.failures.'.($result->failure ?? PublicationFailure::InternalError)->value),
            ]).($result->failureDetails !== [] ? ' '.__('editor.validate.flash.failure_details', ['list' => implode(' ; ', $result->failureDetails)]) : ''))),
        };
    }

    private function reviewer(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function ensureValidator(): void
    {
        if (! $this->isValidator()) {
            abort(403, __('messages.validators_only'));
        }
    }

    private function isValidator(): bool
    {
        return Auth::user()?->isReviewer() ?? false;
    }
}
