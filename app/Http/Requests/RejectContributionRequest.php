<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Refus d'une contribution par un validateur. Le motif est obligatoire : il est transmis au
 * contributeur, qui doit comprendre pourquoi son travail n'est pas publié.
 */
class RejectContributionRequest extends FormRequest
{
    public const NOTES_MIN = 5;

    public const NOTES_MAX = 1000;

    public function authorize(): bool
    {
        return $this->user()?->isReviewer() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'min:'.self::NOTES_MIN, 'max:'.self::NOTES_MAX],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'notes.required' => __('editor.validate.errors.reason_required', ['min' => self::NOTES_MIN]),
            'notes.min' => __('editor.validate.errors.reason_required', ['min' => self::NOTES_MIN]),
            'notes.max' => __('editor.validate.errors.reason_max', ['max' => self::NOTES_MAX]),
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('notes'));
    }
}
