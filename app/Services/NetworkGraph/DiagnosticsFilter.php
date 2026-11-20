<?php

namespace App\Services\NetworkGraph;

/**
 * Filtre d'une liste de diagnostics du graphe (/validate/graph) : pour les jonctions, type
 * (`spread`, `connector`, `jump`) et ligne concernée (élément dont `line_ids` contient la ligne).
 * Les autres listes ne se filtrent pas : le filtre y laisse tout passer.
 */
final readonly class DiagnosticsFilter
{
    public function __construct(
        public string $check,
        public ?string $type = null,
        public ?int $lineId = null,
    ) {}

    public function active(): bool
    {
        return $this->check === 'junctions' && ($this->type !== null || $this->lineId !== null);
    }

    /** @param  array<string, mixed>  $item */
    public function matches(array $item): bool
    {
        if (! $this->active()) {
            return true;
        }
        if ($this->type !== null && ($item['type'] ?? null) !== $this->type) {
            return false;
        }

        return $this->lineId === null || in_array($this->lineId, (array) ($item['line_ids'] ?? []), true);
    }
}
