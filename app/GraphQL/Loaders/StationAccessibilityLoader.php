<?php

namespace App\GraphQL\Loaders;

use App\Models\Station;
use App\Services\Accessibility\StationAccessibility;
use GraphQL\Deferred;

/**
 * Chargement groupé de l'accessibilité en GraphQL : toutes les gares d'une même liste sont lues en une requête SQL
 * (StationAccessibility::forStations). Une instance par position dans la requête (BatchLoaderRegistry).
 */
final class StationAccessibilityLoader
{
    /** @var array<int, true> gares demandées, pas encore lues */
    private array $pending = [];

    /** @var array<int, ?array<string, mixed>> blocs déjà lus (null : rien pour la gare) */
    private array $loaded = [];

    public function __construct(private readonly StationAccessibility $accessibility) {}

    public function load(Station $station): Deferred
    {
        $id = (int) $station->id;
        if (! array_key_exists($id, $this->loaded)) {
            $this->pending[$id] = true;
        }

        return new Deferred(function () use ($id): ?array {
            if ($this->pending !== []) {
                $ids = array_keys($this->pending);
                $this->loaded += $this->accessibility->forStations($ids) + array_fill_keys($ids, null);
                $this->pending = [];
            }

            return $this->loaded[$id] ?? null;
        });
    }
}
