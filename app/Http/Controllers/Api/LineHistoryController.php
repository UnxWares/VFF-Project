<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineHistoryController extends Controller
{
    /**
     * Chronologie d'une ligne.
     *
     * Toute l'histoire d'une ligne : ses événements datés et un résumé de son
     * état à chaque ère.
     *
     * **`events`**, dans l'ordre chronologique. `date` est au format ISO
     * `AAAA-MM-JJ`, à lire avec `date_precision` : `day` (date exacte, à
     * afficher jj/mm/aaaa), `month` (seuls le mois et l'année sont connus :
     * le jour vaut 01), `year` (seule l'année est connue : mois et jour valent 01).
     * `section_from` / `section_to` bornent la portion concernée quand
     * l'événement ne touche pas toute la ligne. Types (`type`) :
     * - trafic, tous services : `opening` (ouverture), `closure` (fermeture),
     *   `reopening` (réouverture) ;
     * - un seul service : `opening_passenger`, `closure_passenger`,
     *   `reopening_passenger` (voyageurs), `opening_freight`, `closure_freight`,
     *   `reopening_freight` (fret). Une ligne fermée aux voyageurs mais ouverte
     *   au fret devient une ligne fret ;
     * - infrastructure : `electrification`, `modernization`, `dismantling`
     *   (dépose de la voie), `conversion` (reconversion de l'emprise en voie
     *   verte, route…, voie déposée) ;
     * - administration : `concession`, `concession_end`, `declassification`
     *   (retrait juridique du réseau, sans preuve de dépose) ;
     * - associations : `heritage_start` / `heritage_end` (train touristique,
     *   vélorail, musée) ;
     * - `accident`, `other`.
     *
     * `source` indique la provenance (`wikipedia`, `wikidata`, `ohm`,
     * `lignes-oubliees`, `osm`, `manual`…) et
     * `source_url` le document consulté : citez-les en réutilisant la donnée.
     * Les événements d'un fournisseur à `open_licence: false` (voir `/sources`)
     * ne sont pas sous ODbL : usage non commercial uniquement, avec un crédit et un lien vers `source_url`.
     * Un événement dont aucune source ne permet la rediffusion hors du site n'est pas servi (même règle que
     * `GET /api/v1/events`).
     *
     * **`status_by_era`** : pour chaque ère (instantané au 31/12 de l'année,
     * 2030 = aujourd'hui) où la ligne a des tronçons, le statut et le trafic
     * les plus représentés (`dominant_status` : `in_service`, `abandoned`,
     * `dismantled`… ; `dominant_traffic` : `mixed`, `passenger`, `freight`,
     * `unknown`), le nombre de tronçons, de tronçons électrifiés, et la
     * présence d'une portion exploitée par une association.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-420000')]
    #[ApiResponse(200, 'Chronologie de la ligne.', examples: [[
        'line' => ['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest', 'type' => 'mixed', 'historical_operator' => null],
        'events' => [[
            'type' => 'opening', 'date' => '1840-01-01', 'date_precision' => 'year',
            'section_from' => 'Versailles-Rive-Gauche', 'section_to' => 'Paris-Montparnasse-Pasteur',
            'description' => "Ouverture de la portion Versailles-Rive-Gauche - Paris-Montparnasse-Pasteur (16,7 km), d'après OpenHistoricalMap.",
            'source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/205265139',
        ]],
        'status_by_era' => [[
            'year' => 1850, 'segment_count' => 77, 'dominant_status' => 'in_service', 'dominant_traffic' => 'mixed',
            'has_heritage_section' => false, 'electrified_count' => 0,
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue (slug introuvable).')]
    public function show(Line $line): JsonResponse
    {
        // Lecture brute (toBase) : un type d'événement ajouté en base avant
        // l'enum PHP ne doit pas faire planter l'endpoint (ValueError du cast).
        // Jamais un événement sans source redistribuable (RedistributableDates, portée du modèle).
        $events = $line->events()->redistributable()->toBase()
            ->orderBy('event_date')
            ->get(['id', 'type', 'event_date', 'date_precision', 'description',
                'section_from', 'section_to', 'source', 'source_url']);

        $segmentsByEra = $line->segments()
            ->with('era:id,year,is_current')
            ->get(['id', 'era_id', 'status', 'traffic_type', 'is_heritage', 'electrified', 'voltage_v', 'max_speed_kmh'])
            // Tri chronologique par ANNÉE (les id d'ères ne suivent pas l'ordre
            // des décennies : 1990-2030 = 1-5, 1900-1980 = 6-14).
            ->sortBy(fn ($s) => $s->era->year)
            ->groupBy(fn ($s) => $s->era->year);

        $statusByEra = $segmentsByEra->map(function ($segments, $year) {
            // Agrège les statuts pour cette ère : domine le plus représenté
            $statuses = $segments->groupBy('status')->map->count()->sortDesc();
            $traffics = $segments->groupBy('traffic_type')->map->count()->sortDesc();
            $heritage = $segments->where('is_heritage', true)->count() > 0;

            return [
                'year' => (int) $year,
                'segment_count' => $segments->count(),
                'dominant_status' => $statuses->keys()->first(),
                'dominant_traffic' => $traffics->keys()->first(),
                'has_heritage_section' => $heritage,
                'electrified_count' => $segments->where('electrified', true)->count(),
            ];
        })->values();

        return response()->json([
            'line' => [
                'slug' => $line->slug,
                'code' => $line->code,
                'name' => $line->name,
                'type' => $line->type?->value,
                'historical_operator' => $line->historical_operator,
            ],
            'events' => $events->map(fn ($e) => [
                'type' => $e->type,
                'date' => substr((string) $e->event_date, 0, 10),
                // day | month | year : n'afficher que la partie connue de la date
                'date_precision' => $e->date_precision ?? 'day',
                'section_from' => $e->section_from,
                'section_to' => $e->section_to,
                'description' => $e->description,
                'source' => $e->source,
                'source_url' => $e->source_url,
            ])->values(),
            'status_by_era' => $statusByEra,
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
