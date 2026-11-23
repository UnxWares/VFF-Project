<?php

namespace App\Http\Resources;

use App\Services\Accessibility\StationAccessibility;
use App\Services\Api\Provenance;
use App\Services\Heritage\StationHeritage;
use App\Services\Sncf\StationAttendance;
use App\Services\Sncf\StationPlatforms;
use App\Services\Timetable\Timetables;
use App\Support\FrenchDepartments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Fiche complète d'une gare pour l'API publique, tirée de la construction de la page web
 * (App\Services\StationSheet::page) : identité, dates, position, présence par décennie,
 * chronologie, lignes desservies, sources, gares voisines et quais (SNCF Réseau). Chaque événement et chaque source
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
            'opened_after' => $station['opened_precision'] === 'range' ? $station['opened_after'] : null,
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
            'lines' => array_map(fn (array $l): array => self::withServiceEvents($l) + ['links' => [
                'web' => url('/lines/'.$l['slug']), 'api' => route('api.lines.show', $l['slug']),
            ]], self::items($page['lines'])),
            'sources' => array_map(fn (array $s): array => ['pk_m' => is_numeric($s['pk_m']) ? (int) $s['pk_m'] : null]
                + $s + ['provenance' => $provenance->for('station_sources', $s['source'], $s['url'])], $page['sources']),
            'neighbors' => array_map(fn (array $n): array => $n + ['links' => [
                'web' => url('/stations/'.$n['slug']), 'api' => route('api.stations.show', $n['slug']),
            ]], self::items($page['neighbors'])),
            // Quais (SNCF Réseau, ODbL) : null quand le jeu « Liste des quais » n'en donne aucun pour la gare.
            'platforms' => app(StationPlatforms::class)->forStation((int) $station['id']),
            // Fréquentation annuelle (SNCF Gares & Connexions, ODbL) : null quand les jeux n'en donnent aucune.
            'attendance' => app(StationAttendance::class)->forStation((int) $station['id']),
            // Desserte d'un jour ouvrable type (horaires GTFS SNCF et Transilien, ODbL) : null quand aucun train de voyageurs ne s'y arrête.
            'timetable' => app(Timetables::class)->forStation((int) $station['id']),
            // Accessibilité et services de la gare (SNCF Gares & Connexions, ODbL) : null quand les jeux n'ont rien pour la gare.
            'accessibility' => app(StationAccessibility::class)->forStation((int) $station['id']),
            // Patrimoine du bâtiment voyageurs et des ouvrages (Mérimée, Wikidata) : null quand rien n'est connu.
            'heritage' => app(StationHeritage::class)->forStation((int) $station['id']),
            // Vie de la gare en une liste datée (App\Services\StationChronology), liens absolus.
            'chronology' => array_map(fn (array $e): array => self::chronologyEntry($e), $page['chronology'] ?? []),
            'links' => [
                'web' => url('/stations/'.$station['slug']),
                'events' => route('api.stations.events', $station['slug']),
                // Vues aériennes historiques de l'IGN (1950-1965, 1965-1980, 1980-1995).
                'aerial' => route('api.stations.aerial', $station['slug']),
                'attendance' => route('api.stations.attendance', $station['slug']),
                'timetable' => route('api.stations.timetable', $station['slug']),
                'accessibility' => route('api.stations.accessibility', $station['slug']),
                'heritage' => route('api.stations.heritage', $station['slug']),
                'media' => route('api.media.index', ['station' => $station['slug']]),
                // Accidents dont cette gare est la gare la plus proche du lieu (accidents.nearest_station_id).
                'accidents' => route('api.accidents.index', ['station' => $station['slug']]),
                'department' => $station['department_code'] !== null && FrenchDepartments::find((string) $station['department_code']) !== null
                    ? route('api.departments.show', strtoupper((string) $station['department_code'])) : null,
            ],
        ];
    }

    /**
     * Début et fin de desserte datés (StationServiceDates::bounds) avec le lien de leur événement de ligne
     * (`GET /api/v1/events/{id}`) : même forme dans la fiche gare et la fiche ligne.
     *
     * @param  array<string, mixed>  $item  ligne desservie (fiche gare) ou gare (fiche ligne)
     * @return array<string, mixed>
     */
    public static function withServiceEvents(array $item): array
    {
        foreach (['service_start', 'service_end'] as $key) {
            $bound = $item[$key] ?? null;
            if (is_array($bound)) {
                $item[$key] = $bound + ['links' => [
                    'event' => $bound['event_id'] !== null ? route('api.events.show', $bound['event_id']) : null,
                ]];
            }
        }

        return $item;
    }

    /**
     * Entrée de la chronologie avec ses liens absolus : source (fiche d'accident du site), lignes (`web`, `api`),
     * accident (`web`, `api`) et événement de ligne (`GET /api/v1/events/{id}`) qui date une desserte.
     *
     * @param  array<string, mixed>  $e
     * @return array<string, mixed>
     */
    public static function chronologyEntry(array $e): array
    {
        if (is_string($e['source_url'] ?? null) && str_starts_with($e['source_url'], '/')) {
            $e['source_url'] = url($e['source_url']);
        }
        $e['lines'] = array_map(fn (array $l): array => $l + ['links' => [
            'web' => url('/lines/'.$l['slug']), 'api' => route('api.lines.show', $l['slug']),
        ]], $e['lines'] ?? []);
        if (is_array($e['accident'] ?? null)) {
            $e['accident'] += ['links' => [
                'web' => url('/accidents/'.$e['accident']['slug']), 'api' => route('api.accidents.show', $e['accident']['slug']),
            ]];
        }
        $e['links'] = ['event' => is_int($e['event_id'] ?? null) ? route('api.events.show', $e['event_id']) : null];

        return $e;
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
