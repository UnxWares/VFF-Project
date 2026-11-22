<?php

namespace App\Enums;

/**
 * Nature d'un accident ou d'un événement grave du rail (table accidents.kind).
 *
 * Une seule nature par fiche : la première circonstance décrite par la source (« dérive puis
 * déraillement » est une dérive). Les libellés traduits sont dans lang/<locale>/accidents.php
 * (accidents.kinds.<valeur>).
 */
enum AccidentKind: string
{
    /**
     * Natures gardées en base mais jamais publiées (voir App\Models\Scopes\PublicAccidents).
     *
     * @var list<string>
     */
    public const HIDDEN = ['person_struck'];

    /** Collision entre trains, ou d'un train avec un obstacle sur la voie (heurtoir, engin). */
    case Collision = 'collision';

    case Derailment = 'derailment';

    /** Collision avec un véhicule routier ou un piéton à un passage à niveau. */
    case LevelCrossing = 'level_crossing';

    case Fire = 'fire';

    case Explosion = 'explosion';

    /** Rupture ou effondrement d'un ouvrage : pont, tunnel, mur, rail. */
    case StructureFailure = 'structure_failure';

    case Attack = 'attack';

    /** Dommage causé par une action militaire : bombardement, mitraillage, ouvrage détruit par une armée. Les sabotages de la Résistance restent des attentats. */
    case WarAction = 'war_action';

    /** Personnes heurtées par un train hors passage à niveau (voyageurs, agents, piétons). */
    case PersonStruck = 'person_struck';

    /** Train ou wagon parti en dérive. */
    case Runaway = 'runaway';

    /** Incident grave sans accident : quasi-collision, dépassement de vitesse, immobilisation. */
    case Incident = 'incident';

    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
