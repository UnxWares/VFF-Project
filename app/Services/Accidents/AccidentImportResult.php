<?php

namespace App\Services\Accidents;

/**
 * Bilan d'un import d'accidents : ce qui a été lu, écarté, fusionné, écrit, et les cas douteux.
 */
final class AccidentImportResult
{
    /** @var array<string, int> candidats lus par source */
    public array $collected = [];

    /** @var array<string, array<string, int>> éléments écartés à la lecture, par source et motif */
    public array $rejected = [];

    /** Rangées du BEA-TT fusionnées dans une fiche Wikidata. */
    public int $merged = 0;

    /** @var list<array{key: string, name: string, reason: string}> candidats hors périmètre */
    public array $skipped = [];

    /** @var array<string, array<string, int>> compteurs : written, precision, confidence, kind */
    public array $counts = [];

    /** @var array<string, list<array{code: string, detail: string}>> fiches à vérifier (slug → notes) */
    public array $toReview = [];

    /** @var list<string> fiches en base absentes des sources lors de cet import (jamais supprimées) */
    public array $unseen = [];

    /** @var list<string> fiches absentes des sources retirées (option --prune) */
    public array $pruned = [];

    /** @var list<string> doublons retirés (ancienne fiche → fiche qui la remplace) */
    public array $retired = [];

    /** @var list<array<string, mixed>> aperçu (--dry-run) */
    public array $preview = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> éléments absents du cache en mode hors ligne */
    public array $missing = [];

    public int $requests = 0;

    public function count(string $group, string $key): void
    {
        $this->counts[$group][$key] = ($this->counts[$group][$key] ?? 0) + 1;
    }
}
