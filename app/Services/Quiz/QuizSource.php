<?php

namespace App\Services\Quiz;

use App\Enums\QuizLevel;

/**
 * Données d'un jeu du quiz : vivier des sujets possibles, propositions plausibles pour un sujet,
 * question publique (sans la réponse) et réponse révélée après le choix.
 */
interface QuizSource
{
    /**
     * Sujets possibles, triés par identifiant. Chaque élément a au moins `id`, `key` (slug de la fiche) et
     * `label` (nom affiché) ; les libellés sont distincts.
     *
     * @return list<array<string, mixed>>
     */
    public function pool(): array;

    /**
     * Autres éléments du vivier qui peuvent servir de propositions pour ce sujet, du plus plausible au
     * moins plausible (le sujet n'y figure pas).
     *
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $pool
     * @return list<array<string, mixed>>
     */
    public function candidates(array $subject, array $pool, QuizLevel $level): array;

    /**
     * Question telle que la page la reçoit : rien qui désigne la bonne proposition.
     *
     * @return array<string, mixed>
     */
    public function question(QuizPick $pick): array;

    /**
     * Réponse, phrase tirée de la base avec sa source, et lien vers la fiche.
     *
     * @param  array<string, mixed>  $subject
     * @return array<string, mixed>
     */
    public function reveal(array $subject): array;
}
