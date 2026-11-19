<?php

namespace App\Services\ArgentanWebFerro;

use App\Enums\ArgentanFactKind;
use App\Enums\FactDatePrecision;
use App\Enums\LineEventType;
use App\Enums\StationType;

/**
 * Un fait lu dans un article : événement daté, arrêt desservi ou période d'exploitation.
 * Jamais de date complétée : une année seule reste une année (FactDatePrecision::Year).
 */
final readonly class ExtractedFact
{
    public function __construct(
        public ArgentanFactKind $kind,
        public string $quote,
        public ?LineEventType $type = null,
        public ?int $year = null,
        public ?int $month = null,
        public ?int $day = null,
        public ?int $yearTo = null,
        public ?FactDatePrecision $precision = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $stopName = null,
        public ?StationType $stopNature = null,
        public ?int $pkM = null,
        public ?string $operator = null,
    ) {}

    /** Premier jour de la période connue (AAAA-MM-JJ), à lire avec la précision. */
    public function date(): ?string
    {
        if ($this->year === null) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $this->year, $this->month ?? 1, $this->day ?? 1);
    }

    /** Clé de dédoublonnage dans un article (sans la citation). */
    public function key(): string
    {
        return implode('|', [
            $this->kind->value, $this->type?->value, $this->date(), $this->yearTo, $this->precision?->value,
            mb_strtolower((string) $this->from), mb_strtolower((string) $this->to),
            mb_strtolower((string) $this->stopName), $this->pkM, mb_strtolower((string) $this->operator),
        ]);
    }

    /** Empreinte stable d'un fait d'un article WordPress donné. */
    public function fingerprint(string $wpType, int $wpId): string
    {
        return sha1("{$wpType}:{$wpId}|".$this->key());
    }

    /** Copie avec d'autres valeurs (curation). @param  array<string, mixed>  $changes */
    public function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
