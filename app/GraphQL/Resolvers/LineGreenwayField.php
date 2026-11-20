<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Loaders\LineGreenwayLoader;
use App\Models\Line;
use App\Services\Greenways\LineGreenways;
use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Voies vertes d'une ligne en GraphQL : champs `Line.greenways` et `Line.greenway_length_m`, mêmes
 * valeurs que l'API REST (`GET /api/v1/lines/{line}/greenways`). Chargement groupé : les lignes d'une
 * même liste sont lues en une requête SQL, et les deux champs d'une ligne partagent cette lecture.
 */
final class LineGreenwayField
{
    public function __construct(private readonly LineGreenways $greenways) {}

    /** @param  array<string, mixed>  $args */
    public function forLine(Line $line, array $args, GraphQLContext $context, ResolveInfo $info): Deferred
    {
        return $this->loader($info)->load($line);
    }

    /** @param  array<string, mixed>  $args */
    public function length(Line $line, array $args, GraphQLContext $context, ResolveInfo $info): SyncPromise
    {
        return $this->loader($info)->load($line)
            ->then(fn (array $groups): int => $this->greenways->totalLength($groups));
    }

    public function attribution(): string
    {
        return LineGreenways::ATTRIBUTION;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->greenways->provenance();
    }

    /**
     * Chargeur commun aux deux champs d'une même liste de lignes : la clé est le chemin du parent
     * (sans les indices de liste), suivi d'un nom propre à ces champs.
     */
    private function loader(ResolveInfo $info): LineGreenwayLoader
    {
        $path = [...array_slice($info->path, 0, -1), 'greenways@'];

        return BatchLoaderRegistry::instance($path, fn (): LineGreenwayLoader => new LineGreenwayLoader($this->greenways));
    }
}
