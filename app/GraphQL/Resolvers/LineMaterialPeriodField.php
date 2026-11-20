<?php

namespace App\GraphQL\Resolvers;

use App\Http\Resources\LineMaterialPeriodResource;
use App\Models\LineMaterialPeriod;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Champs calculés d'une période de circulation (`provenance`) : la même valeur que l'API REST, lue
 * dans LineMaterialPeriodResource.
 */
final class LineMaterialPeriodField
{
    public function __invoke(LineMaterialPeriod $period, array $args, GraphQLContext $context, ResolveInfo $info): mixed
    {
        return LineMaterialPeriodResource::make($period)->resolve(new Request)[$info->fieldName] ?? null;
    }
}
