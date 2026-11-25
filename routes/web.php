<?php

use App\Enums\ApiAccessProfile;
use App\Enums\LegalDocument;
use App\Http\Controllers\AccidentController;
use App\Http\Controllers\AccidentStatisticsController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountModerationController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChronologyController;
use App\Http\Controllers\CommuneController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\ContributionRemovalController;
use App\Http\Controllers\CoverageController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ErrorReportController;
use App\Http\Controllers\ErrorReportReviewController;
use App\Http\Controllers\EventContributionController;
use App\Http\Controllers\GlossaryController;
use App\Http\Controllers\HeritageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\LineStoryController;
use App\Http\Controllers\LineTrackLayoutPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MapRouteController;
use App\Http\Controllers\MaterialContributionController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialTimelineController;
use App\Http\Controllers\MediaContributionController;
use App\Http\Controllers\NearbyController;
use App\Http\Controllers\NetworkFiguresController;
use App\Http\Controllers\NetworkGraphAdminController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnThisDayController;
use App\Http\Controllers\OpenDataController;
use App\Http\Controllers\PreservedMaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\RailProjectController;
use App\Http\Controllers\RoutebookController;
use App\Http\Controllers\SearchPageController;
use App\Http\Controllers\SegmentAuditAdminController;
use App\Http\Controllers\StationAuditAdminController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\StationTrackLayoutPageController;
use App\Http\Controllers\TouristRailwayController;
use App\Http\Controllers\TraceEditorController;
use App\Http\Controllers\WebhookSubscriptionController;
use App\Http\Middleware\SetLocale;
use App\Models\Station;
use App\Services\Contributions\ContributionLimits;
use App\Services\NetworkGraph\GraphDiagnostics;
use App\Services\NetworkGraph\GraphFeatureDetails;
use App\Services\NetworkGraph\GraphTiles;
use App\Services\Reputation\ReputationLevels;
use App\Services\Webhooks\WebhookSubscriptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/whoarewe', fn () => Inertia::render('WhoAreWe'))->name('whoarewe');
Route::get('/maps', [MapController::class, 'index'])->name('maps');
// Itinéraires dessinés par la carte du site : tracé complet pour l'affichage (l'API applique la règle des droits).
Route::middleware('throttle:public-api')->group(function (): void {
    Route::get('/maps/route', [MapRouteController::class, 'route'])->name('maps.route');
    Route::get('/maps/route/compare', [MapRouteController::class, 'compare'])->name('maps.route.compare');
});
Route::get('/lines', [LineController::class, 'index'])->name('lines.index');
Route::get('/lines/{line:slug}', [LineController::class, 'show'])->name('lines.show');
// Schéma des voies en plein écran (état actuel) : voies, aiguillages, signaux, tableau de parcours (API track-layout).
Route::get('/lines/{line:slug}/voies', [LineTrackLayoutPageController::class, 'show'])->name('lines.tracks');
// Histoire d'un événement de la ligne : images de l'époque et documents de Gallica (EventStories).
Route::get('/lines/{line:slug}/stories/{event}', [LineStoryController::class, 'show'])
    ->where('event', '[0-9]{1,18}')->name('lines.stories.show');
Route::get('/stations', [StationController::class, 'index'])->name('stations.index');
// Voies d'une gare (état actuel) : schéma des voies de chaque ligne autour de la gare et quais (API track-layout).
Route::get('/stations/{station:slug}/voies', [StationTrackLayoutPageController::class, 'show'])
    ->name('stations.tracks')
    ->missing(function (Request $request) {
        $old = $request->route('station');
        if (is_string($old) && ($canon = Station::redirectSlugFor($old)) !== null) {
            return redirect()->route('stations.tracks', $canon, 301);
        }
        abort(404);
    });
Route::get('/stations/{station:slug}', [StationController::class, 'show'])
    ->name('stations.show')
    // Ancien slug (osm-<id>-…, fiche fusionnée dans une autre) → redirection 301 permanente vers le slug canonique.
    ->missing(function (Request $request) {
        $old = $request->route('station');
        if (is_string($old) && ($canon = Station::redirectSlugFor($old)) !== null) {
            return redirect()->route('stations.show', $canon, 301);
        }
        abort(404);
    });
// Le rail d'une commune (gares, lignes, chronologie, carte) : seulement celles qui ont une gare ou une ligne.
Route::get('/communes/{slug}', [CommuneController::class, 'show'])
    ->where('slug', '(?:[0-9]{5}|2[AB][0-9]{3})(?:-[a-z0-9-]*)?')->name('communes.show');
