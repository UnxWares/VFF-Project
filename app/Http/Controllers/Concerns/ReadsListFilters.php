<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Lecture défensive des filtres d'URL des pages de liste (/lines, /stations).
 *
 *   - un paramètre envoyé en tableau (« ?q[]=x ») est ignoré : Request::string()
 *     le convertissait en chaîne → « Array to string conversion » → HTTP 500 ;
 *   - la recherche est LITTÉRALE : « % », « _ » et « \ » sont échappés avant
 *     ILIKE (avant, « ?q=% » listait tout et « ?q=\ » ne trouvait rien).
 */
trait ReadsListFilters
{
    /** Valeur texte d'un paramètre de requête, '' s'il est absent ou non scalaire. */
    protected function filterString(Request $request, string $key, bool $trim = false): string
    {
        /** @var mixed $value Laravel type query() trop étroitement (array|string|null). */
        $value = $request->query($key);
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return '';
        }

        return $trim ? trim((string) $value) : (string) $value;
    }

    /** Motif ILIKE « contient » pour une recherche littérale. */
    protected function containsPattern(string $search): string
    {
        return '%'.$this->escapeLike($search).'%';
    }

    /** Motif LIKE « commence par » pour une recherche littérale. */
    protected function prefixPattern(string $search): string
    {
        return $this->escapeLike($search).'%';
    }

    /** Échappe les jokers LIKE de PostgreSQL (caractère d'échappement par défaut : « \ »). */
    protected function escapeLike(string $search): string
    {
        return addcslashes($search, '\\%_');
    }
}
