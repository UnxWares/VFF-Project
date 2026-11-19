<?php

namespace App\Enums;

/**
 * Nature d'un fait tiré d'un article d'Argentan Web Ferroviaire (table argentanwebferro_facts).
 */
enum ArgentanFactKind: string
{
    /** Événement daté d'une ligne ou d'une section (ouverture, fermeture, dépose…). */
    case Event = 'event';

    /** Arrêt desservi (gare, halte, arrêt, bifurcation), avec son PK s'il est cité. */
    case Stop = 'stop';

    /** Période d'exploitation par une compagnie. */
    case Operator = 'operator';
}
