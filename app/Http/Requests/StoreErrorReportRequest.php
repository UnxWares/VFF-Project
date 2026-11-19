<?php

namespace App\Http\Requests;

use App\Enums\ErrorReportSubject;
use App\Services\ErrorReports\ErrorReportFormToken;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Signalement d'une erreur sur une fiche, ouvert aux visiteurs comme aux membres.
 *
 * Anti-abus sans service externe : limite de débit (route, limiteur « error-reports »), champ
 * piège `homepage` (traité par le contrôleur) et jeton d'ouverture qui impose une durée minimale
 * de remplissage (ErrorReportFormToken).
 */
class StoreErrorReportRequest extends FormRequest
{
    /** Longueurs admises du texte libre (caractères). */
    public const DESCRIPTION_MIN = 10;

    public const DESCRIPTION_MAX = 2000;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'string', Rule::enum(ErrorReportSubject::class)],
            'subject_slug' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:'.self::DESCRIPTION_MIN, 'max:'.self::DESCRIPTION_MAX],
            'source_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'contact_email' => ['nullable', 'string', 'max:255', 'email:rfc'],
            'form_token' => ['nullable', 'string', 'max:1000'],
            'homepage' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'description.required' => __('reports.errors.description_required'),
            'description.min' => __('reports.errors.description_min', ['min' => self::DESCRIPTION_MIN]),
            'description.max' => __('reports.errors.description_max', ['max' => self::DESCRIPTION_MAX]),
            'source_url.url' => __('reports.errors.source_url'),
            'source_url.max' => __('reports.errors.source_url'),
            'contact_email.email' => __('reports.errors.contact_email'),
            'contact_email.max' => __('reports.errors.contact_email'),
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if (! app(ErrorReportFormToken::class)->accepts($this->input('form_token'))) {
                    $validator->errors()->add('form', __('reports.errors.too_fast'));

                    return;
                }
                $subject = ErrorReportSubject::from((string) $this->input('subject_type'));
                if ($subject->find((string) $this->input('subject_slug')) === null) {
                    $validator->errors()->add('form', __('reports.errors.subject_missing'));
                }
            },
        ];
    }

    /** Champ piège rempli : envoi d'un robot, accepté en apparence et jamais enregistré. */
    public function isBot(): bool
    {
        return filled($this->input('homepage'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
            'source_url' => is_string($this->input('source_url')) ? (trim($this->input('source_url')) ?: null) : $this->input('source_url'),
            'contact_email' => is_string($this->input('contact_email')) ? (trim($this->input('contact_email')) ?: null) : $this->input('contact_email'),
        ]);
    }
}