// Le rail d'un département (longueurs et gares par décennie, grandes dates, lignes, communes, accidents) : agrégats en cache.
Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
Route::get('/departments/{slug}', [DepartmentController::class, 'show'])
    ->where('slug', '(?:[0-9]{2,3}|2[ABab])(?:-[a-z0-9-]*)?')->name('departments.show');
// Les compagnies de chemins de fer historiques (Nord, Est, Ouest, PO, PLM, Midi, État, Alsace-Lorraine, SNCF, réseaux secondaires) : agrégats en cache.
Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
// Carte et lignes d'une compagnie, lues par la page à la demande : débit limité comme /materials/{slug}/map.json
// (le premier calcul de la carte lit les tracés de toutes les lignes rattachées).
Route::get('/companies/{slug}/map.json', [CompanyController::class, 'map'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware('throttle:public-api')->name('companies.map');
Route::get('/companies/{slug}/lines.json', [CompanyController::class, 'lines'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware('throttle:public-api')->name('companies.lines');
Route::get('/companies/{slug}', [CompanyController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('companies.show');
// Le réseau en chiffres : longueurs, gares, électrification, fermetures et accidents par décennie (agrégats en cache).
Route::get('/network', [NetworkFiguresController::class, 'index'])->name('network.figures');
// Ce que sait l'encyclopédie : couverture des données (dates, tracés, photos, fréquentation...), agrégats en cache.
Route::get('/network/coverage', [CoverageController::class, 'index'])->name('network.coverage');
// Chemins de fer touristiques et vélorails exploités aujourd'hui, voies vertes sur ancienne voie à part.
Route::get('/tourist-railways', [TouristRailwayController::class, 'index'])->name('tourist-railways');
// Patrimoine ferroviaire protégé : bâtiments voyageurs et ouvrages d'art classés, inscrits ou labellisés (Mérimée, Wikidata).
Route::get('/heritage', [HeritageController::class, 'index'])->name('heritage');
// Projets : réouvertures, lignes nouvelles, LGV, RER métropolitains et électrifications, avec l'état et la source de chaque étape.
Route::get('/projects', [RailProjectController::class, 'index'])->name('projects');
// Chronologie du rail en France : faits datés année par année, une décennie à la fois (en cache).
Route::get('/chronology', [ChronologyController::class, 'index'])->name('network.chronology');
// Ce jour-là : ce qui s'est passé un jour du calendrier les années précédentes (aujourd'hui par défaut).
Route::get('/on-this-day/{monthDay?}', [OnThisDayController::class, 'index'])->where('monthDay', '[0-9]{2}-[0-9]{2}')->name('network.on-this-day');
// Autour de moi : lignes et gares autour de la position (arrondie dans le navigateur) ou d'une commune, sans carte lourde.
Route::get('/nearby', [NearbyController::class, 'index'])->name('nearby');
Route::get('/routebook', [RoutebookController::class, 'show'])->middleware('throttle:public-api')->name('routebook');
Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
// Frise du matériel roulant : avant la fiche, dont l'adresse accepterait « timeline » comme identifiant.
Route::get('/materials/timeline', [MaterialTimelineController::class, 'index'])->name('materials.timeline');
// Matériel préservé : lieux et pièces, avant la fiche (même raison).
Route::get('/materials/preserved', [PreservedMaterialController::class, 'index'])->name('materials.preserved');
Route::get('/materials/{material:slug}', [MaterialController::class, 'show'])->name('materials.show');
Route::get('/accidents', [AccidentController::class, 'index'])->name('accidents.index');
// Les accidents en chiffres : avant la fiche, dont l'adresse accepterait « statistics » comme identifiant.
Route::get('/accidents/statistics', [AccidentStatisticsController::class, 'index'])->name('accidents.statistics');
Route::get('/accidents/{accident:slug}', [AccidentController::class, 'show'])->name('accidents.show');
// Page de résultats de la recherche (Entrée dans la recherche de l'en-tête et de l'accueil) : non indexée.
Route::get('/search', [SearchPageController::class, 'index'])->middleware('throttle:public-api')->name('search');
// Quiz du rail : dix questions tirées d'une graine (adresse partageable), réponse vérifiée par le serveur
// après le choix, photo du matériel par une redirection ; rien n'est enregistré.
Route::get('/quiz', [QuizController::class, 'index'])->middleware('throttle:30,1')->name('quiz');
Route::get('/quiz/answer', [QuizController::class, 'answer'])->middleware('throttle:60,1')->name('quiz.answer');
Route::get('/quiz/photo', [QuizController::class, 'photo'])->middleware('throttle:60,1')->name('quiz.photo');
// Signalement d'une erreur depuis une fiche (visiteurs et membres) : jeton d'ouverture du
// formulaire (durée minimale de remplissage), puis envoi limité en fréquence (AppServiceProvider).
Route::get('/reports/token', [ErrorReportController::class, 'token'])->middleware('throttle:30,1')->name('reports.token');
Route::post('/reports', [ErrorReportController::class, 'store'])->middleware(['throttle:error-reports', 'not.suspended'])->name('reports.store');
Route::get('/contribute', fn () => Inertia::render('Contribute'))->name('contribute');
// Page d'aide : ce que comptent les chiffres de réputation et les seuils de chaque niveau (config/reputation.php).
Route::get('/contribute/reputation', fn (ReputationLevels $levels) => Inertia::render('Contribute/Reputation', ['levels' => $levels->all()]))->name('contribute.reputation');
Route::get('/donate', fn () => Inertia::render('Donate'))->name('donate');

// Journal de bord : articles Markdown versionnés (resources/content/journal), flux Atom et RSS et plans du site : bootstrap/app.php (sans session).
Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
Route::get('/journal/{slug}', [JournalController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('journal.show');

Route::get('/legal', fn () => Inertia::render('Legal'))->name('legal');
// Date de la version en vigueur (config/legal.php), affichée en haut de la page.
Route::get('/privacy', fn () => Inertia::render('Privacy', ['updated' => LegalDocument::Privacy->currentVersion()]))->name('privacy');
Route::get('/use-conditions', fn () => Inertia::render('UseConditions', ['updated' => LegalDocument::UseConditions->currentVersion()]))->name('use-conditions');
Route::get('/accessibility', fn () => Inertia::render('Accessibility'))->name('accessibility');
// Glossaire du vocabulaire ferroviaire (resources/content/glossary, lang/*/glossary_terms.php).
Route::get('/glossary', [GlossaryController::class, 'index'])->name('glossary');

// Limites de débit par profil (config/api.php), affichées dans « Authentification et quotas » ;
// règles de livraison des webhooks (config/webhooks.php), affichées dans « Webhooks ».
Route::get('/api', fn () => Inertia::render('ApiDocs', [
    'rate_limits' => array_combine(
        array_map(fn (ApiAccessProfile $p): string => $p->value, ApiAccessProfile::cases()),
        array_map(fn (ApiAccessProfile $p): int => $p->perMinute(), ApiAccessProfile::cases()),
    ),
    // Règles de livraison des webhooks (config/webhooks.php), section « Webhooks ».
    'webhooks' => [
        'tolerance' => (int) config('webhooks.tolerance', 300),
        'timeout' => (int) config('webhooks.timeout', 5),
        'attempts' => max(1, (int) config('webhooks.attempts', 5)),
        'disable_after' => WebhookSubscriptions::disableAfter(),
    ],
]))->name('api.docs');

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

// Jeux de données ouverts (vff:export-open-data, lancée à la main) : catalogue lu dans public/downloads/open-data,
// fichiers servis en statique sous /downloads/open-data/.
Route::get('/open-data', OpenDataController::class)->name('open-data');

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
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login-ip');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register-ip');

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:auth-password-ip')->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth-password-ip')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Droits sur ses données (politique de confidentialité) : export JSON et suppression du compte.
    // Limités en fréquence : l'export lit toutes les contributions, la suppression vérifie le mot de passe.
    Route::get('/profile/data', [AccountController::class, 'export'])->middleware('throttle:6,1')->name('profile.export');
    Route::delete('/profile', [AccountController::class, 'destroy'])->middleware('throttle:6,1')->name('profile.destroy');

    // Jetons d'API personnels (lecture seule) : création refusée à un compte suspendu, révocation toujours possible.
    Route::post('/profile/api-tokens', [ApiTokenController::class, 'store'])->middleware(['not.suspended', 'throttle:10,1'])->name('profile.api-tokens.store');
    Route::post('/profile/api-tokens/{token}/renew', [ApiTokenController::class, 'renew'])->whereNumber('token')->middleware(['not.suspended', 'throttle:30,1'])->name('profile.api-tokens.renew');
    Route::delete('/profile/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->whereNumber('token')->middleware('throttle:30,1')->name('profile.api-tokens.destroy');

    // Webhooks de l'API : création et réactivation refusées à un compte suspendu, suppression toujours possible.
    Route::post('/profile/webhooks', [WebhookSubscriptionController::class, 'store'])->middleware(['not.suspended', 'throttle:10,1'])->name('profile.webhooks.store');
    Route::post('/profile/webhooks/{subscription}/enable', [WebhookSubscriptionController::class, 'enable'])->whereNumber('subscription')->middleware(['not.suspended', 'throttle:30,1'])->name('profile.webhooks.enable');
    Route::delete('/profile/webhooks/{subscription}', [WebhookSubscriptionController::class, 'destroy'])->whereNumber('subscription')->middleware('throttle:30,1')->name('profile.webhooks.destroy');

    // Notifications sur le site (suite donnée aux contributions, relectures en attente).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

    // Contributions (membres connectés)
    Route::get('/contribute/mine', [ContributionController::class, 'mine'])->name('contribute.mine');
    Route::get('/contribute/edit-segment', [ContributionController::class, 'editSegment'])->middleware('not.suspended')->name('contribute.edit-segment');
    Route::post('/contribute/edit-segment', [ContributionController::class, 'submit'])->middleware('not.suspended')->name('contribute.submit');
    // whereNumber : un id non numérique (« abc ») atteignait thanks(int $id) → TypeError → 500.
    Route::get('/contribute/thanks/{id}', [ContributionController::class, 'thanks'])->whereNumber('id')->name('contribute.thanks');

    // Événement daté d'une ligne et image (Commons ou fichier déposé), publiés après deux approbations.
    Route::get('/contribute/event', [EventContributionController::class, 'create'])->middleware('not.suspended')->name('contribute.event.create');
    Route::post('/contribute/event', [EventContributionController::class, 'store'])->middleware(['not.suspended', 'throttle:20,1'])->name('contribute.event.store');
    Route::get('/contribute/media', [MediaContributionController::class, 'create'])->middleware('not.suspended')->name('contribute.media.create');
    Route::post('/contribute/media', [MediaContributionController::class, 'store'])->middleware(['not.suspended', 'throttle:10,1'])->name('contribute.media.store');
    // Aperçu d'un fichier Commons (une requête à l'API de Commons) et fichier déposé en attente (auteur et validateurs).
    Route::get('/contribute/media/commons', [MediaContributionController::class, 'commons'])->middleware(['not.suspended', 'throttle:30,1'])->name('contribute.media.commons');
    Route::get('/contribute/media/{contribution}/file', [MediaContributionController::class, 'file'])->whereNumber('contribution')->name('contribute.media.file');
    // Matériel sur une ligne et série de matériel (nouvelle ou corrigée), publiés après deux approbations ;
    // contrôles avant l'envoi : compatibilité ligne × série et effet d'une correction de série sur ses liens (base
    // seulement), identifiant Wikidata (une requête à Wikidata). Série et vérification : limites communes avec GraphQL.
    Route::get('/contribute/material', [MaterialContributionController::class, 'createLink'])->middleware('not.suspended')->name('contribute.material.create');
    Route::post('/contribute/material', [MaterialContributionController::class, 'storeLink'])->middleware(['not.suspended', 'throttle:20,1'])->name('contribute.material.store');
    Route::get('/contribute/material/check', [MaterialContributionController::class, 'checkLink'])->middleware(['not.suspended', 'throttle:30,1'])->name('contribute.material.check');
    Route::get('/contribute/series', [MaterialContributionController::class, 'createSeries'])->middleware('not.suspended')->name('contribute.series.create');
    Route::post('/contribute/series', [MaterialContributionController::class, 'storeSeries'])->middleware(['not.suspended', 'throttle:'.ContributionLimits::SERIES])->name('contribute.series.store');
    Route::get('/contribute/series/wikidata', [MaterialContributionController::class, 'wikidata'])->middleware(['not.suspended', 'throttle:'.ContributionLimits::WIKIDATA_LOOKUP])->name('contribute.series.wikidata');
    Route::get('/contribute/series/impact', [MaterialContributionController::class, 'seriesImpact'])->middleware(['not.suspended', 'throttle:30,1'])->name('contribute.series.impact');

    // Éditeur de tracé sur fonds historiques IGN (lignes disparues / corrections)
    Route::prefix('contribute/trace')->name('contribute.trace.')->controller(TraceEditorController::class)->middleware('not.suspended')->group(function (): void {
        Route::get('/', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/reference', 'reference')->middleware('throttle:240,1')->name('reference');
        Route::get('/lines/{line:slug}', 'line')->name('line');
        Route::get('/feature/{kind}/{id}', 'feature')->whereIn('kind', ['trace', 'segment', 'line'])->whereNumber('id')->name('feature');
    });

    // Validators dashboard (auth + role check côté controller)
    Route::get('/validate', [ContributionController::class, 'validateList'])->middleware('not.suspended')->name('validate.index');
    Route::post('/validate/{id}/approve', [ContributionController::class, 'approve'])->whereNumber('id')->middleware('not.suspended')->name('validate.approve');
    Route::post('/validate/{id}/reject', [ContributionController::class, 'reject'])->whereNumber('id')->middleware('not.suspended')->name('validate.reject');
    // Signalements d'erreur envoyés depuis les fiches (validateurs et administrateurs, ErrorReportPolicy).
    Route::get('/validate/reports', [ErrorReportReviewController::class, 'index'])->middleware('not.suspended')->name('validate.reports.index');
    // Lien des notifications : renvoie vers l'onglet et la page de la liste qui montrent ce signalement.
    Route::get('/validate/reports/{report}', [ErrorReportReviewController::class, 'show'])->whereNumber('report')->middleware('not.suspended')->name('validate.reports.show');
    Route::patch('/validate/reports/{report}', [ErrorReportReviewController::class, 'update'])->whereNumber('report')->middleware('not.suspended')->name('validate.reports.update');
    // Retrait d'une contribution non publiée par un administrateur (motif envoyé à l'auteur).
    Route::delete('/validate/{contribution}', ContributionRemovalController::class)->whereNumber('contribution')->middleware('not.suspended')->name('validate.remove');
    // Comptes (administrateurs, UserPolicy) : liste, suspension avec motif et fin facultative, levée.
    Route::get('/validate/accounts', [AccountModerationController::class, 'index'])->middleware('not.suspended')->name('validate.accounts.index');
    Route::post('/validate/accounts/{user}/suspension', [AccountModerationController::class, 'suspend'])->whereNumber('user')->middleware('not.suspended')->name('validate.accounts.suspend');
    Route::delete('/validate/accounts/{user}/suspension', [AccountModerationController::class, 'lift'])->whereNumber('user')->middleware('not.suspended')->name('validate.accounts.lift');
    // Graphe ferroviaire d'une ère et ses diagnostics (administrateurs, UserPolicy::viewNetworkGraph ;
    // pas dans l'API publique, voir docs/GRAPHE-ADMIN.md).
    Route::prefix('validate/graph')->name('validate.graph.')->controller(NetworkGraphAdminController::class)->middleware('not.suspended')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{year}/diagnostics', 'diagnostics')->where('year', '[0-9]{1,4}')->name('diagnostics');
        Route::get('/{year}/diagnostics/{check}.csv', 'export')->where('year', '[0-9]{1,4}')
            ->whereIn('check', GraphDiagnostics::CHECKS)->name('export');
        Route::get('/{year}/diagnostics/{check}', 'check')->where('year', '[0-9]{1,4}')
            ->whereIn('check', GraphDiagnostics::CHECKS)->name('check');
        Route::get('/{year}/tiles/{layer}/{z}/{x}/{y}.mvt', 'tile')
            ->where(['year' => '[0-9]{1,4}', 'z' => '[0-9]{1,2}', 'x' => '[0-9]{1,7}', 'y' => '[0-9]{1,7}'])
            ->whereIn('layer', GraphTiles::LAYERS)->name('tiles');
        // Identifiant borné à 18 chiffres : au-delà, il ne tient plus dans un entier PHP (erreur 500).
        Route::get('/{year}/features/{kind}/{id}', 'feature')->where(['year' => '[0-9]{1,4}', 'id' => '[0-9]{1,18}'])
            ->whereIn('kind', GraphFeatureDetails::KINDS)->name('feature');
    });
    // Audit des tronçons (administrateurs, mêmes droits que le graphe ; notes calculées par
    // vff:audit-segments, docs/AUDIT-TRONCONS.md ; lecture publique par l'API).
    Route::prefix('validate/audit/segments')->name('validate.audit.segments.')->controller(SegmentAuditAdminController::class)->middleware('not.suspended')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/export.csv', 'export')->name('export');
    });
    // Audit des gares (administrateurs, mêmes droits que le graphe ; docs/AUDIT-GARES.md) : file de
    // relecture, fiche d'une gare (JSON), export CSV. Lecture seule.
    Route::prefix('validate/audit/stations')->name('validate.audit.stations.')->controller(StationAuditAdminController::class)->middleware('not.suspended')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/export.csv', 'export')->name('export');
        Route::get('/{station}', 'show')->where('station', '[0-9]{1,18}')->name('show');
    });
});
