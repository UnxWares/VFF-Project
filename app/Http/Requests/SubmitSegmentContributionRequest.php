<?php

namespace App\Http\Requests;

use App\Services\Contributions\ContributionSubmissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Proposition de modification d'une portion de ligne par PK. Comme toute contribution sourcée,
 * elle cite au moins une source (adresse ou référence) : les validateurs ne relisent pas une
 * affirmation sans pièce. Règles communes avec l'API GraphQL (ContributionSubmissions).
 */
class SubmitSegmentContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ContributionSubmissions::segmentRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ContributionSubmissions::segmentMessages();
    }
}
