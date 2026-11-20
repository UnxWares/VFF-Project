<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Resolvers\Concerns\FindsEra;
use App\Models\Line;
use App\Services\Signalling\SignallingRanges;

class LineSignallingResolver
{
    use FindsEra;

    /**
     * Tranches de signalisation (même lecture que l'API REST).
     *
     * @param  array{era?: ?int}  $args
     * @return list<array<string, mixed>>
     */
    public function __invoke(Line $line, array $args): array
    {
        $era = self::findEra($args['era'] ?? null);

        return app(SignallingRanges::class)->forLine($line->id, $era->id);
    }
}
