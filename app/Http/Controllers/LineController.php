<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Models\Era;
use App\Models\Line;
use App\Services\Accidents\LineAccidents;
use App\Services\Archives\LineArchives;
use App\Services\Companies\CompanyFigures;
use App\Services\Greenways\LineGreenways;
use App\Services\Journal\JournalRepository;
use App\Services\LevelCrossings\LineLevelCrossings;
use App\Services\LineDirectory;
use App\Services\LineSheet;
use App\Services\LineStats;
use App\Services\Profiles\LineProfiles;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingDating;
use App\Services\Speed\LineSpeeds;
use App\Services\Stories\EventStories;
use App\Services\Structures\LineStructures;
use App\Services\TraceExport\LineTraceExport;
use App\Services\Traction\LineElectrificationHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LineController extends Controller
{
    use ReadsListFilters;

    /** Catégories de réseau (contrat `lines.network`). */
    public const NETWORKS = LineDirectory::NETWORKS;

    /** Types de trafic d'une portion (`line_segments.traffic_type`). */
    public const TRAFFICS = LineDirectory::TRAFFICS;

    /** Part électrifiée (%) sous laquelle une ligne n'est électrifiée qu'à la marge (voies d'une gare, raccordement). */
    public const MARGINAL_ELECTRIFIED_PCT = LineStats::MARGINAL_ELECTRIFIED_PCT;

    /** Groupe des props différées de la fiche (Inertia::defer) : une seule requête après le premier affichage. */
    public const DEFERRED_GROUP = 'sheet';

    /**
     * Liste paginée et filtrée des lignes.
     *
     * Filtres acceptés :
     *   ?q=…                         nom, code, opérateur
     *   ?network=principal|secondaire|militaire|particulier|none
     *   ?traffic=passenger|mixed|freight|unknown   trafic ACTUEL (segments en
     *        service de l'ère courante) - `?type=` accepté comme alias historique
     *   ?status=open|closed          au moins une portion en service / aucune
     *   ?heritage=1                  au moins une portion patrimoniale
     *
     * Avant : le filtre et les compteurs reposaient sur `lines.type`, colonne non
     * curée (NULL partout) → « 0 voyageurs · 0 fret · 0 mixtes » (audit STATS-01).
     */
    public function index(Request $request, LineDirectory $directory): Response
    {
        $q = $this->filterString($request, 'q', trim: true);
        $network = $this->filterString($request, 'network');
        $traffic = $this->filterString($request, 'traffic') ?: $this->filterString($request, 'type');
        if ($traffic === 'passengers') {
            $traffic = 'passenger';
        }
        $status = $this->filterString($request, 'status');
        $heritage = $request->boolean('heritage');
        $eraId = $directory->eraId();

        $network = in_array($network, [...self::NETWORKS, 'none'], true) ? $network : '';
        $traffic = in_array($traffic, self::TRAFFICS, true) ? $traffic : '';
        $status = in_array($status, ['open', 'closed'], true) ? $status : '';

        // Filtres appliqués À LA FOIS à la liste paginée ET aux compteurs de l'en-tête (LineDirectory),
        // pour que les compteurs reflètent la recherche en cours.
        $filters = ['q' => $q, 'network' => $network, 'traffic' => $traffic, 'status' => $status, 'heritage' => $heritage];

        $sort = $this->filterString($request, 'sort');
        // Sans tri choisi : pertinence avec une recherche, sinon le nom.
        $sort = in_array($sort, ['relevance', 'name', 'name-desc', 'code'], true) ? $sort : '';
        $order = $sort !== '' ? $sort : ($q !== '' ? 'relevance' : 'name');

        $lines = $this->applyIndexOrder(
            $directory->apply(Line::query()->select('id', 'slug', 'code', 'name', 'network', 'historical_operator'), $filters, $eraId),
            $order,
            $q,
            $directory,
            $eraId,
        )->paginate(40)->appends($request->query());

        // Trafic dominant (au km) et état actuel des seules lignes de la page.
        $summaries = $directory->summaries(collect($lines->items())->pluck('id')->all(), $eraId);
        $items = collect($lines->items())->map(fn ($l) => [
            'slug' => $l->slug,
            'code' => $l->code,
            'name' => $l->name,
            'network' => $l->network,
            'historical_operator' => $l->historical_operator,
            'traffic' => $summaries[$l->id]['traffic'] ?? null,
            'state' => $summaries[$l->id]['state'] ?? 'unknown',
            'heritage' => $summaries[$l->id]['heritage'] ?? false,
        ])->values();

        return Inertia::render('Lines/Index', [
            // Aperçu de l'encyclopédie entière (sans filtre) : le chapeau de la page ne change pas avec la recherche.
            'overview' => $directory->overview($eraId),
            'lines' => [
                'data' => $items,
                'current_page' => $lines->currentPage(),
                'last_page' => $lines->lastPage(),
                'total' => $lines->total(),
            ],
            'filters' => [
                'q' => $q,
                'network' => $network,
                'traffic' => $traffic,
                'status' => $status,
                'heritage' => $heritage,
                'sort' => $order,
            ],
            // Tri choisi par le visiteur (sinon : pertinence avec une recherche, nom sans recherche).
            'sort_chosen' => $sort !== '',
            // Rien ne correspond mot pour mot à la recherche : la liste montre les noms approchés.
            'approximate' => $directory->approximate($q),
            // Compteurs : chaque facette est calculée sans son propre filtre (cf. LineDirectory::facets).
            'stats' => $directory->facets($filters, $eraId),
        ]);
    }

    /**
     * Ordre de l'annuaire : pertinence de la recherche (LineDirectory::orderByRelevance), code, ou alphabétique
     * FRANÇAIS (accents et capitales sans effet sur le rang, collation ICU).
     *
     * @param  Builder<Line>  $builder
     * @return Builder<Line>
     */
    private function applyIndexOrder($builder, string $sort, string $q, LineDirectory $directory, ?int $eraId)
    {
        $name = 'name COLLATE "fr-x-icu"';

        return match ($sort) {
            'relevance' => $directory->orderByRelevance($builder, $q, $eraId)->orderByRaw("{$name}, id"),
            'code' => $builder->orderByRaw("code IS NULL, code COLLATE \"fr-x-icu\", {$name}, id"),
            'name-desc' => $builder->orderByRaw("{$name} DESC, id"),
            default => $builder->orderByRaw("{$name}, id"),
        };
    }

    /**
     * Fiche détaillée d'une ligne ferroviaire (construction partagée avec l'API : App\Services\LineSheet),
     * plus les accidents rattachés à la ligne (section « Accidents » ; API : `/api/v1/accidents?line=`)
     * ses passages à niveau (section « Passages à niveau » ; API : `/api/v1/lines/{slug}/level-crossings`),
     * ses voies vertes sur l'ancienne emprise (section « Voie verte » ; API : `/api/v1/lines/{slug}/greenways`),
     * la frise des mises en service de signalisation lues dans Wikipédia (section « Signalisation » ; API :
     * `/api/v1/lines/{slug}/signalling-history`), les vitesses maximales par PK et leurs changements datés (section
     * « Vitesses » ; API : `/api/v1/lines/{slug}/speeds`), l'histoire de l'électrification (section « Électrification » ; API :
     * `/api/v1/lines/{slug}/electrification-history`)
     * et ses ouvrages d'art par PK (section « Ouvrages d'art » ; API : `/api/v1/lines/{slug}/structures`)
     * et son profil en long s'il est calculé, allégé à LineProfiles::SHEET_MAX_POINTS points (section
     * « Profil en long » ; profil complet : `/api/v1/lines/{slug}/profile`), et les ères dont le tracé peut
     * être téléchargé en GPX, KML ou GeoJSON (`trace_export_eras` : seules les sources à licence ouverte
     * comptent ; API : `/api/v1/lines/{slug}/gpx`), et les documents de Gallica qui en parlent (section « Dans
     * les archives » ; API : `/api/v1/lines/{slug}/archives`) avec les notices des Archives SNCF
     * (`/api/v1/lines/{slug}/archive-records`), et les événements qui ont une histoire (lien
     * de la chronologie vers `/lines/{slug}/stories/{event}` ; API : `/api/v1/lines/{slug}/stories`).
     *
     * Le premier affichage ne porte que le haut de la fiche ; les sections plus bas sont des props différées
     * (Inertia::defer, groupe DEFERRED_GROUP), que la page demande en une requête juste après s'être affichée.
     * Les réponses REST et GraphQL de la fiche ne changent pas (LineSheet::build sans `forPage`).
     */
    public function show(Line $line, LineSheet $sheet, LineAccidents $accidents, LineLevelCrossings $crossings, LineGreenways $greenways, LineSignallingHistory $signallingHistory, SignallingDating $signallingDating, LineProfiles $profiles, LineSpeeds $speeds, LineElectrificationHistory $electrification, LineStructures $structures, LineTraceExport $traceExport, LineArchives $archives, EventStories $stories, JournalRepository $journal): Response
    {
        // Premier affichage : le haut de la fiche (LineSheet::PAGE_KEYS : en-tête, tracé, « Où et quand », « À propos »,
        // histoire, décennies, gares, chronologie). Fiche construite une fois, et seulement quand la réponse la demande :
        // le chargement des props différées (requête partielle) ne la refait pas.
        $built = null;
        $core = function () use (&$built, $sheet, $line): array {
            return $built ??= $sheet->build($line, forPage: true);
        };
        $props = [];
        foreach (LineSheet::PAGE_KEYS as $key) {
            $props[$key] = fn () => $core()[$key];
        }

        // Plus bas dans la page : props différées, demandées par la page juste après son premier affichage, en un seul
        // groupe (une requête) ; la page pose à leur place un état de chargement (chantier « budget des fiches », 23/11/2026).
        $later = null;
        $deferred = function () use (&$later, $sheet, $line): array {
            return $later ??= $sheet->pageDeferred($line);
        };
        foreach (LineSheet::PAGE_DEFERRED as $key) {
            $props[$key] = Inertia::defer(fn () => $deferred()[$key], self::DEFERRED_GROUP);
        }
        $defer = fn (callable $callback) => Inertia::defer($callback, self::DEFERRED_GROUP);

        return Inertia::render('Lines/Show', [
            // Sans ce que la page n'affiche pas ou lit à part (LineSheet::PAGE_OMITTED, données complètes dans l'API).
            ...$props,
            // Géométrie de la carte, lue par la page quand la carte s'affiche (LinePortionsController).
            'portions_url' => fn () => LinePortionsController::url($line->slug, $core()['portions_version']),
            // Compagnies rattachées par un événement de concession daté (cache de vff:compute-company-figures) : en-tête.
            'companies' => fn () => CompanyFigures::forLine($line->id),
            // Liste des gares : PK, commune et dates de chaque gare (LineSheet::pageStationDetails) ; leur position et
            // leur état, lus par la carte du haut de la page, sont dans `stations` (LineSheet::PAGE_STATION_FIRST).
            'station_details' => $defer(fn () => $sheet->pageStationDetails($line)),
            'accidents' => $defer(fn () => $accidents->forLine($line, app()->getLocale())),
            'level_crossings' => $defer(fn () => $crossings->forSheet($line)),
            'greenways' => $defer(fn () => $greenways->forSheet($line)),
            'signalling_history' => $defer(fn () => $signallingHistory->forSheet($line)),
            // Dates lues dans un article que des documents contredisent, repliées sous la frise (API : `corrections`).
            'signalling_corrections' => $defer(fn () => $signallingHistory->correctionsForSheet($line)),
            // Ligne classique en service sans mise en service de cantonnement datée : mention et date de la dernière vérification.
            'signalling_dating' => $defer(fn () => $signallingDating->forLine($line)),
            'profile' => $defer(fn () => $profiles->forLine($line, LineProfiles::SHEET_MAX_POINTS)),
            // Sans profil : pourquoi (aucun tracé, tracé hors de France, pas encore calculé), dit dans la même section.
            'profile_unavailable' => $defer(fn () => $profiles->unavailable($line)),
            // Ouvrages d'art par PK (BD TOPO, Wikidata ; API : /api/v1/lines/{slug}/structures).
            'structures' => $defer(fn () => $structures->forSheet($line)),
            // Vitesses maximales de l'ère courante par PK (SNCF Réseau) et changements datés (Wikipédia).
            'speeds' => $defer(fn () => ($era = Era::where('is_current', true)->first()) === null ? null
                : $speeds->forLine($line->id, $era->id, (int) $era->year)),
            // Histoire de l'électrification : tranches SNCF et mises sous tension datées (API : /api/v1/lines/{slug}/electrification-history).
            'electrification_history' => $defer(fn () => $electrification->forLine($line)),
            // Téléchargement du tracé, sous la carte : au premier affichage.
            'trace_export_eras' => fn () => $traceExport->exportableEraYears($line),
            'archives' => $defer(fn () => $archives->forSheet($line)),
            // Notices des fonds d'archives (Archives SNCF), sous les documents de Gallica dans la même section.
            'archive_records' => $defer(fn () => $archives->recordsForSheet($line)),
            // Histoires de la ligne (lien « Voir l'histoire » de la chronologie ; API : /api/v1/lines/{slug}/stories) :
            // une liste d'événements par fait, celui de l'adresse de l'histoire d'abord, puis ses autres sources.
            'story_events' => $defer(fn () => array_column($stories->available($line), 'event_ids')),
            // Articles du journal de bord qui parlent de la ligne (lien sous la description).
            'journal' => fn () => $line->code === null ? [] : $journal->aboutLine((string) $line->code, app()->getLocale()),
        ]);
    }
}
