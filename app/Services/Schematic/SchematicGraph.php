<?php

namespace App\Services\Schematic;

/**
 * Graphe d'une ère donné au plan schématique : nœuds et arêtes de la topologie du graphe ferroviaire
 * (network_nodes, network_edges : voies en service à l'ère, cf. EraGraphBuilder), positions en mètres
 * Web Mercator (EPSG:3857).
 *
 * Catégories d'arête (couleur du plan, mêmes règles que la carte, lib/railStyle.js) : `lgv`, `mixed`,
 * `freight`, `unknown`, `heritage`, et `connector` pour un raccord de lacune (sans ligne).
 */
final readonly class SchematicGraph
{
    /** Ordre de priorité des catégories : la plus active l'emporte sur une voie partagée (comme TrackPriority). */
    public const CATEGORY_RANK = ['lgv' => 5, 'mixed' => 4, 'freight' => 3, 'unknown' => 2, 'heritage' => 1, 'connector' => 0];

    /**
     * @param  array<int, array{0: float, 1: float}>  $nodes  position de chaque nœud (x, y), par identifiant
     * @param  list<array{u: int, v: int, length: float, line: ?int, category: string}>  $edges
     * @param  array<int, int>  $nodeStations  gare ouverte posée sur un nœud (identifiant de la gare), par nœud
     * @param  array<int, float>  $stationWeights  importance de chaque gare (choix de la gare qui nomme un nœud)
     */
    public function __construct(
        public array $nodes,
        public array $edges,
        public array $nodeStations = [],
        public array $stationWeights = [],
    ) {}
}
