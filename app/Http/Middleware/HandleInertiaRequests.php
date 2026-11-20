<?php

namespace App\Http\Middleware;

use App\Support\Translations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
            ],
            'locale' => $locale,
            'available_locales' => SetLocale::SUPPORTED,
            // Toutes les chaînes de l'interface (environ 300 Ko) : envoyées au premier chargement, puis gardées
            // par le navigateur d'une page à l'autre (prop « once » d'Inertia : le client annonce les props
            // qu'il a déjà, X-Inertia-Except-Once-Props). La clé suit la langue : changer de langue les renvoie.
            'translations' => Inertia::once(fn () => Translations::load($locale))->as("translations.{$locale}"),
            'auth' => [
                // Whitelist explicite : on n'expose JAMAIS le hash password ni
                // remember_token au front. role est sérialisé en string (enum).
                'user' => fn () => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role->value,
                    'avatar_path' => $request->user()->avatar_path,
                    'locale_preference' => $request->user()->locale_preference,
                    // Compte suspendu : les formulaires de contribution et de signalement l'annoncent.
                    'suspended' => $request->user()->isSuspended(),
                ] : null,
                // Nombre de notifications non lues (menu du compte) : un simple compte, jamais
                // leur contenu ; null pour un visiteur.
                'unread_notifications' => fn () => $request->user()?->unreadNotifications()->count(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
