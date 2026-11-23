<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Resolvers\Concerns\FindsEra;
use App\Models\Line;
use App\Services\Speed\LineSpeeds;

class LineSpeedsResolver
{
    use FindsEra;

    /**
     * Vitesses maximales d'une ligne (même lecture que l'API REST `/lines/{line}/speeds`).
     *
     * @param  array{era?: ?int}  $args
     * @return array<string, mixed>
     */
    public function __invoke(Line $line, array $args): array
    {
        $era = self::findEra($args['era'] ?? null);

        return app(LineSpeeds::class)->forLine($line->id, $era->id, (int) $era->year);
    }
}
