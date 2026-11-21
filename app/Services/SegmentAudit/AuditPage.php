<?php

namespace App\Services\SegmentAudit;

/**
 * Page de tronçons d'un audit de ligne, la même pour l'API REST, GraphQL et le serveur MCP :
 * filtre par note, rang de départ, taille de page (50 par défaut, 200 au plus).
 */
final class AuditPage
{
    public const DEFAULT_LIMIT = 50;

    public const MAX_LIMIT = 200;

    /**
     * @param  list<array<string, mixed>>  $segments  tronçons dans l'ordre de l'audit (le plus mal noté d'abord)
     * @return list<array<string, mixed>>
     */
    public static function filter(array $segments, ?string $grade): array
    {
        return $grade === null
            ? $segments
            : array_values(array_filter($segments, fn (array $s): bool => $s['worst_grade'] === $grade));
    }

    /**
     * Audit complet -> synthèse de la ligne (inchangée) et une page de tronçons, avec `segments_total`
     * (après le filtre), `limit` et `offset`.
     *
     * @param  array<string, mixed>  $audit
     * @return array<string, mixed>
     */
    public static function apply(array $audit, ?string $grade, ?int $limit, ?int $offset): array
    {
        $segments = self::filter($audit['segments'], $grade);
        $limit = max(1, min(self::MAX_LIMIT, $limit ?? self::DEFAULT_LIMIT));
        $offset = max(0, $offset ?? 0);
        unset($audit['segments']);

        return $audit + ['segments_total' => count($segments), 'limit' => $limit, 'offset' => $offset,
            'segments' => array_slice($segments, $offset, $limit)];
    }
}
