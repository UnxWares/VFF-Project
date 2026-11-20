<?php

namespace App\Enums;

/** Jeux du quiz (/quiz) : reconnaître une ligne à son tracé, ou une série de matériel à sa photo. */
enum QuizGame: string
{
    case Lines = 'lines';
    case Materials = 'materials';
}
