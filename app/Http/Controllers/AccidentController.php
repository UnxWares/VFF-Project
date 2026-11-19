<?php

namespace App\Http\Controllers;

use App\Enums\AccidentKind;
use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Http\Resources\AccidentDetailResource;
use App\Models\Accident;
use App\Models\AccidentLine;
use App\Services\Api\AccidentQuery;
use App\Support\FrenchDepartments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages /accidents (liste chronologique filtrable) et /accidents/{slug} (fiche). Mêmes filtres
 * que l'API (AccidentQuery) ; la fiche reçoit la même représentation que `GET /api/v1/accidents/{slug}`.
 */
class AccidentController extends Controller
{
    use ReadsListFilters;

    private const PER_PAGE = 30;

    private const SORTS = ['date', '-date', '-deaths'];

    public function index(Request $request, AccidentQuery $query): Response
    {
        $filters = $this->filters($request);
        $apiFilters = array_filter([
            'q' => $filters['q'] !== '' ? $filters['q'] : null,
            'from' => $filters['decade'],
            'to' => $filters['decade'] !== null ? $filters['decade'] + 9 : null,
            'kind' => $filters['kind'],
            'department' => $filters['dept'],
            'line' => $filters['line'],
            'sort' => $filters['sort'],
        ], fn ($v) => $v !== null);

        $page = $query->apply(Accident::query(), $apiFilters)
            ->with(['lineLinks' => fn ($q) => $q->where('is_primary', true)->with('line:id,slug,code,name')])
            ->paginate(self::PER_PAGE)
            ->withQueryString();
        $locale = app()->getLocale();

        return Inertia::render('Accidents/Index', [
            'accidents' => [
                'data' => $page->getCollection()->map(fn (Accident $a) => $this->listItem($a, $locale))->all(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'filters' => $filters,
            'facets' => $this->facets(),
        ]);
    }

    public function show(Request $request, string $accident): Response
    {
        $model = Accident::query()->withCoordinates()->where('slug', $accident)->firstOrFail();
        $model->load([
            'lineLinks' => fn ($q) => $q->with(['line:id,slug,code,name', 'sectionFrom:id,slug,name', 'sectionTo:id,slug,name']),
            'sources',
            'nearestStation:id,slug,name',
        ]);

        return Inertia::render('Accidents/Show', [
            'accident' => AccidentDetailResource::make($model)->resolve($request),
        ]);
    }

    /**
     * Filtres de l'adresse, lus défensivement : une valeur inconnue est ignorée (jamais d'erreur).
     *
     * @return array{q: string, decade: ?int, kind: ?string, dept: ?string, line: ?string, sort: string}
     */
    private function filters(Request $request): array
    {
        $decade = $this->filterString($request, 'decade', true);
        $kind = $this->filterString($request, 'kind', true);
        $dept = strtoupper($this->filterString($request, 'dept', true));
        $line = $this->filterString($request, 'line', true);
        $sort = $this->filterString($request, 'sort', true);

        return [
            'q' => mb_substr($this->filterString($request, 'q', true), 0, 100),
            'decade' => preg_match('/^1[89]\d0$|^20[0-2]0$/', $decade) ? (int) $decade : null,
            'kind' => AccidentKind::tryFrom($kind)?->value,
            'dept' => FrenchDepartments::find($dept) !== null && $dept !== '' ? $dept : null,
            'line' => $line !== '' && DB::table('lines')->where('slug', $line)->exists() ? $line : null,
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'date',
        ];
    }

    /** @return array<string, mixed> */
    private function listItem(Accident $a, string $locale): array
    {
        /** @var AccidentLine|null $primary */
        $primary = $a->lineLinks->firstWhere('is_primary', true);

        return [
            'slug' => $a->slug,
            'name' => $a->nameIn($locale),
            'date' => $a->occurred_on->toDateString(),
            'date_precision' => $a->date_precision->value,
            'kind' => $a->kind->value,
            'commune' => $a->commune_name,
            'department_code' => $a->department_code,
            'line' => $primary?->line !== null ? [
                'slug' => $primary->line->slug,
                'code' => $primary->line->code,
                'name' => $primary->line->name,
                'confidence' => $primary->confidence->value,
            ] : null,
            'deaths' => $a->deaths,
            'injuries' => $a->injuries,
            'review_status' => $a->review_status->value,
        ];
    }

    /**
     * Valeurs proposées par les filtres, avec leur nombre d'accidents.
     *
     * @return array{decades: list<array{decade: int, count: int}>, kinds: array<string, int>, departments: list<array{code: string, name: string, count: int}>, lines: list<array{slug: string, code: ?string, name: string, count: int}>}
     */
    private function facets(): array
    {
        $decades = DB::table('accidents')->whereNull('alias_of_id')->whereNotIn('kind', AccidentKind::HIDDEN)
            ->selectRaw('(EXTRACT(YEAR FROM occurred_on)::int / 10) * 10 AS decade, COUNT(*) AS n')
            ->groupBy('decade')->orderBy('decade')->get()
            ->map(fn ($r) => ['decade' => (int) $r->decade, 'count' => (int) $r->n])->all();
        $kinds = DB::table('accidents')->whereNull('alias_of_id')->whereNotIn('kind', AccidentKind::HIDDEN)->selectRaw('kind, COUNT(*) AS n')->groupBy('kind')->pluck('n', 'kind')
            ->map(fn ($n) => (int) $n)->all();
        $departments = DB::table('accidents')->whereNull('alias_of_id')->whereNotIn('kind', AccidentKind::HIDDEN)->whereNotNull('department_code')
            ->selectRaw('department_code, COUNT(*) AS n')->groupBy('department_code')->orderBy('department_code')->get()
            ->map(fn ($r) => ['code' => $r->department_code, 'name' => FrenchDepartments::find($r->department_code)[0] ?? $r->department_code, 'count' => (int) $r->n])
            ->all();
        $lines = DB::table('accident_lines')->join('lines', 'lines.id', '=', 'accident_lines.line_id')
            ->join('accidents', 'accidents.id', '=', 'accident_lines.accident_id')->whereNull('accidents.alias_of_id')->whereNotIn('accidents.kind', AccidentKind::HIDDEN)
            ->selectRaw('lines.slug, lines.code, lines.name, COUNT(DISTINCT accident_lines.accident_id) AS n')
            ->groupBy('lines.slug', 'lines.code', 'lines.name')->orderBy('lines.name')->get()
            ->map(fn ($r) => ['slug' => $r->slug, 'code' => $r->code, 'name' => $r->name, 'count' => (int) $r->n])
            ->all();

        return ['decades' => $decades, 'kinds' => $kinds, 'departments' => $departments, 'lines' => $lines];
    }
}
