<?php

use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\GtfsController;
use App\Http\Controllers\Api\LineHistoryController;
use App\Http\Controllers\Api\LineMediaController;
use App\Http\Controllers\Api\LinePkController;
use App\Http\Controllers\Api\LineSignallingController;
use App\Http\Controllers\Api\LineStopsController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

// ── API v1 publique : cartographie ─────────────────────────────────────────
// Toutes les routes sont GET, sans auth (données ouvertes - ODbL).
// Rate-limit généreux (navigation carte = nombreuses tuiles) mais anti-abus.
Route::middleware('throttle:300,1')->group(function (): void {
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
        });

    Route::get('/v1/search', [SearchController::class, 'index'])->name('api.search');
    Route::get('/v1/lines/{line:slug}/history', [LineHistoryController::class, 'show'])->name('api.lines.history');
    Route::get('/v1/lines/{line:slug}/pk', [LinePkController::class, 'fromPoint'])->name('api.lines.pk');
    Route::get('/v1/lines/{line:slug}/stops', [LineStopsController::class, 'index'])->name('api.lines.stops');
    Route::get('/v1/lines/{line:slug}/signalling', [LineSignallingController::class, 'index'])->name('api.lines.signalling');
    Route::get('/v1/lines/{line:slug}/media', [LineMediaController::class, 'index'])->name('api.lines.media');
    Route::get('/v1/route', [RouteController::class, 'route'])->name('api.route');
});
