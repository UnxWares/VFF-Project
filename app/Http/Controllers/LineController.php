<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Models\Line;
use App\Services\Accidents\LineAccidents;
use App\Services\LineDirectory;
use App\Services\LineSheet;
use App\Services\LineStats;
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
        $sort = in_array($sort, ['name', 'name-desc', 'code'], true) ? $sort : 'name';

        $lines = $this->applyIndexOrder(
            $directory->apply(Line::query()->select('id', 'slug', 'code', 'name', 'network', 'historical_operator'), $filters, $eraId),
            $sort,
            $q,
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
                'sort' => $sort,
            ],
            // Compteurs : chaque facette est calculée sans son propre filtre (cf. LineDirectory::facets).
            'stats' => $directory->facets($filters, $eraId),
        ]);
    }

    /**
     * Ordre de l'annuaire : alphabétique FRANÇAIS (accents et capitales sans effet sur le rang, collation ICU),
     * et, pour le tri par nom avec une recherche, le nom exact puis les noms qui commencent par le terme.
     *
     * @param  Builder<Line>  $builder
     * @return Builder<Line>
     */
    private function applyIndexOrder($builder, string $sort, string $q)
    {
        $name = 'name COLLATE "fr-x-icu"';

        if ($sort === 'code') {
            return $builder->orderByRaw("code IS NULL, code COLLATE \"fr-x-icu\", {$name}, id");
        }
        if ($sort === 'name-desc') {
            return $builder->orderByRaw("{$name} DESC, id");
        }
        if ($q !== '') {
            $builder->orderByRaw(
                'CASE WHEN lower(name) = lower(?) THEN 0 WHEN name ILIKE ? THEN 1 ELSE 2 END',
                [$q, $this->prefixPattern($q)],
            );
        }

        return $builder->orderByRaw("{$name}, id");
    }

    /**
     * Fiche détaillée d'une ligne ferroviaire (construction partagée avec l'API : App\Services\LineSheet),
     * plus les accidents rattachés à la ligne (section « Accidents » ; API : `/api/v1/accidents?line=`).
     */
    public function show(Line $line, LineSheet $sheet, LineAccidents $accidents): Response
    {
        return Inertia::render('Lines/Show', [
            ...$sheet->build($line),
            'accidents' => $accidents->forLine($line, app()->getLocale()),
        ]);
    }
}
