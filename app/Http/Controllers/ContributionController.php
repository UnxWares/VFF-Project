<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ContributionAction;
use App\Enums\ContributionStatus;
use App\Enums\SegmentStatus;
use App\Enums\TrafficType;
use App\Enums\UserRole;
use App\Models\Contribution;
use App\Models\Era;
use App\Models\Line;
use App\Services\AuditLog;
use App\Services\TraceContributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContributionController extends Controller
{
    /** Nombre d'approbations indépendantes requises avant application. */
    private const APPROVAL_THRESHOLD = 2;

    /** Attributs d'une portion qu'une modification par PK peut changer (les autres clés sont ignorées). */
    private const SEGMENT_FIELDS = ['status', 'traffic_type', 'electrified', 'voltage_v', 'max_speed_kmh', 'is_heritage'];

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

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'line_code' => 'required|string',
            'era_year' => 'required|integer',
            'pk_start_m' => 'required|integer|min:0|max:9999999',
            'pk_end_m' => 'required|integer|min:0|max:9999999|gt:pk_start_m',
            'changes' => 'required|array',
            // Valeurs des énumérations : avant, une valeur libre passait la validation
            // puis faisait échouer l'application (CHECK SQL) au moment de l'approbation,
            // avec l'erreur SQL brute affichée au validateur.
            'changes.status' => ['nullable', 'string', Rule::enum(SegmentStatus::class)],
            'changes.traffic_type' => ['nullable', 'string', Rule::enum(TrafficType::class)],
            'changes.electrified' => 'nullable|boolean',
            'changes.voltage_v' => 'nullable|integer|min:0|max:50000',
            'changes.max_speed_kmh' => 'nullable|integer|min:0|max:600',
            'changes.is_heritage' => 'nullable|boolean',
            'sources' => 'nullable|array',
            'sources.*' => 'string|max:500',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Le navigateur bloque déjà un envoi vide ; ici le serveur le refuse aussi (champs laissés
        // sur « Inchangé » ou vides, ou clés inconnues seules) : rien à relire pour les validateurs.
        $changes = array_filter(
            array_intersect_key($data['changes'] ?? [], array_flip(self::SEGMENT_FIELDS)),
            fn ($v) => $v !== null,
        );
        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => __('editor.segment.errors.no_change')]);
        }

        $line = Line::query()
            ->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [preg_replace('/\s+/', '', $data['line_code'])])
            ->firstOrFail();
        $era = Era::where('year', $data['era_year'])->firstOrFail();

        $contribution = Contribution::create([
            'user_id' => Auth::id(),
            'type' => 'line_segment',
            'action' => ContributionAction::Update->value,
            'target_type' => Line::class,
            'target_id' => $line->id,
            'status' => ContributionStatus::Pending->value,
            'payload' => [
                'line_code' => $line->code,
                'line_name' => $line->name,
                'era_year' => $era->year,
                'pk_start_m' => (int) $data['pk_start_m'],
                'pk_end_m' => (int) $data['pk_end_m'],
                'changes' => $changes,
                'sources' => $data['sources'] ?? [],
                'notes' => $data['notes'] ?? null,
            ],
        ]);

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
                'payload' => $contribution->payload,
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
                'payload' => $c->payload,
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
     * (approved = 1 approbation sur 2), segments PK comme tracés.
     */
    public function validateList(TraceContributionService $traces): Response
    {
        $this->ensureValidator();
        $me = Auth::id();

        $pending = Contribution::query()
            ->whereIn('status', [ContributionStatus::Pending->value, ContributionStatus::Approved->value])
            ->whereIn('type', ['line_segment', TraceContributionService::TYPE])
            ->with(['user:id,name,reputation', 'target'])
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $approvals = DB::table('contribution_approvals')
            ->whereIn('contribution_id', $pending->pluck('id'))
            ->get(['contribution_id', 'user_id'])
            ->groupBy('contribution_id');

        return Inertia::render('Validate/Index', [
            'threshold' => self::APPROVAL_THRESHOLD,
            'contributions' => $pending->map(function ($c) use ($approvals, $me, $traces) {
                $mine = $approvals->get($c->id, collect());
                $item = [
                    'id' => $c->id,
                    'type' => $c->type,
                    'status' => $c->status->value,
                    'created_at' => $c->created_at->diffForHumans(),
                    // Compte supprimé : la contribution reste, sans auteur (« Contributeur anonyme »).
                    'user' => $c->user_id === null ? [
                        'name' => __('account.anonymous_contributor'),
                        'reputation' => null,
                    ] : [
                        'name' => $c->user->name,
                        'reputation' => $c->user->reputation,
                    ],
                    'is_mine' => (int) $c->user_id === (int) $me,
                    'approval_count' => $mine->count(),
                    'approved_by_me' => $mine->contains('user_id', $me),
                    'payload' => $c->payload,
                    'target' => $c->target instanceof Line ? [
                        'slug' => $c->target->slug,
                        'name' => $c->target->name,
                        'code' => $c->target->code,
                        'network' => $c->target->network,
                    ] : null,
                ];
                if ($c->type === 'line_segment') {
                    // « Avant » vivant : valeurs actuelles de la portion visée (peuvent avoir changé depuis la proposition).
                    $item['current_values'] = $this->currentPortionValues($c);
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
        $fields = array_values(array_intersect(array_keys($p['changes'] ?? []), self::SEGMENT_FIELDS));
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

    public function approve(int $id, TraceContributionService $traces, AuditLog $audit): RedirectResponse
    {
        $this->ensureValidator();
        $contribution = Contribution::findOrFail($id);
        $userId = Auth::id();

        if ((int) $contribution->user_id === (int) $userId) {
            return back()->with('error', __('editor.validate.flash.own'));
        }
        if (! in_array($contribution->status, [ContributionStatus::Pending, ContributionStatus::Approved], true)) {
            return back()->with('error', __('editor.validate.flash.closed'));
        }

        try {
            return $this->approveInTransaction($contribution, $userId, $traces, $audit);
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', __('editor.validate.flash.failed', ['message' => $e->getMessage()]));
        }
    }

    private function approveInTransaction(Contribution $contribution, int $userId, TraceContributionService $traces, AuditLog $audit): RedirectResponse
    {
        return DB::transaction(function () use ($contribution, $userId, $traces, $audit) {
            // Refuse les double-approbations du même validateur
            $existing = DB::table('contribution_approvals')
                ->where('contribution_id', $contribution->id)
                ->where('user_id', $userId)
                ->exists();
            if ($existing) {
                return back()->with('error', __('editor.validate.flash.already'));
            }

            DB::table('contribution_approvals')->insert([
                'contribution_id' => $contribution->id,
                'user_id' => $userId,
                'approved_at' => now(),
            ]);

            $count = DB::table('contribution_approvals')
                ->where('contribution_id', $contribution->id)
                ->count();

            // 2 approbations indépendantes requises avant application
            $threshold = self::APPROVAL_THRESHOLD;
            if ($count >= $threshold) {
                $update = [
                    'status' => ContributionStatus::Applied->value,
                    'reviewed_by' => $userId,
                    'reviewed_at' => now(),
                ];
                if ($contribution->type === TraceContributionService::TYPE) {
                    $result = $traces->apply($contribution);
                    $update['payload'] = [...$contribution->payload, 'applied' => $result + ['at' => now()->toIso8601String()]];
                    $update['target_type'] = Line::class;
                    $update['target_id'] = $result['line_id'];
                } else {
                    $this->applyContribution($contribution);
                }
                $contribution->update($update);
                $audit->record(AuditAction::ContributionApplied, Auth::user(), $contribution, [
                    'type' => $contribution->type,
                    'approvals' => $count,
                    'threshold' => $threshold,
                ]);

                return back()->with('success', __('editor.validate.flash.applied', ['count' => $count, 'threshold' => $threshold]));
            }

            $contribution->update([
                'status' => ContributionStatus::Approved->value,
            ]);
            $audit->record(AuditAction::ContributionApproved, Auth::user(), $contribution, [
                'type' => $contribution->type,
                'approvals' => $count,
                'threshold' => $threshold,
            ]);

            return back()->with('success', __('editor.validate.flash.partial', ['count' => $count, 'threshold' => $threshold]));
        });
    }

    public function reject(Request $request, int $id, AuditLog $audit): RedirectResponse
    {
        $this->ensureValidator();
        $request->validate(['notes' => 'nullable|string|max:1000']);
        $contribution = Contribution::findOrFail($id);
        if ($contribution->status === ContributionStatus::Applied) {
            return back()->with('error', __('editor.validate.flash.closed'));
        }
        $previous = $contribution->status->value;
        DB::transaction(function () use ($contribution, $request, $audit, $previous): void {
            $contribution->update([
                'status' => ContributionStatus::Rejected->value,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_notes' => $request->input('notes'),
            ]);
            // Le motif reste dans la contribution (review_notes) : le journal note seulement sa présence.
            $audit->record(AuditAction::ContributionRejected, Auth::user(), $contribution, [
                'type' => $contribution->type,
                'previous_status' => $previous,
                'with_reason' => filled($request->input('notes')),
            ]);
        });

        return back()->with('success', __('editor.validate.flash.rejected'));
    }

    /**
     * Applique le changement à la géométrie : trouve la sous-ligne via PostGIS
     * (ST_LineSubstring sur le merge), met à jour les segments couverts ou crée
     * un nouveau segment pour la portion exacte.
     */
    private function applyContribution(Contribution $contribution): void
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
            throw new \RuntimeException("Pas de géométrie pour {$line->code} ère {$era->year}");
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

        // 3. Applique les changements aux segments affectés (whitelist colonnes)
        $allowed = array_intersect_key($changes, array_flip([
            'status', 'traffic_type', 'electrified', 'voltage_v', 'max_speed_kmh', 'is_heritage',
        ]));
        if (! empty($allowed)) {
            DB::table('line_segments')->whereIn('id', $ids)->update($allowed);
        }
    }

    private function ensureValidator(): void
    {
        if (! $this->isValidator()) {
            abort(403, __('messages.validators_only'));
        }
    }

    private function isValidator(): bool
    {
        $user = Auth::user();

        return $user && in_array($user->role->value ?? null, [
            UserRole::Validator->value,
            UserRole::Admin->value,
        ], true);
    }
}
