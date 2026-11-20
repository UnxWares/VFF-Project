<?php

namespace App\Services\TraceExport;

/**
 * Un tronçon exportable d'une ligne à une ère : sa géométrie (longitude puis latitude, WGS 84) et
 * le fournisseur de cette géométrie, dont la licence autorise la redistribution.
 */
final readonly class TracePart
{
    /** @param list<array{0: float, 1: float}> $coordinates */
    public function __construct(
        public int $id,
        public ?int $pkStartM,
        public ?int $pkEndM,
        public ?string $status,
        public string $provider,
        public array $coordinates,
    ) {}

    /** Coordonnée en degrés décimaux : point comme séparateur quel que soit le réglage régional, six décimales au plus. */
    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
