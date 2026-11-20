<?php

namespace App\Services\StationService;

use App\Enums\ServicePattern;
use App\Enums\StationServiceClass;

/** Classement d'une gare pour aujourd'hui, avec le motif de sa desserte et une note lisible. */
final readonly class StationServiceVerdict
{
    public function __construct(
        public StationServiceClass $class,
        public ?ServicePattern $pattern = null,
        public ?string $note = null,
    ) {}
}
