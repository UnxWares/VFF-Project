<?php

namespace App\Http\Requests;

use App\Services\Contributions\MediaContributions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Proposition d'une image (fichier Wikimedia Commons ou fichier déposé) rattachée à une ligne, une
 * gare ou un événement. Règles communes avec l'API GraphQL (MediaContributions).
 */
class SubmitMediaContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return MediaContributions::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return MediaContributions::messages();
    }
}
