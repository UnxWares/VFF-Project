<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Set la locale via GET /locale/{code} : pose le cookie côté serveur
     * (chiffrement Laravel standard) et redirige vers la page d'origine.
     *
     * Approche classique Laravel, pas de JS cookie ni d'API Inertia complexe :
     * - le navigateur navigue vers /locale/fr
     * - Laravel set le cookie via la méthode `cookie()` native (chiffré)
     * - le navigateur suit la redirection vers la page précédente
     * - SetLocale middleware lit le cookie (déchiffré par EncryptCookies)
     * - la page s'affiche dans la nouvelle locale
     */
    public function set(string $code, Request $request): RedirectResponse
    {
        if (! in_array($code, SetLocale::SUPPORTED, true)) {
            return redirect('/');
        }

        $minutes = 60 * 24 * 365; // 1 an
        $target = $request->headers->get('referer') ?: '/';

        return redirect($target)->withCookie(cookie('locale', $code, $minutes));
    }
}
