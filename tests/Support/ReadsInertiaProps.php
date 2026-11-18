<?php

namespace Tests\Support;

use Illuminate\Support\Collection;

/**
 * Lecture des props Inertia dans les assertions `where('clé', fn ($valeur) => …)`.
 *
 * La valeur reçue est de type `mixed` pour PHPStan : `collect($valeur)` y échoue (« Unable to resolve
 * the template type TKey/TValue »). `rows()` fait la même chose (une Collection, jamais un cast
 * qui casserait une Collection déjà fournie par le test fluent) avec un type de retour explicite.
 */
trait ReadsInertiaProps
{
    /** @return Collection<array-key, mixed> */
    protected function rows(mixed $value): Collection
    {
        /** @var Collection<array-key, mixed> */
        return new Collection($value);
    }
}
