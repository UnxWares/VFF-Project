<?php

namespace App\Http\Resources;

use App\Services\Api\Provenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Fiche complète d'une gare pour l'API publique, tirée de la construction de la page web
 * (App\Services\StationSheet::page) : identité, dates, position, présence par décennie,
 * chronologie, lignes desservies, sources et gares voisines. Chaque événement et chaque source
 * porte le bloc `provenance` (fournisseur, licence) du registre des droits.
 *
 * @property array<string, mixed> $resource
 */
class StationSheetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $page = $this->resource;
        $station = $page['station'];
        $provenance = app(Provenance::class);

        return [
            'slug' => $station['slug'],
            'name' => $station['name'],
            'alt_names' => $station['alt_names'],
            'type' => $station['type'],
            'status' => $station['status'],
            'source_status' => $station['source_status'],
            'lifecycle' => $station['lifecycle'],
            'city' => $station['city'],
            'department_code' => $station['department_code'],
            'opened_at' => $station['opened_at'],
            'opened_precision' => $station['opened_at'] !== null ? ($station['opened_precision'] ?? 'day') : null,
            'closed_at' => $station['closed_at'],
            'closed_precision' => $station['closed_at'] !== null ? ($station['closed_precision'] ?? 'day') : null,
            'closed_after' => $station['closed_after'],
            'uic_code' => $station['uic_code'],
            'wikidata_id' => $station['wikidata_id'],
            'description' => $station['description'],
            'lat' => $station['lat'],
            'lng' => $station['lng'],
            'position' => [
                'source' => $station['position_source'],
                'spread_m' => $station['position_spread_m'],
                'flagged' => $station['position_flagged'],
            ],
            'attribute_sources' => $station['provenance'],
            'eras' => array_map(fn (array $e): array => array_diff_key($e, ['note_lang' => true]), $page['eras']),
            'events' => array_map(fn (array $e): array => array_diff_key($e, ['note_lang' => true])
                + ['provenance' => $provenance->for('station_events', $e['source'], $e['source_url'])], self::items($page['events'])),
            'lines' => $page['lines'],
            'sources' => array_map(fn (array $s): array => ['pk_m' => is_numeric($s['pk_m']) ? (int) $s['pk_m'] : null]
                + $s + ['provenance' => $provenance->for('station_sources', $s['source'], $s['url'])], $page['sources']),
            'neighbors' => $page['neighbors'],
            'links' => [
                'web' => url('/stations/'.$station['slug']),
                'events' => route('api.stations.events', $station['slug']),
                'media' => route('api.media.index', ['station' => $station['slug']]),
            ],
        ];
    }

    /**
     * Liste de la fiche (tableau ou collection) en liste PHP.
     *
     * @return list<array<string, mixed>>
     */
    private static function items(mixed $list): array
    {
        return array_values($list instanceof Collection ? $list->all() : (array) $list);
    }
}
