<?php

namespace App\GraphQL\Loaders;

use App\Models\Line;
use App\Services\Structures\LineStructures;
use GraphQL\Deferred;

/**
 * Chargement groupé des ouvrages d'art des lignes en GraphQL : toutes les lignes d'une même liste sont lues
 * en une requête SQL. Une instance par position dans la requête (BatchLoaderRegistry de Lighthouse).
 */
final class LineStructureLoader
{
    /** @var array<int, true> lignes demandées, pas encore lues */
    private array $pending = [];

    /** @var array<int, array{summary: array<string, mixed>, items: list<array<string, mixed>>}> */
    private array $loaded = [];

    public function __construct(private readonly LineStructures $structures) {}

    public function load(Line $line): Deferred
    {
        $id = (int) $line->id;
        if (! isset($this->loaded[$id])) {
            $this->pending[$id] = true;
        }

        return new Deferred(function () use ($id): array {
            if ($this->pending !== []) {
                $this->loaded += $this->structures->forLines(array_keys($this->pending));
                $this->pending = [];
            }

            return $this->loaded[$id] ?? $this->structures->forLine($id);
        });
    }
}
