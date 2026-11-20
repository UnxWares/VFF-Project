<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\Signalling\LineSignallingHistory;
use GraphQL\Deferred;

/**
 * Chargement groupé de `Line.signalling_history` : les lignes demandées pendant la résolution d'une liste sont
 * notées, puis leurs faits lus en une seule requête au premier besoin (LineSignallingHistory::publicFactsOfLines).
 * Une instance par champ et par requête GraphQL (BatchLoaderRegistry).
 */
final class SignallingHistoryBatch
{
    /** @var array<int, true> lignes demandées, pas encore lues */
    private array $pending = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $loaded = [];

    public function __construct(private readonly LineSignallingHistory $history) {}

    public function load(Line $line): Deferred
    {
        if (! array_key_exists($line->id, $this->loaded)) {
            $this->pending[$line->id] = true;
        }

        return new Deferred(function () use ($line): array {
            if ($this->pending !== []) {
                $ids = array_keys($this->pending);
                $this->loaded += $this->history->publicFactsOfLines($ids) + array_fill_keys($ids, []);
                $this->pending = [];
            }

            return $this->loaded[$line->id] ?? [];
        });
    }
}
