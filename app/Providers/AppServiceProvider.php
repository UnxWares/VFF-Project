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
use App\Services\Api\AccidentQuery;
use App\Services\Api\LineDirectoryQuery;
use App\Services\Api\LineEventQuery;
use App\Services\Api\MaterialQuery;
use App\Services\Api\MediaQuery;
use App\Services\Api\StationDirectoryQuery;
use App\Services\Api\StationEventQuery;
use App\Support\ApiCodeSamples;
use App\Support\SourceRights;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registre des droits des sources : lu une fois par processus (fournisseurs et licences de l'API).
        $this->app->singleton(SourceRights::class);
    }

    public function boot(): void
    {
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
        Scramble::configure()->withDocumentTransformers(ApiCodeSamples::class);

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
