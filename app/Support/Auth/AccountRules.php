<?php

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

/**
 * Règles de validation d'un nouveau compte, communes au formulaire d'inscription
 * (AuthController::register) et à la commande vff:make-admin : un administrateur créé en
 * console respecte les mêmes exigences qu'un membre inscrit sur le site.
 */
final class AccountRules
{
    /** Longueur minimale d'un mot de passe (reprise dans resources/js/Pages/Auth/authForm.js). */
    public const PASSWORD_MIN = 8;

    /** @return array{name: list<string>, email: list<string>, password: list<mixed>} */
    public static function newAccount(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(self::PASSWORD_MIN)],
        ];
    }
}
