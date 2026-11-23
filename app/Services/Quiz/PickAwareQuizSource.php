<?php

namespace App\Services\Quiz;

/**
 * Jeu dont la réponse parle aussi des autres propositions (« le plus long de ces tunnels » : leurs
 * longueurs). QuizSeries::reveal() l'appelle de préférence à QuizSource::reveal().
 */
interface PickAwareQuizSource
{
    /**
     * Réponse à une question tirée, avec ses quatre propositions.
     *
     * @return array<string, mixed>
     */
    public function revealPick(QuizPick $pick): array;
}
