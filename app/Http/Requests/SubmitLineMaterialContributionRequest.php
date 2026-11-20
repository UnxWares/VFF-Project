<?php

namespace App\Http\Requests;

use App\Services\Contributions\LineMaterialContributions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Proposition d'un lien « matériel sur une ligne » : une série a circulé sur une ligne, d'après une
 * source citée, avec sa période de service facultative.
 * Règles communes avec l'API GraphQL (LineMaterialContributions).
 */
class SubmitLineMaterialContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return LineMaterialContributions::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return LineMaterialContributions::messages();
    }
}
