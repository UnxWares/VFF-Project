<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mot de passe oublié - flow Laravel natif.
 *
 * Utilise le broker `users` (cf. config/auth.php) qui stocke les tokens dans
 * la table `password_reset_tokens` déjà migrée. Le mailer utilise la config
 * existante (`.env`) - pas de setup additionnel ici.
 *
 * Énumération des comptes : la demande de lien reçoit toujours la même réponse, que l'adresse ait
 * un compte ou non. Le lien part après l'envoi de la réponse (le temps de réponse ne dépend donc
 * pas de l'existence du compte), et la limite d'une demande par minute s'applique à toute adresse,
 * connue ou non (celle du broker, par compte, aurait trahi les adresses inscrites). À la
 * réinitialisation, une adresse sans compte reçoit le même message qu'un lien invalide.
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
            'email' => ['required', 'email', 'max:254'],
        ]);

        $email = (string) $request->input('email');
        $throttleKey = self::throttleKey($email);
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            throw ValidationException::withMessages(['email' => __('auth.passwords.throttled')]);
        }
        RateLimiter::hit($throttleKey, (int) config('auth.passwords.users.throttle', 60));

        // Après la réponse : envoi (ou rien, faute de compte) invisible pour le demandeur. Le statut
        // du broker n'est pas lu, pour la même raison.
        dispatch(static function () use ($email): void {
            Password::sendResetLink(['email' => $email]);
        })->afterResponse();

        return back()->with('success', __('auth.passwords.sent'));
    }

    /** Clé de la limite de demandes : l'adresse normalisée, hachée (jamais en clair dans le cache). */
    public static function throttleKey(string $email): string
    {
        return 'password-reset|'.hash('sha256', Str::lower(trim($email)));
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
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->max(1000)],
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

        // Adresse sans compte : même message qu'un lien invalide, sinon le formulaire dirait quelles
        // adresses sont inscrites.
        if ($status === Password::INVALID_USER) {
            $status = Password::INVALID_TOKEN;
        }

        return back()->withErrors(['email' => $this->statusMessage($status)])->onlyInput('email');
    }
}
