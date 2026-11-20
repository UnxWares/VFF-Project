<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ErrorReportStatus;
use App\Enums\ErrorReportSubject;
use App\Http\Requests\HandleErrorReportRequest;
use App\Models\ErrorReport;
use App\Services\AuditLog;
use App\Services\ErrorReports\ErrorReportNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Signalements d'erreur dans l'espace de validation (/validate/reports) : liste filtrée par état
 * (ouverts, traités, rejetés) et traitement, réservés aux validateurs et aux administrateurs.
 */
class ErrorReportReviewController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ErrorReport::class);

        $status = ErrorReportStatus::tryFrom((string) $request->query('status')) ?? ErrorReportStatus::Open;
        $reports = ErrorReport::query()
            ->with(['user:id,name', 'handler:id,name'])
            ->where('status', $status->value)
            // Ouverts : les plus anciens d'abord (à traiter) ; traités : les plus récents d'abord.
            ->when($status->isOpen(), fn ($q) => $q->orderBy('created_at')->orderBy('id'))
            ->when(! $status->isOpen(), fn ($q) => $q->orderByDesc('handled_at')->orderByDesc('id'))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $slugs = $this->currentSlugs(collect($reports->items()));

        $counts = ErrorReport::query()
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return Inertia::render('Validate/Reports', [
            'status' => $status->value,
            'counts' => collect(ErrorReportStatus::cases())
                ->mapWithKeys(fn (ErrorReportStatus $s): array => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'reports' => $reports->through(fn (ErrorReport $r): array => [
                'id' => $r->id,
                'status' => $r->status->value,
                'subject' => [
                    'type' => $r->subject_type->value,
                    'label' => $r->subject_label,
                    // Slug actuel de la fiche (il a pu changer), sinon celui du signalement ; null si
                    // la fiche n'existe plus.
                    'url' => isset($slugs[$r->subject_type->value][$r->subject_id])
                        ? $r->subject_type->path($slugs[$r->subject_type->value][$r->subject_id])
                        : null,
                ],
                'description' => $r->description,
                'source_url' => $r->source_url,
                'contact_email' => $r->contact_email,
                'reporter' => $r->user?->name,
                'locale' => $r->locale,
                // ISO 8601 : le navigateur formate la date dans la langue de la page.
                'created_at' => $r->created_at?->toIso8601String(),
                'handled_at' => $r->handled_at?->toIso8601String(),
                'handler' => $r->handler?->name,
                'resolution_note' => $r->resolution_note,
            ]),
        ]);
    }

    /**
     * Adresse stable d'un signalement (lien des notifications) : redirection vers l'onglet de son
     * état actuel et la page de la liste où il figure, ancrée sur lui. Le signalement peut avoir
     * été traité depuis l'envoi de la notification, ou d'autres être arrivés avant lui.
     */
    public function show(ErrorReport $report): RedirectResponse
    {
        Gate::authorize('viewAny', ErrorReport::class);

        $before = ErrorReport::query()
            ->where('status', $report->status->value)
            ->where(fn ($q) => $report->status->isOpen()
                // Même ordre que la liste : ouverts du plus ancien au plus récent…
                ? $q->where('created_at', '<', $report->created_at)
                    ->orWhere(fn ($q) => $q->where('created_at', $report->created_at)->where('id', '<', $report->id))
                // … fermés du plus récemment traité au plus anciennement traité.
                : $q->where('handled_at', '>', $report->handled_at)
                    ->orWhere(fn ($q) => $q->where('handled_at', $report->handled_at)->where('id', '>', $report->id)))
            ->count();
        $page = intdiv($before, self::PER_PAGE) + 1;

        return redirect()->to(route('validate.reports.index', array_filter([
            'status' => $report->status->isOpen() ? null : $report->status->value,
            'page' => $page > 1 ? $page : null,
        ]), absolute: false).'#report-'.$report->id);
    }

    public function update(HandleErrorReportRequest $request, ErrorReport $report, AuditLog $audit, ErrorReportNotifier $notifier): RedirectResponse
    {
        $status = $request->status();

        $handled = DB::transaction(function () use ($request, $report, $status, $audit, $notifier): bool {
            // Relu verrouillé : deux validateurs qui traitent le même signalement en même temps
            // ne l'enregistrent qu'une fois.
            $locked = ErrorReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->isOpen()) {
                return false;
            }
            $locked->update([
                'status' => $status,
                'resolution_note' => $request->note(),
                'handled_by' => $request->user()?->id,
                'handled_at' => now(),
            ]);
            $audit->record(
                $status === ErrorReportStatus::Resolved ? AuditAction::ErrorReportResolved : AuditAction::ErrorReportRejected,
                $request->user(),
                context: [
                    'report_id' => $locked->id,
                    'subject_type' => $locked->subject_type->value,
                    'with_note' => $locked->resolution_note !== null,
                    'follow_up_queued' => filled($locked->contact_email),
                ],
            );
            // Courriel de suivi à la personne qui a signalé (si elle a laissé son adresse), mis en
            // file une fois la transaction validée.
            $notifier->handled($locked);

            return true;
        });

        return $handled
            ? back()->with('success', __("reports.review.flash.{$status->value}", ['id' => $report->id]))
            : back()->with('error', __('reports.review.flash.closed'));
    }

    /**
     * Slugs actuels des fiches visées, par sorte de fiche : [type => [id => slug]].
     *
     * @param  Collection<int, ErrorReport>  $reports
     * @return array<string, array<int, string>>
     */
    private function currentSlugs(Collection $reports): array
    {
        $slugs = [];
        foreach ($reports->groupBy(fn (ErrorReport $r): string => $r->subject_type->value) as $type => $group) {
            $subject = ErrorReportSubject::from($type);
            $slugs[$type] = $subject->modelClass()::query()
                ->whereKey($group->pluck('subject_id')->unique()->values())
                ->pluck('slug', 'id')
                ->all();
        }

        return $slugs;
    }
}
