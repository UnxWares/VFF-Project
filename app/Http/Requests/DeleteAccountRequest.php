<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Suppression de son propre compte, confirmée par le mot de passe actuel. */
class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->user()) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.current_password' => __('auth.password'),
        ];
    }
}
