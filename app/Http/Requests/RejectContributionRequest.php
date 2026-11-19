<?php

namespace App\Http\Requests;

use App\Services\Contributions\ContributionReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Refus d'une contribution par un validateur. Le motif est obligatoire : il est transmis au
 * contributeur, qui doit comprendre pourquoi son travail n'est pas publié.
 */
class RejectContributionRequest extends FormRequest
{
    public const NOTES_MIN = ContributionReview::REASON_MIN;

    public const NOTES_MAX = ContributionReview::REASON_MAX;

    public function authorize(): bool
    {
        return $this->user()?->isReviewer() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['notes' => ContributionReview::reasonRules()];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ContributionReview::reasonMessages('notes');
    }

    public function reason(): string
    {
        return trim((string) $this->validated('notes'));
    }
}
