<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'API publique n'a pas de « langue de page » : ses erreurs de validation
 * s'adressent à des développeurs et restent en anglais, quelle que soit la
 * langue par défaut du site (fr). Appliqué au seul groupe `api`.
 */
class ApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('en');

        return $next($request);
    }
}
