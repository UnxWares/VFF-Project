<?php

namespace App\Enums;

/**
 * Section du contexte sourcé d'un accident (accident_context_entries.section), dans l'ordre d'affichage :
 *
 *   - circumstances : trains, horaires, lieu exact ;
 *   - course : déroulement ;
 *   - victims : bilan, secours (aucune victime nommée) ;
 *   - causes : causes établies par l'enquête (ce qui n'est que supposé est dit comme tel) ;
 *   - aftermath : suites (procès, rapports, mesures de sécurité adoptées) ;
 *   - memory : monuments, commémorations ;
 *   - disagreements : contradictions entre les sources.
 */
enum AccidentContextSection: string
{
    case Circumstances = 'circumstances';
    case Course = 'course';
    case Victims = 'victims';
    case Causes = 'causes';
    case Aftermath = 'aftermath';
    case Memory = 'memory';
    case Disagreements = 'disagreements';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
