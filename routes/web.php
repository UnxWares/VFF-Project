<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

Route::get('/whoarewe', fn () => Inertia::render('WhoAreWe'))->name('whoarewe');
Route::get('/maps', fn () => Inertia::render('Maps'))->name('maps');
Route::get('/contribute', fn () => Inertia::render('Contribute'))->name('contribute');
Route::get('/donate', fn () => Inertia::render('Donate'))->name('donate');

Route::get('/legal', fn () => Inertia::render('Legal'))->name('legal');
Route::get('/privacy', fn () => Inertia::render('Privacy'))->name('privacy');
Route::get('/use-conditions', fn () => Inertia::render('UseConditions'))->name('use-conditions');

Route::get('/api', fn () => Inertia::render('ApiDocs'))->name('api.docs');

Route::get('/locale/{code}', [LocaleController::class, 'set'])
    ->whereIn('code', \App\Http\Middleware\SetLocale::SUPPORTED)
    ->name('locale.set');
