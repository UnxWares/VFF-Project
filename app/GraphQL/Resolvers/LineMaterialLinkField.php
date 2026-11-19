<?php

namespace App\GraphQL\Resolvers;

use App\Http\Resources\LineMaterialLinkResource;
use App\Models\LineMaterial;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Champs calculés d'un lien ligne × matériel (`evidence`, `provenance`) : la même valeur que
 * l'API REST, lue dans LineMaterialLinkResource.
 */
final class LineMaterialLinkField
{
    public function __invoke(LineMaterial $link, array $args, GraphQLContext $context, ResolveInfo $info): mixed
    {
        return LineMaterialLinkResource::make($link)->resolve(new Request)[$info->fieldName] ?? null;
    }
}
