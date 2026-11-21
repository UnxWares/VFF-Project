<?php

namespace App\Enums;

/**
 * Fiabilité d'un document cité par le contexte d'un accident (accident_references.reliability), de la plus
 * forte à la plus faible : source officielle (rapport d'enquête, texte officiel, débat parlementaire), travail
 * savant, presse, histoire locale (société savante, archives municipales, historien local), association
 * (ferroviaire, mémorielle), encyclopédie collaborative, forum.
 */
enum AccidentReferenceReliability: string
{
    case Official = 'official';
    case Scholarly = 'scholarly';
    case Press = 'press';
    case LocalHistory = 'local_history';
    case Association = 'association';
    case Encyclopedia = 'encyclopedia';
    case Forum = 'forum';
}
