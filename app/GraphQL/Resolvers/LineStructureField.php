<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Loaders\LineStructureLoader;
use App\Models\Line;
use App\Services\Structures\LineStructures;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Ouvrages d'art d'une ligne en GraphQL (`Line.structures`) : mêmes valeurs que l'API REST
 * (`GET /api/v1/lines/{line}/structures`), lues en groupe pour les lignes d'une même liste.
 */
final class LineStructureField
{
    public function __construct(private readonly LineStructures $structures) {}

    /** @param  array<string, mixed>  $args */
    public function forLine(Line $line, array $args, GraphQLContext $context, ResolveInfo $info): Deferred
    {
        $path = [...array_slice($info->path, 0, -1), 'structures@'];

        return BatchLoaderRegistry::instance($path, fn (): LineStructureLoader => new LineStructureLoader($this->structures))
            ->load($line);
    }

    public function attribution(): string
    {
        return LineStructures::ATTRIBUTION;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->structures->provenance();
    }
}
