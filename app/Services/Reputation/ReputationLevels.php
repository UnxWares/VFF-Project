<?php

namespace App\Services\Reputation;

use App\Models\User;

/**
 * Niveau de réputation d'après les seuils de config/reputation.php. Aucune écriture : le niveau
 * se déduit des compteurs de la table users à chaque affichage, donc un seuil modifié s'applique
 * sans recalcul.
 *
 * @phpstan-type Thresholds array{applied: int, reviews: int, acceptance: int}
 */
final readonly class ReputationLevels
{
    /** @param  array<string, Thresholds>  $levels  du niveau le plus bas au plus haut */
    public function __construct(private array $levels) {}

    public static function fromConfig(): self
    {
        /** @var array<string, Thresholds> $levels */
        $levels = config('reputation.levels');

        return new self($levels);
    }

    /** Niveaux et seuils, du plus bas au plus haut (page d'aide). @return array<string, Thresholds> */
    public function all(): array
    {
        return $this->levels;
    }

    public function forUser(User $user): ReputationSummary
    {
        return $this->summarise(
            $user->reputation_applied,
            $user->reputation_rejected,
            $user->reputation_reviews,
            $user->isSuspended(),
        );
    }

    /**
     * Un compte suspendu garde ses chiffres mais n'a pas de niveau ($suspended) : ni niveau ni
     * niveau suivant.
     */
    public function summarise(int $applied, int $rejected, int $reviews, bool $suspended = false): ReputationSummary
    {
        $decided = $applied + $rejected;
        // Arrondi vers le bas : 79,9 % reste 79 %, jamais un niveau atteint « à peu près ».
        $rate = $decided === 0 ? null : intdiv($applied * 100, $decided);

        if ($suspended) {
            return new ReputationSummary($applied, $rejected, $reviews, $rate, null, null);
        }

        $level = array_key_first($this->levels);
        foreach ($this->levels as $key => $thresholds) {
            if ($this->reached($thresholds, $applied, $reviews, $rate)) {
                $level = $key;
            }
        }

        return new ReputationSummary($applied, $rejected, $reviews, $rate, $level, $this->next($level, $applied, $reviews, $rate));
    }

    /**
     * @param  Thresholds  $thresholds
     */
    private function reached(array $thresholds, int $applied, int $reviews, ?int $rate): bool
    {
        return $applied >= $thresholds['applied']
            && $reviews >= $thresholds['reviews']
            && ($rate === null || $rate >= $thresholds['acceptance']);
    }

    /**
     * Ce qui manque pour le niveau juste au-dessus : contributions acceptées et relectures en plus,
     * et le taux d'acceptation exigé s'il n'est pas atteint.
     *
     * @return array{level: string, missing: array{applied: int, reviews: int, acceptance: int|null}}|null
     */
    private function next(string $level, int $applied, int $reviews, ?int $rate): ?array
    {
        $keys = array_keys($this->levels);
        $nextKey = $keys[array_search($level, $keys, true) + 1] ?? null;
        if ($nextKey === null) {
            return null;
        }
        $thresholds = $this->levels[$nextKey];

        return [
            'level' => $nextKey,
            'missing' => [
                'applied' => max(0, $thresholds['applied'] - $applied),
                'reviews' => max(0, $thresholds['reviews'] - $reviews),
                'acceptance' => $rate !== null && $rate < $thresholds['acceptance'] ? $thresholds['acceptance'] : null,
            ],
        ];
    }
}
