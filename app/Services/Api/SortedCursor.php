<?php

namespace App\Services\Api;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

/**
 * Tri stable et paginable par curseur des annuaires (lignes, gares).
 *
 * La requête interne expose trois colonnes de tri : `sort_null` (1 quand la clé de tri manque, pour
 * mettre ces fiches en dernier dans les deux sens), `sort_key` (clé de tri, déjà munie de sa
 * collation : ordre alphabétique français pour les noms) et `id` (départage). On l'enveloppe dans une
 * sous-requête pour que le curseur compare ces colonnes calculées comme de simples colonnes.
 */
final class SortedCursor
{
    /** Colonnes que porte un curseur d'annuaire (contrôle PaginationCursor). */
    public const COLUMNS = ['sort_null', 'sort_key', 'id'];

    public static function wrap(Builder $inner, string $direction): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return DB::query()->fromSub($inner, 'd')
            ->orderBy('sort_null')
            ->orderBy('sort_key', $direction)
            ->orderBy('id', $direction);
    }

    /**
     * Corps d'une page de l'annuaire, au même format que les autres listes paginées par curseur
     * (`data`, `links`, `meta`). Les liens et curseurs se calculent sur les lignes brutes (qui portent
     * les colonnes de tri), les données présentées n'ayant plus ces colonnes.
     *
     * @param  CursorPaginator<int, object>  $page
     * @param  list<array<string, mixed>>  $data
     * @param  array<string, mixed>  $meta  métadonnées propres à la liste
     * @return array{data: list<array<string, mixed>>, links: array<string, ?string>, meta: array<string, mixed>}
     */
    public static function envelope(CursorPaginator $page, array $data, array $meta = []): array
    {
        return [
            'data' => $data,
            'links' => ['first' => null, 'last' => null, 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            'meta' => [
                'path' => $page->path(),
                'per_page' => $page->perPage(),
                'next_cursor' => $page->nextCursor()?->encode(),
                'prev_cursor' => $page->previousCursor()?->encode(),
            ] + $meta,
        ];
    }
}
