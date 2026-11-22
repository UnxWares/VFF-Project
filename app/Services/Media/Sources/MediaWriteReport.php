<?php

namespace App\Services\Media\Sources;

/**
 * Compteurs d'un passage de MediaWriter : lignes écrites ou à écrire, refus par motif, sujets touchés et sujets
 * gagnés (sans aucun média avant ce passage), par type de sujet (`App\Models\Station`…).
 */
final class MediaWriteReport
{
    public int $inserted = 0;

    public int $updated = 0;

    /** @var array<string, int> motif => nombre */
    public array $refused = [];

    /** @var array<string, array<string, true>> type => sujets touchés */
    private array $touched = [];

    /** @var array<string, array<string, true>> type => sujets sans média avant ce passage */
    private array $gained = [];

    public function refuse(string $reason): void
    {
        $this->refused[$reason] = ($this->refused[$reason] ?? 0) + 1;
    }

    public function touch(string $subject, string $type, bool $hadMedia): void
    {
        $this->touched[$type][$subject] = true;
        if (! $hadMedia) {
            $this->gained[$type][$subject] = true;
        }
    }

    public function touchedCount(?string $type = null): int
    {
        return $this->count($this->touched, $type);
    }

    public function gainedCount(?string $type = null): int
    {
        return $this->count($this->gained, $type);
    }

    public function refusedCount(): int
    {
        return array_sum($this->refused);
    }

    /** @return array{inserted: int, updated: int, refused: array<string, int>, subjects: int, gained: int} */
    public function toArray(): array
    {
        return [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'refused' => $this->refused,
            'subjects' => $this->touchedCount(),
            'gained' => $this->gainedCount(),
        ];
    }

    /** @param  array<string, array<string, true>>  $sets */
    private function count(array $sets, ?string $type): int
    {
        if ($type !== null) {
            return count($sets[$type] ?? []);
        }

        return array_sum(array_map('count', $sets));
    }
}
