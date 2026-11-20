<?php

namespace App\GraphQL\Resolvers;

use App\Services\Api\SourceRegistry;

/**
 * Requêtes `sources` et `source` : le registre public des fournisseurs et de leurs licences
 * (SourceRegistry, même contenu que `GET /api/v1/sources`).
 */
final class SourceField
{
    public function __construct(private readonly SourceRegistry $registry) {}

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_map(self::shape(...), $this->registry->all());
    }

    /**
     * @param  array{provider: string}  $args
     * @return array<string, mixed>|null
     */
    public function find(mixed $root, array $args): ?array
    {
        $entry = $this->registry->find($args['provider']);

        return $entry === null ? null : self::shape($entry);
    }

    /**
     * `source_values` en liste de paires (table, valeurs) : GraphQL n'a pas de dictionnaire.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private static function shape(array $entry): array
    {
        $entry['source_values'] = array_map(fn (string $table, array $values) => ['table' => $table, 'values' => $values],
            array_keys($entry['source_values']), $entry['source_values']);

        return $entry;
    }
}
