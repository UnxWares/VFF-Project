<?php

namespace App\Services\Accidents;

/**
 * Noms de fiche retenus (database/data/accident_slugs.php) : le slug d'une fiche dont la date ou la commune de
 * la source a été corrigée d'après le contexte. Lu par l'import à la création d'une fiche, pour qu'une base
 * reconstruite redonne exactement les slugs actuels.
 */
final class AccidentSlugTable
{
    /** @var array<string, string> « fournisseur:identifiant » de la source → slug retenu */
    private array $bySource = [];

    /** @var array<string, true> anciens slugs, qui ne sont plus donnés à une autre fiche */
    private array $retired = [];

    /** @param ?list<array{key: string, from: string, slug: string, reason: string}> $rows lignes (par défaut database/data/accident_slugs.php) */
    public function __construct(private ?array $rows = null)
    {
        $this->rows ??= require dirname(__DIR__, 3).'/database/data/accident_slugs.php';
        foreach ($this->rows as $row) {
            $this->bySource[$row['key']] = $row['slug'];
            if ($row['from'] !== $row['slug']) {
                $this->retired[$row['from']] = true;
            }
        }
    }

    /**
     * Slug retenu pour une fiche dont voici les sources, null si aucune n'est dans la table.
     *
     * @param  list<AccidentSourceRef>  $sources
     */
    public function retained(array $sources): ?string
    {
        foreach ($sources as $source) {
            if (isset($this->bySource[$source->provider.':'.$source->externalId])) {
                return $this->bySource[$source->provider.':'.$source->externalId];
            }
        }

        return null;
    }

    /** Ancien slug d'une fiche renommée : une autre fiche ne le reprend pas. */
    /** Adresse actuelle d'une fiche renommée (« from » de la table vers son « slug »), null si l'adresse n'a jamais été retirée. */
    public function renamedTo(string $slug): ?string
    {
        foreach ($this->rows() as $row) {
            if ($row['from'] === $slug && $row['slug'] !== $slug) {
                return $row['slug'];
            }
        }

        return null;
    }

    public function isRetired(string $slug): bool
    {
        return isset($this->retired[$slug]);
    }

    /** @return list<array{key: string, from: string, slug: string, reason: string}> */
    public function rows(): array
    {
        return $this->rows ?? [];
    }
}
