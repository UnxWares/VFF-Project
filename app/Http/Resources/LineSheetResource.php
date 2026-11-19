<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Fiche complète d'une ligne pour l'API publique, tirée de la construction de la page web
 * (App\Services\LineSheet::build) : mêmes chiffres, mêmes phases, même chronique.
 *
 * Laisse de côté ce qui ne sert qu'à l'affichage (attributs `lang` HTML, libellés d'interface) et
 * la géométrie, sauf demande (`geometry=true` : tracé de chaque portion des phases). Les tracés
 * par ère s'obtiennent aussi par `/lines/{line}/segments` et les GeoJSON par ère.
 *
 * @property array<string, mixed> $resource
 */
class LineSheetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $sheet = $this->resource;
        $line = $sheet['line'];
        $stats = $sheet['stats'];
        $withGeometry = $request->boolean('geometry');

        return [
            'slug' => $line['slug'],
            'code' => $line['code'],
            'name' => $line['name'],
            'names' => $line['names'],
            'wikidata' => $line['wikidata'],
            'network' => $line['network'],
            'is_lgv' => $line['is_lgv'],
            'is_threatened' => $line['is_threatened'],
            'description' => $line['description'],
            'description_source' => $line['description_source'],
            'advocacy' => $line['advocacy'] === null ? null : array_diff_key($line['advocacy'], ['lang' => true]),
            'historical_operator' => $line['historical_operator'],
            'historical_operators' => $line['historical_operators'],
            'concession_year' => $line['concession_year'],
            'regions' => $line['regions'],
            'departements' => $line['departements'],
            'wikipedia_title' => $line['wikipedia_title'],
            'archeo_url' => $line['archeo_url'],
            'today' => [
                'era_year' => $line['display_era_year'],
                'state' => $line['current_state'],
                'traffic' => $line['current_traffic'],
            ],
            'key_figures' => [
                'length_km' => $stats['length_km'] ?? null,
                'length_definition' => $stats['length_definition'] ?? null,
                'active_length_km' => $stats['active_length_km'] ?? null,
                'length_sncf_km' => $stats['length_sncf_km'] ?? null,
                'length_official_m' => $line['length_official_m'],
                'max_speed_kmh' => $stats['max_speed_kmh'] ?? null,
                'max_speed_kmh_official' => $line['max_speed_kmh_official'],
                'gauge_mm' => $stats['gauge_mm'] ?? $line['gauge_mm_default'],
                'tracks' => $line['nb_voies_default'],
                'station_count' => $stats['station_count'] ?? 0,
                'station_open_count' => $stats['station_open_count'] ?? 0,
                'event_count' => $stats['event_count'] ?? 0,
                'segment_count' => $stats['segment_count'] ?? 0,
                'dismantled_from' => $stats['dismantled_at_min'] ?? null,
                'dismantled_to' => $stats['dismantled_at_max'] ?? null,
            ],
            'electrification' => $stats['electrification'] ?? null,
            'signalling' => [
                'block_systems_km' => $stats['signalling_coverage'] ?? [],
                'train_protection_km' => $stats['train_protection_coverage'] ?? [],
                'kvb_equipped' => (bool) ($stats['kvb_equipped'] ?? false),
                'ranges' => $sheet['signalling'],
            ],
            'facts' => $line['facts'],
            'era_summary' => $sheet['era_summary'],
            'portions' => array_map(fn (array $p, int $i): array => ['index' => $i, 'length_m' => $p['length_m']]
                + ($withGeometry ? ['geometry' => $p['geometry'] ?? null] : []), $sheet['portions'], array_keys($sheet['portions'])),
            'phases' => array_map(fn (array $p): array => array_diff_key($p, ['window' => true]), $sheet['phases']),
            'best_phase' => $sheet['best_phase'],
            'phases_unexplained' => $sheet['phases_unexplained'],
            'decades' => $sheet['decades'],
            'chronicle' => $sheet['chronicle'],
            'stations' => array_map(fn (array $s): array => array_diff_key($s, ['era_status' => true, 'effective_status' => true]), self::items($sheet['stations'])),
            'sections' => array_map(fn (array $s): array => array_diff_key($s, ['description_lang' => true]), self::items($sheet['sections'])),
            'heritage' => $sheet['heritage'],
            'materials' => self::items($sheet['materials']),
            'sources' => $sheet['sources'],
            'links' => [
                'web' => url('/lines/'.$line['slug']),
                'events' => route('api.events.index', ['line' => $line['slug']]),
                'history' => route('api.lines.history', $line['slug']),
                'segments' => route('api.lines.segments', $line['slug']),
                'stops' => route('api.lines.stops', $line['slug']),
                'signalling' => route('api.lines.signalling', $line['slug']),
                'media' => route('api.media.index', ['line' => $line['slug']]),
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
