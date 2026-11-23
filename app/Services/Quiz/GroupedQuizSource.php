<?php

namespace App\Services\Quiz;

/**
 * Jeu dont les sujets sont de plusieurs sortes (sujets de questions) : QuizSeries en tire à tour de rôle un
 * de chaque groupe, pour qu'une série ne pose pas dix fois le même genre de question.
 */
interface GroupedQuizSource
{
    /**
     * Groupe d'un sujet du vivier.
     *
     * @param  array<string, mixed>  $subject
     */
    public function group(array $subject): string;
}
