<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Models\Era;
use App\Models\Station;
use App\Services\Api\MediaGallery;
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
        $sort = $this->filterString($request, 'sort');
        $sort = in_array($sort, ['name', 'name-desc', 'city', 'opened', 'closed'], true) ? $sort : 'name';
        $eras = Era::orderBy('year')->get(['id', 'year', 'is_current']);
        $era = $request->integer('era') ? $eras->firstWhere('year', $request->integer('era')) : null;

        $base = $directory->base(['q' => $q, 'department' => $dept, 'flagged' => $flagged], $era);
        $statusExpr = $directory->statusExpression($era);

        $list = $directory->whereStatus(clone $base, $status, $era)
            ->selectRaw(<<<SQL
                s.id, s.slug, s.name, s.type, s.city, s.department_code, s.uic_code, s.sources_count, s.position_flagged,
                {$statusExpr} AS status,
                EXTRACT(YEAR FROM s.opened_at)::int AS opened_year, EXTRACT(YEAR FROM s.closed_at)::int AS closed_year,
                s.provenance->>'opened_at' AS opened_source, s.provenance->>'closed_at' AS closed_source,
                (s.provenance->>'closed_after')::int AS closed_after
            SQL)
            ->tap(fn ($b) => $this->orderIndex($b, $sort, $q))
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
            'filters' => ['q' => $q, 'status' => $status, 'dept' => $dept, 'era' => $era?->year, 'flagged' => $flagged, 'sort' => $sort],
            'overall' => $directory->overall(),
            'counts' => $directory->counts($base, $era),
            'eras' => $eras->pluck('year'),
            'departments' => DB::table('stations')->whereNotNull('department_code')->distinct()->orderBy('department_code')->pluck('department_code'),
        ]);
    }

    /**
     * Ordre de l'annuaire : alphabétique FRANÇAIS (collation ICU : accents et capitales sans effet sur le rang).
     * Tri par nom avec une recherche : nom exact, puis noms qui commencent par le terme, puis le reste.
     * Les dates absentes viennent toujours en dernier.
     */
    private function orderIndex(Builder $builder, string $sort, string $q): void
    {
        $name = 's.name COLLATE "fr-x-icu"';

        match ($sort) {
            'name-desc' => $builder->orderByRaw("{$name} DESC, s.id"),
            'city' => $builder->orderByRaw("s.city IS NULL, s.city COLLATE \"fr-x-icu\", {$name}, s.id"),
            'opened' => $builder->orderByRaw("s.opened_at IS NULL, s.opened_at, {$name}, s.id"),
            'closed' => $builder->orderByRaw("s.closed_at IS NULL, s.closed_at, {$name}, s.id"),
            default => $this->orderByName($builder, $q, $name),
        };
    }

    private function orderByName(Builder $builder, string $q, string $name): void
    {
        if ($q !== '') {
            $builder->orderByRaw(
                'CASE WHEN lower(s.name) = lower(?) THEN 0 WHEN s.name ILIKE ? THEN 1 ELSE 2 END',
                [$q, $this->prefixPattern($q)],
            );
        }
        $builder->orderByRaw("{$name}, s.id");
    }

    public function show(Station $station, StationSheet $sheet, MediaGallery $gallery): Response
    {
        return Inertia::render('Stations/Show', [
            ...$sheet->page($station),
            // Nombre d'images publiées : la galerie (chargée par l'API) n'est posée que s'il y en a.
            'media_count' => $gallery->count($station),
        ]);
    }
}
