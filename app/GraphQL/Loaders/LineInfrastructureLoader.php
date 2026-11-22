<?php

namespace App\GraphQL\Loaders;

use App\Models\Line;
use App\Services\Sncf\LineInfrastructure;
use GraphQL\Deferred;

/**
 * Chargement groupé de l'infrastructure SNCF des lignes en GraphQL : toutes les lignes d'une même liste sont
 * lues en deux requêtes SQL (voies, radio), au lieu de deux par ligne. Une instance par position dans la
 * requête (BatchLoaderRegistry de Lighthouse), oubliée à la fin de la requête.
 */
final class LineInfrastructureLoader
{
    /** @var array<int, true> lignes demandées, pas encore lues */
    private array $pending = [];

    /** @var array<int, array{tracks: array<string, mixed>, radio: array<string, mixed>}> blocs déjà lus */
    private array $loaded = [];

    public function __construct(private readonly LineInfrastructure $infrastructure) {}

    public function load(Line $line): Deferred
    {
        $id = (int) $line->id;
        if (! isset($this->loaded[$id])) {
            $this->pending[$id] = true;
        }

        return new Deferred(function () use ($id): array {
            if ($this->pending !== []) {
                $this->loaded += $this->infrastructure->forLines(array_keys($this->pending));
                $this->pending = [];
            }

            return $this->loaded[$id] ?? $this->infrastructure->forLine($id);
        });
    }
}
