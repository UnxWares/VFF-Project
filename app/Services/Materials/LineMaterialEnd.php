<?php

namespace App\Services\Materials;

use App\Models\LineMaterial;
use App\Models\Material;
use Illuminate\Support\Str;

/**
 * Fin de circulation d'une série sur une ligne, telle que les sources la donnent, et ordre de la section
 * « Matériel roulant » d'une fiche de ligne (le plus récent d'abord). Aucune date n'est déduite : une fin
 * propre à la ligne vient d'une période datée (LineMaterialPeriod), la fin de la série n'est jamais
 * présentée comme celle de la ligne.
 *
 * `kind` :
 *   - `line` : la dernière période datée sur la ligne a une fin, et aucune période ne recommence après
 *     (« jusqu'en 2019 sur la ligne ») ; `year`, `source_url` et `source` de cette période ;
 *   - `series_in_service` : pas de fin sur la ligne, et une source dit que la série est encore en service
 *     (`materials.service_status`, Wikidata P5817 ou infobox) : on ne sait pas si elle roule encore ici ;
 *   - `series_withdrawn` : pas de fin sur la ligne, mais la série est retirée (`in_service_to`, Wikidata
 *     P730, ou état « retirée ») : `year` est celle de la série (nulle si seul l'état est connu) ;
 *   - `unknown` : rien de connu.
 */
final class LineMaterialEnd
{
    /**
     * @param  list<array{start_date: ?string, end_date: ?string, source?: ?string, source_url?: ?string}>  $periods  périodes montrées sur la ligne
     * @return array{kind: string, year: ?int, source: ?string, source_url: ?string}
     */
    public static function of(Material $material, array $periods): array
    {
        $lastEnd = null;
        $lastStart = null;
        foreach ($periods as $period) {
            if ($period['end_date'] !== null && ($lastEnd === null || $period['end_date'] > $lastEnd['end_date'])) {
                $lastEnd = $period;
            }
            if ($period['start_date'] !== null && ($lastStart === null || $period['start_date'] > $lastStart)) {
                $lastStart = $period['start_date'];
            }
        }
        // Une période qui commence après la dernière fin connue (et sans fin) : la série a pu revenir.
        $reopened = $lastEnd !== null && array_filter($periods, fn (array $p): bool => $p['end_date'] === null && $p['start_date'] !== null && $p['start_date'] > $lastEnd['end_date']) !== [];
        if ($lastEnd !== null && ! $reopened) {
            return ['kind' => 'line', 'year' => (int) substr((string) $lastEnd['end_date'], 0, 4), 'source' => $lastEnd['source'] ?? null, 'source_url' => $lastEnd['source_url'] ?? null];
        }
        $provenance = $material->provenance ?? [];
        if ($material->service_status === 'in_service') {
            return ['kind' => 'series_in_service', 'year' => null] + self::source($provenance['service_status'] ?? null);
        }
        if ($material->in_service_to !== null) {
            return ['kind' => 'series_withdrawn', 'year' => (int) $material->in_service_to] + self::source($provenance['in_service_to'] ?? null);
        }
        if ($material->service_status === 'withdrawn') {
            return ['kind' => 'series_withdrawn', 'year' => null] + self::source($provenance['service_status'] ?? null);
        }

        return ['kind' => 'unknown', 'year' => null, 'source' => null, 'source_url' => null];
    }

    /**
     * Ordre de la section « Matériel roulant » : d'abord les séries encore en service sans fin connue sur la
     * ligne, puis la fin la plus récente (fin sur la ligne, sinon retrait de la série), puis le début le plus
     * récent (première période sur la ligne, sinon mise en service de la série), puis le nom (ordre naturel,
     * accents ignorés : « BB 9200 » avant « BB 15000 »). Une série sans
     * aucune date vient en dernier.
     *
     * @param  list<array<string, mixed>>  $materials  entrées avec `name`, `in_service_from`, `periods` et `end`
     * @return list<array<string, mixed>>
     */
    public static function sort(array $materials): array
    {
        usort($materials, fn (array $a, array $b): int => [self::running($b), self::endYear($b), self::startYear($b)] <=> [self::running($a), self::endYear($a), self::startYear($a)]
            ?: strnatcasecmp(Str::ascii((string) $a['name']), Str::ascii((string) $b['name'])));

        return $materials;
    }

    /**
     * Les liens d'une ligne (API REST, serveur MCP) dans l'ordre de sort() : la fin et le début de chaque
     * série se lisent dans les périodes de tous ses liens (import et contribution) ; deux liens d'une même
     * série restent côte à côte, dans l'ordre de leur identifiant. Les liens doivent porter `material` et
     * `shownPeriods`.
     *
     * @param  iterable<LineMaterial>  $links
     * @return list<LineMaterial>
     */
    public static function sortLinks(iterable $links): array
    {
        $groups = [];
        foreach ($links as $link) {
            $groups[$link->material_id][] = $link;
        }
        $keys = [];
        foreach ($groups as $materialId => $group) {
            $periods = [];
            foreach ($group as $link) {
                foreach ($link->shownPeriods as $period) {
                    $periods[] = $period->toSheet();
                }
            }
            $material = $group[0]->material;
            $keys[] = ['name' => $material->name, 'in_service_from' => $material->in_service_from, 'periods' => $periods,
                'end' => self::of($material, $periods), 'material_id' => $materialId];
        }
        $out = [];
        foreach (self::sort($keys) as $key) {
            $group = $groups[$key['material_id']];
            usort($group, fn (LineMaterial $a, LineMaterial $b): int => $a->id <=> $b->id);
            array_push($out, ...$group);
        }

        return $out;
    }

    /** @param array<string, mixed> $m */
    private static function running(array $m): int
    {
        return $m['end']['kind'] === 'series_in_service' ? 1 : 0;
    }

    /** @param array<string, mixed> $m */
    private static function endYear(array $m): int
    {
        return $m['end']['year'] ?? PHP_INT_MIN;
    }

    /** @param array<string, mixed> $m */
    private static function startYear(array $m): int
    {
        $starts = array_filter(array_map(fn (array $p): ?string => $p['start_date'] ?? null, $m['periods']));

        return $starts !== [] ? (int) substr((string) min($starts), 0, 4) : ($m['in_service_from'] ?? PHP_INT_MIN);
    }

    /**
     * Source d'un champ de la série (provenance de l'import) : élément Wikidata (« Q…#P730 »), ou version de
     * l'article Wikipédia lue (« Regio 2N#infobox:Effectif » et `revid`).
     *
     * @param  array{source?: string, ref?: string, revid?: int}|null  $provenance
     * @return array{source: ?string, source_url: ?string}
     */
    private static function source(?array $provenance): array
    {
        $ref = $provenance['ref'] ?? null;
        $url = match (true) {
            ! is_string($ref) => null,
            ($provenance['source'] ?? null) === 'wikidata' => 'https://www.wikidata.org/wiki/'.$ref,
            ($provenance['source'] ?? null) === 'wikipedia' && isset($provenance['revid']) => 'https://fr.wikipedia.org/w/index.php?title='
                .rawurlencode(str_replace(' ', '_', explode('#', $ref)[0])).'&oldid='.(int) $provenance['revid'],
            default => str_starts_with($ref, 'http') ? $ref : null,
        };

        return ['source' => $provenance['source'] ?? null, 'source_url' => $url];
    }
}
