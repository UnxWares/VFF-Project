<?php

namespace App\Providers;

use App\Http\Requests\Api\AccidentMapRequest;
use App\Http\Requests\Api\ListAccidentsRequest;
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
use App\Support\SourceRights;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
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
        ]);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
