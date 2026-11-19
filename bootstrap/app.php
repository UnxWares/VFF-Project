<?php

use App\Http\Middleware\AllowIframeEmbed;
use App\Http\Middleware\ApiLocale;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureAccountNotSuspended;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MarkOfflineReadable;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLastSeen;
use App\Support\Translations;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Dernière visite des membres connectés (délai d'inactivité de 3 ans).
            TrackLastSeen::class,
            // Pages publiques qu'un visiteur non connecté peut relire hors ligne (service worker).
            MarkOfflineReadable::class,
        ]);
        // X-Frame-Options (sauf /embed/*), nosniff, Referrer-Policy. En tête du
        // groupe web pour couvrir aussi les 404 de liaison de modèle et les 419.
        $middleware->web(prepend: [SecurityHeaders::class]);

        // API publique : erreurs de validation en anglais (pas de langue de page).
        $middleware->api(prepend: [ApiLocale::class]);

        // `theme` est posé côté client en JS (clair) pour que le toggle clair/
        // sombre soit instantané sans appel serveur. `locale` est désormais
        // posé côté SERVEUR via /locale/{code} (chiffrement Laravel standard),
        // donc plus besoin de l'exclure du chiffrement.
        $middleware->encryptCookies(except: ['theme']);

        $middleware->alias([
            'iframe.embed' => AllowIframeEmbed::class,
            // Contribuer, relire, signaler : refusé à un compte suspendu (EnsureAccountNotSuspended).
            'not.suspended' => EnsureAccountNotSuspended::class,
            // Jeton personnel facultatif de l'API publique (REST, GraphQL, MCP), avant le limiteur.
            'api.token' => AuthenticateApiToken::class,
        ]);
        // Le jeton doit être lu avant l'authentification (auth:sanctum de /api/user : refus 403 d'un
        // compte suspendu plutôt qu'un 401 muet) et avant le limiteur, dont la limite dépend du compte.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, AuthenticateApiToken::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Page d'erreur d'une URL inconnue (404) ou d'une méthode non autorisée (405) :
        // aucune route n'a été trouvée, le groupe web (SetLocale) n'a pas tourné.
        // On pose la langue ici, puis le rendu par défaut continue (retour null).
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->route() === null) {
                app()->setLocale(SetLocale::forUnroutedRequest($request));
            }

            return null;
        });

        // Pages d'erreur du site (en-tête, pied de page, langue et thème du visiteur) à la place des
        // pages brutes du framework. Le code HTTP est conservé tel quel. L'API, GraphQL et les clients
        // qui attendent du JSON gardent la réponse JSON. Une erreur survenue pendant une navigation
        // Inertia reçoit la page d'erreur comme une page ordinaire (plus de fenêtre modale brute).
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            if (! in_array($status, [403, 404, 405, 419, 429, 500, 503], true)) {
                return $response;
            }
            $inertia = $request->header('X-Inertia') !== null;
            if ($request->is('api/*', 'graphql', 'graphql/*') || ($request->expectsJson() && ! $inertia)) {
                return $response;
            }
            // Mode debug : la page de débogage reste affichée pour les erreurs de code (pas pour les 404).
            if (config('app.debug') && ! $e instanceof HttpExceptionInterface && $status >= 500) {
                return $response;
            }

            // Langue : SetLocale n'a pas forcément tourné (419 levé avant lui, URL inconnue, maintenance).
            // Même ordre que SetLocale : cookie, préférence du profil, Accept-Language.
            $locale = SetLocale::forUnroutedRequest($request);
            if ($request->route() !== null) {
                $cookie = $request->cookie('locale');
                try {
                    $preference = $request->user()?->locale_preference;
                } catch (Throwable) {
                    $preference = null;
                }
                foreach ([$cookie, $preference] as $candidate) {
                    if (is_string($candidate) && in_array($candidate, SetLocale::SUPPORTED, true)) {
                        $locale = $candidate;
                        break;
                    }
                }
            }
            app()->setLocale($locale);

            // Message précis d'un refus (abort(403, '...')), sans le texte anglais par défaut du framework.
            $detail = null;
            if ($status === 403 && $e instanceof HttpExceptionInterface) {
                $message = trim($e->getMessage());
                $detail = in_array($message, ['', 'Forbidden', 'This action is unauthorized.'], true) ? null : $message;
            }
            $retryAfter = $response->headers->get('Retry-After');

            try {
                $page = Inertia::render('Error', [
                    'status' => $status,
                    'detail' => $detail,
                    'retry_after' => is_numeric($retryAfter) ? (int) $retryAfter : null,
                    // Propriétés partagées : le groupe web n'a pas tourné pour une URL inconnue ni en maintenance.
                    'locale' => $locale,
                    'available_locales' => SetLocale::SUPPORTED,
                    'translations' => Translations::load($locale),
                ])->toResponse($request);
            } catch (Throwable) {
                // Base de données ou session indisponibles : page simple, sans dépendance.
                return response()->view('errors.fallback', ['status' => $status, 'detail' => $detail], $status)
                    ->withHeaders(['Cache-Control' => 'no-store']);
            }

            $page->setStatusCode($status);
            foreach (['Retry-After', 'Allow', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'] as $name) {
                if ($response->headers->has($name)) {
                    $page->headers->set($name, $response->headers->get($name));
                }
            }

            return $page;
        });
    })
    ->create();
