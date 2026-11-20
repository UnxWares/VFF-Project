<?php

namespace App\Services\TraceExport;

use App\Models\Era;
use App\Models\Line;

/**
 * Tracé exportable d'une ligne à une ère : les tronçons dont la source autorise la redistribution,
 * et le résumé de ceux qui ne l'autorisent pas (jamais exportés).
 *
 * Un élément de `withheld` : `provider` (clé du registre, null si la source n'y est pas déclarée),
 * `name` et `license` du fournisseur, `reason` (`licence_not_open` : licence non ouverte, non
 * commerciale ou statut incertain ; `unknown_source` : source absente du registre), `sections`
 * (nombre de tronçons) et `length_m`.
 */
final readonly class LineTrace
{
    public const NOT_OPEN = 'licence_not_open';

    public const UNKNOWN_SOURCE = 'unknown_source';

    /**
     * @param  list<TracePart>  $parts  tronçons exportables, dans l'ordre des PK
     * @param  list<string>  $providers  fournisseurs des tronçons exportables
     * @param  list<array{provider: ?string, name: ?string, license: ?string, reason: string, sections: int, length_m: int}>  $withheld
     */
    public function __construct(
        public Line $line,
        public Era $era,
        public int $segmentCount,
        public array $parts,
        public array $providers,
        public array $withheld,
    ) {}

    /** Nombre de tronçons non exportés. */
    public function withheldSections(): int
    {
        return array_sum(array_column($this->withheld, 'sections'));
    }

    /** Longueur (m) des tronçons non exportés. */
    public function withheldLengthM(): int
    {
        return array_sum(array_column($this->withheld, 'length_m'));
    }
}
