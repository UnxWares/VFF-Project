<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * L'ère courante (réseau d'aujourd'hui) vient d'être recalculée par vff:refresh-current-era parce
 * que des lignes ou des gares ont un événement daté depuis le dernier passage.
 */
final class CurrentEraRefreshed
{
    use Dispatchable;

    public function __construct(
        public readonly int $year,
        public readonly string $asOf,
        public readonly int $linesCount,
        public readonly int $stationsCount,
    ) {}
}
