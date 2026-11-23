<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;

/**
 * `line(slug:)` : la ligne de ce slug, ou celle qui l'a porté avant d'être renommée
 * (database/data/line_slug_redirects.php) ; son champ `slug` donne alors le slug actuel.
 */
final class LineBySlugResolver
{
    /** @param  array{slug: string}  $args */
    public function __invoke(mixed $root, array $args): ?Line
    {
        $slug = $args['slug'];

        return Line::query()->where('slug', $slug)->first()
            ?? (($current = Line::redirectSlugFor($slug)) !== null ? Line::query()->where('slug', $current)->first() : null);
    }
}
