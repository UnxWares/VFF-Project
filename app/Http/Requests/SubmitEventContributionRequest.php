<?php

namespace App\Http\Requests;

use App\Services\Contributions\EventContributions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Proposition d'un événement daté d'une ligne (ouverture, fermeture, électrification…), avec sa
 * source. Règles communes avec l'API GraphQL (EventContributions).
 */
class SubmitEventContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return EventContributions::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return EventContributions::messages();
    }
}
