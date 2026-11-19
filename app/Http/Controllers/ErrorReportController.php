<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ErrorReportStatus;
use App\Enums\ErrorReportSubject;
use App\Http\Requests\StoreErrorReportRequest;
use App\Models\ErrorReport;
use App\Services\AuditLog;
use App\Services\ErrorReports\ErrorReportFormToken;
use App\Services\ErrorReports\ErrorReportNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Signalement d'une erreur depuis une fiche (bouton « Signaler une erreur » en bas des fiches
 * ligne, gare, matériel et accident), par un visiteur ou un membre connecté.
 */
class ErrorReportController extends Controller
{
    /**
     * Jeton d'ouverture du formulaire, demandé par la fenêtre à son ouverture : il fixe le début
     * de la durée minimale de remplissage. Jamais mis en cache.
     */
    public function token(ErrorReportFormToken $tokens): JsonResponse
    {
        return response()
            ->json(['token' => $tokens->issue()])
            ->header('Cache-Control', 'no-store');
    }

    public function store(StoreErrorReportRequest $request, AuditLog $audit, ErrorReportNotifier $notifier): RedirectResponse
    {
        // Champ piège rempli : réponse identique à un envoi réussi, rien n'est enregistré.
        if ($request->isBot()) {
            return back();
        }

        $subject = ErrorReportSubject::from($request->validated('subject_type'));
        $model = $subject->find($request->validated('subject_slug'));
        abort_if($model === null, 422);

        DB::transaction(function () use ($request, $subject, $model, $audit, $notifier): void {
            $report = ErrorReport::create([
                'subject_type' => $subject,
                'subject_id' => $model->getKey(),
                'subject_slug' => (string) $model->getAttribute('slug'),
                'subject_label' => mb_substr($subject->labelOf($model), 0, 255),
                'description' => $request->validated('description'),
                'source_url' => $request->validated('source_url'),
                'contact_email' => $request->validated('contact_email'),
                'locale' => app()->getLocale(),
                'user_id' => $request->user()?->id,
                'status' => ErrorReportStatus::Open,
            ]);
            // Le journal garde le numéro et la sorte de fiche, jamais le texte ni l'adresse.
            $audit->record(AuditAction::ErrorReportReceived, $request->user(), context: [
                'report_id' => $report->id,
                'subject_type' => $subject->value,
                'with_source' => $report->source_url !== null,
                'with_contact' => $report->contact_email !== null,
            ]);
            $notifier->received($report);
        });

        return back();
    }
}
