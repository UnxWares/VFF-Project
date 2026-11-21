<?php

namespace App\Http\Middleware;

use App\Support\Translations;
use Illuminate\Http\Request;
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
            // Adresse du dictionnaire de l'interface de la langue (344 Ko de chaînes) : le navigateur le lit
            // une fois et le garde en cache (l'adresse porte l'empreinte du contenu) au lieu de le recevoir
            // dans chaque page (resources/js/lib/i18n.js). Les pages d'erreur rendues hors du groupe web
            // portent encore les chaînes elles-mêmes (`translations`, bootstrap/app.php).
            'translations_url' => fn () => Translations::url($locale),
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
