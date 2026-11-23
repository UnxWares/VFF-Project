<?php

namespace App\Services\StationNames;

/**
 * Fusionne les noms datés lus (Wikipédia, Wikidata) dans `stations.alt_names` sans toucher aux autres entrées :
 * les entrées de nos deux sources sont remplacées à chaque exécution (idempotent), celles des autres sources
 * (SNCF, OSM, OHM, Wikidata sans date, lignes oubliées…) sont gardées telles quelles.
 */
final class StationNameMerger
{
    /** Sources écrites par cette importation. */
    public const SOURCES = [StationNameTimeline::SOURCE, WikidataNames::SOURCE];

    /**
     * @param  list<mixed>  $existing  alt_names actuel
     * @param  list<array<string, mixed>>  $candidates  noms lus
     * @return list<mixed>
     */
    public static function merge(array $existing, array $candidates, string $ownName): array
    {
        $kept = array_values(array_filter($existing, fn (mixed $a): bool => ! (is_array($a) && in_array($a['source'] ?? null, self::SOURCES, true))));
        $own = RenameExtractor::key($ownName);
        $dated = [];
        $out = $kept;
        foreach ($candidates as $c) {
            $key = RenameExtractor::key((string) $c['name']);
            if (($key === $own && ! ($c['renamed'] ?? false)) || $key === '') {
                continue;
            }
            $period = ($c['from'] ?? '').'-'.($c['to'] ?? '');
            // Même nom, même période (Wikipédia et Wikidata disent la même chose) : une seule entrée, la première.
            if (isset($dated[$key.'|'.$period])) {
                continue;
            }
            $isDated = ($c['from'] ?? null) !== null || ($c['to'] ?? null) !== null;
            // Un nom sans date déjà connu par une autre source n'apporte rien.
            if (! $isDated && self::known($kept, $key)) {
                continue;
            }
            $dated[$key.'|'.$period] = true;
            $out[] = $c;
        }

        return $out;
    }

    /** @param list<mixed> $entries */
    private static function known(array $entries, string $key): bool
    {
        foreach ($entries as $a) {
            if (is_array($a) && is_string($a['name'] ?? null) && RenameExtractor::key($a['name']) === $key) {
                return true;
            }
        }

        return false;
    }
}
