<?php

namespace App\Enums;

/**
 * Niveau du quiz. Il ne change que les propositions : `easy` en tire trois au hasard parmi les voisines
 * plausibles (lignes de la même région, séries du même type), `hard` prend les plus proches (lignes les
 * plus voisines, séries du même type mises en service aux dates les plus proches).
 */
enum QuizLevel: string
{
    case Easy = 'easy';
    case Hard = 'hard';
}
