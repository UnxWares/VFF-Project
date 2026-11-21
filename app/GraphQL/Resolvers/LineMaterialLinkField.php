<?php

namespace App\GraphQL\Resolvers;

use App\Http\Resources\LineMaterialLinkResource;
use App\Models\LineMaterial;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Champs calculés d'un lien ligne × matériel (`evidence`, `provenance`, `end`) : la même valeur que
 * l'API REST, lue dans LineMaterialLinkResource.
 */
final class LineMaterialLinkField
{
    public function __invoke(LineMaterial $link, array $args, GraphQLContext $context, ResolveInfo $info): mixed
    {
        if ($info->fieldName === 'end') {
            // `end` se calcule sur la série et les périodes affichées : préchargées par `@with`, sinon chargées ici.
            $link->loadMissing(['material', 'shownPeriods']);
        }

        return LineMaterialLinkResource::make($link)->resolve(new Request)[$info->fieldName] ?? null;
    }
}
