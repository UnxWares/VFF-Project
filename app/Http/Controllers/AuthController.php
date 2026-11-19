<?php

namespace App\Http\Controllers;

use App\Enums\LegalDocument;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Auth\AccountRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authentification web (session Inertia, pas d'API tokens).
 *
 * Stratégie :
 *   - Auth via guard `web` (session standard Laravel).
 *   - À l'inscription, on attribue d'office le rôle `visitor`. La progression
 *     vers `contributor` etc. se fera plus tard via le système de réputation.
 *   - Régénération de session systématique après auth pour limiter le risque
 *     de session fixation.
 *   - L'inscription exige la case d'acceptation des CGU ; la date et la version acceptée
 *     (config/legal.php) sont enregistrées avec le compte et figurent dans l'export de ses données.
 *   - Connexion limitée à MAX_LOGIN_ATTEMPTS échecs par minute pour un couple
 *     e-mail + IP (force brute), comme le LoginRequest de Breeze.
 */
class AuthController extends Controller
{
    /** Échecs de connexion tolérés par minute pour un même e-mail depuis une même IP. */
    public const MAX_LOGIN_ATTEMPTS = 5;

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        // Avant : aucune limite, la force brute sur /login était illimitée.
        $throttleKey = 'login|'.Str::transliterate(Str::lower($credentials['email'])).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors(['email' => __('auth.failed')])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...AccountRules::newAccount(),
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => __('auth.errors.terms_required'),
        ]);

        // `role` n'est pas affectable en masse (User::$fillable) : posé explicitement.
        $user = (new User)->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // hashed cast in User model
            'role' => UserRole::Visitor->value,
            'locale_preference' => app()->getLocale(),
            'terms_accepted_at' => now(),
            'terms_version' => LegalDocument::UseConditions->currentVersion(),
        ]);
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')
            ->with('success', __('auth.registered'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
