<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\TrackLayout\TrackLayout;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_track_layout')]
#[Title("Schéma des voies d'une ligne")]
#[Description(<<<'TXT'
Schéma des voies d'une ligne aujourd'hui, à la manière d'un « routebook » : voies principales numérotées, voies de gare, d'évitement et de service, liaisons, aiguillages, traversées, signaux et points d'exploitation dans l'ordre des points kilométriques (PK, en mètres), bande des vitesses et des déclivités ; même contenu que l'API REST GET /api/v1/lines/{line}/track-layout. Réseau d'aujourd'hui seulement.
Sources par ordre de préférence, champ `source` de chaque objet : `rinf` (RINF, registre de l'infrastructure de l'Agence de l'Union européenne pour les chemins de fer, données déclarées par SNCF Réseau), `osm` (OpenStreetMap, là où le RINF ne dit rien), `vff` (PK des gares, vitesses et passages à niveau de SNCF Réseau, déclivités du profil en long de VFF). `precision` : `exact` (position déclarée), `derived` (calculée par VFF), `approximate` (placée à peu près : une voie de gare du RINF n'a pas de PK propre et est centrée sur le PK de la gare). Dire la précision quand elle n'est pas `exact`.
Réponse : `tracks[]` (ref courte : V1, V2, VU = voie unique ; kind : running, siding, loop, yard, crossover, spur ; lane : rangée du schéma, 0 voie 1 ou voie unique, 1 voie 2) ; `operational_points[]` (uopid, station_slug) ; `switches[]`, `signals[]` (SNCF Réseau n'en déclare aucun au RINF : sans OpenStreetMap, listes vides, ce n'est pas une absence sur le terrain) ; `strip.speeds[]` (km/h), `strip.gradients[]` (‰ dans le sens des PK croissants) ; `coverage` (total_m, rinf_m, osm_m, missing[] : portions sans donnée par famille, à signaler). Une ligne non décrite renvoie des listes vides, pas une erreur.
Licences (`sources[]`, bloc d'attribution) : RINF © Agence de l'Union européenne pour les chemins de fer, dump officiel sous EUPL 1.2, mention « RINF data © European Union Agency for Railways » à reproduire ; OpenStreetMap ODbL ; SNCF Réseau ODbL ; IGN Licence Ouverte 2.0.
Exemples : {"line": "rfn-579000"} ; {"line": "570000", "from_pk_m": 17000, "to_pk_m": 18000, "kinds": ["tracks", "operational_points"], "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineTrackLayoutTool extends VffTool
{
    /** Fournisseurs du registre des droits derrière chaque clé de source du schéma. */
    private const PROVIDERS = ['rinf' => ['rinf'], 'osm' => ['osm'], 'vff' => ['sncf', 'ign']];

    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'from_pk_m' => ['sometimes', 'nullable', 'integer', 'between:-100000,5000000'],
            'to_pk_m' => ['sometimes', 'nullable', 'integer', 'between:-100000,5000000'],
            'kinds' => ['sometimes', 'array', 'max:7'],
            'kinds.*' => ['string', 'in:'.implode(',', TrackLayout::KINDS)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-579000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "579000").'),
            'from_pk_m' => $schema->integer()->min(-100000)->max(5000000)
                ->description('Début de la portion, PK en mètres (ex. 17000 pour le PK 17+000). Par défaut : début de la ligne.'),
            'to_pk_m' => $schema->integer()->min(-100000)->max(5000000)
                ->description('Fin de la portion, PK en mètres. Par défaut : fin de la ligne. Sur une longue ligne, demander une portion.'),
            'kinds' => $schema->array()->items($schema->string()->enum(TrackLayout::KINDS))->max(7)
                ->description('Familles à servir : operational_points, tracks, switches, signals, crossings, speeds, gradients. Par défaut : toutes.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $layout = app(TrackLayout::class)->forLine($line, $input['from_pk_m'] ?? null, $input['to_pk_m'] ?? null, $input['kinds'] ?? null);
        $providers = [];
        foreach ($layout['sources'] as $source) {
            $providers = [...$providers, ...(self::PROVIDERS[$source['key']] ?? [])];
        }

        return $this->respond(
            $layout,
            $providers,
            $lang,
            self::url('/lines/'.$line->slug),
            ['tracks', 'operational_points', 'switches', 'signals', 'crossings', 'strip.speeds', 'strip.gradients', 'coverage.missing'],
        );
    }
}
