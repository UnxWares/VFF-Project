<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Models\Media;
use App\Models\Station;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Champs `line` et `station` d'un média : l'entité à laquelle il est rattaché, si c'est de ce type.
 */
final class MediaSubjectResolver
{
    public function __invoke(Media $media, array $args, GraphQLContext $context, ResolveInfo $info): Line|Station|null
    {
        $subject = $media->mediable;
        $expected = $info->fieldName === 'line' ? Line::class : Station::class;

        return $subject instanceof $expected ? $subject : null;
    }
}
