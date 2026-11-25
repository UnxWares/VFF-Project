<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Attribution;
use App\Mcp\Support\VffTool;
use App\Services\NetworkGraph\RouteException;
use App\Services\Routebook\Routebook;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('routebook')]
#[Title('Livret de parcours entre deux gares')]
#[Description(<<<'TXT'
Livret de parcours d'aujourd'hui entre deux gares (« quelles lignes, quels PK, quelles voies entre Rennes et Saint-Malo ? ») : le plus court chemin sur le réseau actuel (comme historical_route sans année), puis, ligne par ligne dans le sens de marche, les PK de chaque portion et son schéma des voies (comme line_track_layout), départ et arrivée au centre de leur point d'exploitation.
Renvoie : distance_km, objects (nombre d'objets), legs (lignes traversées dans l'ordre : code, slug, nom, bouts = gare ou changement de ligne en pleine voie, PK de début et de fin, direction up = PK croissants ou down, pk_precision exact / derived / approximate, km parcourus, has_detail), line_changes, coverage (mètres couverts par le RINF et par OpenStreetMap, portions sans données détaillées), puis les objets demandés par kinds, triés par kilomètre parcouru (route_km) avec leur ligne, leur PK de ligne et leur provenance (source, precision). Une portion sans donnée n'est pas une portion sans voie : la source ne la décrit pas.
Gares : slug (de préférence, via search), identifiant, nom exact ou nom de ville. Erreurs à code stable : graph_not_built, station_not_found, ambiguous_station (avec `candidates`), same_station, station_not_in_network, no_path.
Exemple : {"from": "rennes", "to": "st-malo", "kinds": ["operational_points", "switches"]}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class RoutebookTool extends VffTool
{
    /** Listes d'objets que l'on peut demander (`kinds`). */
    public const KINDS = ['operational_points', 'tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones', 'strip'];

    /** Listes renvoyées par défaut : les plus utiles à la lecture d'un parcours. */
    public const DEFAULT_KINDS = ['operational_points', 'switches', 'strip'];

    public function __construct(Attribution $attribution, private readonly Routebook $routebook)
    {
        parent::__construct($attribution);
    }

    protected function rules(): array
    {
        return [
            'from' => ['required', 'string', 'min:1', 'max:200'],
            'to' => ['required', 'string', 'min:1', 'max:200'],
            'kinds' => ['sometimes', 'array', 'max:'.count(self::KINDS)],
            'kinds.*' => ['string', 'in:'.implode(',', self::KINDS)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->min(1)->max(200)->required()
                ->description('Gare de départ : slug (ex. "rennes"), nom exact ou nom de ville.'),
            'to' => $schema->string()->min(1)->max(200)->required()
                ->description('Gare d\'arrivée, mêmes formes que from (ex. "st-malo").'),
            'kinds' => $schema->array()->items($schema->string()->enum(self::KINDS))
                ->description('Objets à joindre : operational_points, tracks, switches, signals, crossings, buffer_stops (heurtoirs), milestones (bornes kilométriques), strip (vitesses et déclivités). Par défaut : operational_points, switches, strip.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        try {
            $book = $this->routebook->between($input['from'], $input['to']);
        } catch (RouteException $e) {
            return $this->fail($e->errorCode, $e->details + ['from' => $input['from'], 'to' => $input['to']],
                array_intersect_key($e->details, array_flip(['candidates', 'side', 'station', 'status'])));
        }

        $kinds = array_values(array_intersect(self::KINDS, $input['kinds'] ?? self::DEFAULT_KINDS));
        $payload = [
            'from' => $book['from'],
            'to' => $book['to'],
            'era_year' => $book['era_year'],
            'distance_km' => $book['distance_km'],
            'objects' => $book['objects'],
            'legs' => array_map(fn (array $l): array => [
                'code' => $l['line']['code'], 'slug' => $l['line']['slug'], 'name' => $l['line']['name'],
                'from' => $l['from']['name'] ?? $l['from']['kind'], 'to' => $l['to']['name'] ?? $l['to']['kind'],
                'pk_from_m' => $l['pk_from_m'], 'pk_to_m' => $l['pk_to_m'], 'direction' => $l['direction'],
                'pk_precision' => $l['pk_precision'], 'route_from_km' => $l['route_from_km'], 'route_to_km' => $l['route_to_km'],
                'has_detail' => $l['has_detail'],
            ], $book['legs']),
            'line_changes' => $book['line_changes'],
            'coverage' => $book['coverage'],
            'sources' => $book['sources'],
        ];
        $lists = [];
        foreach ($kinds as $kind) {
            $payload[$kind] = $book[$kind];
            $lists = [...$lists, ...($kind === 'strip' ? ['strip.speeds', 'strip.gradients'] : [$kind])];
        }
        // Fournisseurs du registre des droits derrière chaque source, comme line_track_layout : la source `vff` sert les
        // vitesses et passages à niveau de SNCF Réseau (ODbL) et les déclivités du profil en long de l'IGN (Licence
        // Ouverte 2.0), à créditer tous deux.
        $known = $this->attribution->providerKeys();
        $providers = [];
        foreach (array_column($book['sources'], 'key') as $key) {
            $providers = [...$providers, ...(LineTrackLayoutTool::PROVIDERS[$key] ?? [$key])];
        }
        $providers = array_values(array_unique(array_filter($providers, fn (string $k): bool => in_array($k, $known, true))));

        return $this->respond($payload, $providers, $lang,
            self::url('/routebook?'.http_build_query(['from' => $book['from']['slug'], 'to' => $book['to']['slug']])),
            [...$lists, 'coverage.missing']);
    }
}
