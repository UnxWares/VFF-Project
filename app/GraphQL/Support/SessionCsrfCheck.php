<?php

namespace App\GraphQL\Support;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Exceptions\OriginMismatchException;
use Illuminate\Http\Request;

/**
 * Contrôle CSRF d'une mutation GraphQL authentifiée par la session du site : mêmes règles que le
 * middleware du site (ValidateCsrfToken), sans son exception pour les tests et sans exclusion, pour
 * que la vérification s'applique partout. La requête passe si le navigateur la déclare de même
 * origine (`Sec-Fetch-Site: same-origin`) ou si elle porte le jeton CSRF de la session
 * (`X-XSRF-TOKEN`, lu par axios dans le cookie XSRF-TOKEN, ou `X-CSRF-TOKEN`).
 *
 * Les requêtes de lecture de /graphql ne sont pas concernées : elles restent ouvertes à tous.
 */
final class SessionCsrfCheck extends ValidateCsrfToken
{
    public function passes(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        try {
            return $this->hasValidOrigin($request) || $this->tokensMatch($request);
        } catch (OriginMismatchException) {
            return false;
        }
    }
}
