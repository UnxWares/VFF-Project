<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Loaders\LineInfrastructureLoader;
use App\Models\Line;
use App\Services\Sncf\LineInfrastructure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Infrastructure SNCF d'une ligne en GraphQL (`Line.infrastructure`) : mêmes valeurs que l'API REST
 * (`GET /api/v1/lines/{line}/infrastructure`), lues en groupe pour les lignes d'une même liste.
 */
final class LineInfrastructureField
{
    public function __construct(private readonly LineInfrastructure $infrastructure) {}

    /** @param  array<string, mixed>  $args */
    public function forLine(Line $line, array $args, GraphQLContext $context, ResolveInfo $info): Deferred
    {
        $path = [...array_slice($info->path, 0, -1), 'infrastructure@'];

        return BatchLoaderRegistry::instance($path, fn (): LineInfrastructureLoader => new LineInfrastructureLoader($this->infrastructure))
            ->load($line);
    }

    public function attribution(): string
    {
        return LineInfrastructure::ATTRIBUTION;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->infrastructure->provenance();
    }
}
