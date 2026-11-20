<?php

namespace App\Services\NetworkGraph;

/**
 * Composantes connexes d'un graphe non orienté (union-find avec compression de chemin), pour les
 * diagnostics du graphe d'une ère (GraphDiagnostics) : îles de la topologie, îles du graphe
 * gare-à-gare, lignes en plusieurs morceaux.
 */
final class ConnectedComponents
{
    /** @var array<int, int> parent de chaque nœud (un représentant est son propre parent) */
    private array $parent = [];

    /** Nœud sans arête : il forme sa propre composante tant qu'aucune arête ne le relie. */
    public function add(int $node): void
    {
        $this->parent[$node] ??= $node;
    }

    public function link(int $a, int $b): void
    {
        $this->add($a);
        $this->add($b);
        $ra = $this->find($a);
        $rb = $this->find($b);
        if ($ra !== $rb) {
            $this->parent[$ra] = $rb;
        }
    }

    public function find(int $node): int
    {
        $this->add($node);
        while ($this->parent[$node] !== $node) {
            $this->parent[$node] = $this->parent[$this->parent[$node]];
            $node = $this->parent[$node];
        }

        return $node;
    }

    public function count(): int
    {
        return count($this->groups());
    }

    /**
     * Nœuds de chaque composante, indexés par leur représentant, nœuds en ordre croissant.
     *
     * @return array<int, list<int>>
     */
    public function groups(): array
    {
        $groups = [];
        foreach (array_keys($this->parent) as $node) {
            $groups[$this->find($node)][] = $node;
        }
        foreach ($groups as &$nodes) {
            sort($nodes);
        }

        return $groups;
    }
}
