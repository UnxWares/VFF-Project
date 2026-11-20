<?php

namespace App\Services\StationService;

use App\Enums\ServicePattern;

/**
 * Desserte d'une gare par un mode sur une fenêtre : volume, jours, semaines et motif.
 */
final readonly class ServiceProfile
{
    /** @param  array<int, int>  $weekdays  jour ISO (1 = lundi) => jours desservis */
    public function __construct(
        public ServicePattern $pattern,
        public int $calls,
        public int $servedDays,
        public int $from,
        public int $to,
        public int $servedWeeks,
        public int $weeks,
        public int $first,
        public int $last,
        public array $weekdays,
    ) {}

    /** Meilleure desserte : régulière d'abord, puis plus de jours desservis, puis plus d'arrêts. */
    public function isBetterThan(?self $other): bool
    {
        return $other === null
            || [$this->pattern->isRegular(), $this->servedDays, $this->calls] > [$other->pattern->isRegular(), $other->servedDays, $other->calls];
    }

    /** @return array<string, mixed> forme rangée en base (station_services.evidence) et dans les rapports */
    public function toArray(): array
    {
        return [
            'pattern' => $this->pattern->value,
            'calls' => $this->calls,
            'served_days' => $this->servedDays,
            'window' => [Ymd::iso($this->from), Ymd::iso($this->to)],
            'served_weeks' => $this->servedWeeks,
            'weeks' => $this->weeks,
            'first' => Ymd::iso($this->first),
            'last' => Ymd::iso($this->last),
            'weekdays' => $this->weekdays,
        ];
    }
}
