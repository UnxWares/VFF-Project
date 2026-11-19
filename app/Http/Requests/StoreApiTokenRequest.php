<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Création d'un jeton d'API personnel depuis le profil : un nom pour le reconnaître. */
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

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('token_name'))) {
            $this->merge(['token_name' => trim($this->input('token_name'))]);
        }
    }
}
