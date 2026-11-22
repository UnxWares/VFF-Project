<?php

use App\Http\Controllers\BasemapArchiveController;
use App\Http\Controllers\CommonsImageController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\LinePortionsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TranslationsController;
use App\Http\Middleware\AllowIframeEmbed;
use App\Http\Middleware\ApiLocale;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureAccountNotSuspended;
use App\Http\Middleware\EnsureRealtimeEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MarkOfflineReadable;
use App\Http\Middleware\RequirePrivateBroadcastChannel;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLastSeen;
use App\Support\Basemap\BasemapArchive;
use App\Support\Translations;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // Dictionnaire de l'interface d'une langue, hors du groupe web (ni session ni cookie) : la même
        // réponse pour tous, gardée en cache par le navigateur (App\Http\Controllers\TranslationsController).
        then: function (): void {
            Route::get('/i18n/{locale}/{version}.json', TranslationsController::class)
                ->where(['locale' => '[a-z]{2}', 'version' => '[0-9a-f]{12}'])
                ->name('i18n.translations');
            // Fond de carte libre (archive PMTiles), lu par plages d'octets, lui aussi sans session ni cookie
            // (App\Http\Controllers\BasemapArchiveController).
            Route::get('/basemap/{archive}', BasemapArchiveController::class)
                ->where('archive', BasemapArchive::NAME_PATTERN)
                ->name('basemap.archive');
            // Tracé de la carte d'une fiche de ligne, lu par la page quand la carte s'affiche, sans session ni
            // cookie et gardé en cache par le navigateur (App\Http\Controllers\LinePortionsController).
            // État détaillé pour un moniteur de disponibilité (Uptime Kuma), jeton HEALTH_TOKEN, sans session
            // (App\Http\Controllers\HealthController). `/up` reste la sonde simple de Laravel.
            // `/up` : sonde simple, texte brut sans ressource extérieure (la page de santé par défaut de Laravel
            // charge Tailwind et des polices depuis des CDN que la CSP n'autorise pas). Comme celle de Laravel,
            // elle déclenche DiagnosingHealth ; en maintenance, le noyau répond 503 avant d'arriver ici.
            Route::middleware('web')->get('/up', function (): Response {
                Event::dispatch(new DiagnosingHealth);

                return response('OK', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
            })->name('health.up');
            Route::get('/up/details', HealthController::class)->middleware('throttle:health')->name('health.details');
            Route::get('/lines/{slug}/portions/{version}.json', LinePortionsController::class)
                ->where(['slug' => '[a-z0-9-]{1,100}', 'version' => LinePortionsController::VERSION_PATTERN])
                ->name('lines.portions');
            // Flux du journal et plans du site : publics, en cache, donc sans session ni cookie (hors du groupe
            // web). La langue des flux vient de `?lang=` puis d'Accept-Language (`Vary`).
            Route::get('/journal/atom.xml', [JournalController::class, 'atom'])->name('journal.atom');
            Route::get('/journal/rss.xml', [JournalController::class, 'rss'])->name('journal.rss');
            Route::get('/journal/sitemap.xml', [JournalController::class, 'sitemap'])->name('journal.sitemap');
            Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
            Route::get('/sitemap-{family}-{page}.xml', [SitemapController::class, 'family'])
                ->where(['family' => '[a-z]+', 'page' => '[0-9]{1,4}'])->name('sitemap.family');
            // Relais des vignettes de Wikimedia Commons (config commons_proxy, désactivé par défaut : 404), sans
            // session ni cookie. Le chemin est relu brut et filtré par App\Support\Media\CommonsImagePath.
            Route::get('/media/commons/{path}', CommonsImageController::class)
                ->where('path', '.+')
                ->name('media.commons');
        },
    )
    // Temps réel (config/realtime.php) : autorisation des canaux privés par /broadcasting/auth, pour un
    // membre connecté au site, canaux privés seulement ; introuvable (404) tant que le temps réel est désactivé.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', EnsureRealtimeEnabled::class, 'auth', RequirePrivateBroadcastChannel::class]])
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
        // X-Frame-Options (sauf /embed/*), nosniff, Referrer-Policy, Permissions-Policy, HSTS. Dans la pile
        // globale pour couvrir toutes les familles de réponses (web, API, GraphQL, MCP, dictionnaires, fond
        // de carte, tracés) et aussi les 404 de liaison de modèle, les 419 et les 429 du limiteur.
        $middleware->prepend(SecurityHeaders::class);

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
        // Envoi des erreurs à un outil auto-hébergé qui parle le protocole Sentry (GlitchTip, Bugsink),
        // seulement si SENTRY_LARAVEL_DSN est renseigné (config/sentry.php, docs/SUIVI-ERREURS.md).
        Integration::handles($exceptions);

        // L'API REST répond toujours en JSON (validation 422, 404, 429, 500), quel que soit l'en-tête
        // Accept : sans cela, un paramètre invalide sans `Accept: application/json` redirigeait vers l'accueil.
        // Les pages web, /graphql et /mcp ne sont pas concernés.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson());

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
