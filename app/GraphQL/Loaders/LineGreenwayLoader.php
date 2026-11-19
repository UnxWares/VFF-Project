<?php

namespace App\GraphQL\Loaders;

use App\Models\Line;
use App\Services\Greenways\LineGreenways;
use GraphQL\Deferred;

/**
 * Chargement groupé des voies vertes en GraphQL : toutes les lignes d'une même liste (par exemple
 * `lines(first: 100)`) sont lues en une requête SQL, au lieu d'une par ligne. Une instance par
 * position dans la requête (BatchLoaderRegistry de Lighthouse), oubliée à la fin de la requête.
 */
final class LineGreenwayLoader
{
    /** @var array<int, true> lignes demandées, pas encore lues */
    private array $pending = [];

    /** @var array<int, list<array<string, mixed>>> voies vertes déjà lues, par identifiant de ligne */
    private array $loaded = [];

    public function __construct(private readonly LineGreenways $greenways) {}

    /** Voies vertes de la ligne (`LineGreenways::groups`), lues avec celles des autres lignes demandées. */
    public function load(Line $line): Deferred
    {
        $id = (int) $line->id;
        if (! isset($this->loaded[$id])) {
            $this->pending[$id] = true;
        }

        return new Deferred(function () use ($id): array {
            if ($this->pending !== []) {
                $this->loaded += $this->greenways->groupsByLine(array_keys($this->pending));
                $this->pending = [];
            }

            return $this->loaded[$id] ?? [];
        });
    }
}
