<?php

namespace App\Services\StationService;

use InvalidArgumentException;

/**
 * Flux déclarés dans database/data/station_service_feeds.php.
 */
final readonly class FeedCatalog
{
    /** @var array<string, FeedDefinition> */
    private array $feeds;

    /** @param  list<array<string, mixed>>  $rows */
    public function __construct(array $rows)
    {
        $feeds = [];
        foreach ($rows as $row) {
            $feed = FeedDefinition::fromArray($row);
            $feeds[$feed->key] = $feed;
        }
        $this->feeds = $feeds;
    }

    public static function fromFile(string $path): self
    {
        return new self(require $path);
    }

    /**
     * Flux demandés, ou à défaut tous ceux qui ne sont pas « sur demande ».
     *
     * @param  list<string>  $keys
     * @return list<FeedDefinition>
     */
    public function select(array $keys): array
    {
        if ($keys === []) {
            return array_values(array_filter($this->feeds, fn (FeedDefinition $f): bool => ! $f->optIn));
        }

        return array_map(fn (string $key): FeedDefinition => $this->feeds[$key]
            ?? throw new InvalidArgumentException("Flux inconnu : {$key} (connus : ".implode(', ', array_keys($this->feeds)).')'), $keys);
    }

    /**
     * Départements que seuls certains flux couvrent (Corse).
     *
     * @return list<string>
     */
    public function restrictedDepartments(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (FeedDefinition $f): array => $f->departments, array_values($this->feeds)))));
    }
}
