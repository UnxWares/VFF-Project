<?php

use App\Http\Middleware\AllowIframeEmbed;
use App\Http\Middleware\ApiLocale;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
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
        ]);
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
    })
    ->create();
