<?php

namespace App\GraphQL\Resolvers;

use App\Http\Resources\LineMaterialLinkResource;
use App\Models\LineMaterial;
use App\Services\Materials\LineMaterialFacts;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Champs calculés d'un lien ligne × matériel (`evidence`, `provenance`, `end`, `link_doubt`, `earliest_on_line`,
 * `family`, `folded_into_versions`) : la même valeur que l'API REST, lue dans LineMaterialLinkResource. Les faits
 * de la fiche (LineMaterialFacts) sont calculés une fois par ligne et par requête.
 */
final class LineMaterialLinkField
{
    /** Champs qui viennent de LineMaterialFacts. */
    private const FACT_FIELDS = ['link_doubt', 'earliest_on_line', 'family', 'folded_into_versions'];

    public function __invoke(LineMaterial $link, array $args, GraphQLContext $context, ResolveInfo $info): mixed
    {
        if ($info->fieldName === 'end') {
            // `end` se calcule sur la série et les périodes affichées : préchargées par `@with`, sinon chargées ici.
            $link->loadMissing(['material', 'shownPeriods']);
        }
        $request = new Request;
        if (in_array($info->fieldName, self::FACT_FIELDS, true)) {
            $request->attributes->set(LineMaterialLinkResource::FACTS, self::facts($link, $context));
        }

        return LineMaterialLinkResource::make($link)->resolve($request)[$info->fieldName] ?? null;
    }

    private static function facts(LineMaterial $link, GraphQLContext $context): LineMaterialFacts
    {
        $attributes = $context->request()?->attributes;
        $key = LineMaterialLinkResource::FACTS.'.'.$link->line_id;
        $facts = $attributes?->get($key);
        if (! $facts instanceof LineMaterialFacts) {
            $link->loadMissing('line');
            $facts = LineMaterialFacts::forLine($link->line);
            $attributes?->set($key, $facts);
        }

        return $facts;
    }
}
