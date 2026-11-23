<?php

namespace App\Mcp\Support;

/**
 * Fait de signalisation daté (LineSignallingHistory::forSheet) réduit pour les réponses MCP qui en listent
 * plusieurs lignes (compare_line_eras, what_was_here) : système, rôle, date, portion et source, sans les champs
 * de lecture de l'article. L'historique complet reste dans signalling_history.
 */
final class SignallingFacts
{
    /**
     * @param  array<string, mixed>  $fact
     * @return array{system: mixed, role: mixed, date: mixed, date_precision: mixed, scope: mixed, section: array{from: mixed, to: mixed}|null, source: array{provider: mixed, url: mixed}}
     */
    public static function compact(array $fact): array
    {
        $section = $fact['section'] ?? null;
        $source = $fact['source'] ?? null;

        return [
            'system' => $fact['system'] ?? null,
            'role' => $fact['role'] ?? null,
            'date' => $fact['date'] ?? null,
            'date_precision' => $fact['date_precision'] ?? null,
            'scope' => $fact['scope'] ?? null,
            'section' => is_array($section) ? ['from' => $section['from'] ?? null, 'to' => $section['to'] ?? null] : null,
            'source' => ['provider' => is_array($source) ? ($source['provider'] ?? null) : null, 'url' => is_array($source) ? ($source['url'] ?? null) : null],
        ];
    }
}
