<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use Closure;
use GraphQL\Deferred;

/**
 * Chargement groupé de `Line.signalling_history` et `Line.signalling_corrections` : les lignes demandées pendant la résolution d'une liste sont
 * notées, puis leurs faits lus en une seule requête au premier besoin (`publicFactsOfLines` ou `correctionsOfLines` de LineSignallingHistory).
 * Une instance par champ et par requête GraphQL (BatchLoaderRegistry).
 */
final class SignallingHistoryBatch
{
    /** @var array<int, true> lignes demandées, pas encore lues */
    private array $pending = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $loaded = [];

    /** @param Closure(list<int>): array<int, list<array<string, mixed>>> $loader lit les lignes données en une requête */
    public function __construct(private readonly Closure $loader) {}

    public function load(Line $line): Deferred
    {
        if (! array_key_exists($line->id, $this->loaded)) {
            $this->pending[$line->id] = true;
        }

        return new Deferred(function () use ($line): array {
            if ($this->pending !== []) {
                $ids = array_keys($this->pending);
                $this->loaded += ($this->loader)($ids) + array_fill_keys($ids, []);
                $this->pending = [];
            }

            return $this->loaded[$line->id] ?? [];
        });
    }
}
