<?php

namespace App\Enums;

/** Résultat d'une demande de suspension ou de levée (AccountSuspension). */
enum SuspensionOutcome
{
    /** La suspension a été posée (ou levée). */
    case Done;
    /** Le compte est déjà suspendu : lever la suspension avant d'en poser une autre. */
    case AlreadySuspended;
    /** Le compte n'est pas suspendu : rien à lever. */
    case NotSuspended;
    /** Le compte visé est devenu le dernier administrateur en mesure d'agir. */
    case LastAdmin;
    /** L'administrateur qui agit a été suspendu entre-temps. */
    case ActorSuspended;
}
