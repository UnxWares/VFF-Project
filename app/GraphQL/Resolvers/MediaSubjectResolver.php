<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Champs `line`, `station`, `material` et `event` d'un média : l'entité à laquelle il est rattaché,
 * si c'est de ce type.
 */
final class MediaSubjectResolver
{
    /** Champ GraphQL → classe du modèle attendu. */
    private const FIELDS = [
        'line' => Line::class,
        'station' => Station::class,
        'material' => Material::class,
        'event' => LineEvent::class,
    ];

    public function __invoke(Media $media, array $args, GraphQLContext $context, ResolveInfo $info): ?Model
    {
        $expected = self::FIELDS[$info->fieldName] ?? null;
        $subject = $expected !== null ? $media->mediable : null;

        return $subject instanceof Model && $subject::class === $expected ? $subject : null;
    }
}
