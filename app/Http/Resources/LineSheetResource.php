<?php

namespace App\Http\Resources;

use App\Services\Timetable\Timetables;
use App\Services\TraceExport\LineTraceExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Fiche complète d'une ligne pour l'API publique, tirée de la construction de la page web
 * (App\Services\LineSheet::build) : mêmes chiffres, mêmes phases, même chronique.
 *
 * Laisse de côté ce qui ne sert qu'à l'affichage (attributs `lang` HTML, libellés d'interface) et
 * la géométrie, sauf demande (`geometry=true` : tracé de chaque portion des phases, retenu quand une
 * de ses sources n'autorise pas la redistribution). Les tracés par ère s'obtiennent aussi par
 * `/lines/{line}/segments` et les GeoJSON par ère, sous la même règle.
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
            'description_source' => self::descriptionSource($line['description_source']),
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
            // Trains par jour sur la ligne (un mardi type, horaires GTFS SNCF et Transilien, ODbL), chacun compté une fois : null sans train de voyageurs.
            'timetable' => app(Timetables::class)->forLineSlug((string) $line['slug']),
            'signalling' => [
                'block_systems_km' => $stats['signalling_coverage'] ?? [],
                'train_protection_km' => $stats['train_protection_coverage'] ?? [],
                'kvb_equipped' => (bool) ($stats['kvb_equipped'] ?? false),
                'ranges' => $sheet['signalling'],
                // Mises en service datées (Wikipédia), historique à part de l'état actuel ci-dessus : `/signalling-history`.
                'history' => $sheet['signalling_history'] ?? [],
            ],
            'facts' => $line['facts'],
            'era_summary' => $sheet['era_summary'],
            'portions' => array_map(fn (array $p, int $i): array => ['index' => $i, 'length_m' => $p['length_m']]
                + ($withGeometry ? self::portionGeometry($p) : []), $sheet['portions'], array_keys($sheet['portions'])),
            'phases' => array_map(fn (array $p): array => array_diff_key($p, ['window' => true]), $sheet['phases']),
            'best_phase' => $sheet['best_phase'],
            'phases_unexplained' => $sheet['phases_unexplained'],
            'decades' => $sheet['decades'],
            'chronicle' => $sheet['chronicle'],
            'stations' => array_map(fn (array $s): array => StationSheetResource::withServiceEvents(array_diff_key($s, ['era_status' => true, 'effective_status' => true])),
                self::items($sheet['stations'])),
            'sections' => array_map(fn (array $s): array => array_diff_key($s, ['description_lang' => true]), self::items($sheet['sections'])),
            'heritage' => $sheet['heritage'],
            'materials' => self::items($sheet['materials']),
            'sources' => $sheet['sources'],
            'links' => [
                'web' => url('/lines/'.$line['slug']),
                'events' => route('api.events.index', ['line' => $line['slug']]),
                'history' => route('api.lines.history', $line['slug']),
                'segments' => route('api.lines.segments', $line['slug']),
                'geojson' => route('api.lines.geojson', $line['slug']),
                'gpx' => route('api.lines.gpx', $line['slug']),
                'kml' => route('api.lines.kml', $line['slug']),
                'stops' => route('api.lines.stops', $line['slug']),
                'signalling' => route('api.lines.signalling', $line['slug']),
                'signalling_history' => route('api.lines.signalling-history', $line['slug']),
                'profile' => route('api.lines.profile', $line['slug']),
                'level_crossings' => route('api.lines.level-crossings', $line['slug']),
                'greenways' => route('api.lines.greenways', $line['slug']),
                'accidents' => route('api.accidents.index', ['line' => $line['slug']]),
                'materials' => route('api.lines.materials', $line['slug']),
                'media' => route('api.media.index', ['line' => $line['slug']]),
            ],
        ];
    }

    /**
     * Géométrie d'une portion des phases, selon la règle des droits des tracés (LineTraceExport) :
     * elle n'est jointe que si toutes les sources des tronçons de cette portion autorisent la
     * redistribution ; sinon `geometry` vaut null, `geometry_withheld` true et
     * `geometry_withheld_reason` donne la raison (`licence_not_open`, `unknown_source`).
     *
     * @param  array<string, mixed>  $portion
     * @return array{geometry: mixed, geometry_withheld: bool, geometry_withheld_reason: ?string}
     */
    private static function portionGeometry(array $portion): array
    {
        /** @var list<?string>|null $sources */
        $sources = isset($portion['sources']) && is_array($portion['sources']) ? array_values($portion['sources']) : null;
        $reason = app(LineTraceExport::class)->withheldReasonForSources($sources);

        return [
            'geometry' => $reason === null ? ($portion['geometry'] ?? null) : null,
            'geometry_withheld' => $reason !== null,
            'geometry_withheld_reason' => $reason,
        ];
    }

    /**
     * Source de la description : pour un extrait Wikipédia, de quoi respecter la licence CC BY-SA 4.0
     * (auteur collectif, lien vers l'article, permalien de la révision lue, licence) ; pour une ligne
     * décrite à la main, les sources citées. Le bloc de soutien (`advocacy`) n'est jamais publié :
     * c'est un engagement éditorial du site, pas une donnée.
     *
     * @param  array<string, mixed>|null  $source
     * @return array<string, mixed>|null
     */
    private static function descriptionSource(?array $source): ?array
    {
        if ($source === null || ($source['type'] ?? null) !== 'wikipedia') {
            return $source;
        }
        $title = (string) ($source['title'] ?? '');
        parse_str((string) parse_url((string) $source['url'], PHP_URL_QUERY), $query);
        $revision = isset($query['oldid']) && ctype_digit((string) $query['oldid']) ? (int) $query['oldid'] : null;

        return $source + [
            'revision_id' => $revision,
            'permalink' => $revision !== null ? $source['url'] : null,
            'history_url' => 'https://fr.wikipedia.org/w/index.php?'.http_build_query(['title' => str_replace(' ', '_', $title), 'action' => 'history'], '', '&', PHP_QUERY_RFC3986),
            'author' => 'Contributeurs de Wikipédia',
            'attribution' => sprintf('« %s », Wikipédia en français, par les contributeurs de Wikipédia, %s', $title, $source['license']),
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
