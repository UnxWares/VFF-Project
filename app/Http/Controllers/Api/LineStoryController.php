<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LineEventResource;
use App\Models\Line;
use App\Models\LineEvent;
use App\Services\Archives\LineArchives;
use App\Services\Stories\EventStories;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineStoryController extends Controller
{
    public function __construct(private readonly EventStories $stories) {}

    /**
     * Histoires d'une ligne : événements qui en ont une.
     *
     * Liste des événements de la ligne qui ont une histoire (page `/lines/{line}/stories/{event}` du site), dans
     * l'ordre des dates, avec le nombre d'images et de documents retenus avant la limite du carrousel (24 images).
     * Liste vide pour une ligne sans histoire.
     *
     * Un même fait rapporté par plusieurs sources (une ouverture lue dans Wikipédia et dans OpenHistoricalMap :
     * événements de même type, même date et même précision, sur toute la ligne ou entre les deux mêmes lieux)
     * n'a qu'une histoire. Elle porte l'identifiant de l'événement de la source la plus sûre (lignes-oubliees,
     * puis Wikipédia, OpenHistoricalMap, Wikidata ; puis la confiance et la précision de la date), et
     * `source_event_ids` liste tous les événements du fait, celui-ci d'abord.
     *
     * Chaque entrée : `event` (même forme que `GET /api/v1/events/{event}` en liste : type, date et sa
     * précision, portion, description en français, source, lien, provenance), `source_event_ids`, `images`,
     * `documents`, et `url` (page du site).
     *
     * Règles de sélection :
     *
     * - Événements concernés : ouvertures, réouvertures, fermetures (tous services, voyageurs, fret),
     *   déclassements, déposes, reconversions, électrifications, modernisations, débuts et fins d'exploitation
     *   touristique, accidents. Les concessions et `other` n'ont pas d'histoire.
     * - Images : celles de la ligne dont toutes les années possibles sont à 5 ans au plus de l'événement (année
     *   exacte, décennie, fourchette, ou année « vers » comptée à 2 ans près ; jamais une borne seule « avant » ou
     *   « après »), du domaine public ou sous licence ouverte, plus celles rattachées à l'événement. Les images
     *   que le site montre avec une simple autorisation de leur auteur ne sont pas diffusées par l'API : le
     *   nombre d'images peut donc être plus petit que sur le site, et une histoire du site peut manquer ici.
     * - Documents : notices de Gallica de la ligne dans la même fenêtre, datées à l'année près au moins.
     * - Seuil : 3 images et documents au moins, dont 2 images.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-340000')]
    #[ApiResponse(200, "Événements de la ligne qui ont une histoire (liste vide s'il n'y en a aucun).", examples: [[
        'line' => ['slug' => 'rfn-340000', 'code' => '340000', 'name' => 'Ligne de Paris-St-Lazare au Havre'],
        'rules' => ['window_years' => 5, 'min_items' => 3, 'min_images' => 2, 'max_images' => 24],
        'total' => 1,
        'stories' => [[
            'event' => [
                'id' => 12793, 'type' => 'opening', 'date' => '1843-05-03', 'date_precision' => 'day',
                'section_from' => 'Vernon-Giverny', 'section_to' => 'Port-Villez',
                'description' => "Ouverture de la portion Vernon-Giverny - Port-Villez (4,8 km), d'après OpenHistoricalMap.",
                'confidence' => 'medium', 'source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/200903673',
                'provenance' => ['provider' => 'ohm', 'name' => 'OpenHistoricalMap', 'license' => 'CC0 1.0 (domaine public), sauf licence contraire indiquée par objet (tag license=*)', 'license_status' => 'verified'],
            ],
            'source_event_ids' => [12793],
            'images' => 6,
            'documents' => 9,
            'url' => 'https://vff-project.org/lines/rfn-340000/stories/12793',
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Line $line): JsonResponse
    {
        $available = $this->stories->available($line, true);
        $events = LineEvent::query()->whereIn('id', array_keys($available))->get()->keyBy('id');
        $items = [];
        foreach ($available as $id => $counts) {
            $event = $events->get($id);
            if ($event instanceof LineEvent) {
                $items[] = [
                    'event' => $this->eventSummary($event),
                    'source_event_ids' => $counts['event_ids'],
                    'images' => $counts['images'],
                    'documents' => $counts['documents'],
                    'url' => route('lines.stories.show', ['line' => $line->slug, 'event' => $id]),
                ];
            }
        }

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'rules' => self::rules(),
            'total' => count($items),
            'stories' => $items,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Histoire d'un événement : texte, images, documents.
     *
     * L'événement (`event`, même forme que `GET /api/v1/events/{event}` en liste : description en français
     * telle que la source la donne, `source`, `source_url`, `provenance` avec la licence), toutes les sources du
     * même fait (`sources`, même forme, l'événement de l'histoire d'abord), la fenêtre de dates
     * (`window` : `years`, `from`, `to`, années incluses), les images retenues et les documents de Gallica de la
     * période, et les gares de la ligne nommées par la portion de l'événement (`stations` : nom et slug, pour
     * `GET /api/v1/stations/{station}`).
     *
     * `images` : au plus 24, les plus proches de la date, rangées dans l'ordre chronologique (sans date en
     * dernier) ; même forme que `GET /api/v1/lines/{line}/media` (auteur, `license`, `license_url`,
     * `attribution`, `source_url`, `rights_status`…), plus `gap_years` (écart signé avec l'année de
     * l'événement, calculé sur `depicted_year` : 4 = quatre ans après, -2 = deux ans avant, 0 = la même année,
     * null = image de l'événement sans date), `gap_years_min` et `gap_years_max` (écarts extrêmes possibles
     * d'après la précision de la date : égaux à `gap_years` pour une date exacte, -5 et -1 pour une photo
     * « vers 1840 » d'un événement de 1843), `gap_exact` (false : dites l'écart comme approximatif, « environ
     * trois ans avant », ou comme un intervalle) et `attached` (true : image rattachée à l'événement lui-même).
     * `images_total` : nombre d'images retenues avant la limite de 24.
     *
     * `documents` : même forme que `GET /api/v1/lines/{line}/archives`, plus `gap_years` (0 quand l'intervalle
     * du document contient l'année de l'événement). Seules les métadonnées des notices de la BnF sont
     * servies (Licence Ouverte, mention `attribution`) ; jamais d'image de Gallica : suivez `gallica_url`.
     *
     * Règles de sélection :
     *
     * - Événements concernés : ouvertures, réouvertures, fermetures, déclassements, déposes, reconversions,
     *   électrifications, modernisations, exploitation touristique, accidents.
     * - Images : tout l'intervalle possible de la date à 5 ans au plus (`day`, `month`, `year` ; `circa` compté
     *   à 2 ans près ; `decade` ; `range` avec sa fin), domaine public ou licence ouverte (pas les images
     *   montrées sur le site avec une simple autorisation) ; plus celles rattachées à l'événement.
     * - Seuil : 3 images et documents au moins, dont 2 images ; sinon 404.
     *
     * Un fait rapporté par plusieurs sources n'a qu'une histoire, à l'adresse de l'événement de la source la plus
     * sûre : demandée par l'identifiant d'une autre source, l'histoire répond 301 vers cette adresse (`Location`).
     *
     * **Droits** : affichez chaque image avec son auteur, sa licence (et son lien) et sa source, comme le fait le
     * site ; citez les sources de l'événement (`provenance` de chacune).
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne.', example: 'rfn-340000')]
    #[PathParameter('event', "Identifiant de l'événement (`id` des événements de `GET /api/v1/lines/{line}` ou de `GET /api/v1/lines/{line}/stories`). Il doit appartenir à la ligne.", type: 'integer', example: 12793)]
    #[ApiResponse(200, "Histoire de l'événement.", examples: [[
        'line' => ['slug' => 'rfn-340000', 'code' => '340000', 'name' => 'Ligne de Paris-St-Lazare au Havre'],
        'event' => [
            'id' => 12793, 'type' => 'opening', 'date' => '1843-05-03', 'date_precision' => 'day',
            'section_from' => 'Vernon-Giverny', 'section_to' => 'Port-Villez',
            'description' => "Ouverture de la portion Vernon-Giverny - Port-Villez (4,8 km), d'après OpenHistoricalMap.",
            'confidence' => 'medium', 'source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/200903673',
            'provenance' => ['provider' => 'ohm', 'name' => 'OpenHistoricalMap', 'license' => 'CC0 1.0 (domaine public), sauf licence contraire indiquée par objet (tag license=*)', 'license_status' => 'verified'],
        ],
        'sources' => [[
            'id' => 12793, 'type' => 'opening', 'date' => '1843-05-03', 'date_precision' => 'day',
            'section_from' => 'Vernon-Giverny', 'section_to' => 'Port-Villez',
            'description' => "Ouverture de la portion Vernon-Giverny - Port-Villez (4,8 km), d'après OpenHistoricalMap.",
            'confidence' => 'medium', 'source' => 'ohm', 'source_url' => 'https://www.openhistoricalmap.org/way/200903673',
            'provenance' => ['provider' => 'ohm', 'name' => 'OpenHistoricalMap', 'license' => 'CC0 1.0 (domaine public), sauf licence contraire indiquée par objet (tag license=*)', 'license_status' => 'verified'],
        ]],
        'window' => ['years' => 5, 'from' => 1838, 'to' => 1848],
        'images_total' => 6,
        'images' => [[
            'id' => 37231, 'provider' => 'commons', 'provider_label' => 'Wikimedia Commons', 'type' => 'map',
            'title' => 'Illustrirte Zeitung (1843) 07 004 1 Karte der Eisenbahn von Paris nach Rouen',
            'caption' => 'Karte der Eisenbahn von Paris nach Rouen.',
            'thumb_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/af/Illustrirte_Zeitung_%281843%29_07_004_1_Karte_der_Eisenbahn_von_Paris_nach_Rouen.PNG/500px-Illustrirte_Zeitung_%281843%29_07_004_1_Karte_der_Eisenbahn_von_Paris_nach_Rouen.PNG',
            'display_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/af/Illustrirte_Zeitung_%281843%29_07_004_1_Karte_der_Eisenbahn_von_Paris_nach_Rouen.PNG/1280px-Illustrirte_Zeitung_%281843%29_07_004_1_Karte_der_Eisenbahn_von_Paris_nach_Rouen.PNG',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Illustrirte_Zeitung_(1843)_07_004_1_Karte_der_Eisenbahn_von_Paris_nach_Rouen.PNG',
            'width' => 1810, 'height' => 433, 'depicted_year' => 1843, 'depicted_year_to' => null, 'date_precision' => 'day',
            'captured_at' => '1843-08-12', 'author' => 'Johann Jacob Weber (Hrsg.), 1803-1880',
            'attribution' => 'Johann Jacob Weber (Hrsg.), 1803-1880, Public domain, via Wikimedia Commons',
            'license' => 'Public domain', 'license_url' => null, 'rights_status' => 'public_domain',
            'commercial_ok' => true, 'share_alike' => false, 'attribution_required' => false, 'featured' => false,
            'contribution_id' => null, 'location' => null, 'gap_years' => 0, 'gap_years_min' => 0, 'gap_years_max' => 0,
            'gap_exact' => true, 'attached' => false,
        ]],
        'documents' => [[
            'gallica_ark' => 'ark:/12148/btv1b53035187p', 'gallica_url' => 'https://gallica.bnf.fr/ark:/12148/btv1b53035187p',
            'catalogue_ark' => 'ark:/12148/cb425386313', 'catalogue_url' => 'https://catalogue.bnf.fr/ark:/12148/cb425386313',
            'title' => 'Chemin de fer de Paris au Hâvre', 'type' => 'map', 'authors' => ['Delarue'],
            'date' => ['label' => '1838', 'year' => 1838, 'year_to' => null, 'exact' => null, 'precision' => 'year'],
            'shelfmark' => 'GE DL 1838-38',
            'match' => ['score' => 11, 'threshold' => 9, 'max_score' => 11, 'reasons' => [['code' => 'rail_route', 'points' => 4], ['code' => 'ends', 'points' => 2], ['code' => 'rail_word', 'points' => 2], ['code' => 'date_in_life', 'points' => 2], ['code' => 'doc_type', 'points' => 1]], 'places' => ['Paris', 'Le Havre']],
            'record_updated_on' => '2021-03-15', 'fetched_at' => '2026-11-20T10:20:46+01:00', 'gap_years' => -5,
        ]],
        'stations' => [['name' => 'Vernon-Giverny', 'slug' => 'vernon-giverny'], ['name' => 'Port-Villez', 'slug' => 'port-villez']],
        'rules' => ['window_years' => 5, 'min_items' => 3, 'min_images' => 2, 'max_images' => 24],
        'attribution' => LineArchives::ATTRIBUTION,
        'url' => 'https://vff-project.org/lines/rfn-340000/stories/12793',
    ]])]
    #[ApiResponse(301, "Événement d'une autre source d'un fait qui a déjà son histoire : l'en-tête `Location` donne l'adresse de l'histoire (identifiant de l'événement de la source la plus sûre).")]
    #[ApiResponse(404, "Ligne inconnue, événement d'une autre ligne, ou événement sans histoire (contenu sous le seuil, type sans histoire).", examples: [
        ErrorExamples::LINE_NOT_FOUND,
        ['message' => 'This event has no story: fewer than 3 dated images and documents (2 images at least) within 5 years.'],
    ])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Line $line, int $event): JsonResponse|RedirectResponse
    {
        $model = LineEvent::query()->whereKey($event)->where('line_id', $line->id)->first();
        $story = $model === null ? null : $this->stories->story($line, $model, true);
        if ($story === null) {
            return response()->json(['message' => $model === null
                ? 'No event '.$event.' on line '.$line->slug.'.'
                : sprintf('This event has no story: fewer than %d dated images and documents (%d images at least) within %d years.', EventStories::MIN_ITEMS, EventStories::MIN_IMAGES, EventStories::WINDOW_YEARS),
            ], 404);
        }
        if ($story['event']['id'] !== $model->id) {
            return redirect()->route('api.lines.stories.show', ['line' => $line->slug, 'event' => $story['event']['id']], 301);
        }

        return response()->json([
            'line' => $story['line'],
            'event' => $story['event'],
            'sources' => $story['sources'],
            'window' => $story['window'],
            'images_total' => $story['images_total'],
            'images' => $story['images'],
            'documents' => $story['documents'],
            'stations' => $story['stations'],
            'rules' => self::rules(),
            'attribution' => $story['documents'] === [] ? null : LineArchives::ATTRIBUTION,
            'url' => route('lines.stories.show', ['line' => $line->slug, 'event' => $model->id]),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /** @return array{window_years: int, min_items: int, min_images: int, max_images: int} */
    public static function rules(): array
    {
        return [
            'window_years' => EventStories::WINDOW_YEARS,
            'min_items' => EventStories::MIN_ITEMS,
            'min_images' => EventStories::MIN_IMAGES,
            'max_images' => EventStories::MAX_IMAGES,
        ];
    }

    /** @return array<string, mixed> */
    private function eventSummary(LineEvent $event): array
    {
        return LineEventResource::make($event)->resolve();
    }
}
