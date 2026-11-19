<?php

use App\Http\Controllers\AccidentController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\ContributionRemovalController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\ErrorReportController;
use App\Http\Controllers\ErrorReportReviewController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\TraceEditorController;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/whoarewe', fn () => Inertia::render('WhoAreWe'))->name('whoarewe');
Route::get('/maps', [MapController::class, 'index'])->name('maps');
Route::get('/lines', [LineController::class, 'index'])->name('lines.index');
Route::get('/lines/{line:slug}', [LineController::class, 'show'])->name('lines.show');
Route::get('/stations', [StationController::class, 'index'])->name('stations.index');
Route::get('/stations/{station:slug}', [StationController::class, 'show'])
    ->name('stations.show')
    // Ancien slug (osm-<id>-…) → redirection 301 permanente vers le slug canonique.
    ->missing(function (Request $request) {
        $old = $request->route('station');
        $sid = DB::table('station_redirects')->where('old_slug', $old)->value('station_id');
        if ($sid && ($canon = DB::table('stations')->where('id', $sid)->value('slug'))) {
            return redirect()->route('stations.show', $canon, 301);
        }
        abort(404);
    });
Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
Route::get('/materials/{material:slug}', [MaterialController::class, 'show'])->name('materials.show');
Route::get('/accidents', [AccidentController::class, 'index'])->name('accidents.index');
Route::get('/accidents/{accident:slug}', [AccidentController::class, 'show'])->name('accidents.show');
// Signalement d'une erreur depuis une fiche (visiteurs et membres) : jeton d'ouverture du
// formulaire (durée minimale de remplissage), puis envoi limité en fréquence (AppServiceProvider).
Route::get('/reports/token', [ErrorReportController::class, 'token'])->middleware('throttle:30,1')->name('reports.token');
Route::post('/reports', [ErrorReportController::class, 'store'])->middleware('throttle:error-reports')->name('reports.store');
Route::get('/contribute', fn () => Inertia::render('Contribute'))->name('contribute');
Route::get('/donate', fn () => Inertia::render('Donate'))->name('donate');

Route::get('/legal', fn () => Inertia::render('Legal'))->name('legal');
Route::get('/privacy', fn () => Inertia::render('Privacy'))->name('privacy');
Route::get('/use-conditions', fn () => Inertia::render('UseConditions'))->name('use-conditions');
Route::get('/accessibility', fn () => Inertia::render('Accessibility'))->name('accessibility');

Route::get('/api', fn () => Inertia::render('ApiDocs'))->name('api.docs');

// Documentation de l'API. REST : /docs/api (Scalar, vue resources/views/vendor/scramble/docs.blade.php)
// et /docs/api.json sont déclarées par dedoc/scramble ; Swagger UI lit la même spécification.
Route::view('/docs/swagger', 'docs.swagger')->name('docs.swagger');
// GraphQL : référence statique Magidoc générée dans public/docs/graphql/ (make docs-graphql).
// Les pages sont des fichiers .html appelés sans extension (/docs/graphql/queries/line) :
// le serveur web les sert directement en production (try_files $uri $uri.html), cette
// route fait de même pour `artisan serve` et sert l'accueil /docs/graphql.
Route::get('/docs/graphql/{page?}', function (?string $page = null) {
    $root = realpath(public_path('docs/graphql'));
    $file = $root ? realpath($root.'/'.($page === null ? 'index' : $page).'.html') : false;
    abort_unless($file && str_starts_with($file, $root.DIRECTORY_SEPARATOR), 404);

    return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8']);
})->where('page', '[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*')->name('docs.graphql');

Route::prefix('credits')->name('credits.')->group(function (): void {
    Route::get('/tech', [CreditsController::class, 'tech'])->name('tech');
    Route::get('/media', [CreditsController::class, 'media'])->name('media');
});

Route::get('/locale/{code}', [LocaleController::class, 'set'])
    ->whereIn('code', SetLocale::SUPPORTED)
    ->name('locale.set');

// Routes intégrables en iframe sur des sites tiers - pas de X-Frame-Options.
Route::middleware('iframe.embed')->prefix('embed')->name('embed.')->group(function (): void {
    Route::get('/maps', [MapController::class, 'embed'])->name('maps');
});

/*
|--------------------------------------------------------------------------
| Authentification (session web Inertia)
|--------------------------------------------------------------------------
| Pas d'API tokens Sanctum ici - uniquement le guard `web`. La progression
| vers contributor/validator/admin se fait via le système de réputation,
| pas via une page admin pour l'instant.
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Droits sur ses données (politique de confidentialité) : export JSON et suppression du compte.
    // Limités en fréquence : l'export lit toutes les contributions, la suppression vérifie le mot de passe.
    Route::get('/profile/data', [AccountController::class, 'export'])->middleware('throttle:6,1')->name('profile.export');
    Route::delete('/profile', [AccountController::class, 'destroy'])->middleware('throttle:6,1')->name('profile.destroy');

    // Notifications sur le site (suite donnée aux contributions, relectures en attente).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

    // Contributions (membres connectés)
    Route::get('/contribute/mine', [ContributionController::class, 'mine'])->name('contribute.mine');
    Route::get('/contribute/edit-segment', [ContributionController::class, 'editSegment'])->name('contribute.edit-segment');
    Route::post('/contribute/edit-segment', [ContributionController::class, 'submit'])->name('contribute.submit');
    // whereNumber : un id non numérique (« abc ») atteignait thanks(int $id) → TypeError → 500.
    Route::get('/contribute/thanks/{id}', [ContributionController::class, 'thanks'])->whereNumber('id')->name('contribute.thanks');

    // Éditeur de tracé sur fonds historiques IGN (lignes disparues / corrections)
    Route::prefix('contribute/trace')->name('contribute.trace.')->controller(TraceEditorController::class)->group(function (): void {
        Route::get('/', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/reference', 'reference')->middleware('throttle:240,1')->name('reference');
        Route::get('/lines/{line:slug}', 'line')->name('line');
        Route::get('/feature/{kind}/{id}', 'feature')->whereIn('kind', ['trace', 'segment', 'line'])->whereNumber('id')->name('feature');
    });

    // Validators dashboard (auth + role check côté controller)
    Route::get('/validate', [ContributionController::class, 'validateList'])->name('validate.index');
    Route::post('/validate/{id}/approve', [ContributionController::class, 'approve'])->whereNumber('id')->name('validate.approve');
    Route::post('/validate/{id}/reject', [ContributionController::class, 'reject'])->whereNumber('id')->name('validate.reject');
    // Signalements d'erreur envoyés depuis les fiches (validateurs et administrateurs, ErrorReportPolicy).
    Route::get('/validate/reports', [ErrorReportReviewController::class, 'index'])->name('validate.reports.index');
    // Lien des notifications : renvoie vers l'onglet et la page de la liste qui montrent ce signalement.
    Route::get('/validate/reports/{report}', [ErrorReportReviewController::class, 'show'])->whereNumber('report')->name('validate.reports.show');
    Route::patch('/validate/reports/{report}', [ErrorReportReviewController::class, 'update'])->whereNumber('report')->name('validate.reports.update');
    // Retrait d'une contribution non publiée par un administrateur (motif envoyé à l'auteur).
    Route::delete('/validate/{contribution}', ContributionRemovalController::class)->whereNumber('contribution')->name('validate.remove');
});
