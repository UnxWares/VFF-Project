<?php

namespace App\Providers;

use App\Http\Requests\Api\AccidentMapRequest;
use App\Http\Requests\Api\AccidentStatisticsRequest;
use App\Http\Requests\Api\EraTileRequest;
use App\Http\Requests\Api\ListAccidentsRequest;
use App\Http\Requests\Api\ListLevelCrossingsRequest;
use App\Http\Requests\Api\ListLineEventsRequest;
use App\Http\Requests\Api\ListLineMaterialsRequest;
use App\Http\Requests\Api\ListLinesRequest;
use App\Http\Requests\Api\ListMaterialsRequest;
use App\Http\Requests\Api\ListMediaRequest;
use App\Http\Requests\Api\ListStationEventsRequest;
use App\Http\Requests\Api\ListStationsRequest;
use App\Http\Requests\Api\MaterialTimelineRequest;
use App\Models\User;
use App\Services\Api\AccidentQuery;
use App\Services\Api\LineDirectoryQuery;
use App\Services\Api\LineEventQuery;
use App\Services\Api\MaterialQuery;
use App\Services\Api\MediaQuery;
use App\Services\Api\PublicApiRateLimit;
use App\Services\Api\StationDirectoryQuery;
use App\Services\Api\StationEventQuery;
use App\Services\Contributions\ContributionLimits;
use App\Services\Reputation\ReputationLevels;
use App\Services\TrackLayout\RinfTrackLayoutSource;
use App\Services\TrackLayout\TrackLayout;
use App\Support\ApiAuthDocumentation;
use App\Support\ApiCodeSamples;
use App\Support\DatabaseGuard;
use App\Support\SourceRights;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Plus grand numéro de page des listes paginées (`?page=`) : au-delà, (page - 1) × taille de page ne tient
     * plus dans un entier et le décalage SQL levait une erreur 500. Même borne que l'API REST (`page` 1 à 1 000 000).
     */
    public const MAX_PAGE = 1_000_000;

    public function register(): void
    {
        // Registre des droits des sources : lu une fois par processus (fournisseurs et licences de l'API).
        $this->app->singleton(SourceRights::class);

        // Niveaux de réputation : lus dans config/reputation.php à chaque résolution (pas de copie figée).
        $this->app->bind(ReputationLevels::class, fn (): ReputationLevels => ReputationLevels::fromConfig());

        // Schéma des voies : sources étiquetées TrackLayout::TAG, lues dans l'ordre TrackLayout::PRIORITY (rinf, osm,
        // vff). Une nouvelle source (OpenStreetMap) s'ajoute par une ligne : $this->app->tag([…::class], TrackLayout::TAG).
        $this->app->tag([RinfTrackLayoutSource::class], TrackLayout::TAG);
        $this->app->bind(TrackLayout::class, fn ($app): TrackLayout => new TrackLayout($app->tagged(TrackLayout::TAG)));
    }

    public function boot(): void
    {
        // Page courante de paginate() : entier de 1 à MAX_PAGE (comme le résolveur de Laravel, borné en haut).
        Paginator::currentPageResolver(function (string $pageName = 'page'): int {
            $page = filter_var($this->app['request']->input($pageName), FILTER_VALIDATE_INT);

            return is_int($page) && $page >= 1 ? min($page, self::MAX_PAGE) : 1;
        });

        // Les commandes qui vident la base ne tournent que sur une base de test (voir DatabaseGuard).
        $connection = config('database.default');
        DB::prohibitDestructiveCommands(! DatabaseGuard::isDisposable(config("database.connections.{$connection}.database")));

        // Pas de compilation JIT de PostgreSQL pour les pages et l'API : le planificateur surestime les
        // fonctions PostGIS (ST_Dump, ST_Subdivide…) et compile des requêtes de quelques dizaines de ms
        // (169 ms de JIT pour 70 ms de travail aux terminus d'une fiche ligne, 0,66 s sur une tuile du
        // zoom 5). Les commandes du moteur, longues, gardent le réglage du serveur.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() === 'pgsql'
                && (! $this->app->runningInConsole() || config('database.web_jit_off_in_console'))) {
                $event->connection->statement('SET jit = off');
            }
        });

        // Les paramètres de ces listes sont décrits à la main (attributs QueryParameter des contrôleurs : description,
        // bornes, exemple) ; Scramble ne doit pas les redéduire des règles de validation, sans description.
        FormRequestParametersExtractor::ignoreInstanceOf([
            ListLineEventsRequest::class, ListStationEventsRequest::class, ListMediaRequest::class,
            LineEventQuery::class, StationEventQuery::class, MediaQuery::class,
            ListMaterialsRequest::class, ListLineMaterialsRequest::class, MaterialQuery::class, MaterialTimelineRequest::class,
            ListLinesRequest::class, ListStationsRequest::class, LineDirectoryQuery::class, StationDirectoryQuery::class,
            ListAccidentsRequest::class, AccidentQuery::class, AccidentMapRequest::class, AccidentStatisticsRequest::class,
            ListLevelCrossingsRequest::class, EraTileRequest::class,
        ]);

        // Exemples JavaScript et Python des principaux points d'accès (extension x-codeSamples lue par Scalar).
        // Jeton facultatif, quotas par profil, réponses 401, 403 et 429 (section « Authentification et quotas »).
        Scramble::configure()->withDocumentTransformers([ApiCodeSamples::class, ApiAuthDocumentation::class]);

        // Le jeton d'un compte suspendu est refusé partout où Sanctum authentifie (auth:sanctum) ;
        // l'API publique le refuse avec un message dans AuthenticateApiToken.
        Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $isValid): bool => $isValid && ! ($token->tokenable instanceof User && $token->tokenable->isSuspended()),
        );

        $this->configureRateLimiting();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Signalements d'erreur (POST /reports, ouverts aux visiteurs) : 6 envois par 10 minutes et
     * 20 par jour, par compte ou à défaut par adresse IP (l'adresse n'est pas enregistrée, seul
     * le compteur du cache la connaît le temps de la fenêtre). Au-delà, le formulaire affiche un
     * message au lieu d'une page d'erreur.
     */
    private function configureRateLimiting(): void
    {
        // API publique (REST, GraphQL, MCP) : limite par profil, anonyme, membre ou partenaire.
        RateLimiter::for(PublicApiRateLimit::NAME, PublicApiRateLimit::for(...));

        // Propositions qui font appeler Wikidata (série, vérification d'identifiant) : site et GraphQL.
        ContributionLimits::register();

        // Formulaires d'authentification, par adresse IP (en plus des limites par adresse e-mail des
        // contrôleurs) : sans elles, une seule IP essayait autant d'adresses qu'elle voulait (bourrage
        // d'identifiants), créait des comptes sans fin ou faisait envoyer des courriels à n'importe qui.
        RateLimiter::for('auth-login-ip', fn (Request $request): Limit => Limit::perMinute(20)->by('login-ip:'.$request->ip()));
        RateLimiter::for('auth-register-ip', fn (Request $request): Limit => Limit::perHour(10)->by('register-ip:'.$request->ip()));
        RateLimiter::for('auth-password-ip', fn (Request $request): Limit => Limit::perHour(20)->by('password-ip:'.$request->ip()));

        // Rapports de violation de la CSP (CspReportController), envoyés par les navigateurs : par adresse IP.
        RateLimiter::for('csp-reports', fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('csp.reports_per_minute', 30)))
            ->by('ip:'.$request->ip())
            ->response(fn () => response()->noContent(429)));

        // Erreurs JavaScript du navigateur (ClientErrorController) : par adresse IP, réponse vide au-delà.
        RateLimiter::for('client-errors', fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('monitoring.client_errors_per_minute')))
            ->by('client-errors:'.$request->ip())
            ->response(fn () => response()->noContent(429)));

        // État détaillé pour le moniteur de disponibilité (HealthController).
        RateLimiter::for('health', fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('monitoring.health_per_minute')))
            ->by('health:'.$request->ip()));

        RateLimiter::for('error-reports', function (Request $request): array {
            $who = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            $refuse = fn () => back()->withErrors(['form' => __('reports.errors.too_many')]);

            return [
                Limit::perMinutes(10, 6)->by('burst:'.$who)->response($refuse),
                Limit::perDay(20)->by('day:'.$who)->response($refuse),
            ];
        });
    }
}
