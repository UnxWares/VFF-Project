<?php

namespace App\Enums;

/** Jeux du quiz (/quiz) : reconnaître une ligne à son tracé, une série de matériel à sa photo, ou répondre à des questions de culture ferroviaire. */
enum QuizGame: string
{
    case Lines = 'lines';
    case Materials = 'materials';
    case Facts = 'facts';
}
