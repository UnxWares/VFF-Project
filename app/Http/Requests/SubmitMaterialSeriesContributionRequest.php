<?php

namespace App\Http\Requests;

use App\Services\Contributions\MaterialSeriesContributions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Proposition d'une nouvelle série de matériel ou de la correction d'une série, avec sa source.
 * Règles communes avec l'API GraphQL (MaterialSeriesContributions).
 */
class SubmitMaterialSeriesContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return MaterialSeriesContributions::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return MaterialSeriesContributions::messages();
    }
}
