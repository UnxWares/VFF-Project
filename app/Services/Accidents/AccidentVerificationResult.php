<?php

namespace App\Services\Accidents;

/**
 * Bilan d'une vérification des accidents (vff:verify-accidents) : état avant et après, fiches passées à « ok »
 * et pourquoi, alias, natures reclassées, lignes rattachées d'après une source.
 */
final class AccidentVerificationResult
{
    /** @var array<string, int> fiches publiées avant : total, ok, to_review, other */
    public array $before = [];

    /** @var array<string, int> fiches publiées après */
    public array $after = [];

    /** @var array<string, string> fiches passées à « ok » : slug → règle (primary_source, two_sources) */
    public array $promoted = [];

    /** @var array<string, int> règle satisfaite par les fiches « ok » après vérification (null : aucune) */
    public array $rules = [];

    /** @var list<string> fiches « ok » qu'aucune règle ne confirme (laissées « ok » : le comptage ne les rétrograde pas) */
    public array $unconfirmedOk = [];

    /** @var list<string> fiches « ok » dont la date et la commune sont confirmées, entre autres, par la presse numérisée */
    public array $pressConfirmed = [];

    /** @var list<string> fiches repassées « à vérifier » parce que les sources divergent */
    public array $demoted = [];

    /** @var list<array{alias: string, of: string, reason: string}> doublons gardés en alias */
    public array $aliases = [];

    /** @var list<string> paires possibles de doublons, signalées sans fusion */
    public array $possibleDuplicates = [];

    /** @var list<array{slug: string, from: string, to: string}> natures reclassées d'après la source */
    public array $reclassified = [];

    /** @var array<string, int> rattachements : created, updated, unresolved */
    public array $citedLines = [];

    /** @var list<string> titres d'articles de ligne cités qu'aucune ligne de VFF ne porte */
    public array $unresolvedLineTitles = [];

    /** @var list<string> fiches publiques vérifiées pour la première fois, annoncées aux webhooks (accident.published) */
    public array $announced = [];

    /** @var list<string> */
    public array $warnings = [];

    public function count(string $group, string $key): void
    {
        $this->{$group}[$key] = ($this->{$group}[$key] ?? 0) + 1;
    }
}
