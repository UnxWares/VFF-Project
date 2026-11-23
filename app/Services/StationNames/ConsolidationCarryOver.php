<?php

namespace App\Services\StationNames;

/**
 * Ce que vff:consolidate-stations ne produit pas et ne doit donc jamais perdre en réécrivant une fiche :
 *
 *   - les entrées d'`alt_names` des sources qu'elle ne possède pas (Wikipédia et Wikidata de
 *     vff:import-station-names, lieux osm_place, contributions…) : seules les entrées des sources de
 *     `station_sources` sont recalculées ;
 *   - les clés de `provenance` qu'elle ne calcule pas (`kind`, `closed_after`, `opened_after`…).
 *
 * Lors d'une fusion (action « merge »), les entrées de la fiche absorbée passent à la fiche gardée.
 */
final class ConsolidationCarryOver
{
    /** Clés de `provenance` recalculées par la consolidation (toutes les autres sont reportées). */
    public const OWNED_PROVENANCE = ['geom', 'geom_precision_m', 'geom_method', 'name', 'type', 'uic_code', 'wikidata_id', 'status', 'opened_at', 'closed_at'];

    /**
     * Entrées à reporter : toutes celles dont la source n'est pas l'une des sources recalculées (y compris
     * les entrées sans source ou qui ne sont pas des tableaux).
     *
     * @param  list<mixed>  $alt  alt_names actuel
     * @param  list<string>  $ownedSources  sources de station_sources
     * @return list<mixed>
     */
    public static function foreignAltNames(array $alt, array $ownedSources): array
    {
        return array_values(array_filter($alt, fn (mixed $a): bool => ! (is_array($a) && in_array($a['source'] ?? null, $ownedSources, true))));
    }

    /**
     * alt_names recalculés suivis des entrées reportées (sans doublon exact).
     *
     * @param  list<mixed>  $built
     * @param  list<mixed>  $carried
     * @return list<mixed>
     */
    public static function withCarried(array $built, array $carried): array
    {
        $out = $built;
        $seen = array_map(fn (mixed $a): string => json_encode($a, JSON_UNESCAPED_UNICODE) ?: '', $built);
        foreach ($carried as $a) {
            $key = json_encode($a, JSON_UNESCAPED_UNICODE) ?: '';
            if (! in_array($key, $seen, true)) {
                $seen[] = $key;
                $out[] = $a;
            }
        }

        return $out;
    }

    /**
     * Clés de provenance que la consolidation ne calcule pas.
     *
     * @param  array<string, mixed>  $provenance
     * @return array<string, mixed>
     */
    public static function foreignProvenance(array $provenance): array
    {
        return array_diff_key($provenance, array_flip(self::OWNED_PROVENANCE));
    }

    /**
     * Nom ferroviaire d'une gare dont le nom OpenStreetMap est celui du lieu (osm_place) : si le nouveau nom
     * recalculé redevient celui du lieu, l'ancien nom est gardé.
     *
     * @param  list<mixed>  $existingAlt
     */
    public static function revertsToPlaceName(array $existingAlt, string $oldName, string $newName): bool
    {
        $new = RenameExtractor::key($newName);
        if ($new === '' || $new === RenameExtractor::key($oldName)) {
            return false;
        }
        foreach ($existingAlt as $a) {
            if (is_array($a) && ($a['source'] ?? null) === 'osm_place' && is_string($a['name'] ?? null) && RenameExtractor::key($a['name']) === $new) {
                return true;
            }
        }

        return false;
    }
}
