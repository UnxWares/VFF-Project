<?php

namespace App\Services\StationAudit;

/**
 * Règle de rang PROPOSÉE pour choisir la date d'une gare (docs/AUDIT-GARES.md) : texte officiel
 * (correction relue et sourcée) > Wikipédia et Wikidata > lignes-oubliées, OHM, OSM > estimation.
 * Elle ne sert cette nuit qu'à compter les gares qui changeraient ; le moteur
 * (StationTimeline::SOURCE_RANK) n'est pas modifié.
 */
final class SourceRank
{
    public static function of(?string $source): int
    {
        return match (true) {
            $source === null || $source === '' => 0,
            $source === 'correction', $source === 'manual' => 4,
            in_array($source, ['wikipedia', 'wikidata', 'wikidata:qualifiers'], true) => 3,
            in_array($source, ['lignes-oubliees', 'ohm', 'osm', 'osm:note'], true) => 2,
            default => 1,
        };
    }

    /**
     * La date candidate remplacerait-elle celle de VFF ? Oui si VFF n'en a pas, si la source de la
     * candidate est de rang supérieur, ou de même rang avec une précision plus fine et compatible.
     */
    public static function wouldReplace(string $comparison, string $candidateSource, ?string $vffSource): bool
    {
        if ($comparison === 'new') {
            return true;
        }
        if (in_array($comparison, ['same', 'less_precise', 'station_open'], true)) {
            return false;
        }
        $a = self::of($candidateSource);
        $b = self::of($vffSource);

        return $a > $b || ($a === $b && $comparison === 'more_precise');
    }
}
