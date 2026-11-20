<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Pagination\Cursor;

/**
 * Curseur de pagination renvoyé par l'API (`meta.next_cursor`) : une chaîne qu'on ne fabrique pas.
 *
 * Sans ce contrôle, une chaîne illisible rendrait silencieusement la première page, et le curseur d'une
 * autre liste (qui n'a pas les mêmes colonnes de tri) ferait échouer la requête en erreur 500.
 */
final class PaginationCursor implements ValidationRule
{
    /** @param  list<string>  $columns  colonnes de tri de la liste, que le curseur doit porter */
    public function __construct(private readonly array $columns) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parameters = is_string($value) ? Cursor::fromEncoded($value)?->toArray() : null;
        $valid = $parameters !== null && array_all($this->columns, fn (string $column): bool => array_key_exists($column, $parameters));

        if (! $valid) {
            $fail('The :attribute is not a cursor returned by this endpoint (use meta.next_cursor as is).');
        }
    }
}
