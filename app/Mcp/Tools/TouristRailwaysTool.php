<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\TouristRailways;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('tourist_railways')]
#[Title('Chemins de fer touristiques et vélorails')]
#[Description(<<<'TXT'
Les chemins de fer touristiques et vélorails sur rails en activité aujourd'hui, et, sur demande, les voies vertes sur ancienne voie. Mêmes données que l'API REST GET /api/v1/tourist-railways et la page /tourist-railways (service TouristRailways).
Chaque exploitation : `id`, `name`, `type` (steam vapeur, railcar autorail, tram, rack crémaillère, velorail, train : traction non précisée par les sources), `operator` (null quand aucune source ne le nomme), `gauge_mm` et `gauges_mm`, `length_m` (longueur exploitée, null si inconnue), `start_year` (année de début de l'exploitation touristique, pas de l'ouverture de la ligne), `regions`, `departements`, `lines` (fiches de ligne VFF : slug, name, length_m de la portion ; vide si non rattachée), `website`, `wikipedia_url`, `wikidata`, `lat`, `lng` quand connus, `origin` (both, catalogue, database) et `sources` : pour chaque fait, la clé de sa source dans `sources` de la réponse (libellé, adresse, licence), null quand le fait est inconnu.
Limites : état actuel seulement, sans dates de saison ni horaires (voir le site officiel `website`) ; un champ null signifie inconnu, jamais « aucun ». Les voies vertes (`greenways`, avec `greenways: true`) viennent d'OpenStreetMap (ODbL), sont listées à partir de 5 km et ne s'obtiennent qu'avec les exploitations sans filtre `type`.
Filtres : `type` et `region` (nom exact de la région, tel que listé dans `regions` de la réponse). Pagination : `limit` et `offset`, `total` donne le nombre d'exploitations qui correspondent.
Exemples : {} ; {"type": "steam", "region": "Occitanie"} ; {"type": "velorail", "limit": 20, "offset": 20}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class TouristRailwaysTool extends VffTool
{
    private const MAX_LIMIT = 100;

    /** Clé de source du service vers le fournisseur de l'attribution. */
    private const PROVIDERS = [
        'wp-list' => 'wikipedia', 'wp-article' => 'wikipedia', 'wp-line' => 'wikipedia', 'wikidata' => 'wikidata',
        'ign' => 'ign', 'osm' => 'osm', 'lignes-oubliees' => 'lignes-oubliees',
    ];

    protected function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:'.implode(',', TouristRailways::TYPES)],
            'region' => ['sometimes', 'string', 'max:100'],
            'greenways' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(TouristRailways::TYPES)->description('Type d\'exploitation. Par défaut : tous.'),
            'region' => $schema->string()->description('Nom exact de la région (ex. "Occitanie"), tel que listé dans `regions` de la réponse. Par défaut : toutes.'),
            'greenways' => $schema->boolean()->default(false)->description('Ajouter les voies vertes sur ancienne voie (d\'au moins 5 km ; vide avec `type`).'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(40)->description('Nombre d\'exploitations renvoyées (40 par défaut).'),
            'offset' => $schema->integer()->min(0)->default(0)->description('Nombre d\'exploitations à sauter (pagination).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $data = app(TouristRailways::class)->get();
        $type = $input['type'] ?? null;
        $region = $input['region'] ?? null;
        $matching = array_values(array_filter($data['railways'], fn (array $r): bool => ($type === null || $r['type'] === $type)
            && ($region === null || in_array($region, $r['regions'], true))));
        $offset = (int) ($input['offset'] ?? 0);
        $limit = (int) ($input['limit'] ?? 40);
        $page = array_slice($matching, $offset, $limit);
        $payload = ['filters' => ['type' => $type, 'region' => $region], 'total' => count($matching), 'offset' => $offset,
            'has_more' => $offset + $limit < count($matching), 'railways' => $page];
        if (($input['greenways'] ?? false) === true) {
            $payload['greenways'] = $type !== null ? [] : array_values(array_filter($data['greenways'],
                fn (array $g): bool => $region === null || in_array($region, $g['regions'], true)));
        }
        $keys = [];
        foreach ($page as $r) {
            $keys = array_merge($keys, array_filter((array) $r['sources']));
        }
        foreach ($payload['greenways'] ?? [] as $g) {
            $keys = array_merge($keys, array_filter((array) $g['sources']));
        }
        $keys = array_values(array_unique($keys));
        $payload['regions'] = $data['regions'];
        $payload['types'] = $data['types'];
        $payload['sources'] = array_intersect_key($data['sources'], array_flip($keys));

        return $this->respond($payload, array_map(fn (string $k): ?string => self::PROVIDERS[$k] ?? null, $keys), $lang,
            self::url('/tourist-railways'), ['railways', 'greenways']);
    }
}
