<?php

namespace App\Services;

use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Services\Api\AccidentQuery;
use App\Services\Api\MaterialQuery;
use App\Support\FrenchDepartments;
use App\Support\Search\SearchFields;
use App\Support\Search\SearchHighlight;
use App\Support\Search\SearchQuery;
use App\Support\Search\SearchText;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Page de résultats de la recherche (/search) : lignes, gares, accidents et matériel roulant trouvés, comptés et
 * paginés. Aucune règle propre : chaque groupe passe par la requête de son annuaire (LineDirectory,
 * StationDirectory, AccidentQuery, MaterialQuery), donc par la recherche commune App\Support\Search (mêmes
 * correspondances, même pertinence, noms approchés quand rien ne correspond mot pour mot). Lecture seule.
 *
 * Vue « tout » : les premiers résultats de chaque groupe (PREVIEW). Vue d'un type : ses résultats page par page
 * (PER_PAGE). Les filtres d'état (`open` en service, `closed` fermée) et de département ne valent que pour les
 * lignes et les gares, dans la vue de leur type.
 *
 * État d'une gare : en service si elle est ouverte aux voyageurs, desservie par un train touristique ou ouverte
 * au fret seul (comme une ligne en service, quel que soit son trafic) ; fermée si elle est fermée ou disparue ; une
 * gare projetée n'est ni l'une ni l'autre.
 */
final class SearchResultsPage
{
    /** Types de résultats, dans l'ordre de la page. */
    public const TYPES = ['line', 'station', 'accident', 'material'];

    /** Types qui ont les filtres d'état et de département. */
    public const FILTERABLE = ['line', 'station'];

    public const STATES = ['open', 'closed'];

    /** Résultats par groupe dans la vue « tout ». */
    public const PREVIEW = 5;

    /** Résultats par page dans la vue d'un type. */
    public const PER_PAGE = 20;

    /** Statuts affichés (StationDirectory::STATUSES) de chaque état d'une gare. */
    private const STATION_STATES = [
        'open' => ['active', 'tourist', 'no_commercial'],
        'closed' => ['declassified', 'gone'],
    ];

    public function __construct(
        private readonly LineDirectory $lines,
        private readonly StationDirectory $stations,
        private readonly AccidentQuery $accidents,
        private readonly MaterialQuery $materials,
    ) {}

    /**
     * @param  string  $q  requête (CatalogSearch::normalizeQuery), 2 caractères au moins
     * @param  string  $type  '' (tout) ou l'un des TYPES
     * @param  string  $state  '' ou l'un des STATES (lignes et gares)
     * @param  string  $dept  '' ou un code de département (lignes et gares)
     *
     * `counts` : nombre par type, état et département compris pour les lignes et les gares ; `total` : sans eux.
     * @return array{
     *     groups: list<array{type: string, total: int, approximate: bool, current_page: int, last_page: int, per_page: int, items: list<array<string, mixed>>}>,
     *     counts: array<string, int>,
     *     total: int,
     *     approximate: bool,
     *     suggestion: ?string,
     *     departments: list<array{code: string, name: string, count: int}>
     * }
     */
    public function results(string $q, string $type = '', string $state = '', string $dept = '', int $page = 1): array
    {
        $search = SearchQuery::parse($q);
        $filtered = in_array($type, self::FILTERABLE, true);
        $state = $filtered && in_array($state, self::STATES, true) ? $state : '';
        $dept = $filtered && array_key_exists($dept, FrenchDepartments::ALL) ? $dept : '';

        $builders = [];
        $counts = [];
        $approximate = [];
        foreach (self::TYPES as $t) {
            [$builders[$t], $approximate[$t]] = $this->query($t, $q, $search, $state, $dept);
            $counts[$t] = (clone $builders[$t])->count();
        }

        $groups = [];
        foreach ($type === '' ? self::TYPES : [$type] as $t) {
            if ($counts[$t] === 0 && $type === '') {
                continue;
            }
            $perPage = $type === '' ? self::PREVIEW : self::PER_PAGE;
            $last = max(1, (int) ceil($counts[$t] / $perPage));
            $current = min(max(1, $page), $last);
            $rows = $this->ordered($t, $builders[$t], $q, $search, $approximate[$t])
                ->offset(($current - 1) * $perPage)->limit($perPage)->get()->all();
            $groups[] = [
                'type' => $t,
                'total' => $counts[$t],
                'approximate' => $approximate[$t],
                'current_page' => $current,
                'last_page' => $last,
                'per_page' => $perPage,
                'items' => $this->present($t, $rows, $approximate[$t] ? null : $search),
            ];
        }

        // Rien mot pour mot dans aucun groupe : la correction la plus proche, à proposer.
        $exact = array_sum(array_map(fn (string $t): int => $approximate[$t] ? 0 : $counts[$t], self::TYPES));

        return [
            'groups' => $groups,
            'counts' => $counts,
            // Total de la vue « tout », sans état ni département (que l'onglet « Tout » ne garde pas).
            'total' => $state === '' && $dept === '' ? array_sum($counts) : array_sum(array_map(
                fn (string $t): int => in_array($t, self::FILTERABLE, true) ? $this->query($t, $q, $search)[0]->count() : $counts[$t],
                self::TYPES,
            )),
            'approximate' => $exact === 0 && array_sum($counts) > 0,
            'suggestion' => $exact === 0 ? $this->suggestion($search) : null,
            'departments' => $filtered ? $this->departments($type, $q, $search, $state) : [],
        ];
    }

