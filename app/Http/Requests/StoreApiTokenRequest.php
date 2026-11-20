<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un jeton d'API personnel depuis le profil : un nom pour le reconnaître et, cochée au
 * besoin, la capacité de contribuer par l'API (`contribute`, décochée par défaut).
 */
class StoreApiTokenRequest extends FormRequest
{
    public const NAME_MAX = 60;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'token_name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'contribute' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'token_name.required' => __('api_tokens.errors.name_required'),
            'token_name.string' => __('api_tokens.errors.name_required'),
            'token_name.max' => __('api_tokens.errors.name_max', ['max' => self::NAME_MAX]),
        ];
    }

    /** Capacité de contribution demandée (case cochée). */
    public function wantsContribute(): bool
    {
        return (bool) $this->validated('contribute', false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('token_name'))) {
            $this->merge(['token_name' => trim($this->input('token_name'))]);
        }
    }
}
