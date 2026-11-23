<?php

namespace App\GraphQL\Resolvers;

use App\Services\CatalogSearch;
use App\Services\GlossarySearch;

/**
 * Requête GraphQL `search(query, limit)` : même recherche par nom que `GET /api/v1/search` et l'outil MCP
 * `search` (CatalogSearch), lignes, gares, matériels et accidents, plus les termes du glossaire (GlossarySearch).
 */
final readonly class SearchField
{
    public function __construct(private CatalogSearch $search, private GlossarySearch $glossary) {}

    /**
     * @param  array{query: string, limit?: int|null}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $_, array $args): array
    {
        $limit = $args['limit'] ?? 8;

        return [
            ...$this->search->search($args['query'], $limit, $limit, $limit, $limit),
            'glossary' => $this->glossary->search($args['query'], $limit),
        ];
    }

    /**
     * Nom d'un accident trouvé, dans la langue demandée (français à défaut).
     *
     * @param  array{names: array<string, string>, name: string}  $accident
     * @param  array{lang?: string}  $args
     */
    public function accidentName(array $accident, array $args): string
    {
        return $accident['names'][$args['lang'] ?? 'fr'] ?? $accident['name'];
    }

    /**
     * Nom d'un terme du glossaire trouvé, dans la langue demandée (français à défaut).
     *
     * @param  array{names: array<string, string>, name: string}  $term
     * @param  array{lang?: string}  $args
     */
    public function glossaryTermName(array $term, array $args): string
    {
        return $term['names'][$args['lang'] ?? 'fr'] ?? $term['name'];
    }
}
