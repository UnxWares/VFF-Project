<?php

namespace App\GraphQL\Resolvers;

use App\Models\Era;
use App\Rules\PaginationCursor;
use App\Services\Api\LineDirectoryQuery;
use App\Services\Api\SortedCursor;
use App\Services\Api\StationDirectoryQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Validator;

/**
 * Requêtes `lineDirectory` et `stationDirectory` : les annuaires de l'API REST (`GET /api/v1/lines`,
 * `GET /api/v1/stations`), mêmes filtres, même tri, même curseur (une valeur `next_cursor` de l'un
 * vaut pour l'autre). Les compteurs (`facets`) ne sont calculés que s'ils sont demandés.
 */
final class DirectoryResolver
{
    public function __construct(private readonly LineDirectoryQuery $lines, private readonly StationDirectoryQuery $stations) {}

    /**
     * @param  array{filter?: array<string, mixed>, first?: int, after?: ?string}  $args
     * @return array<string, mixed>
     */
    public function lines(mixed $root, array $args): array
    {
        $filters = $this->lines->validate(self::lowerOrder($args['filter'] ?? []));
        $page = $this->page($this->lines->query($filters), $args);

        return [
            'data' => $this->lines->present($page->items(), $filters),
            'next_cursor' => $page->nextCursor()?->encode(),
            'era_year' => Era::whereKey($this->lines->eraId($filters))->value('year'),
            '_filters' => $filters,
        ];
    }

    /**
     * @param  array{filter?: array<string, mixed>, first?: int, after?: ?string}  $args
     * @return array<string, mixed>
     */
    public function stations(mixed $root, array $args): array
    {
        $filters = $this->stations->validate(self::lowerOrder($args['filter'] ?? []));
        $page = $this->page($this->stations->query($filters), $args);

        return [
            'data' => $this->stations->present($page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
            'era_year' => $this->stations->era($filters)?->year,
            '_filters' => $filters,
        ];
    }

    /**
     * @param  array{_filters: array<string, mixed>}  $root
     * @return array<string, mixed>
     */
    public function lineFacets(array $root): array
    {
        return $this->lines->facets($root['_filters']);
    }

    /**
     * Nombre de gares par statut, en liste de paires (statut, nombre).
     *
     * @param  array{_filters: array<string, mixed>}  $root
     * @return list<array{code: string, count: int}>
     */
    public function stationFacets(array $root): array
    {
        $counts = $this->stations->facets($root['_filters']);

        return array_map(fn (string $code, int $count) => ['code' => $code, 'count' => $count], array_keys($counts), $counts);
    }

    /**
     * Nombre de gares par département, en liste de paires (code INSEE, nombre).
     *
     * @param  array{_filters: array<string, mixed>}  $root
     * @return list<array{code: string, count: int}>
     */
    public function stationDepartmentFacets(array $root): array
    {
        $counts = $this->stations->departmentFacets($root['_filters']);

        return array_map(fn (int|string $code, int $count) => ['code' => (string) $code, 'count' => $count], array_keys($counts), $counts);
    }

    /**
     * Dictionnaire de compteurs (`networks`, `traffics`, `departments`) en liste de paires (code, nombre).
     *
     * @param  array<string, mixed>  $root
     * @param  array<string, mixed>  $args
     * @return list<array{code: string, count: int}>
     */
    public function pairs(array $root, array $args, mixed $context, ResolveInfo $info): array
    {
        $counts = (array) ($root[$info->fieldName] ?? []);

        return array_map(fn (int|string $code, int $count) => ['code' => (string) $code, 'count' => $count], array_keys($counts), $counts);
    }

    /**
     * Page de la requête triée : `first` de 1 à 100 (25 par défaut), `after` = curseur `next_cursor`.
     *
     * @param  array{first?: int, after?: ?string}  $args
     * @return CursorPaginator<int, object>
     */
    private function page(Builder $query, array $args): CursorPaginator
    {
        $input = Validator::make($args, [
            'first' => ['nullable', 'integer', 'min:1', 'max:100'],
            'after' => ['nullable', 'string', 'max:512', new PaginationCursor(SortedCursor::COLUMNS)],
        ])->validate();
        $cursor = isset($input['after']) ? Cursor::fromEncoded($input['after']) : null;

        return $query->cursorPaginate((int) ($input['first'] ?? 25), ['*'], 'cursor', $cursor);
    }

    /**
     * L'énumération `SortOrder` arrive en majuscules (`DESC`) : les règles attendent `asc` ou `desc`.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    private static function lowerOrder(array $filter): array
    {
        if (isset($filter['order']) && is_string($filter['order'])) {
            $filter['order'] = strtolower($filter['order']);
        }

        return $filter;
    }
}
