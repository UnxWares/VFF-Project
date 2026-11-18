<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mot de passe oublié - flow Laravel natif.
 *
 * Utilise le broker `users` (cf. config/auth.php) qui stocke les tokens dans
 * la table `password_reset_tokens` déjà migrée. Le mailer utilise la config
 * existante (`.env`) - pas de setup additionnel ici.
 */
class PasswordResetController extends Controller
{
    public function showForgot(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', $this->statusMessage($status));
        }

        return back()->withErrors(['email' => $this->statusMessage($status)])->onlyInput('email');
    }

    /**
     * Message traduit d'un statut du broker (« passwords.sent »…). Les textes
     * vivent dans lang/{fr,en,de}/auth.php sous auth.passwords.* : traduire le
     * statut brut tombait sur le fichier anglais du framework (pas de
     * lang/fr/passwords.php), d'où des messages en anglais pour tout le monde.
     */
    private function statusMessage(string $status): string
    {
        $key = 'auth.'.$status;

        return __($key) !== $key ? __($key) : __($status);
    }

    public function showReset(string $token, Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', $this->statusMessage($status));
        }

        return back()->withErrors(['email' => $this->statusMessage($status)])->onlyInput('email');
    }
}
