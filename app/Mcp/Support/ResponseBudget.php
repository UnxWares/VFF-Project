<?php

namespace App\Mcp\Support;

/**
 * Plafond de taille des réponses du serveur MCP (config mcp.vff.max_response_bytes) : tant que le
 * JSON dépasse le plafond, la plus longue des listes désignées est raccourcie par la fin (un quart
 * à la fois) et le nombre d'éléments retirés est noté dans `truncated` (chemin => nombre).
 * Les listes sont désignées par un chemin à points (« events », « line.phases »).
 */
final class ResponseBudget
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $lists  chemins des listes que l'on peut raccourcir
     * @return array<string, mixed>
     */
    public static function fit(array $payload, array $lists, int $maxBytes): array
    {
        $truncated = [];
        while (self::size($payload) > $maxBytes) {
            $longest = null;
            $count = 1;
            foreach ($lists as $path) {
                $n = count((array) data_get($payload, $path, []));
                if ($n > $count) {
                    [$longest, $count] = [$path, $n];
                }
            }
            if ($longest === null) {
                break;
            }
            $drop = max(1, intdiv($count, 4));
            data_set($payload, $longest, array_slice((array) data_get($payload, $longest), 0, $count - $drop));
            $truncated[$longest] = ($truncated[$longest] ?? 0) + $drop;
        }
        if ($truncated !== []) {
            $payload['truncated'] = $truncated;
        }

        return $payload;
    }

    /** @param  array<string, mixed>  $payload */
    public static function size(array $payload): int
    {
        return strlen((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
