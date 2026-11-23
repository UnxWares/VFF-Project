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
     * Natures dont une fiche n'est publiée que si elle repose sur une enquête officielle (source `bea-tt`) ou sur un
     * contexte sourcé relu (`context_checked_on`, documents lus) : un heurt de personne ne se publie pas sur la
     * seule mention d'une liste (décision du 22/11/2026, qui remplace celle du 19/11 : natures jamais publiées).
     * Voir publishableSql() et App\Models\Scopes\PublicAccidents.
     *
     * @var list<string>
     */
    public const SOURCED_ONLY = ['person_struck'];

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

    /**
     * Condition SQL « fiche publiable au regard de sa nature » : toute nature, sauf SOURCED_ONLY qui demande une source
     * `bea-tt` ou un contexte sourcé. À combiner par ET avec `alias_of_id IS NULL`.
     *
     * @param  string  $table  nom ou alias de la table accidents dans la requête
     */
    public static function publishableSql(string $table = 'accidents'): string
    {
        $kinds = implode(', ', array_map(fn (string $k) => "'{$k}'", self::SOURCED_ONLY));

        return "({$table}.kind NOT IN ({$kinds}) OR {$table}.context_checked_on IS NOT NULL OR EXISTS ("
            ."SELECT 1 FROM accident_sources s WHERE s.accident_id = {$table}.id AND s.provider = 'bea-tt'))";
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
