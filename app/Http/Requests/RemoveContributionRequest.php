<?php

namespace App\Http\Requests;

use App\Models\Contribution;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Retrait d'une contribution par un administrateur. Le motif est obligatoire : il est envoyé à
 * l'auteur (CGU : « Elle en informe l'auteur avec les motifs »).
 */
class RemoveContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contribution = $this->route('contribution');

        return $contribution instanceof Contribution
            && ($this->user()?->can('remove', $contribution) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
