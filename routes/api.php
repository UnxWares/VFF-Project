<?php

use App\Http\Controllers\Api\AccidentController;
use App\Http\Controllers\Api\EraComparisonController;
use App\Http\Controllers\Api\EraController;
use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\GtfsController;
use App\Http\Controllers\Api\LevelCrossingController;
use App\Http\Controllers\Api\LineController;
use App\Http\Controllers\Api\LineEventController;
use App\Http\Controllers\Api\LineGreenwayController;
use App\Http\Controllers\Api\LineHistoryController;
use App\Http\Controllers\Api\LineMaterialController;
use App\Http\Controllers\Api\LineMediaController;
use App\Http\Controllers\Api\LinePkController;
use App\Http\Controllers\Api\LineProfileController;
use App\Http\Controllers\Api\LineSignallingController;
use App\Http\Controllers\Api\LineStopsController;
use App\Http\Controllers\Api\LineTraceController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SignallingMapController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\StationEventController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Compte du jeton (lecture seule) ; un jeton d'un compte suspendu est refusé (AuthenticateApiToken).
Route::middleware(['api.token', 'auth:sanctum', 'throttle:public-api'])->get('/user', fn (Request $request) => $request->user());

// ── API v1 publique : cartographie ─────────────────────────────────────────
// Toutes les routes sont GET, sans auth (données ouvertes - ODbL). Jeton personnel facultatif
// (api.token) : il relève la limite de débit du compte (limiteur public-api, config/api.php).
// Limite généreuse (navigation carte = nombreuses tuiles) mais anti-abus.
Route::middleware(['api.token', 'throttle:public-api'])->group(function (): void {
    // Longueurs bornées : un entier géant (« 99999999999999999999 ») ne rentre pas
    // dans le paramètre int du contrôleur (TypeError, HTTP 500) → 404 de routage.
    Route::prefix('v1/eras/{year}')
        ->where(['year' => '[0-9]{1,4}'])
        ->group(function (): void {
            Route::get('/lines.geojson', [GeoController::class, 'lines'])->name('api.eras.lines');
            Route::get('/stations.geojson', [GeoController::class, 'stations'])->name('api.eras.stations');
            Route::get('/tiles/{z}/{x}/{y}.mvt', [GeoController::class, 'tile'])
                ->where(['z' => '[0-9]{1,2}', 'x' => '[0-9]{1,7}', 'y' => '[0-9]{1,7}'])
                ->name('api.eras.tiles');
            Route::get('/gtfs.zip', [GtfsController::class, 'export'])->name('api.eras.gtfs');
            // Comparaison de deux ères (résumé chiffré et tuiles des changements).
            Route::get('/compare/{other}', [EraComparisonController::class, 'show'])
                ->where('other', '[0-9]{1,4}')
                ->name('api.eras.compare');
            Route::get('/compare/{other}/tiles/{z}/{x}/{y}.mvt', [EraComparisonController::class, 'tile'])
                ->where(['other' => '[0-9]{1,4}', 'z' => '[0-9]{1,2}', 'x' => '[0-9]{1,7}', 'y' => '[0-9]{1,7}'])
                ->name('api.eras.compare.tiles');
            // Signalisation (tranches de line_signalling placées sur les formes SNCF Réseau).
            Route::get('/signalling/tiles/{z}/{x}/{y}.mvt', [SignallingMapController::class, 'tile'])
                ->where(['z' => '[0-9]{1,2}', 'x' => '[0-9]{1,7}', 'y' => '[0-9]{1,7}'])
                ->name('api.eras.signalling.tiles');
        });
    // Toutes les ères empilées (érosion du réseau).
    Route::get('/v1/eras/stacked/tiles/{z}/{x}/{y}.mvt', [EraComparisonController::class, 'stackedTile'])
        ->where(['z' => '[0-9]{1,2}', 'x' => '[0-9]{1,7}', 'y' => '[0-9]{1,7}'])
        ->name('api.eras.stacked.tiles');

    Route::get('/v1/search', [SearchController::class, 'index'])->name('api.search');

    // Annuaires et fiches complètes (ce que montrent /lines, /stations), ères et statistiques, registre des sources.
    Route::get('/v1/stats', [StatsController::class, 'show'])->name('api.stats');
    Route::get('/v1/eras', [EraController::class, 'index'])->name('api.eras.index');
    Route::get('/v1/eras/{year}', [EraController::class, 'show'])->where('year', '[0-9]{1,4}')->name('api.eras.show');
    Route::get('/v1/lines', [LineController::class, 'index'])->name('api.lines.index');
    Route::get('/v1/lines/{line:slug}', [LineController::class, 'show'])->name('api.lines.show');
    Route::get('/v1/lines/{line:slug}/segments', [LineController::class, 'segments'])->name('api.lines.segments');
    Route::get('/v1/lines/{line:slug}/geojson', [LineController::class, 'geojson'])->name('api.lines.geojson');
    // Tracé en fichier à télécharger (GPX 1.1, KML 2.2) : seules les sources redistribuables y figurent.
    Route::get('/v1/lines/{line:slug}/gpx', [LineTraceController::class, 'gpx'])->name('api.lines.gpx');
    Route::get('/v1/lines/{line:slug}/kml', [LineTraceController::class, 'kml'])->name('api.lines.kml');
    Route::get('/v1/stations', [StationController::class, 'index'])->name('api.stations.index');
    Route::get('/v1/stations/{station:slug}', [StationController::class, 'show'])->name('api.stations.show');
    Route::get('/v1/sources', [SourceController::class, 'index'])->name('api.sources.index');
    Route::get('/v1/sources/{provider}', [SourceController::class, 'show'])->where('provider', '[a-z0-9_-]{1,64}')->name('api.sources.show');
    Route::get('/v1/lines/{line:slug}/history', [LineHistoryController::class, 'show'])->name('api.lines.history');
    Route::get('/v1/lines/{line:slug}/pk', [LinePkController::class, 'fromPoint'])->name('api.lines.pk');
    Route::get('/v1/lines/{line:slug}/stops', [LineStopsController::class, 'index'])->name('api.lines.stops');
    Route::get('/v1/lines/{line:slug}/signalling', [LineSignallingController::class, 'index'])->name('api.lines.signalling');
    Route::get('/v1/lines/{line:slug}/signalling-history', [LineSignallingController::class, 'history'])->name('api.lines.signalling-history');
    Route::get('/v1/lines/{line:slug}/greenways', [LineGreenwayController::class, 'index'])->name('api.lines.greenways');
    Route::get('/v1/lines/{line:slug}/profile', [LineProfileController::class, 'show'])->name('api.lines.profile');
    Route::get('/v1/lines/{line:slug}/media', [LineMediaController::class, 'index'])->name('api.lines.media');
    Route::get('/v1/route', [RouteController::class, 'route'])->name('api.route');
    Route::get('/v1/route/compare', [RouteController::class, 'compare'])->name('api.route.compare');

    // Événements datés et médias (lecture seule, pagination par curseur ; identifiants bornés comme ci-dessus).
    Route::get('/v1/events', [LineEventController::class, 'index'])->name('api.events.index');
    Route::get('/v1/events/{event}', [LineEventController::class, 'show'])->where('event', '[0-9]{1,18}')->name('api.events.show');
    Route::get('/v1/stations/{station:slug}/events', [StationEventController::class, 'index'])->name('api.stations.events');
    Route::get('/v1/media', [MediaController::class, 'index'])->name('api.media.index');
    Route::get('/v1/media/{media}', [MediaController::class, 'show'])->where('media', '[0-9]{1,18}')->name('api.media.show');

    // Matériel roulant (fiches Wikidata, liens avec les lignes attestés par une source).
    Route::get('/v1/materials', [MaterialController::class, 'index'])->name('api.materials.index');
    Route::get('/v1/materials/{material:slug}', [MaterialController::class, 'show'])->where('material', '[a-z0-9-]{1,255}')->name('api.materials.show');
    Route::get('/v1/lines/{line:slug}/materials', [LineMaterialController::class, 'index'])->name('api.lines.materials');

    // Accidents et événements graves (Wikidata, Wikipédia, BEA-TT ; slug = date puis commune).
    Route::get('/v1/accidents', [AccidentController::class, 'index'])->name('api.accidents.index');
    // Points des accidents localisés (calque « Accidents » de la carte /maps).
    Route::get('/v1/accidents.geojson', [AccidentController::class, 'map'])->name('api.accidents.map');
    // Voies vertes OSM (ODbL) sur les emprises des lignes disparues ou fermées : tuiles du calque « Voies vertes » de la carte.
    Route::get('/v1/greenways/tiles/{z}/{x}/{y}.mvt', [LineGreenwayController::class, 'tile'])
        ->where(['z' => '[0-9]{1,2}', 'x' => '[0-9]{1,7}', 'y' => '[0-9]{1,7}'])
        ->name('api.greenways.tiles');
    // Passages à niveau (SNCF Réseau, ODbL) : liste, PN d'une ligne, tuiles du calque de la carte.
    Route::get('/v1/level-crossings', [LevelCrossingController::class, 'index'])->name('api.level-crossings.index');
    Route::get('/v1/level-crossings/tiles/{z}/{x}/{y}.mvt', [LevelCrossingController::class, 'tile'])
        ->where(['z' => '[0-9]{1,2}', 'x' => '[0-9]{1,7}', 'y' => '[0-9]{1,7}'])
        ->name('api.level-crossings.tiles');
    Route::get('/v1/lines/{line:slug}/level-crossings', [LevelCrossingController::class, 'forLine'])->name('api.lines.level-crossings');
    Route::get('/v1/accidents/{accident}', [AccidentController::class, 'show'])->where('accident', '[a-z0-9-]{1,160}')->name('api.accidents.show');
});
