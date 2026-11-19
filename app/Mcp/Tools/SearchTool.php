<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\CatalogSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search')]
#[Title('Rechercher une ligne, une gare ou un matériel')]
#[Description(<<<'TXT'
Recherche par nom dans l'encyclopédie VFF (lignes de chemin de fer françaises actuelles et disparues, gares, matériels roulants).
Point de départ obligé : renvoie les identifiants (`slug`) attendus par les autres outils (get_line, line_events, get_station, historical_route).
Lignes : par nom (« Paris à Brest ») ou code SNCF Réseau à 6 chiffres (« 420000 », espaces tolérés) ; les lignes en service passent avant les lignes fermées.
Gares : par nom, avec ville, code de département et coordonnées WGS 84 (lat, lng en degrés décimaux) pour distinguer les homonymes.
Insensible à la casse et aux accents (« saint-etienne » trouve « Saint-Étienne »). Résultats triés par pertinence : nom exact, début du nom, mot du nom, puis le reste.
Exemple : {"query": "brest"} ; {"query": "420000", "kinds": ["lines"]}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SearchTool extends VffTool
{
    public const KINDS = ['lines', 'stations', 'materials'];

    protected function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'kinds' => ['sometimes', 'array', 'min:1', 'max:3'],
            'kinds.*' => ['string', 'distinct', 'in:'.implode(',', self::KINDS)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->min(2)->max(100)->required()
                ->description('Texte recherché, 2 à 100 caractères : nom de ligne, code de ligne à 6 chiffres, nom de gare ou de matériel. Exemple : "Limoges".'),
            'kinds' => $schema->array()->items($schema->string()->enum(self::KINDS))->min(1)->max(3)
                ->description('Catégories cherchées parmi lines, stations, materials. Par défaut : les trois.'),
            'limit' => $schema->integer()->min(1)->max(20)->default(8)
                ->description('Nombre maximal de résultats par catégorie, 1 à 20 (8 par défaut).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $kinds = $input['kinds'] ?? self::KINDS;
        $limit = (int) ($input['limit'] ?? 8);
        $found = app(CatalogSearch::class)->search(
            $input['query'],
            in_array('lines', $kinds, true) ? $limit : 0,
            in_array('stations', $kinds, true) ? $limit : 0,
            in_array('materials', $kinds, true) ? $limit : 0,
        );
        $link = fn (string $prefix) => fn (array $r) => array_diff_key($r, ['type' => true]) + ['vff_url' => self::url("/{$prefix}/{$r['slug']}")];

        return $this->respond([
            'query' => $input['query'],
            'lines' => array_map($link('lines'), $found['lines']),
            'stations' => array_map($link('stations'), $found['stations']),
            'materials' => array_map($link('materials'), $found['materials']),
        ], [], $lang);
    }
}
