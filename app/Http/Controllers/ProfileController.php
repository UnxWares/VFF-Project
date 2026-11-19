<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Services\Api\ApiTokens;
use App\Services\Reputation\ReputationLevels;
use App\Services\Webhooks\WebhookSubscriptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request, ApiTokens $tokens, ReputationLevels $levels, WebhookSubscriptions $webhooks): Response
    {
        $user = $request->user();

        return Inertia::render('Auth/Profile', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'role' => $user->role->value,
                'avatar_path' => $user->avatar_path,
                'locale_preference' => $user->locale_preference,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            // Réputation : trois chiffres, le niveau (null si le compte est suspendu) et ce qu'il manque
            // pour le suivant (page d'aide /contribute/reputation).
            'reputation' => $levels->forUser($user)->toArray(),
            // Suspension en cours : motif et fin éventuelle, pour que le membre sache pourquoi
            // il ne peut plus contribuer (null sinon).
            'suspension' => $user->isSuspended() ? [
                'since' => $user->suspended_at?->toIso8601String(),
                'until' => $user->suspended_until?->toIso8601String(),
                'reason' => $user->suspension_reason,
            ] : null,
            // Jetons d'API personnels (sans secret), quota du compte ; secret du jeton tout juste
            // créé, montré une seule fois (donnée flash de ApiTokenController::store).
            'api_access' => $tokens->forProfile($user, $request->session()->get(ApiTokenController::FLASH)),
            // Abonnements webhook (sans secret) et leurs dernières livraisons ; secret de l'abonnement
            // tout juste créé, montré une seule fois (donnée flash de WebhookSubscriptionController::store).
            'webhooks' => $webhooks->forProfile($user, $request->session()->get(WebhookSubscriptionController::FLASH)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'locale_preference' => ['required', 'string', 'in:'.implode(',', SetLocale::SUPPORTED)],
        ]);

        $request->user()->update($data);

        // La nouvelle langue s'applique tout de suite : le cookie de /locale/{code}
        // (prioritaire dans SetLocale) est aligné sur le choix du profil.
        $locale = $data['locale_preference'];
        app()->setLocale($locale);

        return back()
            ->with('success', __('auth.profile_updated'))
            ->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
