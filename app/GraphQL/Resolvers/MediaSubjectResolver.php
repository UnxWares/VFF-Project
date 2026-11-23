<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Loaders\MediaSubjectLoader;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use App\Support\RedistributableDates;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Champs `line`, `station`, `material` et `event` d'un média : l'entité à laquelle il est rattaché,
 * si c'est de ce type. Chargement groupé (MediaSubjectLoader) : les quatre champs des médias d'une
 * même liste partagent une lecture, une requête par type d'entité.
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

    /** @param  array<string, mixed>  $args */
    public function __invoke(Media $media, array $args, GraphQLContext $context, ResolveInfo $info): ?SyncPromise
    {
        $expected = self::FIELDS[$info->fieldName] ?? null;
        if ($expected === null) {
            return null;
        }
        // Un média d'un autre type d'entité n'a rien à lire pour ce champ.
        if ($media->getAttribute('mediable_type') !== $expected) {
            return null;
        }
        $path = [...array_slice($info->path, 0, -1), 'mediable@'];
        $loader = BatchLoaderRegistry::instance($path, fn (): MediaSubjectLoader => new MediaSubjectLoader);

        return $loader->load($media)->then(
            fn (?Model $subject): ?Model => $subject !== null && $subject::class === $expected
                // Événement sans source redistribuable : jamais servi hors du site.
                && (! $subject instanceof LineEvent || app(RedistributableDates::class)->servesLineEvent($subject->source, $subject->source_url))
                ? $subject : null,
        );
    }
}