    /**
     * Requête filtrée d'un type, sans ordre, et « noms approchés seulement » (rien mot pour mot).
     *
     * @return array{0: QueryBuilder|EloquentBuilder<Line>|EloquentBuilder<Accident>|EloquentBuilder<Material>, 1: bool}
     */
    private function query(string $type, string $q, SearchQuery $search, string $state = '', string $dept = ''): array
    {
        return match ($type) {
            'line' => [
                $this->lines->apply(Line::query()->select('lines.id', 'lines.slug', 'lines.code', 'lines.name'),
                    ['q' => $q, 'status' => $state, 'department' => $dept], $this->lines->eraId()),
                $this->lines->approximate($q),
            ],
            'station' => $this->stationQuery($q, $state, $dept),
            'accident' => [
                $this->accidents->apply(Accident::query()->select('accidents.*'), ['q' => $q])->reorder(),
                CatalogSearch::dateCondition($q) === null && $search->onlyApproximate(Accident::query(), SearchFields::keys('accident', 'accidents.')),
            ],
            'material' => [
                $this->materials->whereSearch(Material::query()->select('materials.id', 'materials.slug', 'materials.name', 'materials.type', 'materials.builder'), $q),
                $search->onlyApproximate(Material::query(), SearchFields::keys('material', 'materials.')),
            ],
            default => throw new \InvalidArgumentException("Type de résultat inconnu : {$type}"),
        };
    }

    /** @return array{0: QueryBuilder, 1: bool} */
    private function stationQuery(string $q, string $state, string $dept): array
    {
        $filters = ['q' => $q, 'department' => $dept];
        $approximate = $this->stations->approximate($filters, null);
        $status = $this->stations->statusExpression(null);
        $builder = $this->stations->base($filters, null, $approximate);
        if ($state !== '') {
            $statuses = self::STATION_STATES[$state];
            $builder->whereRaw("{$status} IN (".implode(',', array_fill(0, count($statuses), '?')).')', $statuses);
        }

        return [$builder, $approximate];
    }

    /**
     * Ordre de pertinence de chaque annuaire, puis ordre alphabétique français (une date d'accident : par date).
     *
     * @param  QueryBuilder|EloquentBuilder<Line>|EloquentBuilder<Accident>|EloquentBuilder<Material>  $builder
     * @return QueryBuilder|EloquentBuilder<Line>|EloquentBuilder<Accident>|EloquentBuilder<Material>
     */
    private function ordered(string $type, QueryBuilder|EloquentBuilder $builder, string $q, SearchQuery $search, bool $approximate): QueryBuilder|EloquentBuilder
    {
        if ($type === 'line') {
            /** @var EloquentBuilder<Line> $builder */
            return $this->lines->orderByRelevance($builder, $q, $this->lines->eraId())->orderByRaw('lines.name COLLATE "fr-x-icu", lines.id');
        }
        if ($type === 'station') {
            /** @var QueryBuilder $builder */
            $status = $this->stations->statusExpression(null);
            $builder->selectRaw("s.id, s.slug, s.name, s.city, s.department_code, {$status} AS status");

            return $this->stations->orderByRelevance($builder, $q, $approximate)->orderByRaw('s.name COLLATE "fr-x-icu", s.id');
        }
        if ($type === 'accident' && CatalogSearch::dateCondition($q) !== null) {
            return $builder->orderBy('accidents.occurred_on')->orderBy('accidents.id');
        }
        $table = $type === 'accident' ? 'accidents' : 'materials';
        [$nameKey, $allKey] = SearchFields::keys($type === 'accident' ? 'accident' : 'material', "{$table}.");
        $name = $type === 'accident' ? "COALESCE(accidents.name->>'fr', accidents.slug)" : 'materials.name';

        return $search->orderByRelevance($builder, $nameKey, $allKey, $approximate)
            ->orderByRaw("{$name} COLLATE \"fr-x-icu\"")
            ->when($type === 'accident', fn ($b) => $b->orderBy('accidents.occurred_on'))
            ->orderBy("{$table}.id");
    }

