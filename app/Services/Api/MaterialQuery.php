<?php

namespace App\Services\Api;

use App\Enums\MaterialType;
use App\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Filtres et tris de la liste du matériel roulant, communs à l'API REST (`GET /api/v1/materials`)
 * et à GraphQL (`materials(filter: …)`) : mêmes règles de validation, même requête.
 */
final class MaterialQuery
{
    /** Tris proposés : clé publique => [colonne, sens]. Les valeurs inconnues (nulles) viennent en dernier. */
    public const SORTS = [
        'name' => ['name', 'asc'],
        '-name' => ['name', 'desc'],
        'in_service_from' => ['in_service_from', 'asc'],
        '-in_service_from' => ['in_service_from', 'desc'],
        'max_speed_kmh' => ['max_speed_kmh', 'asc'],
        '-max_speed_kmh' => ['max_speed_kmh', 'desc'],
        'units_built' => ['units_built', 'asc'],
        '-units_built' => ['units_built', 'desc'],
        'lines_count' => ['lines_count', 'asc'],
        '-lines_count' => ['lines_count', 'desc'],
    ];

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(MaterialType::class)],
            'builder' => ['nullable', 'string', 'max:100'],
            'operator' => ['nullable', 'string', 'max:100'],
            'line' => ['nullable', 'string', 'max:255', 'exists:lines,slug'],
            'in_service_at' => ['nullable', 'integer', 'between:1800,2100'],
            'from_year' => ['nullable', 'integer', 'between:1800,2100'],
            'to_year' => ['nullable', 'integer', 'between:1800,2100', 'gte:from_year'],
            'gauge_mm' => ['nullable', 'integer', 'between:500,2000'],
            'has_image' => ['nullable', 'boolean'],
            'has_lines' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ];
    }

    /**
     * Validation des filtres venant de GraphQL (l'API REST passe par son Form Request).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function validate(array $filters): array
    {
        return Validator::make($filters, self::rules())->validate();
    }

    /**
     * @param  Builder<Material>  $query
     * @param  array<string, mixed>  $filters  filtres validés
     * @return Builder<Material>
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $query->withCount(['lineLinks as lines_count' => fn (Builder $q) => $q->select(\DB::raw('count(distinct line_id)'))]);

        $this->whereSearch($query, (string) ($filters['q'] ?? ''));
        if (filled($filters['type'] ?? null)) {
            $query->where('materials.type', $filters['type']);
        }
        if (filled($filters['builder'] ?? null)) {
            $query->whereRaw('unaccent(COALESCE(materials.builder, \'\')) ILIKE unaccent(?)', ['%'.self::escapeLike((string) $filters['builder']).'%']);
        }
        if (filled($filters['operator'] ?? null)) {
            $operator = trim((string) $filters['operator']);
            $query->whereRaw(
                'EXISTS (SELECT 1 FROM jsonb_array_elements(COALESCE(materials.operators, \'[]\'::jsonb)) AS o
                 WHERE o->>\'wikidata_id\' = ? OR unaccent(o->>\'name\') ILIKE unaccent(?))',
                [$operator, '%'.self::escapeLike($operator).'%'],
            );
        }
        if (filled($filters['line'] ?? null)) {
            $query->whereHas('lines', fn (Builder $q) => $q->where('lines.slug', $filters['line']));
        }
        if (isset($filters['in_service_at'])) {
            $year = (int) $filters['in_service_at'];
            $query->where('materials.in_service_from', '<=', $year)
                ->where(fn (Builder $q) => $q->whereNull('materials.in_service_to')->orWhere('materials.in_service_to', '>=', $year));
        }
        if (isset($filters['from_year'])) {
            $query->where('materials.in_service_from', '>=', (int) $filters['from_year']);
        }
        if (isset($filters['to_year'])) {
            $query->where('materials.in_service_from', '<=', (int) $filters['to_year']);
        }
        if (isset($filters['gauge_mm'])) {
            $query->where('materials.gauge_mm', (int) $filters['gauge_mm']);
        }
        if (isset($filters['has_image'])) {
            filter_var($filters['has_image'], FILTER_VALIDATE_BOOLEAN) ? $query->whereNotNull('materials.image') : $query->whereNull('materials.image');
        }
        if (isset($filters['has_lines'])) {
            filter_var($filters['has_lines'], FILTER_VALIDATE_BOOLEAN) ? $query->has('lineLinks') : $query->doesntHave('lineLinks');
        }

        [$column, $direction] = self::SORTS[$filters['sort'] ?? 'name'];
        $qualified = $column === 'lines_count' ? 'lines_count' : "materials.{$column}";
        $query->orderByRaw("{$qualified} {$direction} NULLS LAST");
        if ($column !== 'name') {
            $query->orderBy('materials.name');
        }

        return $query->orderBy('materials.id');
    }

    /** Colonnes que porte un curseur de la liste du matériel (contrôle PaginationCursor). */
    public const CURSOR_COLUMNS = ['sort_null', 'sort_key', 'sort_name', 'id'];

    /** Clé de tri publique (sans le préfixe « - ») => [expression de tri, expression « valeur absente »]. */
    private const CURSOR_KEYS = [
        'name' => ['m.name COLLATE "fr-x-icu"', '0'],
        'in_service_from' => ['COALESCE(m.in_service_from, 0)', '(m.in_service_from IS NULL)::int'],
        'max_speed_kmh' => ['COALESCE(m.max_speed_kmh, 0)', '(m.max_speed_kmh IS NULL)::int'],
        'units_built' => ['COALESCE(m.units_built, 0)', '(m.units_built IS NULL)::int'],
        'lines_count' => ['m.lines_count', '0'],
    ];

    /**
     * Liste filtrée et triée, prête pour `cursorPaginate` (API REST, GraphQL, serveur MCP) : même tri
     * que apply() (valeurs inconnues en dernier, puis ordre alphabétique, puis identifiant), exprimé
     * par des colonnes calculées (CURSOR_COLUMNS) que le curseur compare comme de simples colonnes.
     *
     * @param  array<string, mixed>  $filters  filtres validés
     * @return Builder<Material>
     */
    public function cursorQuery(array $filters): Builder
    {
        $sort = (string) ($filters['sort'] ?? 'name');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        [$key, $null] = self::CURSOR_KEYS[ltrim($sort, '-')] ?? self::CURSOR_KEYS['name'];

        $filtered = $this->apply(Material::query(), $filters)->reorder()->toBase();
        $keyed = DB::query()->fromSub($filtered, 'm')->select('m.*')
            ->selectRaw("{$null} AS sort_null, {$key} AS sort_key, m.name COLLATE \"fr-x-icu\" AS sort_name");

        return Material::query()->fromSub($keyed, 'materials')
            ->orderBy('sort_null')
            ->orderBy('sort_key', $direction)
            ->orderBy('sort_name')
            ->orderBy('id', $direction);
    }

    /**
     * Recherche libre (sans accents ni casse) dans le nom, les autres noms (`designations` : « Picasso »
     * trouve l'X 3800), le constructeur et les numéros des engins. Sans effet si `$search` est vide.
     *
     * @param  Builder<Material>  $query
     * @return Builder<Material>
     */
    public function whereSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }
        $like = '%'.self::escapeLike($search).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw('unaccent(materials.name) ILIKE unaccent(?)', [$like])
            ->orWhereRaw('unaccent(COALESCE(materials.designations::text, \'\')) ILIKE unaccent(?)', [$like])
            ->orWhereRaw('unaccent(COALESCE(materials.builder, \'\')) ILIKE unaccent(?)', [$like])
            ->orWhereRaw('COALESCE(materials.fleet_numbers, \'\') ILIKE ?', [$like]));
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
