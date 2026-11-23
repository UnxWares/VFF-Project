<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Models\Era;
use App\Models\Station;
use App\Services\Api\MediaGallery;
use App\Services\Heritage\StationHeritage;
use App\Services\Media\Aerial\AerialImages;
use App\Services\Sncf\StationAttendance;
use App\Services\StationDirectory;
use App\Services\StationSheet;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gares à travers le temps : annuaire filtrable (par décennie, statut,
 * département) et fiche (chronologie, présence par décennie, lignes
 * desservies selon l'époque, sources et provenance des attributs).
 *
 * Données : vff:import-station-sources → vff:consolidate-stations →
 * vff:attach-stations → vff:build-station-eras.
 */
class StationController extends Controller
{
    use ReadsListFilters;

    public function index(Request $request, StationDirectory $directory): Response
    {
        $q = $this->filterString($request, 'q', trim: true);
        $status = $this->filterString($request, 'status');
        $dept = $this->filterString($request, 'dept', trim: true);
        $flagged = $request->boolean('flagged');
        $heritage = $request->boolean('heritage');
        $sort = $this->filterString($request, 'sort');
        // Sans tri choisi : pertinence avec une recherche, sinon le nom.
        $sort = in_array($sort, ['relevance', 'name', 'name-desc', 'city', 'opened', 'closed'], true) ? $sort : '';
        $order = $sort !== '' ? $sort : ($q !== '' ? 'relevance' : 'name');
        $eras = Era::orderBy('year')->get(['id', 'year', 'is_current']);
        $era = $request->integer('era') ? $eras->firstWhere('year', $request->integer('era')) : null;

        $filters = ['q' => $q, 'department' => $dept, 'flagged' => $flagged, 'heritage' => $heritage];
        $approximate = $directory->approximate($filters, $era);
        $base = $directory->base($filters, $era, $approximate);
        $statusExpr = $directory->statusExpression($era);

        $list = $directory->whereStatus(clone $base, $status, $era)
            ->selectRaw(<<<SQL
                s.id, s.slug, s.name, s.type, s.city, s.department_code, s.uic_code, s.sources_count, s.position_flagged,
                {$statusExpr} AS status,
                EXTRACT(YEAR FROM s.opened_at)::int AS opened_year, s.opened_precision,
                (s.provenance->>'opened_after')::int AS opened_after, EXTRACT(YEAR FROM s.closed_at)::int AS closed_year,
                s.provenance->>'opened_at' AS opened_source, s.provenance->>'closed_at' AS closed_source,
                (s.provenance->>'closed_after')::int AS closed_after
            SQL)
            ->tap(fn ($b) => $this->orderIndex($b, $directory, $order, $q, $approximate))
            ->paginate(50)
            ->appends($request->query());
        $linesByStation = $directory->lines(collect($list->items())->pluck('id')->all());

        return Inertia::render('Stations/Index', [
            'stations' => [
                'data' => collect($list->items())->map(function ($row) use ($linesByStation) {
                    $row = (array) $row;
                    $row['lines'] = $linesByStation[$row['id']] ?? ['items' => [], 'more' => 0];
                    unset($row['id']);

                    return $row;
                })->all(),
                'current_page' => $list->currentPage(),
                'last_page' => $list->lastPage(),
                'total' => $list->total(),
            ],
            'filters' => ['q' => $q, 'status' => $status, 'dept' => $dept, 'era' => $era?->year, 'flagged' => $flagged, 'heritage' => $heritage, 'sort' => $order],
            // Tri choisi par le visiteur (sinon : pertinence avec une recherche, nom sans recherche).
            'sort_chosen' => $sort !== '',
            // Rien ne correspond mot pour mot à la recherche : la liste montre les noms approchés.
            'approximate' => $approximate,
            'overall' => $directory->overall(),
            'counts' => $directory->counts($base, $era),
            'eras' => $eras->pluck('year'),
            'departments' => DB::table('stations')->whereNotNull('department_code')->distinct()->orderBy('department_code')->pluck('department_code'),
        ]);
    }

    /**
     * Ordre de l'annuaire : pertinence de la recherche (StationDirectory::orderByRelevance), ou alphabétique FRANÇAIS
     * (collation ICU : accents et capitales sans effet sur le rang). Les dates absentes viennent toujours en dernier.
     */
    private function orderIndex(Builder $builder, StationDirectory $directory, string $sort, string $q, bool $approximate): void
    {
        $name = 's.name COLLATE "fr-x-icu"';

        match ($sort) {
            'relevance' => $directory->orderByRelevance($builder, $q, $approximate)->orderByRaw("{$name}, s.id"),
            'name-desc' => $builder->orderByRaw("{$name} DESC, s.id"),
            'city' => $builder->orderByRaw("s.city IS NULL, s.city COLLATE \"fr-x-icu\", {$name}, s.id"),
            'opened' => $builder->orderByRaw("s.opened_at IS NULL, s.opened_at, {$name}, s.id"),
            'closed' => $builder->orderByRaw("s.closed_at IS NULL, s.closed_at, {$name}, s.id"),
            default => $builder->orderByRaw("{$name}, s.id"),
        };
    }

    public function show(Station $station, StationSheet $sheet, MediaGallery $gallery, StationAttendance $attendance, StationHeritage $heritage): Response
    {
        $page = $sheet->page($station);

        return Inertia::render('Stations/Show', [
            ...$page,
            // Nombre d'images publiées : la galerie (chargée par l'API) n'est posée que s'il y en a.
            'media_count' => $gallery->count($station),
            // Vues aériennes historiques de l'IGN (relais /media/aerial) : une par période, absente si hors couverture.
            'aerial_views' => AerialImages::views($station->slug, $page['station']['lat'], $page['station']['lng'], (int) $station->id),
            // Fréquentation annuelle (SNCF Gares & Connexions, ODbL) : nulle quand les jeux n'en donnent aucune.
            'attendance' => $attendance->forStation((int) $station->id),
            // Patrimoine du bâtiment voyageurs et des ouvrages (Mérimée, Wikidata) : nul sans table ou sans donnée.
            'heritage' => $heritage->forStation((int) $station->id),
        ]);
    }
}
