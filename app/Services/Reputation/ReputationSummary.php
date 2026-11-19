<?php

namespace App\Services\Reputation;

/**
 * Réputation d'un membre telle qu'on l'affiche : trois chiffres, le taux d'acceptation qui s'en
 * déduit, le niveau (null pour un compte suspendu : il garde son historique mais n'affiche pas de
 * niveau) et ce qu'il manque pour le niveau suivant.
 *
 * @phpstan-type Missing array{applied: int, reviews: int, acceptance: int|null}
 */
final readonly class ReputationSummary
{
    /**
     * @param  array{level: string, missing: Missing}|null  $next  null au niveau le plus haut, ou pour un compte suspendu
     */
    public function __construct(
        public int $applied,
        public int $rejected,
        public int $reviews,
        public ?int $acceptanceRate,
        public ?string $level,
        public ?array $next,
    ) {}

    /**
     * @return array{applied: int, rejected: int, reviews: int, acceptance_rate: int|null, level: string|null, next: array{level: string, missing: Missing}|null}
     */
    public function toArray(): array
    {
        return [
            'applied' => $this->applied,
            'rejected' => $this->rejected,
            'reviews' => $this->reviews,
            'acceptance_rate' => $this->acceptanceRate,
            'level' => $this->level,
            'next' => $this->next,
        ];
    }

    /**
     * Version pour un validateur qui lit la contribution d'un autre membre : les mêmes chiffres, sans
     * ce qu'il manque pour le niveau suivant (utile au membre seul).
     *
     * @return array{applied: int, rejected: int, reviews: int, acceptance_rate: int|null, level: string|null}
     */
    public function forReviewer(): array
    {
        return [
            'applied' => $this->applied,
            'rejected' => $this->rejected,
            'reviews' => $this->reviews,
            'acceptance_rate' => $this->acceptanceRate,
            'level' => $this->level,
        ];
    }
}
