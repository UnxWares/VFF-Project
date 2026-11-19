<?php

namespace App\Providers;

use App\Http\Requests\Api\AccidentMapRequest;
use App\Http\Requests\Api\ListAccidentsRequest;
use App\Http\Requests\Api\ListLevelCrossingsRequest;
use App\Http\Requests\Api\ListLineEventsRequest;
use App\Http\Requests\Api\ListLineMaterialsRequest;
use App\Http\Requests\Api\ListLinesRequest;
use App\Http\Requests\Api\ListMaterialsRequest;
use App\Http\Requests\Api\ListMediaRequest;
use App\Http\Requests\Api\ListStationEventsRequest;
use App\Http\Requests\Api\ListStationsRequest;
use App\Models\User;
use App\Services\Api\AccidentQuery;
use App\Services\Api\LineDirectoryQuery;
use App\Services\Api\LineEventQuery;
use App\Services\Api\MaterialQuery;
use App\Services\Api\MediaQuery;
use App\Services\Api\PublicApiRateLimit;
use App\Services\Api\StationDirectoryQuery;
use App\Services\Api\StationEventQuery;
use App\Services\Reputation\ReputationLevels;
use App\Support\ApiAuthDocumentation;
use App\Support\ApiCodeSamples;
use App\Support\DatabaseGuard;
use App\Support\SourceRights;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registre des droits des sources : lu une fois par processus (fournisseurs et licences de l'API).
        $this->app->singleton(SourceRights::class);

        // Niveaux de réputation : lus dans config/reputation.php à chaque résolution (pas de copie figée).
        $this->app->bind(ReputationLevels::class, fn (): ReputationLevels => ReputationLevels::fromConfig());
    }

    public function boot(): void
    {
        // Les commandes qui vident la base ne tournent que sur une base de test (voir DatabaseGuard).
        $connection = config('database.default');
        DB::prohibitDestructiveCommands(! DatabaseGuard::isDisposable(config("database.connections.{$connection}.database")));

        // Les paramètres de ces listes sont décrits à la main (attributs QueryParameter des contrôleurs : description,
        // bornes, exemple) ; Scramble ne doit pas les redéduire des règles de validation, sans description.
        FormRequestParametersExtractor::ignoreInstanceOf([
            ListLineEventsRequest::class, ListStationEventsRequest::class, ListMediaRequest::class,
            LineEventQuery::class, StationEventQuery::class, MediaQuery::class,
            ListMaterialsRequest::class, ListLineMaterialsRequest::class, MaterialQuery::class,
            ListLinesRequest::class, ListStationsRequest::class, LineDirectoryQuery::class, StationDirectoryQuery::class,
            ListAccidentsRequest::class, AccidentQuery::class, AccidentMapRequest::class,
            ListLevelCrossingsRequest::class,
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
