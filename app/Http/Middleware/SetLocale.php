<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locales supportées. La première est la locale canonique de fallback.
     */
    public const SUPPORTED = ['fr', 'en', 'de'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        return $next($request);
    }

    /**
     * Langue d'une requête qui n'est passée par aucune route (URL inconnue,
     * méthode non autorisée) : le groupe web n'a donc pas tourné, ni SetLocale
     * ni le déchiffrement des cookies. Sert à rendre la page d'erreur dans la
     * langue choisie : cookie `locale` déchiffré à la main, puis Accept-Language.
     */
    public static function forUnroutedRequest(Request $request): string
    {
        $raw = $request->cookies->get('locale');
        if (is_string($raw) && $raw !== '') {
            try {
                $value = CookieValuePrefix::remove(Crypt::decrypt($raw, false));
            } catch (\Throwable) {
                $value = null;
            }
            if (is_string($value) && in_array($value, self::SUPPORTED, true)) {
                return $value;
            }
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED);

        return is_string($preferred) && in_array($preferred, self::SUPPORTED, true)
            ? $preferred
            : (string) config('app.locale', 'fr');
    }

    /**
     * Ordre de résolution :
     * 1. Cookie `locale` (si valide) : choix explicite via /locale/{code}
     * 2. Langue du profil du membre connecté (locale_preference) ; avant, elle
     *    était enregistrée mais jamais appliquée à l'interface
     * 3. Header Accept-Language du navigateur
     * 4. Locale par défaut de l'application (config/app.php)
     */
    protected function resolveLocale(Request $request): string
    {
        $cookie = $request->cookie('locale');
        if (is_string($cookie) && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }

        $preference = $request->user()?->locale_preference;
        if (is_string($preference) && in_array($preference, self::SUPPORTED, true)) {
            return $preference;
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED);
        if (is_string($preferred) && in_array($preferred, self::SUPPORTED, true)) {
            return $preferred;
        }

        return config('app.locale', 'fr');
    }
}
