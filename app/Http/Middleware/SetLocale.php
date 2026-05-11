<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
     * Ordre de résolution :
     * 1. Cookie `locale` (si valide)
     * 2. Header Accept-Language du navigateur
     * 3. Locale par défaut de l'application (config/app.php)
     */
    protected function resolveLocale(Request $request): string
    {
        $cookie = $request->cookie('locale');
        if (is_string($cookie) && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED);
        if (is_string($preferred) && in_array($preferred, self::SUPPORTED, true)) {
            return $preferred;
        }

        return config('app.locale', 'fr');
    }
}
