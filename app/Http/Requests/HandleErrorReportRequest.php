<?php

namespace App\Http\Requests;

use App\Enums\ErrorReportStatus;
use App\Models\ErrorReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Traitement d'un signalement par un validateur ou un administrateur : traité (avec une note
 * facultative) ou rejeté (avec un motif obligatoire, pour que l'équipe garde trace de sa décision).
 */
class HandleErrorReportRequest extends FormRequest
{
    public const NOTE_MAX = 2000;

    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report instanceof ErrorReport && ($this->user()?->can('handle', $report) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([ErrorReportStatus::Resolved->value, ErrorReportStatus::Rejected->value])],
            'note' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === ErrorReportStatus::Rejected->value),
                'nullable', 'string', 'max:'.self::NOTE_MAX,
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required' => __('reports.review.errors.note_required'),
            'note.max' => __('reports.review.errors.note_max', ['max' => self::NOTE_MAX]),
        ];
    }

    public function status(): ErrorReportStatus
    {
        return ErrorReportStatus::from((string) $this->validated('status'));
    }

    public function note(): ?string
    {
        $note = trim((string) $this->validated('note'));

        return $note === '' ? null : $note;
    }
}
