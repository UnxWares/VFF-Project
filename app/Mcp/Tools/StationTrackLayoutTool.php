<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\TrackLayout\StationTrackLayout;
use App\Services\TrackLayout\TrackLayout;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('station_track_layout')]
#[Title("Voies d'une gare")]
#[Description(<<<'TXT'
Plan des voies d'une gare aujourd'hui (« combien de voies à quai à Poitiers, quelle hauteur de quai ? ») : pour chaque ligne qui passe par la gare et dont les voies sont décrites autour d'elle, le schéma des voies de la ligne sur une fenêtre autour de la gare (même contenu que l'outil line_track_layout : voies numérotées, voies à quai, voies de service, aiguillages, traversées, heurtoirs, signaux, points d'exploitation, vitesses, déclivités), et les bords de quai de la gare ; même contenu que l'API REST GET /api/v1/stations/{station}/track-layout. Réseau d'aujourd'hui seulement.
Réponse : `lines[]` (une par ligne, la ligne qui a le plus de voies d'abord : `line`, `station_pk_m` et `station_pk_source`, `window` (PK en mètres de la ligne), `track_layout` au format de line_track_layout, avec `source` et `precision` sur chaque objet) ; `platforms[]` (un bord de quai par voie bordée, un quai central en a deux : `ref` du quai, `track_ref` de la voie, `line_code`, `track` = id de la voie dans le schéma de sa ligne, `length_m` utile et `height_mm` au-dessus du rail du RINF, `pk_from_m`/`pk_to_m` de la « Liste des quais » de SNCF Réseau (`pk_source` sncf, `precision` exact) ou placés autour du PK de la gare (`pk_source` vff, `precision` approximate)) ; `operational_points[]` (UOPID du RINF rattachés à la gare) ; `counts` ; `sources[]`. Dire la précision quand elle n'est pas `exact`. SNCF Réseau ne déclare au RINF ni aiguillage ni signal : ils viennent d'OpenStreetMap, une liste vide n'est pas une absence sur le terrain. Une gare sans voie décrite renvoie `lines` vide (et ses quais s'ils sont connus), pas une erreur.
Licences (`sources[]`, bloc d'attribution) : RINF © Agence de l'Union européenne pour les chemins de fer, dump officiel sous EUPL 1.2, mention « RINF data © European Union Agency for Railways » à reproduire ; OpenStreetMap ODbL ; SNCF Réseau ODbL.
Prend le slug renvoyé par search. Exemples : {"station": "poitiers"} ; {"station": "bretigny", "line": "550000", "kinds": ["tracks", "switches", "operational_points"]}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StationTrackLayoutTool extends VffTool
{
    /** Fournisseurs du registre des droits derrière chaque clé de source. */
    private const PROVIDERS = ['rinf' => ['rinf'], 'osm' => ['osm'], 'vff' => ['sncf', 'ign'], 'sncf' => ['sncf']];

    /** Listes de chaque schéma de ligne que l'on peut raccourcir, la plus longue d'abord. */
    private const LISTS = ['tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones', 'operational_points', 'strip.speeds', 'strip.gradients', 'coverage.missing'];

    protected function rules(): array
    {
        return [
            'station' => ['required', 'string', 'min:1', 'max:255'],
            'line' => ['sometimes', 'nullable', 'string', 'min:1', 'max:100'],
            'kinds' => ['sometimes', 'array', 'max:9'],
            'kinds.*' => ['string', 'in:'.implode(',', TrackLayout::KINDS)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'station' => $schema->string()->min(1)->max(255)->required()
                ->description('Gare : slug renvoyé par search (ex. "poitiers").'),
            'line' => $schema->string()->min(1)->max(100)
                ->description('Une seule ligne : slug (ex. "rfn-570000") ou code SNCF Réseau à 6 chiffres (ex. "570000"). Par défaut : toutes les lignes de la gare.'),
            'kinds' => $schema->array()->items($schema->string()->enum(TrackLayout::KINDS))->max(9)
                ->description('Familles du schéma de chaque ligne : operational_points, tracks, switches, signals, crossings, buffer_stops, milestones, speeds, gradients. Par défaut : toutes.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $station = $this->station($input['station']);
        if ($station === null) {
            return $this->fail('station_not_found', ['query' => $input['station']], ['suggestions' => $this->suggestions($input['station'], 'stations')]);
        }
        $layout = app(StationTrackLayout::class)->forStation($station, $input['kinds'] ?? null, $input['line'] ?? null);
        $providers = [];
        foreach ($layout['sources'] as $source) {
            $providers = [...$providers, ...(self::PROVIDERS[$source['key']] ?? [])];
        }
        $lists = ['platforms'];
        foreach (array_keys($layout['lines']) as $i) {
            foreach (self::LISTS as $list) {
                $lists[] = "lines.{$i}.track_layout.{$list}";
            }
        }

        return $this->respond($layout, array_values(array_unique($providers)), $lang, self::url('/stations/'.$station->slug.'/voies'), $lists);
    }
}