    /**
     * Résultats d'une page, avec le passage trouvé (`highlight`, sans objet pour les noms approchés).
     *
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function present(string $type, array $rows, ?SearchQuery $search): array
    {
        $mark = fn (string $name): array => $search === null ? [['text' => $name, 'mark' => false]] : SearchHighlight::segments($name, $search);

        if ($type === 'line') {
            /** @var list<Line> $rows */
            $digits = $search?->digits();
            $summaries = $this->lines->summaries(array_map(fn (Line $l): int => (int) $l->id, $rows), $this->lines->eraId());

            return array_map(fn (Line $l): array => [
                'slug' => (string) $l->slug,
                'name' => (string) $l->name,
                'highlight' => $mark((string) $l->name),
                'code' => $l->code,
                // Ligne trouvée par son code (« 570 000 ») : la plaque du code est marquée.
                'code_match' => $digits !== null && strlen($digits) >= 3 && str_starts_with(str_replace(' ', '', (string) $l->code), $digits),
                'state' => $summaries[(int) $l->id]['state'] ?? 'unknown',
            ], $rows);
        }
        if ($type === 'station') {
            return array_map(fn (object $s): array => [
                'slug' => (string) $s->slug,
                'name' => (string) $s->name,
                'highlight' => $mark((string) $s->name),
                'city' => $s->city,
                'department_code' => $s->department_code,
                'status' => $s->status,
            ], $rows);
        }
        if ($type === 'accident') {
            $locale = app()->getLocale();

            /** @var list<Accident> $rows */
            return array_map(function (Accident $a) use ($mark, $locale): array {
                $name = $a->nameIn($locale);

                return [
                    'slug' => $a->slug,
                    'name' => $name,
                    'highlight' => $mark($name),
                    'date' => $a->occurred_on->toDateString(),
                    'date_precision' => $a->date_precision->value,
                    'kind' => $a->kind->value,
                    'commune' => $a->commune_name,
                    'department_code' => $a->department_code,
                ];
            }, $rows);
        }

        /** @var list<Material> $rows */
        return array_map(fn (Material $m): array => [
            'slug' => (string) $m->slug,
            'name' => (string) $m->name,
            'highlight' => $mark((string) $m->name),
            'kind' => $m->type,
            'builder' => $m->builder,
        ], $rows);
    }

    /**
     * Nom le plus ressemblant de toute l'encyclopédie parmi les noms approchés (fautes de frappe, même seuil), s'il
     * diffère de la requête ; null sinon.
     */
    private function suggestion(SearchQuery $search): ?string
    {
        if (! $search->canBeFuzzy()) {
            return null;
        }
        $candidates = [
            [Line::query()->toBase(), 'line', 'lines.', 'lines.name'],
            [$this->stations->base([], null), 'station', 's.', 's.name'],
            [Accident::query()->toBase(), 'accident', 'accidents.', "accidents.name->>'fr'"],
            [Material::query()->toBase(), 'material', 'materials.', 'materials.name'],
        ];
        $best = null;
        foreach ($candidates as [$base, $entity, $prefix, $name]) {
            [$nameKey] = SearchFields::keys($entity, $prefix);
            [$fuzzy, $fuzzyBind] = $search->fuzzySql($nameKey);
            // Parmi les noms approchés, celui qui ressemble le plus à la requête entière (pas à l'un de ses mots :
            // « angoulemme » propose « Angoulême », pas « Ligne d'Angoulême-Ville à Confolens »).
            $row = $base->whereRaw($fuzzy, $fuzzyBind)
                ->selectRaw("{$name} AS label, similarity(?, {$nameKey}) AS similarity", [$search->fuzzyText()])
                ->orderByDesc('similarity')
                ->first();
            if ($row !== null && ($best === null || (float) $row->similarity > $best[1])) {
                $best = [(string) $row->label, (float) $row->similarity];
            }
        }

        return $best !== null && SearchText::core(SearchText::key($best[0])) !== $search->core ? $best[0] : null;
    }

    /**
     * Départements où le type a des résultats (filtre d'état compris), pour le filtre de département.
     *
     * @return list<array{code: string, name: string, count: int}>
     */
    private function departments(string $type, string $q, SearchQuery $search, string $state): array
    {
        [$builder] = $this->query($type, $q, $search, $state);
        $territories = app(Territories::class);
        $counts = $type === 'line'
            ? $territories->lineCounts($builder->select('lines.id')->toBase())
            : $territories->stationCounts($builder);
        ksort($counts, SORT_STRING);

        $out = [];
        foreach ($counts as $code => $n) {
            $known = FrenchDepartments::find((string) $code);
            if ($known !== null) {
                $out[] = ['code' => (string) $code, 'name' => $known[0], 'count' => $n];
            }
        }

        return $out;
    }
}
